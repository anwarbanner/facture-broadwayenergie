<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Support\Branding;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    use RedirectsToList;

    public function __construct()
    {
        $this->authorizeResource(Contact::class, 'contact');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only clients and suppliers are managed (the lead / prospect types were removed from the forms).
        $contacts = Contact::query()
            ->whereIn('type', ['client', 'supplier'])
            ->search(request('q'))
            ->when(in_array(request('type'), ['client', 'supplier'], true), fn ($q) => $q->where('type', request('type')))
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.contacts.index', compact('contacts'));
    }

    public function clients()
    {
        $request = request()->merge(['type' => 'client']);

        return $this->index();
    }

    public function suppliers()
    {
        $request = request()->merge(['type' => 'supplier']);

        return $this->index();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // "Nouveau fournisseur" (simple mode) opens the same form with the supplier type.
        $contact = new Contact(['type' => request('type') === 'supplier' ? 'supplier' : 'client']);

        return view('erp.contacts.create', compact('contact'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRequest $request)
    {
        $data = $request->validated();
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $contact = Contact::create($data);

        return $this->createdRedirect('contacts', $contact, 'Contact cree.', Branding::simpleMode() ? ['type' => $contact->type] : []);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        $contact->load('addresses');

        return view('erp.contacts.show', compact('contact'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        return view('erp.contacts.edit', compact('contact'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $data = $request->validated();
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['updated_by'] = Auth::id();

        $contact->update($data);

        return redirect()->route('erp.contacts.index', Branding::simpleMode() ? ['type' => $contact->type] : [])->with('success', 'Contact mis a jour.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect()->route('erp.contacts.index', Branding::simpleMode() ? ['type' => $contact->type] : [])->with('success', 'Contact supprime.');
    }
}
