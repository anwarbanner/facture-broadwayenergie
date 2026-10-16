<?php

namespace App\Services\Pdf;

use App\Models\DeliveryNote;

/** Printed on the company letterhead: see LetterheadDocument and resources/views/erp/pdf/letterhead.blade.php. */
class DeliveryNotePdfService
{
    public function __construct(private LetterheadDocument $letterhead)
    {
    }

    public function download(DeliveryNote $note)
    {
        return $this->letterhead->download($this->letterhead->deliveryNote($note));
    }
}
