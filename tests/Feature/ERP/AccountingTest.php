<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-28 10:00:00');

        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Hotel Atlas', 'status' => 'active']);
        $this->supplier = Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Grossiste Nord', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function clientInvoice(string $number, string $date, float $ht, array $extra = []): ClientInvoice
    {
        return ClientInvoice::forceCreate(array_merge([
            'invoice_number' => $number, 'client_id' => $this->client->id, 'invoice_date' => $date,
            'due_date' => Carbon::parse($date)->addDays(30)->toDateString(),
            'document_type' => 'invoice', 'state' => 'validated',
            'amount_ht' => $ht, 'tva_amount' => $ht * 0.2, 'total_ttc' => $ht * 1.2, 'tva_rate' => 20,
            'amount_paid' => 0, 'balance_remaining' => $ht * 1.2, 'status' => 'unpaid',
        ], $extra));
    }

    private function supplierInvoice(string $number, string $date, float $ht): SupplierInvoice
    {
        return SupplierInvoice::forceCreate([
            'invoice_number' => $number, 'supplier_id' => $this->supplier->id, 'invoice_date' => $date,
            'due_date' => Carbon::parse($date)->addDays(30)->toDateString(),
            'document_type' => 'invoice', 'state' => 'validated',
            'amount_ht' => $ht, 'tva_amount' => $ht * 0.2, 'total_ttc' => $ht * 1.2, 'tva_rate' => 20,
            'amount_paid' => 0, 'balance_remaining' => $ht * 1.2, 'status' => 'unpaid',
        ]);
    }

    public function test_journal_counts_issued_documents_with_credit_notes_negative_and_skips_drafts(): void
    {
        $this->clientInvoice('FAC-1', '2026-09-02', 1000);
        $this->clientInvoice('FAC-2', '2026-09-10', 500, ['state' => 'cancelled', 'balance_remaining' => 0]);
        $this->clientInvoice('AV-1', '2026-09-11', 500, ['document_type' => 'credit_note', 'balance_remaining' => 0]);
        $this->clientInvoice('BROUILLON-X', '2026-09-12', 9999, ['state' => 'draft']);
        $this->clientInvoice('FAC-OLD', '2026-08-30', 7000);

        $journal = app(AccountingService::class)->journal('sales', Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(['FAC-1', 'FAC-2', 'AV-1'], $journal->pluck('number')->all());
        $this->assertEquals(1000, $journal->sum('ht'));   // 1000 + 500 (cancelled) - 500 (credit note)
        $this->assertEquals(200, $journal->sum('tva'));
    }

    public function test_vat_due_is_collected_minus_deductible_by_month(): void
    {
        $this->clientInvoice('FAC-1', '2026-08-15', 1000);   // 200 collected in August
        $this->clientInvoice('FAC-2', '2026-09-15', 2000);   // 400 collected in September
        $this->supplierInvoice('FSA-1', '2026-09-20', 500);  // 100 deductible in September

        $vat = app(AccountingService::class)->vat(Carbon::parse('2026-08-01'), Carbon::parse('2026-09-30'), 'month')->keyBy('key');

        $this->assertEquals(200, $vat['2026-08']['due']);
        $this->assertEquals(300, $vat['2026-09']['due']);
        $this->assertSame('Septembre 2026', $vat['2026-09']['label']);
    }

    public function test_cash_uses_active_payments_only(): void
    {
        Payment::forceCreate(['contact_id' => $this->client->id, 'direction' => 'in', 'payment_date' => '2026-09-05', 'amount' => 300, 'method' => 'cash']);
        Payment::forceCreate(['contact_id' => $this->supplier->id, 'direction' => 'out', 'payment_date' => '2026-09-06', 'amount' => 120, 'method' => 'transfer']);
        Payment::forceCreate(['contact_id' => $this->client->id, 'direction' => 'in', 'payment_date' => '2026-09-07', 'amount' => 999, 'method' => 'cash', 'voided_at' => now()]);

        $cash = app(AccountingService::class)->cash(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), 'month')->sole();

        $this->assertEquals(300, $cash['in']);
        $this->assertEquals(120, $cash['out']);
        $this->assertEquals(180, $cash['net']);
    }

    public function test_aging_sorts_open_balances_by_lateness(): void
    {
        $this->clientInvoice('FAC-1', '2026-09-20', 100);                                    // due 20/10: not due
        $this->clientInvoice('FAC-2', '2026-08-10', 200);                                    // due 09/09: 19 days late
        $this->clientInvoice('FAC-3', '2026-05-01', 300);                                    // due 31/05: +90 days
        $this->clientInvoice('FAC-4', '2026-08-10', 400, ['balance_remaining' => 0]);        // paid: ignored

        $aging = app(AccountingService::class)->aging('sales');

        $this->assertEquals(120, $aging['totals']['not_due']);
        $this->assertEquals(240, $aging['totals']['1_30']);
        $this->assertEquals(360, $aging['totals']['over_90']);
        $this->assertEquals(720, $aging['totals']['total']);
    }

    public function test_every_tab_renders_and_exports_for_the_admin(): void
    {
        $this->actingAsAdmin();
        $this->clientInvoice('FAC-1', '2026-09-02', 1000);
        $this->supplierInvoice('FSA-1', '2026-09-03', 400);

        foreach (['summary', 'sales', 'purchases', 'vat', 'cash', 'receivables', 'payables', 'ranking'] as $tab) {
            $this->get(route('erp.accounting.index', ['tab' => $tab, 'preset' => 'month']))->assertOk();
        }

        $this->get(route('erp.accounting.index', ['tab' => 'summary']))->assertSee('Marge brute HT')->assertSee('600,00');

        $csv = $this->get(route('erp.accounting.index', ['tab' => 'sales', 'preset' => 'month', 'export' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('FAC-1;Facture;"Hotel Atlas";1000,00;200,00;1200,00', $csv->streamedContent());
    }

    public function test_accounting_entries_are_balanced_per_document(): void
    {
        $this->clientInvoice('FAC-1', '2026-09-02', 1000);                                        // 1200 TTC
        $this->clientInvoice('AV-1', '2026-09-05', 100, ['document_type' => 'credit_note', 'balance_remaining' => 0]);
        $this->supplierInvoice('FSA-1', '2026-09-03', 500);                                        // 600 TTC
        $this->clientInvoice('BROUILLON-X', '2026-09-04', 999, ['state' => 'draft']);             // never exported
        Payment::forceCreate(['contact_id' => $this->client->id, 'direction' => 'in', 'payment_date' => '2026-09-10', 'amount' => 300, 'method' => 'cash']);
        Payment::forceCreate(['contact_id' => $this->supplier->id, 'direction' => 'out', 'payment_date' => '2026-09-11', 'amount' => 600, 'method' => 'transfer', 'reference' => 'VIR-77']);
        Payment::forceCreate(['contact_id' => $this->client->id, 'direction' => 'in', 'payment_date' => '2026-09-12', 'amount' => 50, 'method' => 'cash', 'voided_at' => now()]);

        $entries = app(AccountingService::class)->entries(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        foreach ($entries->groupBy('piece') as $piece => $lines) {
            $this->assertEquals(round($lines->sum('debit'), 2), round($lines->sum('credit'), 2), "piece {$piece} is balanced");
        }
        $this->assertFalse($entries->contains('piece', 'BROUILLON-X'));

        $invoice = $entries->where('piece', 'FAC-1')->keyBy('account');
        $this->assertEquals(1200, $invoice['3421']['debit']);
        $this->assertEquals(1000, $invoice['7111']['credit']);
        $this->assertEquals(200, $invoice['4455']['credit']);

        $credit = $entries->where('piece', 'AV-1')->keyBy('account');
        $this->assertEquals(120, $credit['3421']['credit'], 'a credit note is the reverse of an invoice');
        $this->assertEquals(100, $credit['7111']['debit']);

        $purchase = $entries->where('piece', 'FSA-1')->keyBy('account');
        $this->assertEquals(500, $purchase['6111']['debit']);
        $this->assertEquals(100, $purchase['3455']['debit']);
        $this->assertEquals(600, $purchase['4411']['credit']);

        $cash = $entries->where('journal', 'CA');
        $this->assertCount(2, $cash, 'the voided cash payment is not exported');
        $this->assertEquals(300, $cash->firstWhere('account', '5161')['debit']);
        $bank = $entries->where('piece', 'VIR-77')->keyBy('account');
        $this->assertEquals(600, $bank['4411']['debit']);
        $this->assertEquals(600, $bank['5141']['credit']);

        $this->actingAsAdmin();
        $this->get(route('erp.accounting.index', ['tab' => 'entries', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertSee('Ecritures comptables')->assertSee('debit = credit');
        $csv = $this->get(route('erp.accounting.index', ['tab' => 'entries', 'from' => '2026-09-01', 'to' => '2026-09-30', 'export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Journal;Date;Piece;Compte;Libelle;Tiers;Debit;Credit', $csv);
        $this->assertStringContainsString('VT;02/09/2026;FAC-1;3421;', $csv);
    }

    public function test_accounting_is_reserved_to_finance(): void
    {
        $this->actingAs($this->userWithRole('Technicien'));

        $this->get(route('erp.accounting.index'))->assertForbidden();
        $this->get(route('erp.sales-quotes.index'))->assertOk()->assertDontSee('Journal des ventes');
    }
}
