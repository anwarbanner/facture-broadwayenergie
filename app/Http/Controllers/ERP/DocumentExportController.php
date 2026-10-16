<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\SupplierInvoice;
use App\Services\Export\DocumentSpreadsheet;
use App\Services\Pdf\LetterheadDocument;
use Illuminate\Http\Request;

/** "Excel" button next to "PDF" on every document page: same content as the PDF, as an .xlsx file. */
class DocumentExportController extends Controller
{
    /** route segment => [model, LetterheadDocument method] */
    public const DOCUMENTS = [
        'sales-quotes' => [SalesQuote::class, 'salesQuote'],
        'sales-orders' => [SalesOrder::class, 'salesOrder'],
        'deliveries' => [Delivery::class, 'delivery'],
        'client-invoices' => [ClientInvoice::class, 'clientInvoice'],
        'purchase-quotes' => [PurchaseQuote::class, 'purchaseQuote'],
        'purchase-orders' => [PurchaseOrder::class, 'purchaseOrder'],
        'delivery-notes' => [DeliveryNote::class, 'deliveryNote'],
        'supplier-invoices' => [SupplierInvoice::class, 'supplierInvoice'],
    ];

    public function excel(Request $request, LetterheadDocument $letterhead, DocumentSpreadsheet $spreadsheet)
    {
        // Route values are read by name: the document type is a route default, the id comes from the URL.
        $type = (string) $request->route('type');
        abort_unless(isset(self::DOCUMENTS[$type]), 404);
        [$model, $method] = self::DOCUMENTS[$type];

        return $spreadsheet->download($letterhead->{$method}($model::findOrFail((int) $request->route('id'))));
    }
}
