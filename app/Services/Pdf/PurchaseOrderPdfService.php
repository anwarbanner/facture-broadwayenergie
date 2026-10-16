<?php

namespace App\Services\Pdf;

use App\Models\PurchaseOrder;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class PurchaseOrderPdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(PurchaseOrder $order)
    {
        return $this->letterhead->download($this->letterhead->purchaseOrder($order));
    }
}
