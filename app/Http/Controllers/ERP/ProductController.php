<?php

namespace App\Http\Controllers\ERP;

use App\Events\ProductStockChanged;
use App\Exports\ProductsModuleExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Products;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    use RedirectsToList;

    public function __construct(private StockService $stock)
    {
        $this->authorizeResource(Products::class, 'product');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Products::query()
            ->where('type', 'product')
            ->with(['category'])
            ->search(request('q'))
            ->when(request('category_id'), fn ($q) => $q->where('category_id', request('category_id')))
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = Category::active()->orderBy('name')->get();

        return view('erp.products.index', compact('products', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $product = new Products();
        $categories = Category::active()->orderBy('name')->get();

        return view('erp.products.create', compact('product', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $initialStock = (float) ($data['stock_quantity'] ?? 0);
        $data['stock_quantity'] = 0;
        $data['type'] = 'product';
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $product = DB::transaction(function () use ($data, $initialStock) {
            $product = Products::create($data);
            $this->stock->adjustTo($product, $initialStock, 'INITIAL_STOCK', 'Stock initial a la creation du produit');

            return $product;
        });

        return $this->createdRedirect('products', $product, 'Produit cree.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Products $product)
    {
        $product->load(['category', 'stockMovements.warehouse', 'stockMovements.user']);

        return view('erp.products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Products $product)
    {
        $categories = Category::active()->orderBy('name')->get();

        return view('erp.products.edit', compact('product', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Products $product)
    {
        $data = $request->validated();
        $targetStock = (float) $data['stock_quantity'];
        unset($data['stock_quantity']); // stock only changes through StockService
        $data['type'] = 'product';
        $data['updated_by'] = Auth::id();

        $previousAlertLevel = (float) $product->min_stock_alert_level;

        DB::transaction(function () use ($product, $data, $targetStock) {
            $product->update($data);
            $this->stock->adjustTo($product, $targetStock, 'MANUAL_ADJUSTMENT', 'Correction manuelle depuis la fiche produit');
        });

        // A new alert threshold alone does not move stock: re-check the low-stock alert explicitly.
        if ((float) $product->fresh()->min_stock_alert_level !== $previousAlertLevel) {
            event(new ProductStockChanged($product->fresh()));
        }

        return redirect()->route('erp.products.index')->with('success', 'Produit mis a jour.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Products $product)
    {
        $product->delete();

        return redirect()->route('erp.products.index')->with('success', 'Produit supprime.');
    }

    public function export()
    {
        $this->authorize('viewAny', Products::class);

        return Excel::download(new ProductsModuleExport(), 'products-export.xlsx');
    }
}
