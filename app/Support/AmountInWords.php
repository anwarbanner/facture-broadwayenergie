<?php

namespace App\Support;

/**
 * French amount in words for invoices: 18480.50 MAD -> "dix-huit mille quatre cent quatre-vingts dirhams
 * et cinquante centimes". Traditional spelling (hyphens under one hundred, "et un", "quatre-vingts", "cents").
 */
final class AmountInWords
{
    private const UNITS = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];

    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    /** currency code => [singular, plural, sub-unit singular, sub-unit plural] */
    private const CURRENCIES = [
        'MAD' => ['dirham', 'dirhams', 'centime', 'centimes'],
        'EUR' => ['euro', 'euros', 'centime', 'centimes'],
        'USD' => ['dollar', 'dollars', 'cent', 'cents'],
    ];

    public static function money(float $amount, string $currency = 'MAD'): string
    {
        $amount = round(abs($amount), 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        [$one, $many, $subOne, $subMany] = self::CURRENCIES[strtoupper($currency)] ?? [strtoupper($currency), strtoupper($currency), 'centime', 'centimes'];

        $text = self::number($whole) . ' ' . ($whole > 1 ? $many : $one);
        if ($cents > 0) {
            $text .= ' et ' . self::number($cents) . ' ' . ($cents > 1 ? $subMany : $subOne);
        }

        return $text;
    }

    public static function number(int $n): string
    {
        if ($n < 0) {
            return 'moins ' . self::number(-$n);
        }
        if ($n < 1000) {
            return self::underThousand($n, true);
        }

        $parts = [];
        foreach ([1000000000 => ['milliard', 'milliards'], 1000000 => ['million', 'millions']] as $value => [$singular, $plural]) {
            if ($n >= $value) {
                $count = intdiv($n, $value);
                $parts[] = self::number($count) . ' ' . ($count > 1 ? $plural : $singular);
                $n %= $value;
            }
        }
        if ($n >= 1000) {
            $count = intdiv($n, 1000);
            // "mille" is invariable and never preceded by "un"; a hundred before it stays without "s" (deux cent mille).
            $parts[] = ($count === 1 ? '' : self::underThousand($count, false) . ' ') . 'mille';
            $n %= 1000;
        }
        if ($n > 0) {
            $parts[] = self::underThousand($n, true);
        }

        return implode(' ', $parts);
    }

    /** $final: the group ends the number, so "quatre-vingts" / "deux cents" take their "s". */
    private static function underThousand(int $n, bool $final): string
    {
        if ($n < 100) {
            return self::underHundred($n, $final);
        }

        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $text = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds] . ' cent' . ($rest === 0 && $final ? 's' : '');

        return $rest === 0 ? $text : $text . ' ' . self::underHundred($rest, $final);
    }

    private static function underHundred(int $n, bool $final): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        $tens = intdiv($n, 10);
        $unit = $n % 10;

        if ($tens === 7 || $tens === 9) {
            // 70-79 = soixante + 10..19, 90-99 = quatre-vingt + 10..19
            $base = $tens === 7 ? 'soixante' : 'quatre-vingt';
            $link = ($tens === 7 && $unit === 1) ? ' et ' : '-';

            return $base . $link . self::UNITS[10 + $unit];
        }

        if ($tens === 8) {
            return $unit === 0 ? 'quatre-vingt' . ($final ? 's' : '') : 'quatre-vingt-' . self::UNITS[$unit];
        }

        if ($unit === 0) {
            return self::TENS[$tens];
        }

        return self::TENS[$tens] . ($unit === 1 ? ' et un' : '-' . self::UNITS[$unit]);
    }
}
