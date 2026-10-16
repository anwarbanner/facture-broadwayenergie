<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;

class StockMovementController extends Controller
{
    public function index()
    {
        $movements = StockMovement::query()
            ->with(['product', 'warehouse', 'user'])
            ->when(request('type'), fn ($q) => $q->where('movement_type', request('type')))
            ->when(request('q'), function ($q, $term) {
                $q->where('reference_document', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%");
            })
            ->latest('movement_date')
            ->paginate(20)
            ->withQueryString();

        return view('erp.stock_movements.index', compact('movements'));
    }
}
