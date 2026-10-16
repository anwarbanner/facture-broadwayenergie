{{--
    Lifecycle header for a client or supplier invoice page.
    Params: $invoice (ClientInvoice|SupplierInvoice), $routePrefix ('erp.client-invoices' | 'erp.supplier-invoices')
--}}

<div class="erp-page-head">
    <div>
        <h4 class="mb-1">{{ $invoice->documentLabel() }} {{ $invoice->displayNumber() }}</h4>
        <span class="badge {{ $invoice->isDraft() ? 'badge-secondary bg-secondary' : ($invoice->isCancelled() ? 'badge-danger bg-danger' : 'badge-success bg-success') }}">{{ $invoice->stateLabel() }}</span>
        @unless($invoice->isDraft())
            <span class="badge badge-info bg-info">{{ $invoice->statusLabel() }}</span>
        @endunless
        @if($invoice->isCreditNote() && $invoice->creditedInvoice)
            <small class="ml-2 ms-2">Avoir sur la facture
                <a href="{{ route($routePrefix . '.show', $invoice->creditedInvoice) }}">{{ $invoice->creditedInvoice->invoice_number }}</a>@if($invoice->credit_reason) - {{ $invoice->credit_reason }}@endif</small>
        @endif
        @if(!$invoice->isCreditNote() && $invoice->creditNotes->isNotEmpty())
            <small class="ml-2 ms-2">{{ $invoice->isCancelled() ? 'Entierement creditee par' : 'Avoirs' }} :
                @foreach($invoice->creditNotes as $note)
                    <a href="{{ route($routePrefix . '.show', $note) }}">{{ $note->invoice_number }}</a> ({{ \App\Support\Format::money($note->total_ttc) }} TTC){{ $loop->last ? '' : ',' }}
                @endforeach
            </small>
        @endif
    </div>

    <div class="erp-page-actions">
        @if($invoice->isDraft())
            <a href="{{ route($routePrefix . '.edit', $invoice) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @can('manage-finance')
            <form method="POST" action="{{ route($routePrefix . '.validate', $invoice) }}"
                  onsubmit="return confirm('Valider ce brouillon ? Il recevra son numero definitif et ne pourra plus etre modifie.')">
                @csrf
                <button class="btn btn-success"><i class="fe fe-check-circle" aria-hidden="true"></i> Valider la facture</button>
            </form>
            @else
            <span class="text-muted align-self-center">Validation par un administrateur</span>
            @endcan
            <form method="POST" action="{{ route($routePrefix . '.destroy', $invoice) }}" onsubmit="return confirm('Supprimer ce brouillon ?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer le brouillon</button>
            </form>
        @elseif($invoice->isValidated() && !$invoice->isCreditNote() && auth()->user()?->can('manage-finance'))
            <a href="#erp-credit-note" class="btn btn-outline-primary" data-no-loader
               onclick="var p=document.getElementById('erp-credit-note'); p.open=true; p.scrollIntoView({behavior:'smooth', block:'start'}); return false;">
                <i class="fe fe-corner-up-left" aria-hidden="true"></i> Faire un avoir</a>
            <form method="POST" action="{{ route($routePrefix . '.credit-note', $invoice) }}"
                  onsubmit="return confirm('Creer un avoir sur tout ce qui reste de la facture ? Elle sera annulee definitivement.')">
                @csrf
                <input type="hidden" name="mode" value="full">
                <button class="btn btn-outline-danger"><i class="fe fe-rotate-ccw" aria-hidden="true"></i> Annuler par un avoir</button>
            </form>
        @endif
        @if(Route::has($routePrefix . '.print'))
            <a href="{{ route($routePrefix . '.print', $invoice) }}" target="_blank" rel="noopener" class="btn btn-primary" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
        @endif
        <a href="{{ route($routePrefix . '.pdf', $invoice) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
        <a href="{{ route($routePrefix . '.excel', ['id' => $invoice->id]) }}" class="btn btn-light" title="Telecharger au format Excel"><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
    </div>
</div>

