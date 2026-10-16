<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Single source for the company identity shown in screens and PDFs (config/branding.php, filled from .env).
 * No company is hard-coded: without a logo the initials of the configured name are used.
 */
final class Branding
{
    public static function companyName(): string
    {
        return trim((string) config('branding.company_name')) ?: (config('app.name') ?: 'Mon Entreprise');
    }

    public static function tagline(): string
    {
        return (string) config('branding.tagline', '');
    }

    /** Absolute path of the configured logo, or null when none is configured / the file is missing. */
    public static function logoFile(): ?string
    {
        $path = trim((string) config('branding.logo_path'));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $file = public_path($path);

        return is_file($file) ? $file : null;
    }

    public static function logoUrl(): ?string
    {
        return self::logoFile() ? asset(trim((string) config('branding.logo_path'))) : null;
    }

    public static function faviconUrl(): string
    {
        if ($favicon = self::publicFile((string) config('branding.favicon_path'))) {
            return asset(trim((string) config('branding.favicon_path')));
        }

        return self::logoUrl() ?? asset('assets/img/brand/erp-icon.svg');
    }

    public static function watermarkFile(): ?string
    {
        return self::publicFile((string) config('branding.watermark_path'));
    }

    public static function legalForm(): string
    {
        return trim((string) config('branding.legal_form'));
    }

    /** @return string[] business lines printed top-right of the letterhead */
    public static function sloganLines(): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) config('branding.slogan')))));
    }

    /** @return string[] the three letterhead footer lines: address, phone / e-mail, legal identifiers */
    public static function footerLines(): array
    {
        $address = trim(implode(' - ', array_filter([
            trim((string) config('branding.address')),
            trim(config('branding.city') . ' ' . config('branding.country')),
        ])));

        $contact = implode(' / ', array_filter([
            trim((string) config('branding.phone')) !== '' ? 'GSM : ' . trim((string) config('branding.phone')) : '',
            trim((string) config('branding.email')) !== '' ? 'Email : ' . trim((string) config('branding.email')) : '',
        ]));

        return array_values(array_filter([$address, $contact, self::legalLine()], fn ($line) => $line !== ''));
    }

    public static function colorPrimary(): string
    {
        return self::cssColor((string) config('branding.color_primary'), '#0061A3');
    }

    public static function colorAccent(): string
    {
        return self::cssColor((string) config('branding.color_accent'), '#E91E25');
    }

    private static function cssColor(string $value, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($value)) ? trim($value) : $default;
    }

    /** Absolute path of an image under public/, or null (never outside public/). */
    private static function publicFile(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return is_file(public_path($path)) ? public_path($path) : null;
    }

    /** "Mon Entreprise" -> "ME", "Acme" -> "AC". */
    public static function initials(): string
    {
        $words = preg_split('/\s+/', Str::ascii(self::companyName()), -1, PREG_SPLIT_NO_EMPTY) ?: ['ERP'];
        $initials = count($words) > 1
            ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
            : mb_substr($words[0], 0, 2);

        return mb_strtoupper($initials);
    }

    public static function contactLine(): string
    {
        return implode(' | ', array_filter([
            (string) config('branding.phone'),
            (string) config('branding.email'),
            trim(config('branding.address') . ' ' . config('branding.city') . ' ' . config('branding.country')),
        ], fn ($value) => trim($value) !== ''));
    }

    public static function legalLine(): string
    {
        $moroccan = [
            'ICE' => config('branding.ice'),
            'RC' => config('branding.rc'),
            'IF' => config('branding.if'),
            'Patente' => config('branding.patente'),
        ];

        $parts = [trim((string) config('branding.legal_ids'))];
        foreach ($moroccan as $label => $value) {
            if (trim((string) $value) !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }

        return implode(' | ', array_filter($parts, fn ($value) => $value !== ''));
    }

    /** Values used by the PDF templates. */
    public static function pdf(): array
    {
        return [
            'company_name' => self::companyName(),
            'logo_path' => self::logoFile(),
            'tagline' => self::tagline(),
            'footer_parts' => array_values(array_filter([
                self::companyName(),
                self::contactLine(),
                self::legalLine(),
                'Genere le ' . now()->format('Y-m-d'),
            ], fn ($value) => $value !== '')),
        ];
    }

    /** Short menu + one-click cash-in on quotes (config branding.simple_mode / SIMPLE_MODE). */
    public static function simpleMode(): bool
    {
        return (bool) config('branding.simple_mode', true);
    }

    public static function defaultCurrency(): string
    {
        return strtoupper((string) config('branding.default_currency', 'MAD')) ?: 'MAD';
    }

    public static function defaultVatRate(): float
    {
        return (float) config('branding.default_vat_rate', 20);
    }
}
