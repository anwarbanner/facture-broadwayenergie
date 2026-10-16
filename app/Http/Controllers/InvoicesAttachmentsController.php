<?php

namespace App\Http\Controllers;

use App\Models\invoices;
use App\Models\invoices_attachments;
use App\Support\InvoiceAttachmentStorage;
use Illuminate\Http\Request;
use Auth;

class InvoicesAttachmentsController extends Controller
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
        $this->validate($request, [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'file_name' => 'required|' . InvoiceAttachmentStorage::VALIDATION_RULE,
        ], [
            'file_name.mimes' => 'La piece jointe doit etre au format pdf, jpeg, png ou jpg.',
        ]);

        // The folder comes from the invoice record, not from a hidden form field.
        $invoice = invoices::findOrFail($request->invoice_id);
        $file_name = InvoiceAttachmentStorage::store($request->file('file_name'), (string) $invoice->invoice_number);

        $attachments =  new invoices_attachments();
        $attachments->file_name = $file_name;
        $attachments->invoice_number = $invoice->invoice_number;
        $attachments->invoice_id = $invoice->id;
        $attachments->Created_by = Auth::user()->name;
        $attachments->save();

        session()->flash('Add', 'Piece jointe ajoutee.');
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\invoices_attachments  $invoices_attachments
     * @return \Illuminate\Http\Response
     */
    public function show(invoices_attachments $invoices_attachments)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\invoices_attachments  $invoices_attachments
     * @return \Illuminate\Http\Response
     */
    public function edit(invoices_attachments $invoices_attachments)
    {
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\invoices_attachments  $invoices_attachments
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, invoices_attachments $invoices_attachments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\invoices_attachments  $invoices_attachments
     * @return \Illuminate\Http\Response
     */
    public function destroy(invoices_attachments $invoices_attachments)
    {
        //
    }
}
