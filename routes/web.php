<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\InvoicesDetailsController;
use App\Http\Controllers\InvoicesAttachmentsController;

use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;


use App\Http\Controllers\InvoiceReportController;
use App\Http\Controllers\CustomerReportController;


use App\Http\Controllers\HomeController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ERP\ProductController as ErpProductController;
use App\Http\Controllers\ERP\ServiceController as ErpServiceController;
use App\Http\Controllers\ERP\CategoryController as ErpCategoryController;
use App\Http\Controllers\ERP\ContactController as ErpContactController;
use App\Http\Controllers\ERP\SalesQuoteController as ErpSalesQuoteController;
use App\Http\Controllers\ERP\SalesOrderController as ErpSalesOrderController;
use App\Http\Controllers\ERP\DeliveryController as ErpDeliveryController;
use App\Http\Controllers\ERP\ClientInvoiceController as ErpClientInvoiceController;
use App\Http\Controllers\ERP\ClientStatementController as ErpClientStatementController;
use App\Http\Controllers\ERP\StockMovementController as ErpStockMovementController;
use App\Http\Controllers\ERP\PurchaseQuoteController as ErpPurchaseQuoteController;
use App\Http\Controllers\ERP\PurchaseOrderController as ErpPurchaseOrderController;
use App\Http\Controllers\ERP\DeliveryNoteController as ErpDeliveryNoteController;
use App\Http\Controllers\ERP\SupplierInvoiceController as ErpSupplierInvoiceController;
use App\Http\Controllers\ERP\SupplierStatementController as ErpSupplierStatementController;
use App\Http\Controllers\ERP\PaymentController as ErpPaymentController;
use App\Http\Controllers\ERP\AccountingController as ErpAccountingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


/*Route::get('/', function () {
    return view('auth.login');
});*/

require __DIR__.'/auth.php';

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('root');

