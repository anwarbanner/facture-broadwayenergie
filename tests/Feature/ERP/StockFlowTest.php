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
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockFlowTest extends TestCase
{
    use RefreshDatabase;

    private Products $product;
    private Contact $client;
    private SalesOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();

        $this->product = Products::forceCreate([
            'product_name' => 'Pompe',
            'type' => 'product',
            'status' => 'active',
            'stock_quantity' => 10,
            'min_stock_alert_level' => 0,
        ]);
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->order = SalesOrder::forceCreate([
            'order_number' => 'CMD-TEST-1',
            'client_id' => $this->client->id,
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
    }

    private function deliveryPayload(string $status, float $qty): array
    {
        return [
            'sales_order_id' => $this->order->id,
            'client_id' => $this->client->id,
            'delivery_date' => now()->toDateString(),
            'status' => $status,
            'item_product_id' => [$this->product->id],
            'item_quantity' => [$qty],
            'item_shipped_qty' => [$qty],
        ];
    }

    private function stock(): float
    {
        return (float) $this->product->fresh()->stock_quantity;
    }

    public function test_pending_delivery_does_not_move_stock(): void
    {
        $this->post(route('erp.deliveries.store'), $this->deliveryPayload('pending', 4))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(10.0, $this->stock());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_editing_a_shipped_delivery_applies_only_the_difference(): void
    {
        $this->post(route('erp.deliveries.store'), $this->deliveryPayload('shipped', 5))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $delivery = Delivery::firstOrFail();
        $this->assertSame(5.0, $this->stock());

        // Saving again with the same data must not decrement a second time (old bug).
        $this->put(route('erp.deliveries.update', $delivery), $this->deliveryPayload('shipped', 5))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(5.0, $this->stock());

        $this->put(route('erp.deliveries.update', $delivery), $this->deliveryPayload('shipped', 3))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(7.0, $this->stock());

        $this->put(route('erp.deliveries.update', $delivery), $this->deliveryPayload('returned', 3))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(10.0, $this->stock());

        // History is append-only: out 5, in 2, in 3.
        $this->assertSame(3, StockMovement::count());
        $this->assertSame(0.0, (float) StockMovement::where('movement_type', 'in')->sum('quantity') - (float) StockMovement::where('movement_type', 'out')->sum('quantity'));
    }

    public function test_deleting_a_shipped_delivery_restores_stock(): void
    {
        $this->post(route('erp.deliveries.store'), $this->deliveryPayload('delivered', 6))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(4.0, $this->stock());

        $this->delete(route('erp.deliveries.destroy', Delivery::firstOrFail()));

        $this->assertSame(10.0, $this->stock());
    }

    public function test_insufficient_stock_rolls_back_the_whole_delivery(): void
    {
        $this->post(route('erp.deliveries.store'), $this->deliveryPayload('pending', 15))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $delivery = Delivery::firstOrFail();

        $this->put(route('erp.deliveries.update', $delivery), $this->deliveryPayload('shipped', 15))
            ->assertSessionHasErrors('item_quantity');

        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertSame(10.0, $this->stock());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_invoices_never_move_stock(): void
    {
        SalesOrderItem::forceCreate([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price_ht' => 100,
            'tva_rate' => 20,
            'total_ht' => 200,
        ]);
        $this->post(route('erp.deliveries.store'), $this->deliveryPayload('pending', 2))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $delivery = Delivery::firstOrFail();

        // Converting marks the delivery as delivered: stock leaves once, here.
        $this->post(route('erp.deliveries.convert-to-invoice', $delivery))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(8.0, $this->stock());

        // A paid invoice used to decrement stock a second time: validate it and pay it in full.
        $invoice = ClientInvoice::firstOrFail();
        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => $invoice->fresh()->balance_remaining])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(8.0, $this->stock());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_receipt_adds_only_received_quantities(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fournisseur SA', 'status' => 'active']);
        $purchaseOrder = PurchaseOrder::forceCreate([
            'order_number' => 'BCA-TEST-1',
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        PurchaseOrderItem::forceCreate([
            'order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 8,
            'price_ht' => 50,
            'tva_rate' => 20,
            'total_ht' => 400,
        ]);

        // Conversion creates a receipt with received_qty = 0: nothing is in stock yet (old bug: +8).
        $this->post(route('erp.purchase-orders.convert-to-delivery', $purchaseOrder))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $note = DeliveryNote::firstOrFail();
        $this->assertSame(10.0, $this->stock());

        $this->put(route('erp.delivery-notes.update', $note), [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'partial',
            'item_product_id' => [$this->product->id],
            'item_mode' => ['product'],
            'item_quantity' => [8],
            'item_received_qty' => [4],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame('partial', $note->fresh()->status);
        $this->assertSame(14.0, $this->stock());

        $this->delete(route('erp.delivery-notes.destroy', $note));
        $this->assertSame(10.0, $this->stock());
    }

    public function test_manual_adjustment_records_a_signed_movement(): void
    {
        $this->put(route('erp.products.update', $this->product), [
            'product_name' => 'Pompe',
            'sale_price_ht' => 100,
            'cost_price_ht' => 60,
            'unit_type' => 'piece',
            'stock_quantity' => 7,
            'min_stock_alert_level' => 0,
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(7.0, $this->stock());
        $movement = StockMovement::firstOrFail();
        $this->assertSame('out', $movement->movement_type);
        $this->assertSame(3.0, (float) $movement->quantity);
        $this->assertSame('MANUAL_ADJUSTMENT', $movement->reference_document);
    }
}
