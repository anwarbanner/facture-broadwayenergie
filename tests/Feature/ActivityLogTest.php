<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Q6: the activity log keeps writes and downloads, not every page view. */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_views_are_not_logged_but_writes_and_downloads_are(): void
    {
        $this->actingAsAdmin();
        ActivityLog::query()->delete();

        $this->get(route('erp.contacts.index'))->assertOk();
        $this->get(route('erp.sales-quotes.create'))->assertOk();
        $this->assertSame(0, ActivityLog::count());

        $this->post(route('erp.contacts.store'), ['type' => 'client', 'company_name' => 'Journal SA', 'status' => 'active']);
        $this->assertSame(1, ActivityLog::where('method', 'POST')->count());

        $this->get(route('erp.products.export'));
        $this->assertSame(1, ActivityLog::where('route_name', 'erp.products.export')->count());
    }

    public function test_entries_keep_the_record_the_message_and_the_outcome(): void
    {
        $this->actingAsAdmin();
        $client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Hotel Atlas', 'status' => 'active']);
        $product = \App\Models\Products::forceCreate(['product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 5]);
        ActivityLog::query()->delete();

        $this->post(route('erp.sales-quotes.store'), [
            'client_id' => $client->id, 'date' => now()->toDateString(), 'status' => 'rejected', 'tva_rate' => 20,
            'item_mode' => ['product'], 'item_product_id' => [$product->id], 'item_quantity' => [1], 'item_price_ht' => [100],
        ])->assertSessionHasNoErrors();
        $quote = \App\Models\SalesQuote::sole();

        $created = ActivityLog::where('route_name', 'erp.sales-quotes.store')->sole();
        $this->assertSame('create', $created->category);
        $this->assertSame('ok', $created->outcome);
        $this->assertSame($quote->quote_number, $created->meta['subject_label']);
        $this->assertSame('Devis client cree.', $created->meta['message']);

        // A refused conversion is recorded as refused, with the reason the user saw.
        $this->from(route('erp.sales-quotes.show', $quote))->post(route('erp.sales-quotes.convert-to-order', $quote));
        $refused = ActivityLog::where('route_name', 'erp.sales-quotes.convert-to-order')->sole();
        $this->assertSame('refused', $refused->outcome);
        $this->assertSame('Ce devis ne peut pas etre converti.', $refused->meta['message']);

        $page = $this->get(route('activity-logs.index'))->assertOk()
            ->assertSee('Creation : devis client')
            ->assertSee('Devis client ' . $quote->quote_number)
            ->assertSee('Transformation en commande')
            ->assertSee('Refuse');
        foreach (['POST', 'erp.sales-quotes.store', 'Methode'] as $technical) {
            $page->assertDontSeeText($technical);
        }

        $this->get(route('activity-logs.index', ['category' => 'refused']))->assertOk()
            ->assertSee('Ce devis ne peut pas etre converti.')->assertDontSee('Devis client cree.');
    }

    public function test_prune_removes_only_old_entries(): void
    {
        ActivityLog::forceCreate(['action' => 'read: old', 'method' => 'GET', 'url' => '/x', 'created_at' => now()->subDays(400)]);
        ActivityLog::forceCreate(['action' => 'create: old', 'method' => 'POST', 'url' => '/x', 'created_at' => now()->subDays(400)]);
        ActivityLog::forceCreate(['action' => 'create: new', 'method' => 'POST', 'url' => '/x', 'created_at' => now()->subDays(5)]);

        $this->artisan('activity-logs:prune', ['--reads-only' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(2, ActivityLog::count());

        $this->artisan('activity-logs:prune', ['--force' => true])->assertSuccessful();
        $this->assertSame(['create: new'], ActivityLog::pluck('action')->all());
    }
}
