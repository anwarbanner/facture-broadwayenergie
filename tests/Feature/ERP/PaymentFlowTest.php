<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\ClientStatement;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Products;
use App\Models\SupplierInvoice;
use App\Models\SupplierStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
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

    /** Validated client invoice: $qty x 100 HT + 20% TVA. */
    private function invoice(int $qty = 2, ?string $dueDate = null): ClientInvoice
    {
        $this->post(route('erp.client-invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => $dueDate ?? now()->addDays(30)->toDateString(),
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_product_id' => [$this->product->id],
            'item_quantity' => [$qty],
            'item_price_ht' => [100],
            'item_tva_rate' => [20],
        ])->assertSessionHasNoErrors();
        $invoice = ClientInvoice::latest('id')->firstOrFail();
        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function pay(ClientInvoice $invoice, float $amount, array $extra = [])
    {
        return $this->post(route('erp.client-invoices.payments', $invoice), array_merge([
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'method' => 'check',
            'reference' => 'CHQ-001',
        ], $extra));
    }

    public function test_a_payment_is_stored_with_its_details_and_can_be_voided(): void
    {
        $invoice = $this->invoice(); // 240
        $this->pay($invoice, 100)->assertSessionHasNoErrors()->assertSessionMissing('error');

        $payment = Payment::with('allocations')->firstOrFail();
        $this->assertSame('in', $payment->direction);
        $this->assertSame('check', $payment->method);
        $this->assertSame('CHQ-001', $payment->reference);
        $this->assertSame(100.0, (float) $payment->allocations->first()->amount);
        $this->assertSame('partial', $invoice->fresh()->status);

        $this->post(route('erp.payments.void', $payment), ['void_reason' => 'Cheque sans provision'])->assertSessionHasNoErrors();

        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertSame(0.0, (float) $invoice->fresh()->amount_paid);
        $this->assertSame(240.0, (float) $invoice->fresh()->balance_remaining);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame(1, Payment::count(), 'a voided payment stays in the history');

        $this->post(route('erp.payments.void', $payment), ['void_reason' => 'encore'])->assertSessionHasErrors('payment');
    }

    public function test_one_payment_can_settle_several_invoices(): void
    {
        $first = $this->invoice(1);  // 120
        $second = $this->invoice(2); // 240

        $this->post(route('erp.payments.store'), [
            'side' => 'client',
            'contact_id' => $this->client->id,
            'payment_date' => now()->toDateString(),
            'method' => 'transfer',
            'reference' => 'VIR-42',
            'amounts' => [$first->id => 120, $second->id => 40],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $payment = Payment::firstOrFail();
        $this->assertSame(160.0, (float) $payment->amount);
        $this->assertSame(2, $payment->allocations()->count());
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame(200.0, (float) $second->fresh()->balance_remaining);
    }

    public function test_grouped_payment_is_all_or_nothing(): void
    {
        $first = $this->invoice(1);  // 120
        $second = $this->invoice(2); // 240

        $this->post(route('erp.payments.store'), [
            'side' => 'client',
            'contact_id' => $this->client->id,
            'payment_date' => now()->toDateString(),
            'method' => 'transfer',
            'amounts' => [$first->id => 50, $second->id => 999],
        ])->assertSessionHasErrors('amounts.' . $second->id);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0.0, (float) $first->fresh()->amount_paid);
    }

    public function test_credit_note_refund_closes_the_account(): void
    {
        $invoice = $this->invoice(); // 240
        $this->pay($invoice, 50);
        $this->post(route('erp.client-invoices.credit-note', $invoice))->assertSessionHasNoErrors();
        $creditNote = $invoice->fresh()->creditNote;
        $this->assertSame(50.0, (float) $creditNote->balance_remaining);

        $this->post(route('erp.client-invoices.payments', $creditNote), [
            'amount' => 50,
            'payment_date' => now()->toDateString(),
            'method' => 'transfer',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $refund = Payment::latest('id')->firstOrFail();
        $this->assertSame('out', $refund->direction, 'refunding a client is money out');
        $this->assertSame(0.0, (float) $creditNote->fresh()->balance_remaining);
        $this->assertSame('Solde', $creditNote->fresh()->statusLabel());

        $this->post(route('erp.client-statements.store'), [
            'client_id' => $this->client->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) ClientStatement::firstOrFail()->balance);
    }

    public function test_voiding_a_payment_of_a_cancelled_invoice_reduces_the_refund(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, 50);
        $this->post(route('erp.client-invoices.credit-note', $invoice));

        $this->post(route('erp.payments.void', Payment::firstOrFail()), ['void_reason' => 'Erreur de saisie']);

        $this->assertSame(0.0, (float) $invoice->fresh()->creditNote->balance_remaining);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_remaining);
    }

    public function test_daily_job_marks_invoices_overdue(): void
    {
        $invoice = $this->invoice(2, now()->addDays(5)->toDateString());
        $this->assertSame('unpaid', $invoice->status);

        $this->travel(10)->days();
        $this->artisan('invoices:refresh-statuses')->assertSuccessful();

        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_statement_has_opening_balance_and_running_lines(): void
    {
        $old = $this->invoice(); // 240, dated today
        $this->pay($old, 40);

        $this->travel(40)->days();
        $recent = $this->invoice(1); // 120

        $this->post(route('erp.client-statements.store'), [
            'client_id' => $this->client->id,
            'period_start' => now()->subDays(5)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $statement = ClientStatement::firstOrFail();

        $this->get(route('erp.client-statements.show', $statement))
            ->assertOk()
            ->assertSee("Solde d'ouverture", false)
            ->assertSee('200,00')              // 240 - 40 before the period (French format)
            ->assertSee($recent->invoice_number)
            ->assertSee('320,00');             // closing
        $this->assertSame(320.0, (float) $statement->balance);
        $this->assertSame(1, (int) $statement->total_invoices);
    }

    public function test_supplier_payment_is_money_out_and_appears_in_the_supplier_ledger(): void
    {
        $supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fournisseur SA', 'status' => 'active']);
        $this->post(route('erp.supplier-invoices.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_product_id' => [$this->product->id],
            'item_mode' => ['product'],
            'item_quantity' => [1],
            'item_price_ht' => [50],
            'item_tva_rate' => [20],
        ])->assertSessionHasNoErrors();
        $invoice = SupplierInvoice::firstOrFail();
        $this->post(route('erp.supplier-invoices.validate', $invoice));

        $this->post(route('erp.supplier-invoices.payments', $invoice), [
            'amount' => 60, 'payment_date' => now()->toDateString(), 'method' => 'transfer',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('out', Payment::firstOrFail()->direction);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->post(route('erp.supplier-statements.store'), [
            'supplier_id' => $supplier->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors();
        $statement = SupplierStatement::firstOrFail();
        $this->assertSame(0.0, (float) $statement->balance);
        $this->get(route('erp.supplier-statements.show', $statement))->assertOk()->assertSee($invoice->fresh()->invoice_number);
    }

    public function test_payment_pages_render(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, 100);

        $this->get(route('erp.payments.index'))->assertOk()->assertSee('CHQ-001')->assertSee($invoice->invoice_number);
        $this->get(route('erp.payments.create', ['side' => 'client', 'contact_id' => $this->client->id]))
            ->assertOk()->assertSee($invoice->invoice_number);
        $this->get(route('erp.payments.create', ['side' => 'supplier']))->assertOk();
        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()->assertSee('CHQ-001')->assertSee('Enregistrer un paiement');
    }
}
