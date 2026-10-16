<?php

namespace App\Services\Pdf;

use App\Models\SupplierInvoice;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class SupplierInvoicePdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(SupplierInvoice $invoice)
    {
        return $this->letterhead->download($this->letterhead->supplierInvoice($invoice));
    }

    public function stream(SupplierInvoice $invoice)
    {
        return $this->letterhead->stream($this->letterhead->supplierInvoice($invoice));
    }
}
