<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\Products;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Service;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Numbering\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 100]);
    }

    /** @param array<int, array{0: ?int, 1: ?int, 2: float, 3: float}> $lines [product_id, service_id, qty, price] */
    private function order(array $lines): SalesOrder
    {
        $order = SalesOrder::forceCreate([
            'order_number' => 'CMD-T-' . uniqid(),
            'client_id' => $this->client->id,
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        foreach ($lines as [$productId, $serviceId, $qty, $price]) {
            SalesOrderItem::forceCreate([
                'order_id' => $order->id, 'product_id' => $productId, 'service_id' => $serviceId,
                'quantity' => $qty, 'price_ht' => $price, 'tva_rate' => 20, 'total_ht' => $qty * $price,
            ]);
        }

        return $order;
    }

    /** Ships a delivery created from the order, with the given shipped quantity per line. */
    private function ship(Delivery $delivery, array $shipped, string $status = 'shipped'): void
    {
        $lines = $delivery->items()->orderBy('id')->get();
        $this->put(route('erp.deliveries.update', $delivery), [
            'sales_order_id' => $delivery->sales_order_id,
            'client_id' => $delivery->client_id,
            'delivery_date' => now()->toDateString(),
            'status' => $status,
            'item_source_id' => $lines->pluck('sales_order_item_id')->all(),
            'item_product_id' => $lines->pluck('product_id')->all(),
            'item_service_id' => $lines->pluck('service_id')->all(),
            'item_quantity' => $shipped,
            'item_shipped_qty' => $shipped,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
    }

    public function test_numbering_continues_after_existing_numbers_and_rollbacks_do_not_burn_numbers(): void
    {
        $numbering = app(DocumentNumberService::class);
        $year = now()->format('Y');
        ClientInvoice::forceCreate([
            'invoice_number' => "FAC-{$year}-0007", 'client_id' => $this->client->id, 'invoice_date' => now()->toDateString(),
            'state' => 'validated', 'total_ttc' => 10,
        ]);

        $this->assertSame("FAC-{$year}-0008", $numbering->next('client_invoices', 'invoice_number', 'FAC'));

        try {
            DB::transaction(function () use ($numbering) {
                $numbering->next('client_invoices', 'invoice_number', 'FAC');
                throw new \RuntimeException('document creation failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame("FAC-{$year}-0009", $numbering->next('client_invoices', 'invoice_number', 'FAC'));
        $this->assertSame("AV-{$year}-0001", $numbering->next('client_invoices', 'invoice_number', 'AV'));
    }

    public function test_partial_deliveries_and_exact_line_prices(): void
    {
        // Same product twice at two prices: the old code billed both lines at the first price.
        $order = $this->order([[$this->product->id, null, 4, 100], [$this->product->id, null, 2, 80]]);

        $this->post(route('erp.sales-orders.convert-to-delivery', $order))->assertSessionHasNoErrors();
        $first = Delivery::firstOrFail();
        $this->assertSame([4.0, 2.0], $first->items()->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all());

        // While the first delivery is pending, everything is planned: no second delivery.
        $this->post(route('erp.sales-orders.convert-to-delivery', $order))->assertSessionHasErrors('status');

        $this->ship($first, [3, 2]);
        $order->refresh();
        $this->assertSame('partial', $order->status);
        $this->assertSame([3.0, 2.0], $order->items()->orderBy('id')->pluck('delivered_qty')->map(fn ($q) => (float) $q)->all());
        $this->assertSame(95.0, (float) $this->product->fresh()->stock_quantity);

        // Second delivery only takes what is left: 1 unit of the first line.
        $this->post(route('erp.sales-orders.convert-to-delivery', $order))->assertSessionHasNoErrors();
        $second = Delivery::latest('id')->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, $second->items()->count());
        $this->assertSame(1.0, (float) $second->items()->first()->quantity);

        $this->ship($second, [1], 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);

        // The invoice of the first delivery uses the price of each exact order line.
        $this->post(route('erp.deliveries.convert-to-invoice', $first))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $invoice = ClientInvoice::firstOrFail();
        $this->assertSame(460.0, (float) $invoice->amount_ht, '3 x 100 + 2 x 80');
        $this->assertSame(2, $invoice->items()->whereNotNull('delivery_item_id')->count());
    }

    public function test_service_lines_are_priced_from_their_own_order_line(): void
    {
        $install = Service::forceCreate(['service_name' => 'Installation', 'status' => 'active']);
        $training = Service::forceCreate(['service_name' => 'Formation', 'status' => 'active']);
        $order = $this->order([[null, $install->id, 1, 200], [null, $training->id, 1, 300]]);

        $this->post(route('erp.sales-orders.convert-to-delivery', $order));
        $this->post(route('erp.deliveries.convert-to-invoice', Delivery::firstOrFail()))->assertSessionHasNoErrors();

        $prices = ClientInvoice::firstOrFail()->items()->orderBy('id')->pluck('price_ht')->map(fn ($p) => (float) $p)->all();
        $this->assertSame([200.0, 300.0], $prices, 'old bug: the second service got the price of the first one');
    }

    public function test_an_order_with_deliveries_is_locked(): void
    {
        $order = $this->order([[$this->product->id, null, 1, 100]]);
        $this->post(route('erp.sales-orders.convert-to-delivery', $order));

        $this->delete(route('erp.sales-orders.destroy', $order))->assertSessionHasErrors('status');
        $this->assertNotNull($order->fresh());
    }

    public function test_partial_receipts_and_billing_of_received_quantities_only(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fournisseur SA', 'status' => 'active']);
        $po = PurchaseOrder::forceCreate([
            'order_number' => 'BCA-T-1', 'supplier_id' => $supplier->id, 'order_date' => now()->toDateString(), 'status' => 'confirmed',
        ]);
        foreach ([[10, 5], [3, 20]] as [$qty, $price]) {
            PurchaseOrderItem::forceCreate([
                'order_id' => $po->id, 'product_id' => $this->product->id, 'quantity' => $qty,
                'price_ht' => $price, 'tva_rate' => 20, 'total_ht' => $qty * $price,
            ]);
        }

        $this->post(route('erp.purchase-orders.convert-to-delivery', $po))->assertSessionHasNoErrors();
        // Converting again reuses the receipt that has nothing counted yet.
        $this->post(route('erp.purchase-orders.convert-to-delivery', $po));
        $this->assertSame(1, DeliveryNote::count());

        $note = DeliveryNote::firstOrFail();
        $lines = $note->items()->orderBy('id')->get();
        $this->put(route('erp.delivery-notes.update', $note), [
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'partial',
            'item_source_id' => $lines->pluck('purchase_order_item_id')->all(),
            'item_product_id' => [$this->product->id, $this->product->id],
            'item_mode' => ['product', 'product'],
            'item_quantity' => [10, 3],
            'item_received_qty' => [6, 0],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $po->refresh();
        $this->assertSame('partial', $po->status);
        $this->assertSame([6.0, 0.0], $po->items()->orderBy('id')->pluck('delivered_qty')->map(fn ($q) => (float) $q)->all());
        $this->assertSame(106.0, (float) $this->product->fresh()->stock_quantity);

        // Invoice: only the 6 received units; the line received at 0 is not billed (old bug: billed 3).
        $this->post(route('erp.delivery-notes.convert-to-invoice', $note))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $invoice = SupplierInvoice::firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame(30.0, (float) $invoice->amount_ht, '6 x 5');

        // A new receipt covers only what is still expected: 4 + 3.
        $this->post(route('erp.purchase-orders.convert-to-delivery', $po))->assertSessionHasNoErrors();
        $second = DeliveryNote::latest('id')->firstOrFail();
        $this->assertSame([4.0, 3.0], $second->items()->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all());
    }

    public function test_order_and_delivery_pages_render(): void
    {
        $order = $this->order([[$this->product->id, null, 2, 100]]);
        $this->post(route('erp.sales-orders.convert-to-delivery', $order));
        $delivery = Delivery::firstOrFail();

        $this->get(route('erp.deliveries.edit', $delivery))->assertOk()->assertSee('item_source_id[]', false);
        $this->get(route('erp.sales-orders.show', $order))->assertOk();
    }
}
