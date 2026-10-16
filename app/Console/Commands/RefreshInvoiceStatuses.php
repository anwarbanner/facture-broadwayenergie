<?php

namespace App\Console\Commands;

use App\Services\Invoicing\PaymentService;
use Illuminate\Console\Command;

class RefreshInvoiceStatuses extends Command
{
    protected $signature = 'invoices:refresh-statuses';

    protected $description = 'Recompute payment status of open invoices (marks the ones past their due date as overdue)';

    public function handle(PaymentService $payments): int
    {
        $changed = $payments->refreshOpenInvoices();
        $this->info("{$changed} facture(s) mise(s) a jour.");

        return self::SUCCESS;
    }
}
