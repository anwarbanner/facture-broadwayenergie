<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\invoices;
use App\Models\Products;

class CustomerReportController extends Controller
{
    public function index() {
        $products = Products::query()->orderBy('product_name')->get();

        return view('customers_reports.index', compact('products'));
    }
    
    public function search(Request $request) {
        $query = invoices::query();

        if ($request->filled('product')) {
            $query->where('product', $request->product);
        }

        if ($request->filled('start_at') && $request->filled('end_at')) {
            $query->whereBetween('invoice_date', [$request->start_at, $request->end_at]);
        }

        $details = $query->get();
        $products = Products::query()->orderBy('product_name')->get();

        return view('customers_reports.index', compact('products', 'details'));
    }
}
