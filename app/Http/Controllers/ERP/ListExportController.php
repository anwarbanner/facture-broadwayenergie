<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Models\ClientInvoice;
use App\Models\Delivery;
use App\Models\PurchaseQuote;
use App\Models\SupplierInvoice;
use App\Models\SalesQuote;
use App\Support\Branding;
use App\Support\Format;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * "Exporter la liste en Excel" on the quote, invoice and BL lists: one row per document, with the same
 * search / status filter as the page (FiltersDocuments), all pages at once.
 */
class ListExportController extends Controller
{
    use FiltersDocuments;

    private const MONEY = '#,##0.00';

    public function export(string $type)
    {
        [$title, $headers, $rows, $moneyColumns] = match ($type) {
            'sales-quotes' => $this->quotes(),
            'client-invoices' => $this->invoices(),
            'deliveries' => $this->deliveries(),
            'purchase-quotes' => $this->purchaseQuotes(),
            'supplier-invoices' => $this->supplierInvoices(),
        };

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator(Branding::companyName())->setTitle($title);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2', true);

        $lastColumn = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(ltrim(Branding::colorPrimary(), '#'));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}" . max(1, count($rows) + 1));
        foreach ($moneyColumns as $column) {
            $sheet->getStyle("{$column}2:{$column}" . (count($rows) + 1))->getNumberFormat()->setFormatCode(self::MONEY);
        }
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $file = $title . '-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function quotes(): array
    {
        $rows = $this->filterDocuments(SalesQuote::query()->with(['client', 'invoice', 'delivery']), 'quote_number', 'client')
            ->latest()->get()
            ->map(fn (SalesQuote $q) => [
                $q->quote_number,
                $q->client->company_name ?? '',
                Format::date($q->date),
                (float) $q->total_ht,
                (float) $q->total_tva,
                (float) $q->total_ttc,
                $q->currency,
                $q->invoice ? 'Encaisse' : Format::label($q->status),
                $q->invoice?->invoice_number ?? '',
                $q->delivery?->delivery_number ?? '',
            ])->all();

        return ['Devis', ['Numero', 'Client', 'Date', 'Total HT', 'TVA', 'Total TTC', 'Devise', 'Etat', 'Facture', 'Bon de livraison'], $rows, ['D', 'E', 'F']];
    }

    private function invoices(): array
    {
        $rows = $this->filterDocuments(ClientInvoice::query()->with(['client', 'quote']), 'invoice_number', 'client')
            ->latest()->get()
            ->map(fn (ClientInvoice $i) => [
                $i->displayNumber(),
                $i->documentLabel(),
                $i->client->company_name ?? '',
                Format::date($i->invoice_date),
                (float) $i->amount_ht,
                (float) $i->tva_amount,
                (float) $i->total_ttc,
                (float) $i->amount_paid,
                (float) $i->balance_remaining,
                $i->currency,
                $i->stateLabel(),
                $i->isDraft() ? '' : $i->statusLabel(),
                $i->quote?->quote_number ?? '',
            ])->all();

        return ['Factures', ['Numero', 'Type', 'Client', 'Date', 'Total HT', 'TVA', 'Total TTC', 'Paye', 'Reste a payer', 'Devise', 'Etat', 'Paiement', 'Devis'], $rows, ['E', 'F', 'G', 'H', 'I']];
    }

    private function purchaseQuotes(): array
    {
        $rows = $this->filterDocuments(PurchaseQuote::query()->with('supplier'), 'quote_number', 'supplier')
            ->latest()->get()
            ->map(fn (PurchaseQuote $q) => [
                $q->quote_number,
                $q->supplier->company_name ?? '',
                Format::date($q->date),
                (float) $q->total_ht,
                (float) $q->total_tva,
                (float) $q->total_ttc,
                $q->currency,
                Format::label($q->status),
            ])->all();

        return ['Devis fournisseurs', ['Numero', 'Fournisseur', 'Date', 'Total HT', 'TVA', 'Total TTC', 'Devise', 'Etat'], $rows, ['D', 'E', 'F']];
    }

    private function supplierInvoices(): array
    {
        $rows = $this->filterDocuments(SupplierInvoice::query()->with(['supplier', 'quote']), 'invoice_number', 'supplier')
            ->latest()->get()
            ->map(fn (SupplierInvoice $i) => [
                $i->displayNumber(),
                $i->documentLabel(),
                $i->supplier->company_name ?? '',
                Format::date($i->invoice_date),
                (float) $i->amount_ht,
                (float) $i->tva_amount,
                (float) $i->total_ttc,
                (float) $i->amount_paid,
                (float) $i->balance_remaining,
                $i->currency,
                $i->stateLabel(),
                $i->isDraft() ? '' : $i->statusLabel(),
                $i->quote?->quote_number ?? '',
            ])->all();

        return ['Factures fournisseurs', ['Numero', 'Type', 'Fournisseur', 'Date', 'Total HT', 'TVA', 'Total TTC', 'Paye', 'Reste a payer', 'Devise', 'Etat', 'Paiement', 'Devis fournisseur'], $rows, ['E', 'F', 'G', 'H', 'I']];
    }

    private function deliveries(): array
    {
        $rows = $this->filterDocuments(Delivery::query()->with(['client', 'quote', 'order']), 'delivery_number', 'client')
            ->latest()->get()
            ->map(fn (Delivery $d) => [
                $d->delivery_number,
                $d->client->company_name ?? '',
                Format::date($d->delivery_date),
                $d->quote?->quote_number ?? $d->order?->order_number ?? '',
                Format::label($d->status),
                Format::qty($d->items()->sum('shipped_qty')),
            ])->all();

        return ['Bons de livraison', ['Numero', 'Client', 'Date', 'Devis / commande', 'Etat', 'Quantite livree'], $rows, []];
    }
}