@if($invoice->isValidated() && !$invoice->isCreditNote() && auth()->user()?->can('manage-finance'))
    @php
        $lifecycle = app(\App\Services\Invoicing\InvoiceLifecycleService::class);
        $creditable = $lifecycle->creditableLines($invoice);
        $remainingAmounts = $lifecycle->remainingAmounts($invoice);
        $creditRate = (float) ($invoice->tva_rate ?? \App\Support\Branding::defaultVatRate());
    @endphp
    <details id="erp-credit-note" class="card mb-3 erp-credit-note" @if($errors->hasAny(['quantities', 'discount_amount']) || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'quantities.'))) open @endif>
        <summary class="card-body py-3"><strong>Faire un avoir</strong>
            <small class="text-muted">- retour de marchandise, erreur sur une ligne ou remise ; reste facture : {{ \App\Support\Format::money($remainingAmounts['ht']) }} HT</small>
        </summary>
        <form method="POST" action="{{ route($routePrefix . '.credit-note', $invoice) }}" class="card-body pt-0" data-credit-form data-rate="{{ $creditRate }}"
              onsubmit="return confirm('Creer cet avoir ? Il sera numerote et ne pourra plus etre modifie.')">
            @csrf
            <input type="hidden" name="mode" value="partial">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead><tr><th>Article</th><th class="text-end">Facture</th><th class="text-end">Deja credite</th><th style="width:150px">A crediter</th><th class="text-end">Prix HT</th><th class="text-end">Total HT</th></tr></thead>
                    <tbody>
                    @foreach($creditable as $line)
                        <tr>
                            <td>{{ \App\Support\Format::designation($line['item']) }}</td>
                            <td class="erp-num">{{ \App\Support\Format::qty($line['item']->quantity) }}</td>
                            <td class="erp-num">{{ $line['credited'] > 0 ? \App\Support\Format::qty($line['credited']) : '-' }}</td>
                            <td>
                                @if($line['remaining'] > 0)
                                    <input type="number" name="quantities[{{ $line['item']->id }}]" class="form-control form-control-sm" min="0" max="{{ $line['remaining'] }}" step="0.001"
                                           value="{{ old('quantities.' . $line['item']->id, 0) }}" data-credit-qty data-price="{{ (float) $line['item']->price_ht }}"
                                           aria-label="Quantite a crediter : {{ \App\Support\Format::designation($line['item']) }}">
                                @else
                                    <span class="text-muted small">Deja credite</span>
                                @endif
                            </td>
                            <td class="erp-num">{{ \App\Support\Format::money($line['item']->price_ht) }}</td>
                            <td class="erp-num" data-credit-line>0,00</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="credit-discount-label">Remise (ligne libre)</label>
                    <input type="text" id="credit-discount-label" name="discount_label" class="form-control" maxlength="255" value="{{ old('discount_label') }}" placeholder="Remise commerciale">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="credit-discount-amount">Montant HT</label>
                    <input type="number" id="credit-discount-amount" name="discount_amount" class="form-control" min="0" step="0.01" value="{{ old('discount_amount') }}" placeholder="0,00" data-credit-discount>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="credit-reason">Motif</label>
                    <input type="text" id="credit-reason" name="reason" class="form-control" maxlength="255" list="credit-reasons" value="{{ old('reason') }}" placeholder="Retour de marchandise...">
                    <datalist id="credit-reasons">
                        <option value="Retour de marchandise"><option value="Erreur de facturation"><option value="Remise commerciale"><option value="Geste commercial"><option value="Article defectueux">
                    </datalist>
                </div>
                <div class="col-md-3">
                    <div class="erp-credit-total">
                        <div><span>Total HT</span> <strong data-credit-ht>0,00</strong></div>
                        <div><span>TVA {{ \App\Support\Format::rate($creditRate) }}</span> <strong data-credit-tva>0,00</strong></div>
                        <div><span>Avoir TTC</span> <strong data-credit-ttc>0,00</strong></div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-2">L'avoir n'a pas d'effet sur le stock : un retour physique de marchandise se saisit sur la livraison / la reception.
                Si tout ce qui reste est credite, la facture est annulee.</p>
            <button type="submit" class="btn btn-primary"><i class="fe fe-check" aria-hidden="true"></i> Creer l'avoir</button>
        </form>
    </details>
    <script>
    (function () {
        var form = document.querySelector('[data-credit-form]');
        if (!form) return;
        var rate = parseFloat(form.getAttribute('data-rate')) || 0;
        var fmt = function (v) { return (Math.round(v * 100) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        function recalc() {
            var ht = 0;
            form.querySelectorAll('tbody tr').forEach(function (row) {
                var input = row.querySelector('[data-credit-qty]');
                var line = input ? Math.round((parseFloat(input.value) || 0) * parseFloat(input.getAttribute('data-price')) * 100) / 100 : 0;
                row.querySelector('[data-credit-line]').textContent = fmt(line);
                ht += line;
            });
            ht += parseFloat(form.querySelector('[data-credit-discount]').value) || 0;
            ht = Math.round(ht * 100) / 100;
            var tva = Math.round(ht * rate) / 100;
            form.querySelector('[data-credit-ht]').textContent = fmt(ht);
            form.querySelector('[data-credit-tva]').textContent = fmt(tva);
            form.querySelector('[data-credit-ttc]').textContent = fmt(ht + tva);
        }
        form.addEventListener('input', recalc);
        recalc();
    })();
    </script>
@endif

@php($supplierSide = $routePrefix === 'erp.supplier-invoices')
@if(\App\Support\Branding::simpleMode() && !$invoice->isCreditNote()
    && $invoice->isValidated() && (float) $invoice->balance_remaining > 0 && auth()->user()?->can('manage-finance'))
    {{-- Simple mode: pay what is left in one click (same payment route, today, full balance). --}}
    <div class="erp-cashin" id="encaisser">
        <div class="erp-cashin-title">
            <strong>{{ $supplierSide ? 'Payer cette facture fournisseur' : 'Encaisser cette facture' }}</strong>
            <span>Reste a payer {{ \App\Support\Format::money($invoice->balance_remaining) }} {{ $invoice->currency }} - cliquez sur le mode de paiement :</span>
        </div>
        <div class="erp-cashin-buttons">
            @foreach(['cash' => ['Especes', 'fe-dollar-sign'], 'check' => ['Cheque', 'fe-edit-3'], 'transfer' => ['Virement', 'fe-repeat']] as $method => [$label, $icon])
                <form method="POST" action="{{ route($routePrefix . '.payments', $invoice) }}"
                      onsubmit="return confirm('Encaisser {{ \App\Support\Format::money($invoice->balance_remaining) }} {{ $invoice->currency }} en {{ strtolower($label) }} ?')">
                    @csrf
                    <input type="hidden" name="amount" value="{{ $invoice->balance_remaining }}">
                    <input type="hidden" name="payment_date" value="{{ now()->toDateString() }}">
                    <input type="hidden" name="method" value="{{ $method }}">
                    <input type="hidden" name="reference" value="{{ $invoice->quote?->quote_number ?? '' }}">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fe {{ $icon }}" aria-hidden="true"></i> {{ $label }}</button>
                </form>
            @endforeach
        </div>
    </div>
@endif

@if($invoice->isValidated() && (float) $invoice->balance_remaining > 0 && auth()->user()?->can('manage-finance'))
    {{-- Invoice: record a payment. Credit note: record the refund of what the client/supplier had already paid.
         Simple mode (client invoice): the one-click box above pays everything; this detailed form is folded. --}}
    @php($folded = \App\Support\Branding::simpleMode() && !$invoice->isCreditNote())
    @if($folded)
    <details class="card mb-3" @if($errors->hasAny(['amount', 'payment_date', 'method'])) open @endif><summary class="card-body py-3"><strong>Paiement partiel ou autre date</strong></summary><div class="card-body pt-0">
    @else
    <div class="card mb-3"><div class="card-body">
        <h6 class="mb-2">{{ $invoice->isCreditNote() ? 'Enregistrer le remboursement' : 'Enregistrer un paiement' }}</h6>
    @endif
        <form method="POST" action="{{ route($routePrefix . '.payments', $invoice) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Montant</label>
                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_remaining }}" name="amount" class="form-control"
                       value="{{ old('amount', $invoice->balance_remaining) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mode</label>
                <select name="method" class="form-control" required>
                    @foreach(\App\Models\Payment::METHODS as $value => $label)
                        <option value="{{ $value }}" {{ old('method', 'transfer') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reference</label>
                <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="N° cheque, virement...">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">{{ $invoice->isCreditNote() ? 'Enregistrer le remboursement' : 'Ajouter le paiement' }}</button>
            </div>
        </form>
        <small class="text-muted">Solde restant : {{ \App\Support\Format::money($invoice->balance_remaining) }}</small>
    @if($folded)
    </div></details>
    @else
    </div></div>
    @endif
@endif

@if($invoice->relationLoaded('paymentAllocations') && $invoice->paymentAllocations->isNotEmpty())
    <div class="card mb-3"><div class="card-body table-responsive">
        <h6 class="mb-2">{{ $invoice->isCreditNote() ? 'Remboursements' : 'Paiements' }}</h6>
        <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>Mode</th><th>Reference</th><th class="text-end">Montant affecte</th><th>Etat</th></tr></thead>
            <tbody>
            @foreach($invoice->paymentAllocations as $allocation)
                <tr class="{{ $allocation->payment->isVoided() ? 'text-muted' : '' }}">
                    <td>{{ $allocation->payment->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $allocation->payment->methodLabel() }}</td>
                    <td>{{ $allocation->payment->reference ?: '-' }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($allocation->amount) }}</td>
                    <td>
                        @if($allocation->payment->isVoided())
                            Annule <small>({{ $allocation->payment->void_reason }})</small>
                        @else
                            @can('manage-finance')
                                <a href="{{ route('erp.payments.index', ['contact_id' => $allocation->payment->contact_id]) }}">Actif</a>
                            @else
                                Actif
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
@endif
