<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Display helpers for lists and documents (dates without "00:00:00", amounts with thousand separators).
 */
final class Format
{
    public static function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function money(mixed $value, ?string $currency = null): string
    {
        $amount = number_format((float) $value, 2, ',', ' ');

        return $currency ? $amount . ' ' . $currency : $amount;
    }

    /** Quantity without useless decimals: 1, 2,5, 1 250, 0,125 (never "1.000"). */
    public static function qty(mixed $value): string
    {
        $formatted = number_format((float) $value, 3, ',', ' ');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }

    /** Coloured status pill (text always present: the colour is never the only signal). */
    public static function statusBadge(?string $code, ?string $label = null): \Illuminate\Support\HtmlString
    {
        $tone = match ($code) {
            'draft', 'pending', 'inactive' => 'neutral',
            'sent', 'confirmed', 'shipped', 'unpaid' => 'info',
            'accepted', 'delivered', 'complete', 'paid', 'validated', 'active' => 'success',
            'partial', 'returned' => 'warning',
            'rejected', 'cancelled', 'overdue' => 'danger',
            'converted' => 'purple',
            default => 'neutral',
        };

        return new \Illuminate\Support\HtmlString('<span class="erp-status erp-status-' . $tone . '">' . e($label ?? self::label($code)) . '</span>');
    }

    /** "20 %", "5,5 %" */
    public static function rate(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',') . ' %';
    }

    /** Name of a document line: product, service or free-text article. */
    public static function designation(object $item): string
    {
        return $item->product?->product_name
            ?? $item->service?->service_name
            ?? (trim((string) ($item->item_name ?? '')) !== '' ? $item->item_name : '-');
    }

    /** French label for stored codes (document statuses, contact types, stock movements...). */
    public static function label(?string $code): string
    {
        $labels = [
            // documents
            'draft' => 'Brouillon', 'sent' => 'Envoye', 'accepted' => 'Accepte', 'rejected' => 'Refuse',
            'converted' => 'Converti', 'confirmed' => 'Confirme', 'partial' => 'Partiel', 'delivered' => 'Livre',
            'cancelled' => 'Annule', 'pending' => 'En attente', 'shipped' => 'Expedie', 'returned' => 'Retourne',
            'complete' => 'Complet', 'unpaid' => 'Non paye', 'paid' => 'Paye', 'overdue' => 'En retard',
            'expired' => 'Expire',
            // master data
            'active' => 'Actif', 'inactive' => 'Inactif', 'unactive' => 'Inactif',
            'client' => 'Client', 'supplier' => 'Fournisseur', 'lead' => 'Piste', 'prospect' => 'Prospect',
            'product' => 'Produit', 'service' => 'Service',
            // stock
            'in' => 'Entree', 'out' => 'Sortie', 'adjustment' => 'Ajustement',
        ];

        return $labels[$code] ?? ucfirst((string) $code);
    }

    /** Old invoicing module: its status text is stored in Arabic, the numeric code is reliable. */
    public static function legacyStatus(mixed $valueStatus): string
    {
        return match ((int) $valueStatus) {
            1 => 'Payee',
            2 => 'Non payee',
            3 => 'Partiellement payee',
            default => '-',
        };
    }

    public static function isRtl(): bool
    {
        return app()->getLocale() === 'ar';
    }
}
