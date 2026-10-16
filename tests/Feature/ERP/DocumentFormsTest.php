<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Products;
use App\Models\PurchaseQuote;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\StockMovement;
use App\Services\Contacts\ContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document forms (2026-09-28): new contacts typed in the form, one VAT rate per document applied
 * to the total HT, no exchange rate, free-text lines on the sales side down to the invoice.
 */
class DocumentFormsTest extends TestCase
{
    use RefreshDatabase;

    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 50, 'sale_price_ht' => 100]);
    }

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'date' => now()->toDateString(),
            'status' => 'draft',
            'tva_rate' => 20,
            'item_mode' => ['product'],
            'item_product_id' => [$this->product->id],
            'item_service_id' => [''],
            'item_name' => [''],
            'item_quantity' => [2],
            'item_price_ht' => [100],
        ], $overrides);
    }

    public function test_a_new_client_typed_in_the_quote_is_added_to_the_contacts_once(): void
    {
        $payload = $this->quotePayload(['client_id' => ContactResolver::NEW_PREFIX . 'Hotel Atlas']);

        $this->post(route('erp.sales-quotes.store'), $payload)->assertSessionHasNoErrors();
        // Same name, other case: the existing contact is reused, not duplicated.
        $this->post(route('erp.sales-quotes.store'), array_merge($payload, ['client_id' => ContactResolver::NEW_PREFIX . 'hotel atlas']))->assertSessionHasNoErrors();

        $client = Contact::where('company_name', 'Hotel Atlas')->sole();
        $this->assertSame('client', $client->type);
        $this->assertSame('active', $client->status);
        $this->assertSame(2, SalesQuote::where('client_id', $client->id)->count());
    }

    public function test_a_new_supplier_typed_in_the_purchase_quote_is_added_as_supplier(): void
    {
        $this->post(route('erp.purchase-quotes.store'), array_merge($this->quotePayload(), [
            'supplier_id' => ContactResolver::NEW_PREFIX . 'Grossiste Nord',
            'validity_date' => now()->addDays(10)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $supplier = Contact::where('company_name', 'Grossiste Nord')->sole();
        $this->assertSame('supplier', $supplier->type);
        $this->assertSame($supplier->id, PurchaseQuote::sole()->supplier_id);
    }

    public function test_an_empty_new_contact_name_is_refused(): void
    {
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload(['client_id' => ContactResolver::NEW_PREFIX . '   ']))
            ->assertSessionHasErrors('client_id');
        $this->assertSame(0, Contact::count());
    }

    public function test_vat_is_one_rate_applied_to_the_total_and_exchange_rate_is_always_one(): void
    {
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);

        // 3 lines of 0.10 at 5.5 %: line by line = 3 x 0.01 = 0.03; on the total 0.30 x 5.5 % = 0.0165 -> 0.02.
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload([
            'client_id' => $client->id,
            'tva_rate' => 5.5,
            'exchange_rate' => 11,
            'item_mode' => ['manual', 'manual', 'manual'],
            'item_product_id' => ['', '', ''],
            'item_service_id' => ['', '', ''],
            'item_name' => ['A', 'B', 'C'],
            'item_quantity' => [1, 1, 1],
            'item_price_ht' => [0.10, 0.10, 0.10],
        ]))->assertSessionHasNoErrors();

        $quote = SalesQuote::sole();
        $this->assertEquals(0.30, (float) $quote->total_ht);
        $this->assertEquals(0.02, (float) $quote->total_tva);
        $this->assertEquals(0.32, (float) $quote->total_ttc);
        $this->assertEquals(5.5, (float) $quote->tva_rate);
        $this->assertEquals(1, (float) $quote->exchange_rate);
        $this->assertTrue($quote->items()->get()->every(fn ($item) => (float) $item->tva_rate === 5.5));
    }

    public function test_the_row_type_decides_which_article_is_kept(): void
    {
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);

        // A product left in the hidden product field of a manual row is ignored.
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload([
            'client_id' => $client->id,
            'item_mode' => ['manual'],
            'item_name' => ['Main d\'oeuvre'],
        ]))->assertSessionHasNoErrors();

        $item = SalesQuote::sole()->items()->sole();
        $this->assertNull($item->product_id);
        $this->assertSame('Main d\'oeuvre', $item->item_name);
    }

    public function test_a_manual_quote_line_follows_the_sales_chain_without_moving_stock(): void
    {
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);

        $this->post(route('erp.sales-quotes.store'), $this->quotePayload([
            'client_id' => $client->id,
            'tva_rate' => 10,
            'item_mode' => ['product', 'manual'],
            'item_product_id' => [$this->product->id, ''],
            'item_service_id' => ['', ''],
            'item_name' => ['', 'Installation sur site'],
            'item_quantity' => [2, 1],
            'item_price_ht' => [100, 350],
        ]))->assertSessionHasNoErrors();

        $quote = SalesQuote::sole();
        $this->post(route('erp.sales-quotes.convert-to-order', $quote))->assertSessionHasNoErrors();
        $order = SalesOrder::sole();
        $this->assertEquals(10, (float) $order->tva_rate);
        $this->assertSame('Installation sur site', $order->items()->whereNull('product_id')->sole()->item_name);

        $this->post(route('erp.sales-orders.convert-to-delivery', $order))->assertSessionHasNoErrors();
        $delivery = Delivery::sole();
        $manualLine = $delivery->items()->whereNull('product_id')->sole();
        $this->assertSame('Installation sur site', $manualLine->item_name);
        $this->assertNotNull($manualLine->sales_order_item_id);

        $this->post(route('erp.deliveries.convert-to-invoice', $delivery))->assertSessionHasNoErrors();
        $invoice = ClientInvoice::sole();
        $this->assertEquals(550, (float) $invoice->amount_ht);
        $this->assertEquals(55, (float) $invoice->tva_amount);
        $this->assertEquals(605, (float) $invoice->total_ttc);
        $this->assertEquals(10, (float) $invoice->tva_rate);
        $this->assertSame('Installation sur site', $invoice->items()->whereNull('product_id')->sole()->item_name);

        // Only the product moved stock.
        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(48, (float) $this->product->fresh()->stock_quantity);

        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()->assertSee('Installation sur site')->assertSee('TVA (10 %)');
    }

    public function test_creating_returns_to_the_list_with_the_new_row_highlighted(): void
    {
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);

        $response = $this->post(route('erp.sales-quotes.store'), $this->quotePayload(['client_id' => $client->id, 'status' => 'rejected']));
        $quote = SalesQuote::sole();
        $response->assertRedirect(route('erp.sales-quotes.index'));
        $response->assertSessionHas('highlight', route('erp.sales-quotes.show', $quote));
        $this->get(route('erp.sales-quotes.index'))
            ->assertSee('Devis client cree.')
            ->assertSee('data-erp-highlight="' . route('erp.sales-quotes.show', $quote) . '"', false)
            // the row links to the same URL, which is how erp-ui.js finds it
            ->assertSee('href="' . route('erp.sales-quotes.show', $quote) . '"', false);
    }

    public function test_a_refused_conversion_explains_why(): void
    {
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload(['client_id' => $client->id, 'status' => 'rejected']));
        $quote = SalesQuote::sole();

        // Before, the refusal was flashed but never displayed on the document page.
        $this->from(route('erp.sales-quotes.show', $quote))
            ->followingRedirects()
            ->post(route('erp.sales-quotes.convert-to-order', $quote))
            ->assertSee('Ce devis ne peut pas etre converti.');
    }

    public function test_document_lists_search_by_number_or_contact_name_and_filter_by_status(): void
    {
        $atlas = Contact::forceCreate(['type' => 'client', 'company_name' => 'Hotel Atlas', 'status' => 'active']);
        $other = Contact::forceCreate(['type' => 'client', 'company_name' => 'Garage Sud', 'status' => 'active']);
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload(['client_id' => $atlas->id, 'status' => 'sent']));
        $this->post(route('erp.sales-quotes.store'), $this->quotePayload(['client_id' => $other->id, 'status' => 'draft']));

        $this->get(route('erp.sales-quotes.index', ['q' => 'atlas']))->assertSee('Hotel Atlas')->assertDontSee('Garage Sud');
        $this->get(route('erp.sales-quotes.index', ['status' => 'draft']))->assertSee('Garage Sud')->assertDontSee('Hotel Atlas');
    }

    public function test_document_forms_render_the_contact_picker_and_single_vat_field(): void
    {
        foreach (['erp.sales-quotes.create', 'erp.sales-orders.create', 'erp.client-invoices.create',
                  'erp.purchase-quotes.create', 'erp.purchase-orders.create', 'erp.supplier-invoices.create'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('data-contact-picker', false)
                ->assertSee('name="tva_rate"', false)
                ->assertSee('value="manual"', false)
                ->assertDontSee('name="exchange_rate"', false)
                ->assertDontSee('name="item_tva_rate[]"', false);
        }
    }
}
