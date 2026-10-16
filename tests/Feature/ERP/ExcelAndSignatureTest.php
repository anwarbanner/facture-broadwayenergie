<?php

namespace Tests\Feature\ERP;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Products;
use App\Models\SalesQuote;
use App\Models\Service;
use App\Models\StockMovement;
use App\Services\Pdf\LetterheadDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Excel export of documents, Excel imports (master data and document lines), uploaded signature. */
class ExcelAndSignatureTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $pump;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAsAdmin();
        $this->client = Contact::forceCreate(['type' => 'client', 'company_name' => 'Hotel Atlas', 'status' => 'active']);
        $this->pump = Products::forceCreate(['reference' => 'POMPE-100', 'product_name' => 'Pompe', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 0, 'sale_price_ht' => 1000, 'cost_price_ht' => 700]);
    }

    private function quote(): SalesQuote
    {
        $this->post(route('erp.sales-quotes.store'), [
            'client_id' => $this->client->id, 'date' => now()->toDateString(), 'status' => 'draft', 'tva_rate' => 20,
            'item_mode' => ['product', 'manual'], 'item_product_id' => [$this->pump->id, ''], 'item_service_id' => ['', ''],
            'item_name' => ['', 'Installation'], 'item_quantity' => [2, 1], 'item_price_ht' => [1000, 300],
        ])->assertSessionHasNoErrors();

        return SalesQuote::sole();
    }

    /** @param array<int, array> $rows first row = titles */
    private function xlsx(array $rows, string $name = 'import.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    // ---- Signature --------------------------------------------------------------------------------------

    public function test_the_admin_uploads_a_signature_printed_on_documents_but_not_on_drafts(): void
    {
        $this->put(route('settings.signature.update'), ['signature' => UploadedFile::fake()->image('signature.png', 400, 150), 'signatory' => 'Le gerant, K. Alami'])
            ->assertRedirect(route('settings.signature.edit'))->assertSessionHasNoErrors();
        $this->get(route('settings.signature.edit'))->assertOk()->assertSee('Le gerant, K. Alami')->assertSee('data:image/png;base64', false);

        $letterhead = app(LetterheadDocument::class);
        $quoteHtml = $letterhead->html($letterhead->salesQuote($this->quote()));
        $this->assertStringContainsString('signature.png', $quoteHtml, 'the PDF reads the image from its file, like the logo');
        $this->assertStringContainsString('Le gerant, K. Alami', $quoteHtml);

        $draft = ClientInvoice::forceCreate(['invoice_number' => 'BROUILLON-X', 'client_id' => $this->client->id, 'invoice_date' => now()->toDateString(),
            'document_type' => 'invoice', 'state' => 'draft', 'amount_ht' => 10, 'tva_amount' => 2, 'total_ttc' => 12]);
        $this->assertStringNotContainsString('signature.png', $letterhead->html($letterhead->clientInvoice($draft)), 'a draft is never signed');

        $this->delete(route('settings.signature.destroy'))->assertRedirect();
        $this->assertStringNotContainsString('signature.png', $letterhead->html($letterhead->salesQuote(SalesQuote::sole())));
    }

    public function test_only_the_admin_manages_the_signature_and_imports(): void
    {
        $this->actingAs($this->userWithRole('Technicien'));
        $this->get(route('settings.signature.edit'))->assertForbidden();
        $this->get(route('erp.imports.index'))->assertForbidden();
    }

    // ---- Excel export -----------------------------------------------------------------------------------

    public function test_a_quote_downloads_as_an_excel_file_with_its_lines_and_totals(): void
    {
        $quote = $this->quote();
        $this->get(route('erp.sales-quotes.show', $quote))->assertSee(route('erp.sales-quotes.excel', ['id' => $quote->id]), false);

        $response = $this->get(route('erp.sales-quotes.excel', ['id' => $quote->id]));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'xls') . '.xlsx';
        file_put_contents($path, $response->streamedContent());
        $cells = collect(IOFactory::load($path)->getActiveSheet()->toArray(null, true, false))->flatten()->filter()->map(fn ($v) => (string) $v);

        $this->assertTrue($cells->contains('DEVIS'));
        $this->assertTrue($cells->contains('N° ' . $quote->quote_number));
        $this->assertTrue($cells->contains('Hotel Atlas'));
        $this->assertTrue($cells->contains('Installation'));
        $this->assertTrue($cells->contains('2760'), 'Total TTC: (2 x 1000 + 300) x 1.2');
    }

    // ---- Mass import ------------------------------------------------------------------------------------

    public function test_products_import_shows_a_preview_then_creates_and_updates(): void
    {
        $this->get(route('erp.imports.template', 'products'))->assertOk();

        $file = $this->xlsx([
            ['Reference *', 'Nom *', 'Categorie', 'Prix de vente HT *', "Prix d'achat HT", 'Stock initial', 'Statut'],
            ['POMPE-100', 'Pompe 100 L/min', 'Pompes', '1 250,50', '900', '99', 'actif'],   // existing: updated, stock untouched
            ['VANNE-25', 'Vanne 25 mm', 'Robinetterie', '85,5', '52', '40', ''],              // new, initial stock 40
            ['', '', '', 'abc', '', '', 'peut-etre'],                                          // errors
        ]);

        $preview = $this->post(route('erp.imports.preview', 'products'), ['file' => $file]);
        $preview->assertOk()->assertSee('A creer')->assertSee('Nom obligatoire')->assertSee("n'est pas un nombre");
        $this->assertSame(1, Products::count(), 'nothing is written by the preview');

        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $m);
        $this->post(route('erp.imports.confirm', 'products'), ['token' => $m[1]])
            ->assertRedirect(route('erp.products.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 produits cree(s), 1 mis a jour') && str_contains($message, '1 ligne(s) ignoree(s)'));

        $this->assertEquals(1250.5, (float) $this->pump->fresh()->sale_price_ht);
        $this->assertEquals(0, (float) $this->pump->fresh()->stock_quantity);
        $valve = Products::where('reference', 'VANNE-25')->sole();
        $this->assertEquals(40, (float) $valve->stock_quantity);
        $this->assertSame('Robinetterie', $valve->category->name ?? null);
        $this->assertSame(1, StockMovement::where('product_id', $valve->id)->count(), 'initial stock is a stock movement');

        // The preview token is single use.
        $this->post(route('erp.imports.confirm', 'products'), ['token' => $m[1]])->assertRedirect(route('erp.imports.index'));
    }

    public function test_contacts_import_reads_a_french_csv_and_does_not_duplicate(): void
    {
        $csv = "Type;Societe;Ville;E-mail\nclient;hotel atlas;Casablanca;contact@atlas.ma\nfournisseur;Grossiste Nord;Tanger;\nprospect;X;;mauvais-mail\n";
        $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
        file_put_contents($path, $csv);

        $preview = $this->post(route('erp.imports.preview', 'contacts'), ['file' => new UploadedFile($path, 'contacts.csv', 'text/csv', null, true)]);
        $preview->assertOk()->assertSee('Type : client ou fournisseur')->assertSee('E-mail invalide');
        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $m);
        $this->post(route('erp.imports.confirm', 'contacts'), ['token' => $m[1]])->assertRedirect(route('erp.contacts.index'));

        $this->assertSame(1, Contact::where('type', 'client')->count(), 'Hotel Atlas updated, not duplicated');
        $this->assertSame('Casablanca', $this->client->fresh()->city);
        $this->assertSame(1, Contact::where('type', 'supplier')->where('company_name', 'Grossiste Nord')->count());
    }

    public function test_services_and_categories_import(): void
    {
        foreach ([
            'services' => [['Nom *', 'Prix HT *', 'Duree (heures)'], ['Installation', '350', '2']],
            'categories' => [['Nom *', 'Categorie parente'], ['Pompes', ''], ['Pompes immergees', 'Pompes']],
        ] as $type => $rows) {
            $preview = $this->post(route('erp.imports.preview', $type), ['file' => $this->xlsx($rows)]);
            preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $m);
            $this->post(route('erp.imports.confirm', $type), ['token' => $m[1]])->assertSessionHas('success');
        }

        $this->assertEquals(350, (float) Service::where('service_name', 'Installation')->value('service_price'));
        $child = \App\Models\Category::where('name', 'Pompes immergees')->sole();
        $this->assertSame('Pompes', \App\Models\Category::find($child->parent_category_id)?->name);
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $this->from(route('erp.imports.index'))
            ->post(route('erp.imports.preview', 'products'), ['file' => $this->xlsx([['Colonne inconnue'], ['x']])])
            ->assertRedirect(route('erp.imports.index'))
            ->assertSessionHasErrors('file');
    }

    // ---- Document lines from Excel ----------------------------------------------------------------------

    public function test_document_lines_are_read_from_excel_with_catalogue_matching(): void
    {
        Service::forceCreate(['service_name' => 'Maintenance', 'service_price' => 400, 'hours_count' => 1, 'status' => 'active']);
        $file = $this->xlsx([
            ['Article', 'Designation', 'Quantite *', 'Prix unitaire HT'],
            ['pompe-100', '', '3', ''],            // product by reference, catalogue price
            ['Maintenance', '', '1', '380'],       // service by name, price from the file
            ['', 'Deplacement', '1', '150'],       // free line
            ['INCONNU', '', '2', '10'],            // not in catalogue -> manual + warning
            ['', '', '0', ''],                     // ignored
        ]);

        $json = $this->postJson(route('erp.imports.lines'), ['file' => $file, 'side' => 'purchase'])->assertOk()->json();

        $this->assertCount(4, $json['lines']);
        $this->assertSame(['product', $this->pump->id, 3.0, 700.0], [$json['lines'][0]['mode'], $json['lines'][0]['product_id'], (float) $json['lines'][0]['quantity'], (float) $json['lines'][0]['price']]);
        $this->assertSame(['service', 380.0], [$json['lines'][1]['mode'], (float) $json['lines'][1]['price']]);
        $this->assertSame(['manual', 'Deplacement'], [$json['lines'][2]['mode'], $json['lines'][2]['name']]);
        $this->assertSame('manual', $json['lines'][3]['mode']);
        $this->assertCount(2, $json['warnings']);

        $this->get(route('erp.sales-quotes.create'))->assertSee('Importer des lignes (Excel)');
    }
}
