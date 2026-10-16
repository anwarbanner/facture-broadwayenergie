<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\ClientStatement;
use App\Models\Contact;
use App\Models\Products;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0]);
    }

    /** 2 x 100 HT + 20% TVA = 240 TTC */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_product_id' => [$this->product->id],
            'item_quantity' => [2],
            'item_price_ht' => [100],
            'item_tva_rate' => [20],
        ], $overrides);
    }

    private function createDraft(array $overrides = []): ClientInvoice
    {
        $this->post(route('erp.client-invoices.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        return ClientInvoice::latest('id')->firstOrFail();
    }

    private function validated(): ClientInvoice
    {
        $invoice = $this->createDraft();
        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasNoErrors()->assertSessionMissing('error');

        return $invoice->fresh();
    }

    public function test_new_invoice_is_an_unpaid_draft_whatever_the_form_sends(): void
    {
        $invoice = $this->createDraft(['status' => 'paid', 'amount_paid' => 240]);

        $this->assertTrue($invoice->isDraft());
        $this->assertStringStartsWith('BROUILLON-', $invoice->invoice_number);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame(240.0, (float) $invoice->balance_remaining);
    }

    public function test_validation_assigns_a_gap_free_number(): void
    {
        $year = now()->format('Y');

        $first = $this->validated();
        $deletedDraft = $this->createDraft();
        $this->delete(route('erp.client-invoices.destroy', $deletedDraft));
        $second = $this->validated();

        $this->assertSame("FAC-{$year}-0001", $first->invoice_number);
        $this->assertSame("FAC-{$year}-0002", $second->invoice_number);
        $this->assertTrue($first->isValidated());
        $this->assertNotNull($first->validated_at);
    }

    public function test_an_empty_invoice_cannot_be_validated(): void
    {
        $invoice = $this->createDraft(['item_price_ht' => [0]]);

        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasErrors('state');

        $this->assertTrue($invoice->fresh()->isDraft());
    }

    public function test_a_validated_invoice_is_locked(): void
    {
        $invoice = $this->validated();

        $this->get(route('erp.client-invoices.edit', $invoice))->assertRedirect(route('erp.client-invoices.show', $invoice));
        $this->put(route('erp.client-invoices.update', $invoice), $this->payload(['item_price_ht' => [999]]))
            ->assertSessionHas('error');
        $this->delete(route('erp.client-invoices.destroy', $invoice))->assertSessionHas('error');

        $fresh = $invoice->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(240.0, (float) $fresh->total_ttc);
    }

    public function test_payments_drive_the_computed_status(): void
    {
        $invoice = $this->validated();

        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 100])->assertSessionHasNoErrors();
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(140.0, (float) $invoice->fresh()->balance_remaining);

        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 500])->assertSessionHasErrors('amount');
        $this->assertSame(100.0, (float) $invoice->fresh()->amount_paid);

        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 140])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_remaining);
    }

    public function test_a_draft_cannot_receive_a_payment(): void
    {
        $invoice = $this->createDraft();

        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 10])->assertSessionHasErrors('amount');
        $this->assertSame(0.0, (float) $invoice->fresh()->amount_paid);
    }

    public function test_credit_note_cancels_the_invoice_and_carries_the_refund(): void
    {
        $invoice = $this->validated();
        $this->post(route('erp.client-invoices.payments', $invoice), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 50]);

        $this->post(route('erp.client-invoices.credit-note', $invoice))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $invoice->refresh();
        $creditNote = $invoice->creditNote;
        $this->assertTrue($invoice->isCancelled());
        $this->assertSame(0.0, (float) $invoice->balance_remaining);

        $this->assertNotNull($creditNote);
        $this->assertTrue($creditNote->isCreditNote());
        $this->assertSame('AV-' . now()->format('Y') . '-0001', $creditNote->invoice_number);
        $this->assertSame(240.0, (float) $creditNote->total_ttc);
        $this->assertSame(50.0, (float) $creditNote->balance_remaining, 'what the client already paid must be refunded');
        $this->assertSame(1, $creditNote->items()->count());

        // Only once.
        $this->post(route('erp.client-invoices.credit-note', $invoice))->assertSessionHasErrors('state');
        $this->assertSame(1, ClientInvoice::where('document_type', 'credit_note')->count());
    }

    public function test_statement_ledger_ignores_drafts_and_nets_credit_notes(): void
    {
        $cancelled = $this->validated();                           // 240, then cancelled with 50 paid -> refund 50
        $this->post(route('erp.client-invoices.payments', $cancelled), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 50]);
        $this->post(route('erp.client-invoices.credit-note', $cancelled));

        $open = $this->validated();                                // 240, 40 paid -> 200 due
        $this->post(route('erp.client-invoices.payments', $open), ['payment_date' => now()->toDateString(), 'method' => 'transfer', 'amount' => 40]);

        $this->createDraft();                                      // draft: ignored

        $this->post(route('erp.client-statements.store'), [
            'client_id' => $this->client->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        // Ledger: +240 invoice, -50 payment, -240 credit note, +240 invoice, -40 payment. The draft is ignored.
        $statement = ClientStatement::firstOrFail();
        $this->assertSame(2, (int) $statement->total_invoices);
        $this->assertSame(90.0, (float) $statement->total_payments);
        $this->assertSame(150.0, (float) $statement->balance, '200 still due minus 50 to refund');
    }

    public function test_supplier_invoices_follow_the_same_lifecycle(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fournisseur SA', 'status' => 'active']);

        $this->post(route('erp.supplier-invoices.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'status' => 'paid',
            'item_product_id' => [$this->product->id],
            'item_mode' => ['product'],
            'item_quantity' => [1],
            'item_price_ht' => [50],
            'item_tva_rate' => [20],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $invoice = SupplierInvoice::firstOrFail();
        $this->assertTrue($invoice->isDraft());
        $this->assertSame('unpaid', $invoice->status);

        $this->post(route('erp.supplier-invoices.validate', $invoice))->assertSessionHasNoErrors();
        $this->assertSame('FSA-' . now()->format('Y') . '-0001', $invoice->fresh()->invoice_number);

        $this->delete(route('erp.supplier-invoices.destroy', $invoice))->assertSessionHas('error');

        $this->post(route('erp.supplier-invoices.credit-note', $invoice))->assertSessionHasNoErrors();
        $this->assertTrue($invoice->fresh()->isCancelled());
        $this->assertSame('AVF-' . now()->format('Y') . '-0001', $invoice->fresh()->creditNote->invoice_number);
    }

    public function test_invoice_pages_render_in_every_state(): void
    {
        $draft = $this->createDraft();
        $invoice = $this->validated();
        $this->post(route('erp.client-invoices.credit-note', $invoice));
        $creditNote = $invoice->fresh()->creditNote;

        $this->get(route('erp.client-invoices.index'))->assertOk()->assertSee('Brouillon #' . $draft->id);
        $this->get(route('erp.client-invoices.show', $draft))->assertOk()->assertSee('Valider la facture');
        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()->assertSee('Annulee par avoir');
        $this->get(route('erp.client-invoices.show', $creditNote))->assertOk()->assertSee('Avoir ' . $creditNote->invoice_number);
        $this->get(route('erp.client-invoices.pdf', $draft))->assertOk();
        $this->get(route('erp.client-invoices.pdf', $creditNote))->assertOk();
        $this->get(route('erp.client-invoices.create'))->assertOk()->assertSee('Attribue a la validation');
        $this->get(route('erp.client-invoices.edit', $draft))->assertOk();
        $this->get(route('erp.supplier-invoices.index'))->assertOk();
        $this->get(route('erp.supplier-invoices.create'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }
}
