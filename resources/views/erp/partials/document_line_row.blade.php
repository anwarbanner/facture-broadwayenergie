{{-- One line of partials/document_lines. @param array $line mode, product_id, service_id, name, quantity, price --}}
@php($mode = in_array($line['mode'] ?? null, ['product', 'service', 'manual'], true) ? $line['mode'] : 'product')
<tr data-line>
    <td>
        @unless($priced)
            {{-- Link to the order line: keeps prices and remaining quantities exact. --}}
            <input type="hidden" name="item_source_id[]" value="{{ $line['source_id'] ?? '' }}">
        @endunless
        <select name="item_mode[]" class="form-control no-search" data-no-search data-line-mode aria-label="Type de ligne">
            <option value="product" @selected($mode === 'product')>Produit</option>
            <option value="service" @selected($mode === 'service')>Service</option>
            <option value="manual" @selected($mode === 'manual')>Manuel</option>
        </select>
    </td>
    <td>
        <div data-field="product" @if($mode !== 'product') hidden @endif>
            <select name="item_product_id[]" class="form-control" data-line-article="product" aria-label="Produit">
                <option value="">Rechercher un produit...</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string) ($line['product_id'] ?? '') === (string) $product->id)>{{ $product->product_name }}</option>
                @endforeach
            </select>
        </div>
        <div data-field="service" @if($mode !== 'service') hidden @endif>
            <select name="item_service_id[]" class="form-control" data-line-article="service" aria-label="Service">
                <option value="">Rechercher un service...</option>
                @foreach(($services ?? []) as $service)
                    <option value="{{ $service->id }}" @selected((string) ($line['service_id'] ?? '') === (string) $service->id)>{{ $service->service_name }}</option>
                @endforeach
            </select>
        </div>
        <div data-field="manual" @if($mode !== 'manual') hidden @endif>
            <input type="text" name="item_name[]" class="form-control" maxlength="255" value="{{ $line['name'] ?? '' }}"
                   placeholder="Designation de l'article (ex. Installation, piece speciale...)" aria-label="Designation">
        </div>
    </td>
    <td data-label="Quantite"><input type="number" name="item_quantity[]" class="form-control" step="0.001" min="0.001" value="{{ $line['quantity'] ?? 1 }}" data-line-qty aria-label="Quantite"></td>
    @if($priced)
        <td data-label="Prix unitaire HT"><input type="number" name="item_price_ht[]" class="form-control" step="0.01" min="0" value="{{ $line['price'] ?? '' }}" placeholder="0,00" data-line-price aria-label="Prix unitaire HT"></td>
        <td class="text-end fw-semibold" data-label="Total HT" data-line-total>0,00</td>
    @elseif(empty($quantities['single']))
        <td data-label="{{ $quantities['label'] }}"><input type="number" name="{{ $quantities['field'] }}[]" class="form-control" step="0.001" min="{{ $quantities['min'] ?? 0 }}" value="{{ $line['done'] ?? '' }}" data-line-done aria-label="{{ $quantities['label'] }}"></td>
    @endif
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-line aria-label="Supprimer la ligne" title="Supprimer la ligne">
            <i class="fe fe-trash-2" aria-hidden="true"></i>
        </button>
    </td>
</tr>