Route::group(['middleware' => ['auth', 'verified_active_user']], function()
{
   // Old invoicing module: read-only (see App\Http\Middleware\LegacyReadOnly).
   Route::resource('invoices', InvoicesController::class)->middleware('legacy.readonly');
   Route::resource('products', ProductsController::class)->middleware('legacy.readonly');
   Route::middleware('admin')->group(function () {
       Route::resource('roles', RoleController::class);
       Route::resource('users', UserController::class);
       Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
       Route::get('/settings/signature', [\App\Http\Controllers\SignatureController::class, 'edit'])->name('settings.signature.edit');
       Route::put('/settings/signature', [\App\Http\Controllers\SignatureController::class, 'update'])->name('settings.signature.update');
       Route::delete('/settings/signature', [\App\Http\Controllers\SignatureController::class, 'destroy'])->name('settings.signature.destroy');
   });
    
    
   Route::get('/edit_invoice/{id}', [InvoicesController::class,'edit'])->middleware('legacy.readonly');
   Route::get('/show_status/{id}', [InvoicesController::class,'show'])->name('Status_show');
   Route::post('/Status_Update/{id}', [InvoicesController::class,'statusUpdate'])->name('Status_Update')->middleware('legacy.readonly');
   
   Route::get('/paid_invoice', [InvoicesController::class,'paid'])->name('paid_invoice');
   Route::get('/unpaid_invoice', [InvoicesController::class,'unpaid'])->name('unpaid_invoice');
   Route::get('/partial_invoice', [InvoicesController::class,'partial'])->name('partial_invoice');
   Route::get('/trashed_invoice', [InvoicesController::class,'trash'])->name('trashed_invoice');
   Route::patch('/cancel_trash', [InvoicesController::class,'cancel_trash'])->name('cancel_trash')->middleware('legacy.readonly');
   Route::delete('/destroyTrash', [InvoicesController::class,'destroyTrash'])->name('destroy-trash')->middleware('legacy.readonly');
   Route::get('/print_invoice/{id}', [InvoicesController::class,'printInvoice'])->name('print-invoice');
   Route::get('/export_invoices', [InvoicesController::class, 'export']);


   Route::get('/InvoicesDetails/{id}', [InvoicesDetailsController::class,'invoiceDetails']);
   Route::get('/View_file/{invoice_number}/{file_name}', [InvoicesDetailsController::class,'openInvoiceFile']);
   Route::get('/download/{invoice_number}/{file_name}', [InvoicesDetailsController::class,'download']);
   Route::post('/delete_file', [InvoicesDetailsController::class,'destroy'])->name('delete_file')->middleware('legacy.readonly');
   
   Route::post('/InvoiceAttachments', [InvoicesAttachmentsController ::class,'store'])->name('attach_file')->middleware('legacy.readonly');
   
  
   
   /// Reports of the invoices
   
   Route::get('/invoices-reports',[InvoiceReportController::class, 'index'])->name('invoices-reports');
   Route::post('/invoices-reports-search',[InvoiceReportController::class, 'search'])->name('invoices-reports-search');
     
   Route::get('/customers-reports',[CustomerReportController::class, 'index'])->name('customers-reports');
   Route::post('/customers-reports-search',[CustomerReportController::class, 'search'])->name('customers-reports-search');
    
   
  Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
  
  Route::get('/markAsRead', [InvoicesController::class, 'markAsRead'])->name('markAsRead');

    Route::prefix('erp')->name('erp.')->group(function () {
          Route::get('products-export', [ErpProductController::class, 'export'])->name('products.export');
                        Route::get('sales-quotes/{sales_quote}/pdf', [ErpSalesQuoteController::class, 'downloadPdf'])->name('sales-quotes.pdf');
                        Route::get('sales-orders/{sales_order}/pdf', [ErpSalesOrderController::class, 'downloadPdf'])->name('sales-orders.pdf');
                        Route::get('deliveries/{delivery}/pdf', [ErpDeliveryController::class, 'downloadPdf'])->name('deliveries.pdf');
            Route::get('client-invoices/{client_invoice}/pdf', [ErpClientInvoiceController::class, 'downloadPdf'])->name('client-invoices.pdf');
            Route::get('sales-quotes/{sales_quote}/print', [ErpSalesQuoteController::class, 'printPdf'])->name('sales-quotes.print');
            Route::get('client-invoices/{client_invoice}/print', [ErpClientInvoiceController::class, 'printPdf'])->name('client-invoices.print');
            Route::get('deliveries/{delivery}/print', [ErpDeliveryController::class, 'printPdf'])->name('deliveries.print');
            Route::get('supplier-invoices/{supplier_invoice}/print', [ErpSupplierInvoiceController::class, 'printPdf'])->name('supplier-invoices.print');
            Route::get('purchase-quotes/{purchase_quote}/print', [ErpPurchaseQuoteController::class, 'printPdf'])->name('purchase-quotes.print');
            Route::get('bons-de-livraison/creer', [\App\Http\Controllers\ERP\SimpleDeliveryController::class, 'create'])->name('deliveries.simple-create');
            Route::post('bons-de-livraison', [\App\Http\Controllers\ERP\SimpleDeliveryController::class, 'store'])->name('deliveries.simple-store');
            Route::get('bons-de-livraison/{delivery}/modifier', [\App\Http\Controllers\ERP\SimpleDeliveryController::class, 'edit'])->name('deliveries.simple-edit');
            Route::put('bons-de-livraison/{delivery}', [\App\Http\Controllers\ERP\SimpleDeliveryController::class, 'update'])->name('deliveries.simple-update');
            Route::get('bons-de-livraison/nouveau', [ErpDeliveryController::class, 'chooseQuote'])->name('deliveries.choose-quote');
            Route::post('sales-quotes/{sales_quote}/delivery', [ErpSalesQuoteController::class, 'makeDelivery'])->name('sales-quotes.delivery');
            // "Exporter la liste en Excel" on the quote / invoice / BL lists (same filters as the page).
            Route::get('list-export/{type}', [\App\Http\Controllers\ERP\ListExportController::class, 'export'])
                ->whereIn('type', ['sales-quotes', 'client-invoices', 'deliveries', 'purchase-quotes', 'supplier-invoices'])->name('list-export');
                        Route::get('purchase-quotes/{purchase_quote}/pdf', [ErpPurchaseQuoteController::class, 'downloadPdf'])->name('purchase-quotes.pdf');
                        Route::get('purchase-orders/{purchase_order}/pdf', [ErpPurchaseOrderController::class, 'downloadPdf'])->name('purchase-orders.pdf');
                        Route::get('delivery-notes/{delivery_note}/pdf', [ErpDeliveryNoteController::class, 'downloadPdf'])->name('delivery-notes.pdf');
                        Route::get('supplier-invoices/{supplier_invoice}/pdf', [ErpSupplierInvoiceController::class, 'downloadPdf'])->name('supplier-invoices.pdf');
            Route::resource('products', ErpProductController::class);
            Route::resource('services', ErpServiceController::class);
            Route::resource('categories', ErpCategoryController::class);
            Route::resource('contacts', ErpContactController::class);
            Route::resource('sales-quotes', ErpSalesQuoteController::class);
            Route::resource('sales-orders', ErpSalesOrderController::class);
            Route::resource('deliveries', ErpDeliveryController::class);
            Route::resource('client-invoices', ErpClientInvoiceController::class);
                        Route::resource('purchase-quotes', ErpPurchaseQuoteController::class);
                        Route::resource('purchase-orders', ErpPurchaseOrderController::class);
                        Route::resource('delivery-notes', ErpDeliveryNoteController::class);
                        Route::resource('supplier-invoices', ErpSupplierInvoiceController::class);

                        Route::post('sales-quotes/{sales_quote}/convert-to-order', [ErpSalesQuoteController::class, 'convertToOrder'])->name('sales-quotes.convert-to-order');
                        Route::post('sales-orders/{sales_order}/convert-to-delivery', [ErpSalesOrderController::class, 'convertToDelivery'])->name('sales-orders.convert-to-delivery');
                        Route::post('deliveries/{delivery}/convert-to-invoice', [ErpDeliveryController::class, 'convertToInvoice'])->name('deliveries.convert-to-invoice');

                        Route::post('purchase-quotes/{purchase_quote}/convert-to-order', [ErpPurchaseQuoteController::class, 'convertToOrder'])->name('purchase-quotes.convert-to-order');
                        Route::post('purchase-orders/{purchase_order}/convert-to-delivery', [ErpPurchaseOrderController::class, 'convertToDelivery'])->name('purchase-orders.convert-to-delivery');
                        Route::post('delivery-notes/{delivery_note}/convert-to-invoice', [ErpDeliveryNoteController::class, 'convertToInvoice'])->name('delivery-notes.convert-to-invoice');

                Route::get('stock-movements', [ErpStockMovementController::class, 'index'])->name('stock-movements.index');

                // Excel import: lines of a document form (everyone), mass import of master data (Admin).
                Route::post('imports/lines', [\App\Http\Controllers\ERP\ImportController::class, 'lines'])->name('imports.lines');
                Route::get('imports/lines/template', [\App\Http\Controllers\ERP\ImportController::class, 'linesTemplate'])->name('imports.lines-template');
                Route::middleware('admin')->group(function () {
                    Route::get('imports', [\App\Http\Controllers\ERP\ImportController::class, 'index'])->name('imports.index');
                    Route::get('imports/{type}/template', [\App\Http\Controllers\ERP\ImportController::class, 'template'])->name('imports.template');
                    Route::post('imports/{type}/preview', [\App\Http\Controllers\ERP\ImportController::class, 'preview'])->name('imports.preview');
                    Route::post('imports/{type}/confirm', [\App\Http\Controllers\ERP\ImportController::class, 'confirm'])->name('imports.confirm');
                });

                // "Excel" button next to "PDF" on every document page (erp.sales-quotes.excel, ...).
                foreach (array_keys(\App\Http\Controllers\ERP\DocumentExportController::DOCUMENTS) as $documentType) {
                    Route::get($documentType . '/{id}/excel', [\App\Http\Controllers\ERP\DocumentExportController::class, 'excel'])
                        ->whereNumber('id')->defaults('type', $documentType)->name($documentType . '.excel');
                }

                // Finance, Admin only: validate invoices, credit notes, payments, statements.
                Route::middleware('can:manage-finance')->group(function () {
                    Route::resource('client-statements', ErpClientStatementController::class);
                    Route::resource('supplier-statements', ErpSupplierStatementController::class);
                    Route::post('purchase-quotes/{purchase_quote}/invoice', [ErpPurchaseQuoteController::class, 'makeInvoice'])->name('purchase-quotes.invoice');
                    Route::post('sales-quotes/{sales_quote}/invoice', [ErpSalesQuoteController::class, 'makeInvoice'])->name('sales-quotes.invoice');
                    Route::post('sales-quotes/{sales_quote}/cash-in', [ErpSalesQuoteController::class, 'cashIn'])->name('sales-quotes.cash-in');
                    Route::post('client-invoices/{client_invoice}/validate', [ErpClientInvoiceController::class, 'validateInvoice'])->name('client-invoices.validate');
                    Route::post('client-invoices/{client_invoice}/payments', [ErpClientInvoiceController::class, 'recordPayment'])->name('client-invoices.payments');
                    Route::post('client-invoices/{client_invoice}/credit-note', [ErpClientInvoiceController::class, 'creditNote'])->name('client-invoices.credit-note');
                    Route::post('supplier-invoices/{supplier_invoice}/validate', [ErpSupplierInvoiceController::class, 'validateInvoice'])->name('supplier-invoices.validate');
                    Route::post('supplier-invoices/{supplier_invoice}/payments', [ErpSupplierInvoiceController::class, 'recordPayment'])->name('supplier-invoices.payments');
                    Route::post('supplier-invoices/{supplier_invoice}/credit-note', [ErpSupplierInvoiceController::class, 'creditNote'])->name('supplier-invoices.credit-note');
                    Route::get('payments', [ErpPaymentController::class, 'index'])->name('payments.index');
                    Route::get('payments/create', [ErpPaymentController::class, 'create'])->name('payments.create');
                    Route::post('payments', [ErpPaymentController::class, 'store'])->name('payments.store');
                    Route::post('payments/{payment}/void', [ErpPaymentController::class, 'void'])->name('payments.void');
                    Route::get('accounting', [ErpAccountingController::class, 'index'])->name('accounting.index');
                });
    });
  
});

/// Control spatie package through the route
/*Route::group(['middleware' => ['role:super-admin']], function () {
    //
});*/


