@extends('layouts.master')

@section('title', 'Details du contact')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $contact->company_name }}</h4>
        <a href="{{ route('erp.contacts.edit', $contact) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Fiche contact</h6>
            <span class="erp-doc-badge">{{ \App\Support\Format::label($contact->type) }}</span>
        </div>
        <div class="erp-doc-body">
            <div class="erp-doc-lines">
                <h6>Informations generales</h6>
                <div class="erp-kv-grid">
                    <div class="erp-kv-item"><span class="label">Type</span><span class="value">{{ \App\Support\Format::label($contact->type) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($contact->status) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Personne de contact</span><span class="value">{{ $contact->contact_person_name ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Email</span><span class="value">{{ $contact->email ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Telephone</span><span class="value">{{ $contact->phone ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Mobile</span><span class="value">{{ $contact->mobile ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Adresse</span><span class="value">{{ $contact->address ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Ville / Pays</span><span class="value">{{ trim(($contact->city ?: '-') . ' / ' . ($contact->country ?: '-')) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Identifiant fiscal</span><span class="value">{{ $contact->tax_id ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Plafond de credit</span><span class="value">{{ \App\Support\Format::money($contact->credit_limit) }}</span></div>
                </div>
            </div>

            <div class="erp-doc-lines mt-3">
                <h6>Adresses complementaires</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contact->addresses as $address)
                                <tr>
                                    <td>{{ ucfirst($address->address_type) }}</td>
                                    <td>{{ $address->address_details }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center">Aucune adresse supplementaire.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

