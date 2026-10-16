{{--
    Lines of a priced document (quotes, orders, invoices; sales and purchases).
    Each line is a product, a service or a free-text ("manual") article. VAT is one rate for the whole
    document, applied to the total HT (HandlesDocumentLines::pricedLines computes the same totals server side).
    Behaviour: public/assets/js/erp-documents.js

    @param \Illuminate\Database\Eloquent\Model $document quote / order / invoice (new or existing, items loaded)
    @param iterable $products, $services
    @param string $side 'sales' | 'purchase' (which product price pre-fills the line)
    @param array|null $quantities  bon de livraison / de reception: no prices, an extra "done" quantity per line
                                   ['field' => 'item_shipped_qty', 'attribute' => 'shipped_qty', 'label' => 'Qt expediee',
                                    'source' => 'sales_order_item_id', 'min' => 0.001]
                                   'single' => true: one quantity column only (simple mode BL)
--}}
@php
    $priceColumn = ($side ?? 'sales') === 'purchase' ? 'cost_price_ht' : 'sale_price_ht';
    $quantities = $quantities ?? null;
    $priced = $quantities === null;

    if (old('item_quantity') !== null) {
        $lines = collect(old('item_quantity'))->keys()->map(fn ($i) => [
            'mode' => old("item_mode.$i", 'product'),
            'product_id' => old("item_product_id.$i"),
            'service_id' => old("item_service_id.$i"),
            'name' => old("item_name.$i"),
            'quantity' => old("item_quantity.$i"),
            'price' => old("item_price_ht.$i"),
            'done' => $priced ? null : old($quantities['field'] . ".$i"),
            'source_id' => old("item_source_id.$i"),
        ]);
    } else {
        $lines = collect($document->items ?? [])->map(fn ($item) => [
            'mode' => $item->product_id ? 'product' : ($item->service_id ? 'service' : 'manual'),
            'product_id' => $item->product_id,
            'service_id' => $item->service_id,
            'name' => $item->item_name,
            'quantity' => (float) $item->quantity,
            'price' => (float) ($item->price_ht ?? 0),
            'done' => $priced ? null : (float) $item->{$quantities['attribute']},
            'source_id' => $priced ? null : $item->{$quantities['source']},
        ]);
    }
    if ($lines->isEmpty()) {
        $lines = collect([$blankLine = ['mode' => 'product', 'product_id' => null, 'service_id' => null, 'name' => null, 'quantity' => 1, 'price' => null, 'done' => 1, 'source_id' => null]]);
    }
    $blankLine = ['mode' => 'product', 'product_id' => null, 'service_id' => null, 'name' => null, 'quantity' => 1, 'price' => null, 'done' => 1, 'source_id' => null];

    $tvaRate = old('tva_rate', $document->tva_rate ?? \App\Support\Branding::defaultVatRate());
    $currency = old('currency', $document->currency ?? \App\Support\Branding::defaultCurrency());
    $currencies = collect([\App\Support\Branding::defaultCurrency(), 'EUR', 'USD', $currency])->filter()->map(fn ($c) => strtoupper($c))->unique();
@endphp

<div class="erp-doc-lines" data-doc-lines>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Articles</h6>
        <small class="text-muted">Produit ou service du catalogue, ou <strong>Manuel</strong> pour saisir librement un article.</small>
    </div>


    <div class="table-responsive mb-2">
        <table class="table table-sm erp-lines-table align-middle">
            <thead>
                <tr>
                    <th style="width:13%">Type</th>
                    <th>Article</th>
                    @if($priced)
                        <th style="width:11%">Quantite</th>
                        <th style="width:15%">Prix unitaire HT</th>
                        <th style="width:14%" class="text-end">Total HT</th>
                    @elseif(!empty($quantities['single']))
                        <th style="width:15%">{{ $quantities['label'] }}</th>
                    @else
                        <th style="width:15%">Qt commandee</th>
                        <th style="width:15%">{{ $quantities['label'] }}</th>
                    @endif
                    <th style="width:48px"></th>
                </tr>
            </thead>
            <tbody data-lines-body>
                @foreach($lines as $line)
                    @include('erp.partials.document_line_row', ['line' => $line])
                @endforeach
            </tbody>
        </table>
    </div>

    <template data-line-template>
        @include('erp.partials.document_line_row', ['line' => $blankLine])
    </template>

    <div class="row g-3 align-items-start">
        <div class="col-md-6">
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-line>
                <i class="fe fe-plus" aria-hidden="true"></i> Ajouter une ligne
            </button>
            {{-- Fill the lines from an Excel file (ImportController@lines); nothing is saved until "Enregistrer". --}}
            <label class="btn btn-sm btn-outline-secondary mb-0 ms-1" title="Article, Designation, Quantite, Prix unitaire HT">
                <i class="fe fe-upload" aria-hidden="true"></i> Importer des lignes (Excel)
                <input type="file" accept=".xlsx,.xls,.csv,.ods" hidden data-import-lines
                       data-url="{{ route('erp.imports.lines') }}" data-side="{{ $side ?? 'sales' }}" data-token="{{ csrf_token() }}">
            </label>
            <a href="{{ route('erp.imports.lines-template') }}" class="small ms-1" data-no-loader>modele</a>
            <div class="small mt-2" data-import-lines-result role="status" aria-live="polite"></div>
        </div>
        @if($priced)
        <div class="col-md-6">
            <div class="erp-totals-box">
                <div class="erp-totals-row">
                    <span>Total HT</span>
                    <strong data-total-ht>0,00</strong>
                </div>
                <div class="erp-totals-row">
                    <span class="d-flex align-items-center gap-2">
                        <label for="tva_rate" class="mb-0">TVA</label>
                        <input type="number" id="tva_rate" name="tva_rate" step="0.01" min="0" max="100"
                               class="form-control form-control-sm erp-tva-input @error('tva_rate') is-invalid @enderror"
                               value="{{ rtrim(rtrim(number_format((float) $tvaRate, 2, '.', ''), '0'), '.') }}" required data-tva-rate>
                        <span>%</span>
                    </span>
                    <strong data-total-tva>0,00</strong>
                </div>
                <div class="erp-totals-row erp-totals-grand">
                    <span class="d-flex align-items-center gap-2">
                        Total TTC
                        <select name="currency" class="form-control form-control-sm erp-currency-input no-search" data-no-search aria-label="Devise">
                            @foreach($currencies as $code)
                                <option value="{{ $code }}" @selected(strtoupper($currency) === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </span>
                    <strong data-total-ttc>0,00</strong>
                </div>
                @error('tva_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        @endif
    </div>
    <script type="application/json" data-catalog>
        {!! json_encode([
            'product' => collect($products)->mapWithKeys(fn ($p) => [$p->id => (float) $p->{$priceColumn}]),
            'service' => collect($services ?? [])->mapWithKeys(fn ($s) => [$s->id => (float) $s->service_price]),
        ]) !!}
    </script>
</div>
