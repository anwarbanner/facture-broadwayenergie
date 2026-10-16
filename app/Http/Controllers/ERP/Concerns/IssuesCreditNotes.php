<?php

namespace App\Http\Controllers\ERP\Concerns;

use App\Models\ClientInvoice;
use App\Models\SupplierInvoice;
use App\Services\Invoicing\InvoiceLifecycleService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Avoir" form of the client and supplier invoice pages.
 * mode=full: credits everything still creditable (cancels the invoice);
 * mode=partial: quantities per line and / or a discount amount.
 */
trait IssuesCreditNotes
{
    protected function issueCreditNote(Request $request, ClientInvoice|SupplierInvoice $invoice, InvoiceLifecycleService $lifecycle, string $routePrefix): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'in:full,partial'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
            'discount_label' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $partial = ($data['mode'] ?? 'full') === 'partial';
        $creditNote = $lifecycle->createCreditNote(
            $invoice,
            $partial ? array_map('floatval', array_filter($data['quantities'] ?? [], fn ($qty) => $qty !== null && $qty !== '')) : null,
            $partial && (float) ($data['discount_amount'] ?? 0) > 0
                ? ['label' => $data['discount_label'] ?? null, 'amount_ht' => (float) $data['discount_amount']]
                : null,
            $data['reason'] ?? null,
        );

        $invoice->refresh();
        $message = 'Avoir ' . $creditNote->invoice_number . ' cree (' . Format::money($creditNote->total_ttc) . ' TTC). ';
        $message .= $invoice->isCancelled()
            ? 'La facture ' . $invoice->invoice_number . ' est entierement creditee : elle est annulee.'
            : 'Reste du sur la facture ' . $invoice->invoice_number . ' : ' . Format::money($invoice->balance_remaining) . '.';
        if ((float) $creditNote->balance_remaining > 0) {
            $message .= ' A rembourser : ' . Format::money($creditNote->balance_remaining) . '.';
        }

        return redirect()->route($routePrefix . '.show', $creditNote)->with('success', $message);
    }
}
