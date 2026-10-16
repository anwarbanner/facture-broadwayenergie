<?php

namespace App\Http\Controllers;

use App\Models\invoices;
use App\Models\invoices_details;
use App\Models\invoices_attachments;

use App\Support\InvoiceAttachmentStorage;
use Illuminate\Http\Request;
class InvoicesDetailsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function invoiceDetails($id)
    {
        
        $invoice = invoices::where('id', $id)->first();
        $invoice_details = invoices_details::where('id_Invoice', $id)->get();
        $invoice_attachments = invoices_attachments::where('invoice_id', $id)->get();
        return view('invoices.details_invoice', compact('invoice', 'invoice_details','invoice_attachments'));
    }
    
    public function openInvoiceFile($invoice_number, $file_name) {
        $path = $this->attachmentPath($invoice_number, $file_name);
        return response()->file($path);
    }
    public function download($invoice_number, $file_name) {
        $path = $this->attachmentPath($invoice_number, $file_name);
        return response()->download($path);
    }

    // Only serve files that are registered in invoices_attachments, never a raw URL path.
    private function attachmentPath(string $invoiceNumber, string $fileName): string
    {
        $attachment = invoices_attachments::where('invoice_number', $invoiceNumber)
            ->where('file_name', $fileName)
            ->firstOrFail();

        $path = InvoiceAttachmentStorage::path($attachment);
        abort_unless(is_file($path), 404);

        return $path;
    }
    
    
    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\invoices_details  $invoices_details
     * @return \Illuminate\Http\Response
     */
    public function show(invoices_details $invoices_details)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\invoices_details  $invoices_details
     * @return \Illuminate\Http\Response
     */
    public function edit(invoices_details $invoices_details)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\invoices_details  $invoices_details
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, invoices_details $invoices_details)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\invoices_details  $invoices_details
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        // Path comes from the DB record, not from invoice_number/file_name in the request.
        $attachment = invoices_attachments::findOrFail($request->id_file);
        InvoiceAttachmentStorage::delete($attachment);
        $attachment->delete();
        session()->flash('delete', 'Piece jointe supprimee.');
        return back();
    }
}
