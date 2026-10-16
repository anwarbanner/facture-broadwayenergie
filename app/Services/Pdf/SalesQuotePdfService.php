<?php

namespace App\Services\Pdf;

use App\Models\SalesQuote;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class SalesQuotePdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(SalesQuote $quote)
    {
        return $this->letterhead->download($this->letterhead->salesQuote($quote));
    }

    public function stream(SalesQuote $quote)
    {
        return $this->letterhead->stream($this->letterhead->salesQuote($quote));
    }
}
