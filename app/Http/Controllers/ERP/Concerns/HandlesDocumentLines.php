<?php

namespace App\Http\Controllers\ERP\Concerns;

use App\Services\Contacts\ContactResolver;
use App\Support\Branding;
use App\Support\DocumentRules;
use App\Support\FinancialHelper;

/**
 * Line handling shared by quotes, orders, invoices, deliveries and receipts
 * (was copied in 8 controllers).
 */
trait HandlesDocumentLines
{
    /**
     * Validation rules shared by every priced document form (quotes, orders, invoices; sales and purchases):
     * one VAT rate for the whole document, and lines that are a product, a service or a free-text article.
     */
    protected function pricedLineRules(): array
    {
        return DocumentRules::pricedLines();
    }

    /**
     * Normalises the item_* arrays of a document form and computes the totals.
     * The row type (item_mode) decides which field is used, so a value left in a hidden field is ignored.
     * VAT is applied once on the total HT, with the document's rate; every line stores that same rate.
     *
     * @return array{0: array<int, array>, 1: array{total_ht: float, total_tva: float, total_ttc: float}, 2: array<string, string>}
     *         lines, totals, and per-row errors (empty when the form is valid)
     */
    protected function pricedLines(array $data): array
    {
        $items = [];
        $errors = [];
        $totalHt = 0.0;
        $rate = (float) ($data['tva_rate'] ?? Branding::defaultVatRate());

        $modes = $data['item_mode'] ?? [];
        $productIds = $data['item_product_id'] ?? [];
        $serviceIds = $data['item_service_id'] ?? [];
        $names = $data['item_name'] ?? [];
        $quantities = $data['item_quantity'] ?? [];
        $prices = $data['item_price_ht'] ?? [];

        $rowCount = max(count($modes), count($productIds), count($serviceIds), count($names), count($quantities), count($prices));

        for ($i = 0; $i < $rowCount; $i++) {
            $productId = !empty($productIds[$i]) ? (int) $productIds[$i] : null;
            $serviceId = !empty($serviceIds[$i]) ? (int) $serviceIds[$i] : null;
            $name = trim((string) ($names[$i] ?? ''));
            $mode = $modes[$i] ?? ($productId ? 'product' : ($serviceId ? 'service' : 'manual'));
            $qty = (float) ($quantities[$i] ?? 0);
            $price = (float) ($prices[$i] ?? 0);
            $label = 'Ligne ' . ($i + 1) . ': ';

            [$productId, $serviceId, $name] = match ($mode) {
                'product' => [$productId, null, ''],
                'service' => [null, $serviceId, ''],
                default => [null, null, $name],
            };

            if (!$productId && !$serviceId && $name === '') {
                // A blank row left at the end of the table is ignored; a priced one without article is an error.
                if ($price > 0) {
                    $errors["item_name.{$i}"] = $label . match ($mode) {
                        'product' => 'choisissez un produit.',
                        'service' => 'choisissez un service.',
                        default => 'saisissez la designation de l\'article.',
                    };
                }
                continue;
            }

            if ($qty <= 0) {
                $errors["item_quantity.{$i}"] = $label . 'la quantite doit etre superieure a 0.';
                continue;
            }

            $lineHt = round($qty * $price, 2);
            $items[] = [
                'product_id' => $productId,
                'service_id' => $serviceId,
                'item_name' => $name !== '' ? $name : null,
                'quantity' => $qty,
                'price_ht' => $price,
                'tva_rate' => $rate,
                'total_ht' => $lineHt,
            ];
            $totalHt += $lineHt;
        }

        if (empty($items) && empty($errors)) {
            $errors['item_name'] = 'Ajoutez au moins un article.';
        }

        return [$items, FinancialHelper::documentTotals($totalHt, $rate), $errors];
    }

    /**
     * Header amounts of a priced document. Documents are in one currency: the exchange rate is always 1.
     *
     * @param bool $invoice invoices store amount_ht / tva_amount instead of total_ht / total_tva
     */
    protected function documentAmounts(array $data, array $totals, bool $invoice = false): array
    {
        return [
            ($invoice ? 'amount_ht' : 'total_ht') => $totals['total_ht'],
            ($invoice ? 'tva_amount' : 'total_tva') => $totals['total_tva'],
            'total_ttc' => $totals['total_ttc'],
            'tva_rate' => (float) ($data['tva_rate'] ?? Branding::defaultVatRate()),
            'currency' => strtoupper((string) ($data['currency'] ?? '')) ?: Branding::defaultCurrency(),
            'exchange_rate' => 1,
        ];
    }

    /** Existing contact id, or the id of the contact created from a name typed in the form. */
    protected function contactId(mixed $value, string $type): int
    {
        return app(ContactResolver::class)->resolve($value, $type);
    }

    /** Sales lines: each row is either a product or a service. */
    protected function checkLineChoice(array $productIds, array $serviceIds): array
    {
        $errors = [];
        $rowCount = max(count($productIds), count($serviceIds));

        for ($i = 0; $i < $rowCount; $i++) {
            $hasProduct = !empty($productIds[$i]);
            $hasService = !empty($serviceIds[$i]);

            if (!$hasProduct && !$hasService) {
                $errors["item_product_id.{$i}"] = 'Ligne ' . ($i + 1) . ': selectionnez un produit ou un service.';
                continue;
            }

            if ($hasProduct && $hasService) {
                $errors["item_service_id.{$i}"] = 'Ligne ' . ($i + 1) . ': choisissez soit un produit, soit un service.';
            }
        }

        return $errors;
    }

    /** Purchase lines: exactly one of product / service / free-text, consistent with the row mode. */
    protected function checkLineChoiceWithManual(array $productIds, array $serviceIds, array $manualNames, array $modes, string $fieldKey, string $modeFieldKey): array
    {
        $errors = [];
        $rowCount = max(count($productIds), count($serviceIds), count($manualNames), count($modes));

        for ($i = 0; $i < $rowCount; $i++) {
            $hasProduct = !empty($productIds[$i]);
            $hasService = !empty($serviceIds[$i]);
            $hasManualName = trim((string) ($manualNames[$i] ?? '')) !== '';
            $mode = $modes[$i] ?? null;

            $picked = ($hasProduct ? 1 : 0) + ($hasService ? 1 : 0) + ($hasManualName ? 1 : 0);

            if ($picked === 0) {
                $errors["{$fieldKey}.{$i}"] = 'Ligne ' . ($i + 1) . ': selectionnez un produit, un service ou saisissez un article manuel.';
                continue;
            }

            if ($picked > 1) {
                $errors["{$fieldKey}.{$i}"] = 'Ligne ' . ($i + 1) . ': choisissez un seul type de ligne (produit, service ou manuel).';
                continue;
            }

            if ($hasProduct && $mode !== 'product') {
                $errors["{$modeFieldKey}.{$i}"] = 'Ligne ' . ($i + 1) . ': mode Base requis pour un produit de la base.';
            }

            if ($hasService && $mode !== 'service') {
                $errors["{$modeFieldKey}.{$i}"] = 'Ligne ' . ($i + 1) . ': mode Service requis pour un service.';
            }

            if ($hasManualName && $mode !== 'manual') {
                $errors["{$modeFieldKey}.{$i}"] = 'Ligne ' . ($i + 1) . ': mode Manuel requis pour un article saisi.';
            }
        }

        return $errors;
    }
}
