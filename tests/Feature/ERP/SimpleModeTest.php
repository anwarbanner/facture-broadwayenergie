<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Products;
use App\Models\SalesQuote;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleModeTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['branding.simple_mode' => true]);
        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 5]);
    }

    /** Quote: 2 x 100 HT + 20 % = 240 TTC. */
    private function quote(): SalesQuote
    {
        $this->post(route('erp.sales-quotes.store'), [
            'client_id' => $this->client->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'tva_rate' => 20,
            'item_mode' => ['product'],
            'item_product_id' => [$this->product->id],
            'item_quantity' => [2],
            'item_price_ht' => [100],
        ])->assertSessionHasNoErrors();

        return SalesQuote::latest('id')->firstOrFail();
    }

    public function test_simple_home_menu_and_quote_pages_render(): void
    {
        $quote = $this->quote();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Nouveau devis')->assertSee('Mes devis')->assertSee($quote->quote_number)
            ->assertDontSee('Journal des achats')->assertDontSee('Bons de reception');
        $this->get(route('erp.sales-quotes.index'))->assertOk()->assertSee('Imprimer')->assertDontSee('Vers commande');
        $this->get(route('erp.sales-quotes.show', $quote))->assertOk()
            ->assertSee('Imprimer le devis')->assertSee('Encaisser ce devis')->assertSee('Especes');
        $this->get(route('erp.sales-quotes.create'))->assertOk()->assertDontSee('Numero devis');
    }

    public function test_creating_a_quote_opens_it_to_print(): void
    {
        $this->post(route('erp.sales-quotes.store'), [
            'client_id' => $this->client->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'tva_rate' => 20,
            'item_mode' => ['product'],
            'item_product_id' => [$this->product->id],
            'item_quantity' => [1],
            'item_price_ht' => [100],
        ])->assertRedirect(route('erp.sales-quotes.show', SalesQuote::latest('id')->first()));
    }

    public function test_print_opens_the_pdf_in_the_browser(): void
    {
        $quote = $this->quote();

        $response = $this->get(route('erp.sales-quotes.print', $quote))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_one_click_cash_in_creates_a_paid_invoice_without_moving_stock(): void
    {
        $quote = $this->quote();

        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash'])
            ->assertRedirect(route('erp.sales-quotes.show', $quote))
            ->assertSessionHasNoErrors();

        $invoice = ClientInvoice::where('sales_quote_id', $quote->id)->firstOrFail();
        $this->assertTrue($invoice->isValidated());
        $this->assertStringStartsNotWith('BROUILLON', $invoice->invoice_number);
        $this->assertSame(240.0, (float) $invoice->total_ttc);
        $this->assertSame(240.0, (float) $invoice->amount_paid);
        $this->assertSame(0.0, (float) $invoice->balance_remaining);
        $this->assertSame('paid', $invoice->status);
        $this->assertCount(1, $invoice->items);

        $payment = Payment::firstOrFail();
        $this->assertSame('cash', $payment->method);
        $this->assertSame('in', $payment->direction);
        $this->assertSame($quote->quote_number, $payment->reference);

        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertSame(5.0, (float) $this->product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());

        $this->get(route('erp.sales-quotes.show', $quote))->assertOk()->assertSee('Devis encaisse')->assertSee('Imprimer la facture');
        $this->get(route('erp.client-invoices.print', $invoice))->assertOk();
    }

    public function test_a_quote_is_cashed_in_only_once_and_then_locked(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'check'])->assertSessionHasNoErrors();

        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash'])->assertSessionHasErrors('cash_in');
        $this->assertSame(1, ClientInvoice::count());
        $this->assertSame(1, Payment::count());

        $this->get(route('erp.sales-quotes.edit', $quote))->assertRedirect(route('erp.sales-quotes.show', $quote));
        $this->delete(route('erp.sales-quotes.destroy', $quote))->assertRedirect(route('erp.sales-quotes.show', $quote));
        $this->assertNotNull($quote->fresh());
        $this->post(route('erp.sales-quotes.convert-to-order', $quote))->assertSessionHasErrors('status');
    }

    public function test_unknown_payment_method_is_refused(): void
    {
        $quote = $this->quote();

        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'bitcoin'])->assertSessionHasErrors('method');
        $this->assertSame(0, ClientInvoice::count());
    }

    public function test_one_click_bl_follows_the_quote_and_never_moves_stock(): void
    {
        $quote = $this->quote();

        $this->post(route('erp.sales-quotes.delivery', $quote))->assertRedirect(route('erp.sales-quotes.show', $quote))->assertSessionHasNoErrors();
        $this->post(route('erp.sales-quotes.delivery', $quote))->assertSessionHasNoErrors(); // second click: same BL

        $this->assertSame(1, Delivery::count());
        $delivery = Delivery::with('items')->firstOrFail();
        $this->assertSame($quote->id, $delivery->sales_quote_id);
        $this->assertNull($delivery->sales_order_id);
        $this->assertFalse($delivery->moves_stock);
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(2.0, (float) $delivery->items->first()->shipped_qty);
        $this->assertSame(5.0, (float) $this->product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());

        // Editing the quote rewrites the BL lines.
        $this->put(route('erp.sales-quotes.update', $quote), [
            'client_id' => $this->client->id, 'date' => now()->toDateString(), 'status' => 'draft', 'tva_rate' => 20,
            'item_mode' => ['product'], 'item_product_id' => [$this->product->id], 'item_quantity' => [3], 'item_price_ht' => [100],
        ])->assertSessionHasNoErrors();
        $this->assertSame(3.0, (float) $delivery->fresh()->items()->first()->shipped_qty);

        $this->get(route('erp.sales-quotes.show', $quote))->assertOk()->assertSee('Imprimer le BL');
        $this->get(route('erp.deliveries.show', $delivery))->assertOk()->assertSee($quote->quote_number)->assertDontSee('Vers facture');
        $this->get(route('erp.deliveries.index'))->assertOk()->assertSee($delivery->delivery_number);
        $response = $this->get(route('erp.deliveries.print', $delivery))->assertOk();
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->get(route('erp.deliveries.excel', ['id' => $delivery->id]))->assertOk();

        // The BL is changed through its quote, and invoiced by cashing in the quote.
        $this->get(route('erp.deliveries.edit', $delivery))->assertRedirect(route('erp.deliveries.simple-edit', $delivery));
        $this->post(route('erp.deliveries.convert-to-invoice', $delivery))->assertSessionHasErrors('status');

        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame($delivery->id, ClientInvoice::firstOrFail()->delivery_id);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(5.0, (float) $this->product->fresh()->stock_quantity);
    }

    public function test_bl_list_has_a_create_button_that_picks_a_quote(): void
    {
        $quote = $this->quote();

        $this->get(route('erp.deliveries.index'))->assertOk()->assertSee('BL depuis un devis')->assertSee('BL sans devis')
            ->assertSee(route('erp.deliveries.choose-quote'));
        $this->get(route('erp.deliveries.choose-quote'))->assertOk()->assertSee($quote->quote_number)->assertSee('Faire le BL');

        $this->post(route('erp.sales-quotes.delivery', $quote), ['open' => 'delivery'])
            ->assertRedirect(route('erp.deliveries.show', Delivery::firstOrFail()));

        // A quote that has its BL is no longer offered.
        $this->get(route('erp.deliveries.choose-quote'))->assertOk()->assertDontSee($quote->quote_number);
    }

    public function test_bl_without_quote_is_typed_by_hand_and_never_moves_stock(): void
    {
        $this->get(route('erp.deliveries.simple-create'))->assertOk()->assertSee('Nouveau bon de livraison (sans devis)')
            ->assertDontSee('name="item_price_ht[]"', false)->assertDontSee('Qt commandee');

        $this->post(route('erp.deliveries.simple-store'), [
            'client_id' => '__new__:Chantier Hay Riad', 'delivery_date' => now()->toDateString(),
            'item_mode' => ['product', 'manual', 'manual'], 'item_product_id' => [$this->product->id, '', ''],
            'item_name' => ['', 'Cable 6mm', ''], 'item_quantity' => [2, 15, 1],
        ])->assertSessionHasNoErrors();

        $delivery = Delivery::with('items')->latest('id')->firstOrFail();
        $this->assertNull($delivery->sales_order_id);
        $this->assertNull($delivery->sales_quote_id);
        $this->assertFalse($delivery->moves_stock);
        $this->assertCount(2, $delivery->items, 'the empty third line is ignored');
        $this->assertSame('Chantier Hay Riad', $delivery->client->company_name);
        $this->assertSame(15.0, (float) $delivery->items->firstWhere('item_name', 'Cable 6mm')->shipped_qty);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(5.0, (float) $this->product->fresh()->stock_quantity);

        $this->get(route('erp.deliveries.show', $delivery))->assertOk()->assertSee('Modifier le BL')->assertDontSee('Vers facture')
            ->assertDontSee('Qte commandee')->assertDontSee('Numero de suivi');
        $pdf = app(\App\Services\Pdf\LetterheadDocument::class);
        $doc = $pdf->delivery($delivery->fresh());
        $this->assertTrue($doc['single']);
        $this->assertStringNotContainsString('Qté commandée', $pdf->html($doc));
        $sheet = app(\App\Services\Export\DocumentSpreadsheet::class)->build($doc)->getActiveSheet();
        $this->assertNotNull($sheet);
        $this->get(route('erp.deliveries.excel', ['id' => $delivery->id]))->assertOk();
        $this->get(route('erp.deliveries.print', $delivery))->assertOk();
        $this->put(route('erp.deliveries.simple-update', $delivery), [
            'client_id' => $delivery->client_id, 'delivery_date' => now()->toDateString(),
            'item_mode' => ['manual'], 'item_name' => ['Cable 10mm'], 'item_quantity' => [3],
        ])->assertRedirect(route('erp.deliveries.show', $delivery));
        $this->assertSame(['Cable 10mm'], $delivery->fresh()->items->pluck('item_name')->all());

        $this->post(route('erp.deliveries.simple-store'), ['client_id' => $this->client->id, 'delivery_date' => now()->toDateString(),
            'item_mode' => ['manual'], 'item_name' => [''], 'item_quantity' => [1]])->assertSessionHasErrors('item_quantity');
        $this->post(route('erp.deliveries.convert-to-invoice', $delivery))->assertSessionHasErrors('status');
        $this->assertSame(0, ClientInvoice::count());
    }

    public function test_a_bl_from_a_quote_can_be_edited_by_hand_and_then_stops_following_the_quote(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.delivery', $quote));
        $delivery = Delivery::firstOrFail();

        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('Modifier le BL');
        $this->get(route('erp.deliveries.show', $delivery))->assertSee('Modifier le BL')->assertSee('Modifier le devis');
        $this->get(route('erp.deliveries.simple-edit', $delivery))->assertOk()->assertSee('ne sera plus mis a jour');

        // Only 1 delivered out of 2 quoted.
        $this->put(route('erp.deliveries.simple-update', $delivery), [
            'client_id' => $this->client->id, 'delivery_date' => now()->toDateString(),
            'item_mode' => ['product'], 'item_product_id' => [$this->product->id], 'item_quantity' => [1],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($delivery->fresh()->follows_quote);

        // The quote changes; the BL edited by hand keeps its own quantity.
        $this->put(route('erp.sales-quotes.update', $quote), [
            'client_id' => $this->client->id, 'date' => now()->toDateString(), 'status' => 'draft', 'tva_rate' => 20,
            'item_mode' => ['product'], 'item_product_id' => [$this->product->id], 'item_quantity' => [5], 'item_price_ht' => [100],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1.0, (float) $delivery->fresh()->items()->first()->shipped_qty);
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('ne suit plus le devis');

        // Deleting the quote keeps this BL, as a BL without quote.
        $this->delete(route('erp.sales-quotes.destroy', $quote));
        $this->assertNotNull($delivery->fresh());
        $this->assertNull($delivery->fresh()->sales_quote_id);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_an_invoiced_bl_can_no_longer_be_edited(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.delivery', $quote));
        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash']);
        $delivery = Delivery::firstOrFail();

        $this->get(route('erp.deliveries.simple-edit', $delivery))->assertRedirect(route('erp.deliveries.show', $delivery));
        $this->put(route('erp.deliveries.simple-update', $delivery), [
            'client_id' => $this->client->id, 'delivery_date' => now()->toDateString(),
            'item_mode' => ['manual'], 'item_name' => ['X'], 'item_quantity' => [1],
        ])->assertRedirect(route('erp.deliveries.show', $delivery));
        $this->assertSame(2.0, (float) $delivery->fresh()->items()->first()->shipped_qty);
        $this->get(route('erp.deliveries.show', $delivery))->assertDontSee('Modifier le BL');
    }

    public function test_deleting_a_quote_deletes_its_bl(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.delivery', $quote));

        $this->delete(route('erp.sales-quotes.destroy', $quote))->assertRedirect(route('erp.sales-quotes.index'));
        $this->assertSame(0, Delivery::count());
    }

    public function test_lists_export_to_excel_with_their_filters(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.delivery', $quote));
        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'transfer']);

        foreach (['sales-quotes', 'client-invoices', 'deliveries'] as $type) {
            $response = $this->get(route('erp.list-export', ['type' => $type, 'q' => 'Client']))->assertOk();
            $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
            $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
        }
        $this->get('/erp/list-export/purchase-orders')->assertNotFound();

        $this->get(route('erp.sales-quotes.index'))->assertSee('Exporter la liste en Excel');
        $this->get(route('erp.client-invoices.index'))->assertSee('Exporter la liste en Excel')->assertSee('Imprimer');
        $this->get(route('erp.deliveries.index'))->assertSee('Exporter la liste en Excel');
    }

    public function test_simple_client_and_product_forms(): void
    {
        $this->get(route('erp.contacts.index', ['type' => 'client']))->assertOk()->assertSee('Mes clients')->assertSee('Faire un devis');
        $this->get(route('erp.contacts.create'))->assertOk()->assertSee('Nom du client')->assertDontSee('Plafond de credit');

        $this->post(route('erp.contacts.store'), [
            'type' => 'client', 'status' => 'active', 'company_name' => 'Ahmed Bennani', 'phone' => '0600000000', 'city' => 'Rabat', 'credit_limit' => 0,
        ])->assertRedirect(route('erp.contacts.index', ['type' => 'client']))->assertSessionHasNoErrors();
        $client = Contact::where('company_name', 'Ahmed Bennani')->firstOrFail();
        $this->get(route('erp.sales-quotes.create', ['client_id' => $client->id]))->assertOk()
            ->assertSee('value="' . $client->id . '" selected', false);

        $this->get(route('erp.products.index'))->assertOk()->assertSee('Mes produits')->assertDontSee('Seuil min');
        $this->get(route('erp.products.create'))->assertOk()->assertSee('Nom du produit')->assertDontSee('Alerte stock minimum');

        // Editing a product in simple mode keeps its stock untouched.
        $this->put(route('erp.products.update', $this->product), [
            'type' => 'product', 'product_name' => 'Pompe solaire', 'sale_price_ht' => 150, 'unit_type' => 'piece',
            'cost_price_ht' => 0, 'stock_quantity' => 5, 'min_stock_alert_level' => 0, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Pompe solaire', $this->product->fresh()->product_name);
        $this->assertSame(5.0, (float) $this->product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_supplier_quotes_and_suppliers_in_simple_mode(): void
    {
        $this->get(route('dashboard'))->assertSee('Devis fournisseurs')->assertSee('Fournisseurs');

        // New supplier from "Mes fournisseurs", then "Faire un devis fournisseur".
        $this->get(route('erp.contacts.index', ['type' => 'supplier']))->assertOk()->assertSee('Mes fournisseurs')->assertSee('Nouveau fournisseur');
        $this->get(route('erp.contacts.create', ['type' => 'supplier']))->assertOk()->assertSee('Nom du fournisseur')
            ->assertSee('name="type" value="supplier"', false);
        $this->post(route('erp.contacts.store'), ['type' => 'supplier', 'status' => 'active', 'company_name' => 'Grossiste Atlas', 'credit_limit' => 0])
            ->assertRedirect(route('erp.contacts.index', ['type' => 'supplier']));
        $supplier = Contact::where('company_name', 'Grossiste Atlas')->firstOrFail();
        $this->get(route('erp.contacts.index', ['type' => 'supplier']))->assertSee('Faire un devis fournisseur');

        $this->get(route('erp.purchase-quotes.create', ['supplier_id' => $supplier->id]))->assertOk()
            ->assertSee('Nouveau devis fournisseur')->assertDontSee('Numero demande')
            ->assertSee('value="' . $supplier->id . '" selected', false);
        $this->post(route('erp.purchase-quotes.store'), [
            'supplier_id' => $supplier->id, 'date' => now()->toDateString(), 'status' => 'draft', 'tva_rate' => 20,
            'item_mode' => ['product'], 'item_product_id' => [$this->product->id], 'item_quantity' => [4], 'item_price_ht' => [50],
        ])->assertSessionHasNoErrors();
        $quote = \App\Models\PurchaseQuote::latest('id')->firstOrFail();

        $this->get(route('erp.purchase-quotes.index'))->assertOk()->assertSee('Devis fournisseurs')->assertSee($quote->quote_number)
            ->assertSee('Exporter la liste en Excel')->assertDontSee('Vers commande');
        $this->get(route('erp.purchase-quotes.show', $quote))->assertOk()->assertSee('Imprimer')->assertDontSee('Convertir en commande');
        $print = $this->get(route('erp.purchase-quotes.print', $quote))->assertOk();
        $this->assertStringStartsWith('inline', (string) $print->headers->get('Content-Disposition'));
        $this->get(route('erp.purchase-quotes.excel', ['id' => $quote->id]))->assertOk();
        $this->get(route('erp.list-export', ['type' => 'purchase-quotes']))->assertOk();
        $this->assertSame(0, StockMovement::count());
    }

    public function test_invoice_now_pay_later_keeps_quote_and_invoice_linked(): void
    {
        $quote = $this->quote();
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('Faire la facture (payer plus tard)');

        $this->post(route('erp.sales-quotes.invoice', $quote))->assertRedirect(route('erp.sales-quotes.show', $quote))->assertSessionHasNoErrors();
        $this->post(route('erp.sales-quotes.invoice', $quote))->assertSessionHasErrors('cash_in');

        $invoice = ClientInvoice::where('sales_quote_id', $quote->id)->firstOrFail();
        $this->assertTrue($invoice->isValidated());
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(240.0, (float) $invoice->balance_remaining);
        $this->assertSame(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame(0, Payment::count());
        $this->assertSame('converted', $quote->fresh()->status);
        $this->get(route('erp.sales-quotes.edit', $quote))->assertRedirect(route('erp.sales-quotes.show', $quote));

        // Links both ways, and the quote number on the printed invoice.
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('Facture ' . $invoice->invoice_number)->assertSee('reste a payer')
            ->assertSee('Voir la facture')->assertSee('Le client paie');
        $this->get(route('erp.sales-quotes.index'))->assertSee('Facture')->assertSee('Encaisser');
        $this->get(route('erp.client-invoices.show', $invoice))->assertSee('Devis')->assertSee($quote->quote_number)
            ->assertSee('Encaisser cette facture');
        $this->get(route('erp.client-invoices.index'))->assertSee('Devis ' . $quote->quote_number);
        $pdf = app(\App\Services\Pdf\LetterheadDocument::class);
        $this->assertSame($quote->quote_number, $pdf->clientInvoice($invoice->fresh())['meta']['Devis']);

        // The client pays: "Encaisser" on the quote pays this same invoice.
        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'check'])->assertSessionHasNoErrors();
        $this->assertSame(1, ClientInvoice::count());
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('check', Payment::firstOrFail()->method);
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('Devis encaisse');
        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash'])->assertSessionHasErrors('cash_in');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_an_invoice_is_paid_in_one_click_from_its_page(): void
    {
        $quote = $this->quote();
        $this->post(route('erp.sales-quotes.invoice', $quote));
        $invoice = ClientInvoice::firstOrFail();

        $this->post(route('erp.client-invoices.payments', $invoice), [
            'amount' => $invoice->balance_remaining, 'payment_date' => now()->toDateString(), 'method' => 'cash', 'reference' => $quote->quote_number,
        ])->assertRedirect(route('erp.client-invoices.show', $invoice))->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->get(route('erp.client-invoices.show', $invoice))->assertDontSee('Encaisser cette facture');
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee('Devis encaisse');
    }

    public function test_supplier_quote_pdf_is_titled_devis_fournisseur(): void
    {
        $quote = \App\Models\PurchaseQuote::forceCreate([
            'quote_number' => 'DPA-T-1', 'supplier_id' => Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fourn', 'status' => 'active'])->id,
            'date' => now()->toDateString(), 'status' => 'draft', 'total_ht' => 0, 'total_tva' => 0, 'total_ttc' => 0, 'currency' => 'MAD',
        ]);
        $pdf = app(\App\Services\Pdf\LetterheadDocument::class);

        $this->assertSame('DEVIS FOURNISSEUR', $pdf->purchaseQuote($quote)['title']);
        config(['branding.simple_mode' => false]);
        $this->assertSame('DEMANDE D\'ACHAT', $pdf->purchaseQuote($quote->fresh())['title']);
    }

    public function test_invoice_without_quote_is_validated_and_ready_to_cash_in(): void
    {
        $this->get(route('erp.client-invoices.index'))->assertSee('Facture sans devis');
        $this->get(route('erp.client-invoices.create'))->assertOk()->assertSee('Facture sans devis')
            ->assertDontSee('name="sales_order_id"', false)->assertDontSee('name="payment_terms"', false)
            ->assertSee('class="col-md-6 mb-3"', false);

        $this->post(route('erp.client-invoices.store'), [
            'client_id' => $this->client->id, 'invoice_date' => now()->toDateString(), 'tva_rate' => 20,
            'item_mode' => ['manual'], 'item_name' => ['Depannage'], 'item_quantity' => [1], 'item_price_ht' => [300],
        ])->assertSessionHasNoErrors();

        $invoice = ClientInvoice::latest('id')->firstOrFail();
        $this->assertTrue($invoice->isValidated());
        $this->assertNull($invoice->sales_quote_id);
        $this->assertSame(360.0, (float) $invoice->balance_remaining);
        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()->assertSee('Encaisser cette facture')->assertSee('Imprimer');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_supplier_invoice_from_a_supplier_quote_then_paid(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Grossiste Atlas', 'status' => 'active']);
        $this->post(route('erp.purchase-quotes.store'), [
            'supplier_id' => $supplier->id, 'date' => now()->toDateString(), 'status' => 'draft', 'tva_rate' => 20,
            'item_mode' => ['product'], 'item_product_id' => [$this->product->id], 'item_quantity' => [4], 'item_price_ht' => [50],
        ])->assertSessionHasNoErrors();
        $quote = \App\Models\PurchaseQuote::latest('id')->firstOrFail();

        $this->get(route('erp.purchase-quotes.show', $quote))->assertSee('Faire la facture fournisseur')
            ->assertSee(now()->format('d/m/Y'))->assertSee('Informations du devis fournisseur');
        $this->post(route('erp.purchase-quotes.invoice', $quote))->assertRedirect(route('erp.purchase-quotes.show', $quote))->assertSessionHasNoErrors();
        $this->post(route('erp.purchase-quotes.invoice', $quote))->assertSessionHasErrors('invoice');

        $invoice = \App\Models\SupplierInvoice::where('purchase_quote_id', $quote->id)->firstOrFail();
        $this->assertTrue($invoice->isValidated());
        $this->assertSame(240.0, (float) $invoice->balance_remaining);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('accepted', $quote->fresh()->status);

        // Linked both ways, quote locked, printed with the quote number.
        $this->get(route('erp.purchase-quotes.show', $quote))->assertSee('Facture fournisseur ' . $invoice->invoice_number)
            ->assertSee('Payer la facture')->assertDontSee('Faire la facture fournisseur');
        $this->get(route('erp.purchase-quotes.edit', $quote))->assertRedirect(route('erp.purchase-quotes.show', $quote));
        $this->get(route('erp.purchase-quotes.index'))->assertSee('Facture');
        $this->get(route('erp.supplier-invoices.show', $invoice))->assertSee($quote->quote_number)->assertSee('Payer cette facture fournisseur');
        $this->get(route('erp.supplier-invoices.index'))->assertSee('Devis ' . $quote->quote_number)->assertSee('Facture fournisseur sans devis');
        $pdf = app(\App\Services\Pdf\LetterheadDocument::class);
        $this->assertSame($quote->quote_number, $pdf->supplierInvoice($invoice->fresh())['meta']['Devis fournisseur']);
        $this->get(route('erp.supplier-invoices.print', $invoice))->assertOk();
        $this->get(route('erp.list-export', ['type' => 'supplier-invoices']))->assertOk();

        // One-click payment (decaissement).
        $this->post(route('erp.supplier-invoices.payments', $invoice), [
            'amount' => 240, 'payment_date' => now()->toDateString(), 'method' => 'transfer', 'reference' => $quote->quote_number,
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('out', Payment::firstOrFail()->direction);
        $this->get(route('erp.purchase-quotes.show', $quote))->assertSee('payee');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_supplier_invoice_without_quote_is_validated(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fourn B', 'status' => 'active']);
        $this->get(route('erp.supplier-invoices.create'))->assertOk()->assertSee('Facture fournisseur sans devis')
            ->assertDontSee('name="purchase_order_id"', false);
        $this->post(route('erp.supplier-invoices.store'), [
            'supplier_id' => $supplier->id, 'invoice_date' => now()->toDateString(), 'tva_rate' => 20,
            'item_mode' => ['manual'], 'item_name' => ['Transport'], 'item_quantity' => [1], 'item_price_ht' => [100],
        ])->assertSessionHasNoErrors();
        $invoice = \App\Models\SupplierInvoice::latest('id')->firstOrFail();
        $this->assertTrue($invoice->isValidated());
        $this->get(route('erp.supplier-invoices.show', $invoice))->assertSee('Payer cette facture fournisseur');
    }

    public function test_cash_in_is_reserved_to_finance(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->userWithRole('Technicien'));

        $this->post(route('erp.sales-quotes.cash-in', $quote), ['method' => 'cash'])->assertForbidden();
        $this->get(route('erp.sales-quotes.show', $quote))->assertOk()->assertDontSee('Encaisser ce devis');
        $this->assertSame(0, ClientInvoice::count());
    }
}
