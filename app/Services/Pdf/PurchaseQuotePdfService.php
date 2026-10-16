<?php

namespace App\Services\Pdf;

use App\Models\PurchaseQuote;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class PurchaseQuotePdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(PurchaseQuote $quote)
    {
        return $this->letterhead->download($this->letterhead->purchaseQuote($quote));
    }

    public function stream(PurchaseQuote $quote)
    {
        return $this->letterhead->stream($this->letterhead->purchaseQuote($quote));
    }
}
