<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Products;
use App\Models\Service;
use App\Services\Numbering\DocumentNumberService;
use App\Support\DocumentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Simple mode bons de livraison (no sales order): typed by hand ("BL sans devis"), or a BL made from a
 * quote that is edited by hand ("Modifier le BL", it then stops following the quote).
 * Client, date, articles and quantities only; these BL never move stock (moves_stock = false).
 */
class SimpleDeliveryController extends Controller
{
    use HandlesDocumentLines;

    public function create()
    {
        return $this->form(new Delivery(['delivery_date' => now()]));
    }

    public function store(Request $request, DocumentNumberService $numbering)
    {
        [$data, $items] = $this->validated($request);
        if (empty($items)) {
            return back()->withErrors(['item_quantity' => 'Ajoutez au moins un article.'])->withInput();
        }

        $delivery = DB::transaction(function () use ($data, $items, $numbering) {
            $delivery = Delivery::create([
                'delivery_number' => $numbering->next('deliveries', 'delivery_number', config('numbering.delivery', 'BLV')),
                'sales_order_id' => null,
                'sales_quote_id' => null,
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'delivery_date' => $data['delivery_date'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'status' => 'delivered',
                'moves_stock' => false,
                'follows_quote' => false,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->saveLines($delivery, $items);

            return $delivery;
        });

        return redirect()->route('erp.deliveries.show', $delivery)
            ->with('success', 'Bon de livraison ' . $delivery->delivery_number . ' cree. Vous pouvez l\'imprimer.');
    }

    public function edit(Delivery $delivery)
    {
        if ($locked = $this->rejectIfLocked($delivery)) {
            return $locked;
        }

        return $this->form($delivery->load('items'));
    }

    public function update(Request $request, Delivery $delivery)
    {
        if ($locked = $this->rejectIfLocked($delivery)) {
            return $locked;
        }
        [$data, $items] = $this->validated($request);
        if (empty($items)) {
            return back()->withErrors(['item_quantity' => 'Ajoutez au moins un article.'])->withInput();
        }

        DB::transaction(function () use ($delivery, $data, $items) {
            $delivery->update([
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'delivery_date' => $data['delivery_date'],
                'shipping_address' => $data['shipping_address'] ?? null,
                // Edited by hand: the quote no longer rewrites this BL.
                'follows_quote' => false,
                'updated_by' => Auth::id(),
            ]);
            $delivery->items()->delete();
            $this->saveLines($delivery, $items);
        });

        return redirect()->route('erp.deliveries.show', $delivery)->with('success', 'Bon de livraison mis a jour.');
    }

    private function form(Delivery $delivery)
    {
        return view('erp.deliveries.simple_form', [
            'delivery' => $delivery,
            'clients' => Contact::clients()->where('status', 'active')->orderBy('company_name')->get(),
            'products' => Products::active()->orderBy('product_name')->get(),
            'services' => Service::active()->orderBy('service_name')->get(),
        ]);
    }

    /** @return array{0: array, 1: array<int, array>} validated data and the non-empty lines */
    private function validated(Request $request): array
    {
        $rules = DocumentRules::pricedLines();
        unset($rules['tva_rate'], $rules['item_price_ht'], $rules['item_price_ht.*']);
        $data = $request->validate(array_merge([
            'client_id' => DocumentRules::contact(),
            'delivery_date' => ['required', 'date'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
        ], $rules));

        $items = [];
        foreach (array_keys($data['item_quantity'] ?? []) as $i) {
            $mode = $data['item_mode'][$i] ?? 'product';
            $productId = $mode === 'product' ? ($data['item_product_id'][$i] ?? null) : null;
            $serviceId = $mode === 'service' ? ($data['item_service_id'][$i] ?? null) : null;
            $name = $mode === 'manual' ? trim((string) ($data['item_name'][$i] ?? '')) : null;
            $quantity = (float) ($data['item_quantity'][$i] ?? 0);
            if ($quantity <= 0 || (!$productId && !$serviceId && !$name)) {
                continue; // empty line
            }
            $items[] = ['product_id' => $productId ?: null, 'service_id' => $serviceId ?: null, 'item_name' => $name ?: null, 'quantity' => $quantity];
        }

        return [$data, $items];
    }

    private function saveLines(Delivery $delivery, array $items): void
    {
        foreach ($items as $item) {
            DeliveryItem::create($item + ['delivery_id' => $delivery->id, 'sales_order_item_id' => null, 'shipped_qty' => $item['quantity']]);
        }
    }

    /** Only simple BL, and not once invoiced (the invoice was built from it). */
    private function rejectIfLocked(Delivery $delivery)
    {
        if (!$delivery->isSimple()) {
            return redirect()->route('erp.deliveries.edit', $delivery);
        }
        if (ClientInvoice::issuedOrDraftFor('delivery_id', $delivery->id)->exists()) {
            return redirect()->route('erp.deliveries.show', $delivery)
                ->with('error', 'Ce bon de livraison est deja facture : il ne peut plus etre modifie.');
        }

        return null;
    }
}
