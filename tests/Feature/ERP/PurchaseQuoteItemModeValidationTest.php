<?php

namespace Tests\Feature\ERP;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseQuoteItemModeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_product_line_when_mode_is_manual(): void
    {
        $user = $this->createActiveUser();
        $supplier = $this->createSupplier();
        $productId = $this->createProduct();

        $response = $this->actingAs($user)->post(route('erp.purchase-quotes.store'), [
            'supplier_id' => $supplier->id,
            'date' => now()->toDateString(),
            'validity_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_mode' => ['manual'],
            'item_product_id' => [$productId],
            'item_name' => [''],
            'item_quantity' => [1],
            'item_price_ht' => [100],
            'item_tva_rate' => [20],
        ]);

        // The line type decides: a line without the article of its type is refused.
        $response->assertSessionHasErrors(['item_name.0']);
        $this->assertDatabaseCount('purchase_quotes', 0);
    }

    public function test_rejects_manual_line_when_mode_is_product(): void
    {
        $user = $this->createActiveUser();
        $supplier = $this->createSupplier();

        $response = $this->actingAs($user)->post(route('erp.purchase-quotes.store'), [
            'supplier_id' => $supplier->id,
            'date' => now()->toDateString(),
            'validity_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_mode' => ['product'],
            'item_product_id' => [''],
            'item_name' => ['Article libre'],
            'item_quantity' => [1],
            'item_price_ht' => [100],
            'item_tva_rate' => [20],
        ]);

        // The line type decides: a line without the article of its type is refused.
        $response->assertSessionHasErrors(['item_name.0']);
        $this->assertDatabaseCount('purchase_quotes', 0);
    }

    public function test_accepts_line_when_mode_matches_product_source(): void
    {
        $user = $this->createActiveUser();
        $supplier = $this->createSupplier();
        $productId = $this->createProduct();

        $response = $this->actingAs($user)->post(route('erp.purchase-quotes.store'), [
            'supplier_id' => $supplier->id,
            'date' => now()->toDateString(),
            'validity_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'currency' => 'MAD',
            'exchange_rate' => 1,
            'item_mode' => ['product'],
            'item_product_id' => [$productId],
            'item_name' => [''],
            'item_quantity' => [2],
            'item_price_ht' => [50],
            'item_tva_rate' => [20],
        ]);

        $response->assertSessionHasNoErrors();
        // Back to the list, the new quote highlighted.
        $response->assertRedirect(route('erp.purchase-quotes.index'));
        $response->assertSessionHas('highlight', route('erp.purchase-quotes.show', \App\Models\PurchaseQuote::sole()));

        $this->assertDatabaseCount('purchase_quotes', 1);
        $this->assertDatabaseHas('purchase_quote_items', [
            'product_id' => $productId,
            'item_name' => null,
            'quantity' => 2,
            'price_ht' => 50,
            'tva_rate' => 20,
        ]);
    }

    private function createActiveUser(): User
    {
        return User::create([
            'name' => 'ERP Tester',
            'email' => 'erp.tester@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role_name' => json_encode(['admin']),
            'status' => 'active',
        ]);
    }

    private function createSupplier(): Contact
    {
        return Contact::create([
            'type' => 'supplier',
            'company_name' => 'Fournisseur Test',
            'status' => 'active',
        ]);
    }

    private function createProduct(): int
    {
        $sectionId = DB::table('sections')->insertGetId([
            'section_name' => 'Section Test',
            'description' => 'Section de test',
            'created_by' => 'tests',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'product_name' => 'Produit Test',
            'description' => 'Produit pour test',
            'section_id' => $sectionId,
            'reference' => 'TEST-' . uniqid(),
            'designation' => 'Designation Test',
            'type' => 'product',
            'sale_price_ht' => 100,
            'cost_price_ht' => 70,
            'unit_type' => 'piece',
            'stock_quantity' => 10,
            'min_stock_alert_level' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
