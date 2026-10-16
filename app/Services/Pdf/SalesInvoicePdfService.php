<?php

namespace App\Services\Pdf;

use App\Models\ClientInvoice;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class SalesInvoicePdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(ClientInvoice $invoice)
    {
        return $this->letterhead->download($this->letterhead->clientInvoice($invoice));
    }

    public function stream(ClientInvoice $invoice)
    {
        return $this->letterhead->stream($this->letterhead->clientInvoice($invoice));
    }
}
