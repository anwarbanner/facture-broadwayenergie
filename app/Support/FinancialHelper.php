<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class FinancialHelper
{
    public static function totals(float $qty, float $priceHt, float $tvaRate): array
    {
        $totalHt = round($qty * $priceHt, 2);
        $totalTva = round($totalHt * ($tvaRate / 100), 2);
        $totalTtc = round($totalHt + $totalTva, 2);

        return [
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
        ];
    }

    /** VAT is applied once, on the document's total HT (not line by line). */
    public static function documentTotals(float $totalHt, float $tvaRate): array
    {
        $totalHt = round($totalHt, 2);
        $totalTva = round($totalHt * ($tvaRate / 100), 2);

        return [
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => round($totalHt + $totalTva, 2),
        ];
    }

    public static function margin(float $saleHt, float $costHt): array
    {
        $amount = round($saleHt - $costHt, 2);
        $rate = $costHt > 0 ? round(($amount / $costHt) * 100, 2) : 0.0;

        return [
            'amount' => $amount,
            'rate' => $rate,
        ];
    }

    public static function balance(float $total, float $paid): float
    {
        $normalizedTotal = max(round($total, 2), 0.0);
        $normalizedPaid = self::paidAmount($normalizedTotal, $paid);

        return round($normalizedTotal - $normalizedPaid, 2);
    }

    public static function paidAmount(float $total, float $paid): float
    {
        $normalizedTotal = max(round($total, 2), 0.0);
        $normalizedPaid = max(round($paid, 2), 0.0);

        return min($normalizedPaid, $normalizedTotal);
    }

    public static function invoiceStatus(float $total, float $paid, ?string $dueDate = null): string
    {
        $normalizedPaid = self::paidAmount($total, $paid);
        $remaining = self::balance($total, $normalizedPaid);

        if ($remaining <= 0.0) {
            return 'paid';
        }

        if ($normalizedPaid > 0.0) {
            return 'partial';
        }

        if ($dueDate) {
            try {
                if (Carbon::parse($dueDate)->startOfDay()->lt(Carbon::today())) {
                    return 'overdue';
                }
            } catch (\Throwable $e) {
                // Invalid dates are already prevented by request validation.
            }
        }

        return 'unpaid';
    }
}
