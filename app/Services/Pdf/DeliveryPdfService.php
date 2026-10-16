<?php

namespace App\Services\Pdf;

use App\Models\Delivery;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class DeliveryPdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(Delivery $delivery)
    {
        return $this->letterhead->download($this->letterhead->delivery($delivery));
    }

    public function stream(Delivery $delivery)
    {
        return $this->letterhead->stream($this->letterhead->delivery($delivery));
    }
}
