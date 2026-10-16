<?php

namespace Tests\Feature;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Products;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_company_is_hard_coded_and_initials_replace_a_missing_logo(): void
    {
        config(['branding.company_name' => 'Acme Industrie', 'branding.logo_path' => '']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Acme Industrie')
            ->assertSee('AI')
            ->assertDontSee('LWEM', false)
            ->assertDontSee('lwem', false);
    }

    public function test_a_configured_logo_is_used_everywhere(): void
    {
        config(['branding.company_name' => 'Acme', 'branding.logo_path' => 'assets/img/brand/erp-icon.svg', 'branding.favicon_path' => '']);
        $this->actingAsAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('assets/img/brand/erp-icon.svg', false)
            ->assertSee('Acme');
        $this->assertSame(Branding::logoUrl(), Branding::faviconUrl());
    }

    public function test_a_path_outside_public_is_never_used_as_logo(): void
    {
        config(['branding.logo_path' => '../.env']);

        $this->assertNull(Branding::logoFile());
    }

    public function test_pdf_uses_the_configured_identity(): void
    {
        config([
            'branding.company_name' => 'Acme Industrie',
            'branding.legal_ids' => 'SIRET 123 456 789 00012',
            'branding.ice' => '',
        ]);
        $this->actingAsAdmin();
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Client SA', 'status' => 'active']);
        $invoice = ClientInvoice::forceCreate([
            'invoice_number' => 'BROUILLON-X', 'client_id' => $client->id, 'invoice_date' => now()->toDateString(), 'total_ttc' => 0,
        ]);

        $html = view('erp.pdf.sales-invoice', ['invoice' => $invoice->load('items', 'client')])->render();

        $this->assertStringContainsString('Acme Industrie', $html);
        $this->assertStringContainsString('SIRET 123 456 789 00012', $html);
        $this->assertStringNotContainsString('ICE:', $html);
        $this->assertStringNotContainsStringIgnoringCase('lwem', $html);
    }

    public function test_default_currency_and_vat_come_from_configuration(): void
    {
        config(['branding.default_currency' => 'eur', 'branding.default_vat_rate' => 5.5]);
        $this->actingAsAdmin();
        Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0]);

        $this->get(route('erp.client-invoices.create'))
            ->assertOk()
            ->assertSee('value="EUR"', false)
            ->assertSee('value="5.5"', false);
    }
}
