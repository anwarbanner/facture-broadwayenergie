<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrenchInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_are_in_french_with_readable_field_names(): void
    {
        $this->actingAsAdmin();

        $this->post(route('erp.client-invoices.store'), [])
            ->assertSessionHasErrors([
                'client_id' => 'Le champ client est obligatoire.',
                'invoice_date' => 'Le champ date de facture est obligatoire.',
            ]);

        $this->post(route('erp.client-invoices.store'), ['client_id' => 999999, 'tva_rate' => 'abc'])
            ->assertSessionHasErrors([
                'client_id' => 'Le contact selectionne est introuvable.',
                'tva_rate' => 'Le champ taux de TVA doit etre un nombre.',
            ]);
    }

    public function test_confirmation_messages_are_in_french(): void
    {
        $this->actingAsAdmin();

        $this->post(route('erp.contacts.store'), [
            'type' => 'client',
            'company_name' => 'Acme',
            'status' => 'active',
        ])->assertSessionHas('success', 'Contact cree.');
    }

    public function test_stored_codes_are_displayed_in_french(): void
    {
        $this->assertSame('Brouillon', Format::label('draft'));
        $this->assertSame('Envoye', Format::label('sent'));
        $this->assertSame('Accepte', Format::label('accepted'));
        $this->assertSame('Fournisseur', Format::label('supplier'));
        $this->assertSame('En attente', Format::label('pending'));
        $this->assertSame('Payee', Format::legacyStatus(1));

        $this->actingAsAdmin();
        Contact::forceCreate(['type' => 'supplier', 'company_name' => 'Fournisseur SA', 'status' => 'active']);

        $this->get(route('erp.sales-quotes.create'))
            ->assertOk()
            ->assertSee('>Brouillon<', false)
            ->assertSee('>Envoye<', false)
            ->assertDontSee('>Draft<', false);

        $this->get(route('erp.contacts.index'))
            ->assertOk()
            ->assertSee('Fournisseur')
            ->assertDontSee('>Supplier<', false);
    }
}
