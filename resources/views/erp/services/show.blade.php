@extends('layouts.master')

@section('title', 'Details du service')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $service->service_name }}</h4>
        <a href="{{ route('erp.services.edit', $service) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Fiche service</h6>
            <span class="erp-doc-badge">Service</span>
        </div>
        <div class="erp-doc-body">
            <div class="erp-doc-lines">
                <h6>Informations generales</h6>
                <div class="erp-kv-grid">
                    <div class="erp-kv-item"><span class="label">Nom du service</span><span class="value">{{ $service->service_name }}</span></div>
                    <div class="erp-kv-item"><span class="label">Nombre d'heures</span><span class="value">{{ \App\Support\Format::money($service->hours_count) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Prix du service HT</span><span class="value">{{ \App\Support\Format::money($service->service_price) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($service->status) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Description</span><span class="value">{{ $service->description ?: '-' }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
