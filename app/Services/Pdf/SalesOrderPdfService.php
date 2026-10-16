<?php

namespace App\Services\Pdf;

use App\Models\SalesOrder;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class SalesOrderPdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(SalesOrder $order)
    {
        return $this->letterhead->download($this->letterhead->salesOrder($order));
    }
}
