<?php

namespace App\Support;

/**
 * Invoice lifecycle values shared by client and supplier invoices.
 * (Kept out of the HasInvoiceLifecycle trait: trait constants need PHP 8.2, composer allows 8.1.)
 */
final class InvoiceLifecycle
{
    public const STATE_DRAFT = 'draft';
    public const STATE_VALIDATED = 'validated';
    public const STATE_CANCELLED = 'cancelled';

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDIT_NOTE = 'credit_note';
}
