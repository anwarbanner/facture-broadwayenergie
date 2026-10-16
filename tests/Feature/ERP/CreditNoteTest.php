<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Products;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P4: partial credit notes (returned quantities, discounts), refunds of overpayments, final cancellation. */
class CreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $pump;
    private Products $valve;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->pump = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0]);
        $this->valve = Products::forceCreate(['product_name' => 'Vanne', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0]);
    }

    /** 10 pumps x 100 + 1 valve x 200 = 1 200 HT, VAT 20 % = 1 440 TTC, validated. */
    private function invoice(): ClientInvoice
    {
        $this->post(route('erp.client-invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tva_rate' => 20,
            'item_mode' => ['product', 'product'],
            'item_product_id' => [$this->pump->id, $this->valve->id],
            'item_quantity' => [10, 1],
            'item_price_ht' => [100, 200],
        ])->assertSessionHasNoErrors();
        $invoice = ClientInvoice::latest('id')->firstOrFail();
        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function line(ClientInvoice $invoice, Products $product): int
    {
        return $invoice->items()->where('product_id', $product->id)->value('id');
    }

    private function credit(ClientInvoice $invoice, array $data)
    {
        return $this->post(route('erp.client-invoices.credit-note', $invoice), ['mode' => 'partial'] + $data);
    }

    private function pay(ClientInvoice $invoice, float $amount): void
    {
        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => $amount])
            ->assertSessionHasNoErrors();
    }

    public function test_a_partial_credit_note_reduces_what_the_client_owes_without_cancelling(): void
    {
        $invoice = $this->invoice();

        $this->credit($invoice, ['quantities' => [$this->line($invoice, $this->pump) => 3], 'reason' => 'Retour de marchandise'])
            ->assertSessionHasNoErrors();

        $note = ClientInvoice::where('document_type', 'credit_note')->sole();
        $this->assertEquals(300, (float) $note->amount_ht);
        $this->assertEquals(60, (float) $note->tva_amount);
        $this->assertEquals(360, (float) $note->total_ttc);
        $this->assertSame('Retour de marchandise', $note->credit_reason);
        $this->assertSame($this->line($invoice, $this->pump), $note->items()->sole()->credited_item_id);

        $invoice->refresh();
        $this->assertTrue($invoice->isValidated(), 'a partial credit note does not cancel the invoice');
        $this->assertEquals(1080, (float) $invoice->balance_remaining);
        $this->assertEquals(0, (float) $note->balance_remaining, 'nothing was paid: nothing to refund');
    }

    public function test_quantities_already_credited_cannot_be_credited_again(): void
    {
        $invoice = $this->invoice();
        $pumpLine = $this->line($invoice, $this->pump);

        $this->credit($invoice, ['quantities' => [$pumpLine => 3]])->assertSessionHasNoErrors();
        $this->credit($invoice, ['quantities' => [$pumpLine => 8]])->assertSessionHasErrors("quantities.{$pumpLine}");
        $this->credit($invoice, [])->assertSessionHasErrors('quantities');

        $this->assertSame(1, ClientInvoice::where('document_type', 'credit_note')->count());
    }

    public function test_a_discount_is_a_free_line_and_cannot_exceed_what_is_still_billed(): void
    {
        $invoice = $this->invoice();

        $this->credit($invoice, ['discount_label' => 'Geste commercial', 'discount_amount' => 100])->assertSessionHasNoErrors();
        $note = ClientInvoice::where('document_type', 'credit_note')->sole();
        $this->assertEquals(120, (float) $note->total_ttc);
        $this->assertSame('Geste commercial', $note->items()->sole()->item_name);
        $this->assertNull($note->items()->sole()->credited_item_id);

        $this->credit($invoice, ['discount_amount' => 1500])->assertSessionHasErrors('discount_amount');
        $this->assertEquals(1320, (float) $invoice->fresh()->balance_remaining);
    }

    public function test_money_already_paid_beyond_the_new_total_is_to_refund(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, 1440);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->credit($invoice, ['quantities' => [$this->line($invoice, $this->pump) => 3]])->assertSessionHasNoErrors();
        $note = ClientInvoice::where('document_type', 'credit_note')->sole();

        $this->assertEquals(0, (float) $invoice->fresh()->balance_remaining);
        $this->assertEquals(360, (float) $note->balance_remaining, 'the client paid 360 too much');

        $this->pay($note, 360);   // refund
        $this->assertEquals(0, (float) $note->fresh()->balance_remaining);
        $this->assertSame('paid', $note->fresh()->status);
    }

    public function test_crediting_what_remains_cancels_the_invoice_to_the_cent(): void
    {
        $invoice = $this->invoice();
        $this->credit($invoice, ['quantities' => [$this->line($invoice, $this->pump) => 3]])->assertSessionHasNoErrors();
        $this->credit($invoice, ['discount_amount' => 33.33])->assertSessionHasNoErrors();

        $this->post(route('erp.client-invoices.credit-note', $invoice), ['mode' => 'full'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $notes = $invoice->creditNotes()->get();
        $this->assertCount(3, $notes);
        $this->assertTrue($invoice->isCancelled());
        $this->assertEquals(0, (float) $invoice->balance_remaining);
        $this->assertEquals(1440, round((float) $notes->sum('total_ttc'), 2), 'credit notes cover the invoice exactly');
        $this->assertEquals(1200, round((float) $notes->sum('amount_ht'), 2));

        // Nothing left to credit.
        $this->post(route('erp.client-invoices.credit-note', $invoice), ['mode' => 'full'])->assertSessionHasErrors('state');
    }

    public function test_sales_journal_and_pages_show_the_net_amounts(): void
    {
        $invoice = $this->invoice();
        $this->credit($invoice, ['quantities' => [$this->line($invoice, $this->pump) => 3]])->assertSessionHasNoErrors();

        $journal = app(AccountingService::class)->journal('sales', now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals(900, $journal->sum('ht'));

        $note = ClientInvoice::where('document_type', 'credit_note')->sole();
        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()
            ->assertSee('Faire un avoir')->assertSee($note->invoice_number)
            ->assertSee('Deja credite');
        $this->get(route('erp.client-invoices.show', $note))->assertOk()->assertSee('Avoir sur la facture');
        $this->get(route('erp.client-invoices.pdf', $note))->assertOk();
    }

    public function test_supplier_invoices_accept_partial_credit_notes_too(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Grossiste', 'status' => 'active']);
        $this->post(route('erp.supplier-invoices.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'tva_rate' => 20,
            'item_mode' => ['product'],
            'item_product_id' => [$this->pump->id],
            'item_quantity' => [4],
            'item_price_ht' => [50],
        ])->assertSessionHasNoErrors();
        $invoice = SupplierInvoice::latest('id')->firstOrFail();
        $this->post(route('erp.supplier-invoices.validate', $invoice))->assertSessionHasNoErrors();

        $line = $invoice->items()->value('id');
        $this->post(route('erp.supplier-invoices.credit-note', $invoice), ['mode' => 'partial', 'quantities' => [$line => 1]])->assertSessionHasNoErrors();

        $this->assertEquals(60, (float) SupplierInvoice::where('document_type', 'credit_note')->sole()->total_ttc);
        $this->assertEquals(180, (float) $invoice->fresh()->balance_remaining);
        $this->assertTrue($invoice->fresh()->isValidated());
    }
}
