<?php

namespace Tests\Feature;

use App\Models\ClientInvoice;
use App\Models\ClientInvoiceItem;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Products;
use App\Models\SalesOrder;
use App\Services\Pdf\LetterheadDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterheadPdfTest extends TestCase
{
    use RefreshDatabase;

    private Contact $client;
    private Products $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'branding.company_name' => 'Broadway Energie',
            'branding.legal_form' => 'SARL',
            'branding.logo_path' => 'assets/img/brand/logo-broadway.png',
            'branding.watermark_path' => 'assets/img/brand/logo-broadway-watermark.png',
            'branding.slogan' => "Froid, Climatisation, Traitement d'air,|Plomberie sanitaire, Electricité",
            'branding.address' => '26 Av Mers Sultan, Appt 3 Etg 1',
            'branding.city' => 'Casablanca',
            'branding.country' => '',
            'branding.phone' => '06 00 58 13 02 / 06 66 78 51 16',
            'branding.email' => 'Broadwayenergie@gmail.com',
            'branding.legal_ids' => 'RC : 577179 - TP : 34104832 - IF : 53701023 - CNSS : 5014370 ICE : 003261782000029',
            'branding.ice' => '', 'branding.rc' => '', 'branding.if' => '', 'branding.patente' => '',
        ]);

        $this->actingAsAdmin();
        $this->client = Contact::forceCreate([
            'type' => 'client', 'company_name' => 'Atlas Distribution', 'status' => 'active',
            'address' => '12 Bd Zerktouni', 'city' => 'Casablanca', 'tax_id' => '001234567000089',
        ]);
        $this->product = Products::forceCreate(['product_name' => 'Pompe centrifuge 2 kW', 'type' => 'product', 'status' => 'active', 'stock_quantity' => 50]);
    }

    private function invoice(int $lines = 1): ClientInvoice
    {
        $invoice = ClientInvoice::forceCreate([
            'invoice_number' => 'FAC-2026-0042', 'document_type' => 'invoice', 'state' => 'validated',
            'client_id' => $this->client->id, 'invoice_date' => '2026-09-14', 'due_date' => '2026-10-14',
            'amount_ht' => 15400 * $lines, 'tva_amount' => 3080 * $lines, 'total_ttc' => 18480 * $lines,
            'amount_paid' => 0, 'balance_remaining' => 18480 * $lines, 'status' => 'unpaid', 'currency' => 'MAD', 'exchange_rate' => 1,
        ]);
        for ($i = 0; $i < $lines; $i++) {
            ClientInvoiceItem::forceCreate([
                'invoice_id' => $invoice->id, 'product_id' => $this->product->id,
                'quantity' => 2, 'price_ht' => 7700, 'tva_rate' => 20, 'total_ht' => 15400,
            ]);
        }

        return $invoice;
    }

    public function test_invoice_is_printed_on_the_company_letterhead(): void
    {
        $letterhead = app(LetterheadDocument::class);
        $html = $letterhead->html($letterhead->clientInvoice($this->invoice()));

        // letterhead
        $this->assertStringContainsString('logo-broadway.png', $html);
        $this->assertStringContainsString('logo-broadway-watermark.png', $html);
        $this->assertStringContainsString('>SARL<', $html);
        $this->assertStringContainsString('Plomberie sanitaire, Electricité', $html);
        $this->assertStringContainsString('26 Av Mers Sultan, Appt 3 Etg 1 - Casablanca', $html);
        $this->assertStringContainsString('GSM : 06 00 58 13 02 / 06 66 78 51 16 / Email : Broadwayenergie@gmail.com', $html);
        $this->assertStringContainsString('RC : 577179 - TP : 34104832 - IF : 53701023 - CNSS : 5014370 ICE : 003261782000029', $html);

        // content
        $this->assertStringContainsString('FACTURE', $html);
        $this->assertStringContainsString('N° FAC-2026-0042', $html);
        $this->assertStringContainsString('Atlas Distribution', $html);
        $this->assertStringContainsString('ICE : 001234567000089', $html);
        $this->assertStringContainsString('15 400,00 MAD', $html);
        $this->assertStringContainsString('TVA 20 %', $html);
        $this->assertStringContainsString('18 480,00 MAD', $html);
        $this->assertStringContainsString('dix-huit mille quatre cent quatre-vingts dirhams TTC', $html);
    }

    public function test_every_document_downloads_as_a_pdf(): void
    {
        $invoice = $this->invoice();
        $order = SalesOrder::forceCreate(['order_number' => 'CMD-2026-0001', 'client_id' => $this->client->id, 'order_date' => '2026-09-10', 'status' => 'confirmed', 'currency' => 'MAD']);
        $delivery = Delivery::forceCreate(['delivery_number' => 'BLV-2026-0001', 'sales_order_id' => $order->id, 'client_id' => $this->client->id, 'delivery_date' => '2026-09-12', 'status' => 'pending']);
        DeliveryItem::forceCreate(['delivery_id' => $delivery->id, 'product_id' => $this->product->id, 'quantity' => 2, 'shipped_qty' => 2]);

        foreach ([
            route('erp.client-invoices.pdf', $invoice),
            route('erp.sales-orders.pdf', $order),
            route('erp.deliveries.pdf', $delivery),
        ] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent(), $url);
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        }
    }

    public function test_long_invoice_flows_on_several_pages(): void
    {
        $letterhead = app(LetterheadDocument::class);
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('erp.pdf.letterhead', ['doc' => $letterhead->clientInvoice($this->invoice(45))]);

        $pdf->render();

        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
