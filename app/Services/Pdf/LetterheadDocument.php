<?php

namespace App\Services\Pdf;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\SupplierInvoice;
use App\Support\AmountInWords;
use App\Support\Format;

/**
 * Builds the data of every commercial document printed on the company letterhead
 * (view erp.pdf.letterhead) and renders it with dompdf.
 */
class LetterheadDocument
{
    public function download(array $doc)
    {
        return $this->render($doc)->download($this->fileName($doc));
    }

    /** Opens the PDF in the browser tab (its viewer has the print button) instead of downloading it. */
    public function stream(array $doc)
    {
        return $this->render($doc)->stream($this->fileName($doc));
    }

    private function render(array $doc)
    {
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadView('erp.pdf.letterhead', ['doc' => $doc]);

        return $pdf;
    }

    private function fileName(array $doc): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', \Illuminate\Support\Str::ascii($doc['file_name'])), '-') . '.pdf';
    }

    public function html(array $doc): string
    {
        return view('erp.pdf.letterhead', ['doc' => $doc])->render();
    }

    // ---- Sales ------------------------------------------------------------------------------------------

    public function clientInvoice(ClientInvoice $invoice): array
    {
        $invoice->loadMissing('items.product', 'items.service', 'client', 'order', 'delivery', 'creditedInvoice', 'quote');
        $credit = $invoice->isCreditNote();

        return $this->priced([
            'title' => $credit ? 'AVOIR' : 'FACTURE',
            'number' => $invoice->displayNumber(),
            'draft' => $invoice->isDraft(),
            'party_label' => 'Client',
            'party' => $invoice->client,
            'meta' => array_filter([
                'Date' => Format::date($invoice->invoice_date),
                'Échéance' => $invoice->due_date ? Format::date($invoice->due_date) : null,
                'Devis' => $invoice->quote?->quote_number,
                'Commande' => $invoice->order?->order_number,
                'Bon de livraison' => $invoice->delivery?->delivery_number,
                'Avoir sur la facture' => $credit ? $invoice->creditedInvoice?->invoice_number : null,
                'Motif' => $credit ? $invoice->credit_reason : null,
                'Conditions de paiement' => $invoice->payment_terms,
            ]),
            'currency' => $invoice->currency,
            'items' => $invoice->items,
            'totals' => [(float) $invoice->amount_ht, (float) $invoice->tva_amount, (float) $invoice->total_ttc],
            'payments' => (!$credit && $invoice->isValidated() && (float) $invoice->amount_paid > 0)
                ? ['paid' => (float) $invoice->amount_paid, 'balance' => (float) $invoice->balance_remaining] : null,
            'words_intro' => $credit ? 'Arrêté le présent avoir à la somme de' : 'Arrêtée la présente facture à la somme de',
        ]);
    }

    public function salesQuote(SalesQuote $quote): array
    {
        $quote->loadMissing('items.product', 'items.service', 'client');

        return $this->priced([
            'title' => 'DEVIS',
            'number' => $quote->quote_number,
            'party_label' => 'Client',
            'party' => $quote->client,
            'meta' => array_filter([
                'Date' => Format::date($quote->date),
                'Valable jusqu\'au' => $quote->validity_date ? Format::date($quote->validity_date) : null,
            ]),
            'currency' => $quote->currency,
            'items' => $quote->items,
            'totals' => [(float) $quote->total_ht, (float) $quote->total_tva, (float) $quote->total_ttc],
            'words_intro' => 'Arrêté le présent devis à la somme de',
        ]);
    }

    public function salesOrder(SalesOrder $order): array
    {
        $order->loadMissing('items.product', 'items.service', 'client', 'quote');

        return $this->priced([
            'title' => 'BON DE COMMANDE',
            'number' => $order->order_number,
            'party_label' => 'Client',
            'party' => $order->client,
            'meta' => array_filter([
                'Date' => Format::date($order->order_date),
                'Livraison prévue' => $order->delivery_date ? Format::date($order->delivery_date) : null,
                'Devis' => $order->quote?->quote_number,
            ]),
            'currency' => $order->currency,
            'items' => $order->items,
            'totals' => [(float) $order->total_ht, (float) $order->total_tva, (float) $order->total_ttc],
            'words_intro' => 'Arrêtée la présente commande à la somme de',
        ]);
    }

    public function delivery(Delivery $delivery): array
    {
        $delivery->loadMissing('items.product', 'items.service', 'client', 'order', 'quote');

        return $this->quantities([
            'title' => 'BON DE LIVRAISON',
            'number' => $delivery->delivery_number,
            'party_label' => 'Client',
            'party' => $delivery->client,
            'extra_party_lines' => $delivery->shipping_address ? ['Livraison : ' . $delivery->shipping_address] : [],
            'meta' => array_filter([
                'Date' => Format::date($delivery->delivery_date),
                'Commande' => $delivery->order?->order_number,
                'Devis' => $delivery->quote?->quote_number,
                'N° de suivi' => $delivery->tracking_number,
            ]),
            'items' => $delivery->items,
            // Simple mode BL (no order): one quantity column, nothing was "ordered".
            'single' => $delivery->isSimple(),
            'done_label' => $delivery->isSimple() ? 'Quantité' : 'Qté livrée',
            'done_field' => 'shipped_qty',
            'signatures' => ['Cachet et signature du client', 'Cachet et signature ' . \App\Support\Branding::companyName()],
        ]);
    }

    // ---- Purchases --------------------------------------------------------------------------------------

    public function purchaseQuote(PurchaseQuote $quote): array
    {
        $quote->loadMissing('items.product', 'items.service', 'supplier');

        return $this->priced([
            // Simple mode calls them "Devis fournisseurs" (owner request 2026-10-09).
            'title' => \App\Support\Branding::simpleMode() ? 'DEVIS FOURNISSEUR' : 'DEMANDE D\'ACHAT',
            'number' => $quote->quote_number,
            'party_label' => 'Fournisseur',
            'party' => $quote->supplier,
            'meta' => array_filter([
                'Date' => Format::date($quote->date),
                'Réponse souhaitée avant le' => $quote->validity_date ? Format::date($quote->validity_date) : null,
            ]),
            'currency' => $quote->currency,
            'items' => $quote->items,
            'totals' => [(float) $quote->total_ht, (float) $quote->total_tva, (float) $quote->total_ttc],
            'words_intro' => null,
        ]);
    }

    public function purchaseOrder(PurchaseOrder $order): array
    {
        $order->loadMissing('items.product', 'items.service', 'supplier', 'quote');

        return $this->priced([
            'title' => 'BON DE COMMANDE',
            'number' => $order->order_number,
            'party_label' => 'Fournisseur',
            'party' => $order->supplier,
            'meta' => array_filter([
                'Date' => Format::date($order->order_date),
                'Livraison attendue' => $order->expected_delivery_date ? Format::date($order->expected_delivery_date) : null,
                'Demande d\'achat' => $order->quote?->quote_number,
            ]),
            'currency' => $order->currency,
            'items' => $order->items,
            'totals' => [(float) $order->total_ht, (float) $order->total_tva, (float) $order->total_ttc],
            'words_intro' => 'Arrêtée la présente commande à la somme de',
        ]);
    }

    public function deliveryNote(DeliveryNote $note): array
    {
        $note->loadMissing('items.product', 'items.service', 'supplier', 'order');

        return $this->quantities([
            'title' => 'BON DE RÉCEPTION',
            'number' => $note->receipt_number,
            'party_label' => 'Fournisseur',
            'party' => $note->supplier,
            'meta' => array_filter([
                'Date' => Format::date($note->delivery_date),
                'Commande' => $note->order?->order_number,
            ]),
            'items' => $note->items,
            'done_label' => 'Qté reçue',
            'done_field' => 'received_qty',
            'signatures' => ['Visa magasin', 'Visa responsable'],
            'notes' => $note->notes ? [$note->notes] : [],
        ]);
    }

    public function supplierInvoice(SupplierInvoice $invoice): array
    {
        $invoice->loadMissing('items.product', 'items.service', 'supplier', 'order', 'deliveryNote', 'creditedInvoice', 'quote');
        $credit = $invoice->isCreditNote();

        return $this->priced([
            'title' => $credit ? 'AVOIR FOURNISSEUR' : 'FACTURE FOURNISSEUR',
            'number' => $invoice->displayNumber(),
            'draft' => $invoice->isDraft(),
            'party_label' => 'Fournisseur',
            'party' => $invoice->supplier,
            'meta' => array_filter([
                'Date' => Format::date($invoice->invoice_date),
                'Échéance' => $invoice->due_date ? Format::date($invoice->due_date) : null,
                'Commande' => $invoice->order?->order_number,
                'Devis fournisseur' => $invoice->quote?->quote_number,
                'Bon de réception' => $invoice->deliveryNote?->receipt_number,
                'Avoir sur la facture' => $credit ? $invoice->creditedInvoice?->invoice_number : null,
                'Motif' => $credit ? $invoice->credit_reason : null,
            ]),
            'currency' => $invoice->currency,
            'items' => $invoice->items,
            'totals' => [(float) $invoice->amount_ht, (float) $invoice->tva_amount, (float) $invoice->total_ttc],
            'payments' => (!$credit && $invoice->isValidated() && (float) $invoice->amount_paid > 0)
                ? ['paid' => (float) $invoice->amount_paid, 'balance' => (float) $invoice->balance_remaining] : null,
            'words_intro' => null,
        ]);
    }

    // ---- Shared -----------------------------------------------------------------------------------------

    private function priced(array $spec): array
    {
        [$ht, $tva, $ttc] = $spec['totals'];
        $currency = strtoupper($spec['currency'] ?: \App\Support\Branding::defaultCurrency());
        $rates = $spec['items']->pluck('tva_rate')->map(fn ($r) => (float) $r)->unique()->values();

        return $this->base($spec) + [
            'kind' => 'priced',
            'currency' => $currency,
            'lines' => $spec['items']->map(fn ($item) => [
                'designation' => $this->designation($item),
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->price_ht,
                'total' => (float) $item->total_ht,
            ])->all(),
            'totals' => [
                'ht' => $ht,
                'tva' => $tva,
                'ttc' => $ttc,
                'tva_label' => $rates->count() === 1 ? 'TVA ' . rtrim(rtrim(number_format($rates[0], 2, ',', ''), '0'), ',') . ' %' : 'TVA',
            ],
            'payments' => $spec['payments'] ?? null,
            'amount_in_words' => $spec['words_intro']
                ? $spec['words_intro'] . ' : ' . AmountInWords::money($ttc, $currency) . ' TTC.'
                : null,
        ];
    }

    private function quantities(array $spec): array
    {
        return $this->base($spec) + [
            'kind' => 'quantities',
            'single' => (bool) ($spec['single'] ?? false),
            'done_label' => $spec['done_label'],
            'lines' => $spec['items']->map(fn ($item) => [
                'designation' => $this->designation($item),
                'ordered' => (float) $item->quantity,
                'done' => (float) $item->{$spec['done_field']},
            ])->all(),
            'signatures' => $spec['signatures'] ?? [],
        ];
    }

    private function base(array $spec): array
    {
        $draft = (bool) ($spec['draft'] ?? false);

        return [
            'title' => $spec['title'] . ($draft ? ' - BROUILLON' : ''),
            'number' => $spec['number'],
            'draft' => $draft,
            'file_name' => $spec['title'] . '-' . $spec['number'],
            'meta' => $spec['meta'],
            'party_label' => $spec['party_label'],
            'party' => $this->party($spec['party'], $spec['extra_party_lines'] ?? []),
            'notes' => $spec['notes'] ?? [],
            // Administration > Signature et cachet; never on a draft (it has no value yet).
            'signature' => !$draft && \App\Support\Signature::exists()
                // File path, like the logo: dompdf does not draw this image from a data: URI.
                ? ['image' => \App\Support\Signature::path(), 'signatory' => \App\Support\Signature::signatory()]
                : null,
        ];
    }

    private function party(?Contact $contact, array $extra): array
    {
        if (!$contact) {
            return ['name' => '-', 'lines' => $extra];
        }

        return [
            'name' => $contact->company_name,
            'lines' => array_values(array_filter(array_merge([
                $contact->contact_person_name ? 'À l\'attention de ' . $contact->contact_person_name : null,
                $contact->address,
                trim(implode(' ', array_filter([$contact->postal_code, $contact->city, $contact->country]))) ?: null,
                implode(' - ', array_filter([$contact->phone ? 'Tél : ' . $contact->phone : null, $contact->email])) ?: null,
                $contact->tax_id ? 'ICE : ' . $contact->tax_id : null,
            ], $extra))),
        ];
    }

    private function designation($item): string
    {
        return $item->product?->product_name
            ?? $item->service?->service_name
            ?? ($item->item_name ?? '-');
    }
}
