@extends('layouts.master')

@section('title', 'Details du devis client')

@section('content')
<div class="container-fluid erp-pro-page">
    @php
        $simple = \App\Support\Branding::simpleMode();
        $finance = (bool) auth()->user()?->can('manage-finance');
        // One invoice per quote: "Faire la facture" (paid later) or "Encaisser" (invoice + payment).
        $quoteInvoice = $quote->invoice;
        $isPaid = $quoteInvoice && round((float) $quoteInvoice->balance_remaining, 2) <= 0;
        $canInvoice = $finance && !$quoteInvoice && !$quote->order && $quote->status !== 'rejected' && (float) $quote->total_ttc > 0;
        $cashMethods = ['cash' => ['Especes', 'fe-dollar-sign'], 'check' => ['Cheque', 'fe-edit-3'], 'transfer' => ['Virement', 'fe-repeat']];
    @endphp
    <div class="erp-page-head">
        <h4>Devis {{ $quote->quote_number }} <small class="text-muted">- {{ $quote->client->company_name ?? '' }}</small></h4>
        <div class="erp-page-actions erp-simple-actions">
            <a href="{{ route('erp.sales-quotes.print', $quote) }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg" data-no-loader>
                <i class="fe fe-printer" aria-hidden="true"></i> Imprimer le devis</a>
            <a href="{{ route('erp.sales-quotes.excel', ['id' => $quote->id]) }}" class="btn btn-light btn-lg" title="Telecharger le devis au format Excel" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            @unless($quoteInvoice)
                <a href="{{ route('erp.sales-quotes.edit', $quote) }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @endunless
            @unless($simple)
            <a href="{{ route('erp.sales-quotes.pdf', $quote) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
            @if($quote->order)
                <a href="{{ route('erp.sales-orders.show', $quote->order) }}" class="btn btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir commande</a>
            @elseif(!in_array($quote->status, ['rejected', 'converted']))
                <form method="POST" action="{{ route('erp.sales-quotes.convert-to-order', $quote) }}">
                    @csrf
                    <button class="btn btn-light" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers commande</button>
                </form>
            @endif
            @endunless
        </div>
    </div>

    {{-- Bon de livraison: one click, printed only (stock never moves). A quote sent to an order uses the order's deliveries. --}}
    @if($quote->delivery)
        <div class="erp-cashin erp-bl-box">
            <div>
                <i class="fe fe-truck" aria-hidden="true"></i>
                <strong>Bon de livraison {{ $quote->delivery->delivery_number }}</strong>
                <span class="text-muted">- {{ $quote->delivery->follows_quote ? 'suit le devis (si vous modifiez le devis, le BL est mis a jour)' : 'modifie a la main, ne suit plus le devis' }}</span>
            </div>
            <div class="erp-cashin-buttons">
                @unless($quoteInvoice)
                    <a href="{{ route('erp.deliveries.simple-edit', $quote->delivery) }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier le BL</a>
                @endunless
                <a href="{{ route('erp.deliveries.print', $quote->delivery) }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg" data-no-loader>
                    <i class="fe fe-printer" aria-hidden="true"></i> Imprimer le BL</a>
                <a href="{{ route('erp.deliveries.excel', ['id' => $quote->delivery->id]) }}" class="btn btn-light btn-lg" data-no-loader>
                    <i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            </div>
        </div>
    @elseif(!$quote->order && $quote->items->isNotEmpty())
        <div class="erp-cashin erp-bl-box">
            <div class="erp-cashin-title">
                <strong>Bon de livraison</strong>
                <span>Les memes articles que le devis, sans les prix. Le stock ne change pas.</span>
            </div>
            <form method="POST" action="{{ route('erp.sales-quotes.delivery', $quote) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg"><i class="fe fe-truck" aria-hidden="true"></i> Faire le bon de livraison</button>
            </form>
        </div>
    @endif

    @if($quoteInvoice)
        <div class="erp-cashin {{ $isPaid ? 'erp-cashin-done' : 'erp-bl-box' }}" id="encaisser">
            <div>
                <i class="fe {{ $isPaid ? 'fe-check-circle' : 'fe-file-text' }}" aria-hidden="true"></i>
                @if($isPaid)
                    <strong>Devis encaisse</strong> - facture {{ $quoteInvoice->invoice_number }}
                    ({{ \App\Support\Format::money($quoteInvoice->total_ttc) }} {{ $quoteInvoice->currency }}, {{ \App\Support\Format::label($quoteInvoice->status) }})
                @else
                    <strong>Facture {{ $quoteInvoice->invoice_number }}</strong>
                    - reste a payer {{ \App\Support\Format::money($quoteInvoice->balance_remaining) }} {{ $quoteInvoice->currency }}
                @endif
            </div>
            <div class="erp-cashin-buttons">
                <a href="{{ route('erp.client-invoices.print', $quoteInvoice) }}" target="_blank" rel="noopener" class="btn btn-success btn-lg" data-no-loader>
                    <i class="fe fe-printer" aria-hidden="true"></i> Imprimer la facture</a>
                <a href="{{ route('erp.client-invoices.excel', ['id' => $quoteInvoice->id]) }}" class="btn btn-light btn-lg" data-no-loader>
                    <i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
                <a href="{{ route('erp.client-invoices.show', $quoteInvoice) }}" class="btn btn-light btn-lg"><i class="fe fe-external-link" aria-hidden="true"></i> Voir la facture</a>
            </div>
            @if(!$isPaid && $finance && $quoteInvoice->isValidated())
                <div class="erp-cashin-pay">
                    <span>Le client paie ? Cliquez sur le mode de paiement :</span>
                    <div class="erp-cashin-buttons">
                        @foreach($cashMethods as $method => [$label, $icon])
                            <form method="POST" action="{{ route('erp.sales-quotes.cash-in', $quote) }}"
                                  onsubmit="return confirm('Encaisser {{ \App\Support\Format::money($quoteInvoice->balance_remaining) }} {{ $quoteInvoice->currency }} en {{ strtolower($label) }} ?')">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method }}">
                                <button type="submit" class="btn btn-success btn-lg"><i class="fe {{ $icon }}" aria-hidden="true"></i> {{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @elseif($canInvoice)
        <div class="erp-cashin" id="encaisser">
            <div class="erp-cashin-title">
                <strong>Encaisser ce devis</strong>
                <span>{{ \App\Support\Format::money($quote->total_ttc) }} {{ $quote->currency }} - cliquez sur le mode de paiement :</span>
            </div>
            <div class="erp-cashin-buttons">
                @foreach($cashMethods as $method => [$label, $icon])
                    <form method="POST" action="{{ route('erp.sales-quotes.cash-in', $quote) }}"
                          onsubmit="return confirm('Encaisser {{ \App\Support\Format::money($quote->total_ttc) }} {{ $quote->currency }} en {{ strtolower($label) }} ?')">
                        @csrf
                        <input type="hidden" name="method" value="{{ $method }}">
                        <button type="submit" class="btn btn-success btn-lg"><i class="fe {{ $icon }}" aria-hidden="true"></i> {{ $label }}</button>
                    </form>
                @endforeach
            </div>
            <div class="erp-cashin-pay">
                <span>Le client paiera plus tard ?</span>
                <form method="POST" action="{{ route('erp.sales-quotes.invoice', $quote) }}"
                      onsubmit="return confirm('Faire la facture de ce devis ? Le devis ne pourra plus etre modifie.')">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-lg"><i class="fe fe-file-text" aria-hidden="true"></i> Faire la facture (payer plus tard)</button>
                </form>
            </div>
        </div>
    @endif

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Informations devis</h6>
            <span class="erp-doc-badge">Devis client</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $quote->quote_number }}</span></div>
                            <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($quote->status) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date devis</span><span class="value">{{ optional($quote->date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Validite</span><span class="value">{{ optional($quote->validity_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Client</span><span class="value">{{ $quote->client->company_name ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Devise</span><span class="value">{{ $quote->currency }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>Articles du devis</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th class="text-end">Quantite</th>
                                <th class="text-end">Prix HT</th>
                                <th class="text-end">Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quote->items as $item)
                                <tr>
                                    <td>{{ \App\Support\Format::designation($item) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->quantity) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->price_ht) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->total_ht) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center">Aucun article.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row justify-content-end mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>Totaux</h6>
                        <div class="erp-summary-row"><span>Total HT</span><strong>{{ \App\Support\Format::money($quote->total_ht) }}</strong></div>
                        <div class="erp-summary-row"><span>TVA ({{ \App\Support\Format::rate($quote->tva_rate) }})</span><strong>{{ \App\Support\Format::money($quote->total_tva) }}</strong></div>
                        <div class="erp-summary-row total"><span>Total TTC</span><strong>{{ \App\Support\Format::money($quote->total_ttc) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($simple && !$quoteInvoice)
        <form method="POST" action="{{ route('erp.sales-quotes.destroy', $quote) }}" class="mt-3 text-end" onsubmit="return confirm('Supprimer ce devis ?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer ce devis</button>
        </form>
    @endif
</div>
@endsection

