<?php

namespace Tests\Unit;

use App\Support\FinancialHelper;
use PHPUnit\Framework\TestCase;

class FinancialHelperTest extends TestCase
{
    public function test_paid_amount_is_capped_to_total(): void
    {
        $this->assertSame(100.0, FinancialHelper::paidAmount(100.0, 150.0));
        $this->assertSame(0.0, FinancialHelper::paidAmount(100.0, -10.0));
    }

    public function test_balance_never_goes_negative(): void
    {
        $this->assertSame(0.0, FinancialHelper::balance(100.0, 150.0));
        $this->assertSame(65.5, FinancialHelper::balance(100.0, 34.5));
    }

    public function test_invoice_status_is_paid_when_fully_settled(): void
    {
        $this->assertSame('paid', FinancialHelper::invoiceStatus(100.0, 100.0, '2026-04-30'));
        $this->assertSame('paid', FinancialHelper::invoiceStatus(100.0, 150.0, '2026-04-30'));
    }

    public function test_invoice_status_is_partial_when_partially_paid(): void
    {
        $this->assertSame('partial', FinancialHelper::invoiceStatus(100.0, 10.0, '2026-04-30'));
    }

    public function test_invoice_status_is_overdue_when_due_date_has_passed_and_unpaid(): void
    {
        $this->assertSame('overdue', FinancialHelper::invoiceStatus(100.0, 0.0, '2000-01-01'));
    }

    public function test_invoice_status_is_unpaid_when_due_date_not_passed_and_no_payment(): void
    {
        $this->assertSame('unpaid', FinancialHelper::invoiceStatus(100.0, 0.0, '2999-01-01'));
    }
}
