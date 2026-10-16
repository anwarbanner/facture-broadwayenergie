<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $this->product = Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0]);
    }

    private function draft(): ClientInvoice
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
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        return ClientInvoice::latest('id')->firstOrFail();
    }

    public function test_technicien_prepares_drafts_but_cannot_touch_finance(): void
    {
        $this->actingAs($this->userWithRole('Technicien'));

        $invoice = $this->draft();

        $this->get(route('erp.client-invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Valider la facture')
            ->assertSee('Validation par un administrateur')
            ->assertDontSee('Encaissements');

        $this->post(route('erp.client-invoices.validate', $invoice))->assertForbidden();
        $this->post(route('erp.client-invoices.payments', $invoice), ['amount' => 10, 'payment_date' => now()->toDateString(), 'method' => 'cash'])->assertForbidden();
        $this->post(route('erp.client-invoices.credit-note', $invoice))->assertForbidden();
        $this->get(route('erp.payments.index'))->assertForbidden();
        $this->get(route('erp.client-statements.index'))->assertForbidden();
        $this->get(route('erp.supplier-statements.index'))->assertForbidden();

        $this->assertTrue($invoice->fresh()->isDraft());
    }

    public function test_admin_sees_and_uses_finance(): void
    {
        $this->actingAsAdmin();

        $invoice = $this->draft();
        $this->get(route('erp.client-invoices.show', $invoice))->assertOk()->assertSee('Valider la facture')->assertSee('Encaissements');
        $this->post(route('erp.client-invoices.validate', $invoice))->assertSessionHasNoErrors();

        $this->assertTrue($invoice->fresh()->isValidated());
    }

    public function test_legacy_invoices_module_is_read_only(): void
    {
        $this->actingAsAdmin();

        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('invoices.create'))->assertRedirect(route('erp.client-invoices.index'));
        $this->post(route('invoices.store'), ['invoice_number' => 'OLD-1'])->assertRedirect(route('erp.client-invoices.index'))->assertSessionHas('error');
        $this->put(route('products.update', $this->product), ['product_name' => 'Hack', 'stock_quantity' => 999])
            ->assertRedirect(route('erp.client-invoices.index'));

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame('Pompe', $this->product->fresh()->product_name);
    }
}
