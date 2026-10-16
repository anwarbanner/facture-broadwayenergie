<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\ClientStatement;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Products;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LockingAndDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 10]);
    }

    private function orderWithDelivery(): array
    {
        $order = SalesOrder::forceCreate([
            'order_number' => 'CMD-L-1', 'client_id' => $this->client->id, 'order_date' => now()->toDateString(), 'status' => 'confirmed',
        ]);
        SalesOrderItem::forceCreate([
            'order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 3, 'price_ht' => 100, 'tva_rate' => 20, 'total_ht' => 300,
        ]);
        $this->post(route('erp.sales-orders.convert-to-delivery', $order))->assertSessionHasNoErrors();

        return [$order, Delivery::firstOrFail()];
    }

    public function test_an_invoiced_delivery_is_locked(): void
    {
        [, $delivery] = $this->orderWithDelivery();
        $this->post(route('erp.deliveries.convert-to-invoice', $delivery))->assertSessionHasNoErrors();

        $this->delete(route('erp.deliveries.destroy', $delivery))->assertSessionHasErrors('status');
        $this->assertNotNull($delivery->fresh());

        // Once the invoice is cancelled by a credit note, the delivery can be corrected again.
        $invoice = ClientInvoice::firstOrFail();
        $this->post(route('erp.client-invoices.validate', $invoice));
        $this->post(route('erp.client-invoices.credit-note', $invoice));
        $this->assertNull($delivery->fresh()->clientInvoice, 'a cancelled invoice is no longer "the" invoice of the delivery');
        $this->delete(route('erp.deliveries.destroy', $delivery))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($delivery);
    }

    public function test_order_page_shows_delivered_and_remaining_quantities(): void
    {
        [$order, $delivery] = $this->orderWithDelivery();
        $lines = $delivery->items()->get();
        $this->put(route('erp.deliveries.update', $delivery), [
            'sales_order_id' => $order->id,
            'client_id' => $this->client->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'shipped',
            'item_source_id' => $lines->pluck('sales_order_item_id')->all(),
            'item_product_id' => [$this->product->id],
            'item_quantity' => [2],
            'item_shipped_qty' => [2],
        ])->assertSessionHasNoErrors();

        $this->get(route('erp.sales-orders.show', $order))
            ->assertOk()
            ->assertSeeInOrder(['Livre', 'Reste'])
            ->assertSeeInOrder(['3', '2', '1']) // quantities without useless decimals (not "3.000")
            ->assertDontSee('3.000');
    }

    public function test_raising_the_alert_threshold_alone_triggers_the_low_stock_alert(): void
    {
        Notification::fake();

        $this->put(route('erp.products.update', $this->product), [
            'product_name' => 'Pompe',
            'sale_price_ht' => 100,
            'cost_price_ht' => 60,
            'unit_type' => 'piece',
            'stock_quantity' => 10,      // unchanged
            'min_stock_alert_level' => 15,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo(auth()->user(), LowStockNotification::class);
    }

    public function test_statement_list_shows_live_balance(): void
    {
        $this->post(route('erp.client-invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_product_id' => [$this->product->id],
            'item_quantity' => [1],
            'item_price_ht' => [100],
            'item_tva_rate' => [20],
        ]);
        $invoice = ClientInvoice::firstOrFail();
        $this->post(route('erp.client-invoices.validate', $invoice));
        $this->post(route('erp.client-statements.store'), [
            'client_id' => $this->client->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);
        $this->assertSame(120.0, (float) ClientStatement::firstOrFail()->balance);

        // Paid after the statement was generated: the list must not keep showing 120.
        $this->post(route('erp.client-invoices.payments', $invoice), ['amount' => 120, 'payment_date' => now()->toDateString(), 'method' => 'cash']);

        $listed = $this->get(route('erp.client-statements.index'))->assertOk()->viewData('statements')->first();
        $this->assertSame(0.0, (float) $listed->balance);
        $this->assertSame(120.0, (float) $listed->total_payments);
    }
}
