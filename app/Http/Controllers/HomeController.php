<?php

namespace App\Http\Controllers;

use App\Models\SalesQuote;
use App\Services\Dashboard\DashboardService;
use App\Support\Branding;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * ERP home page (dashboard). Built from the ERP data only; the old invoicing module is read-only
     * and no longer feeds the home page (its figures were all at 0 and made the page misleading).
     */
    public function index(DashboardService $dashboard)
    {
        // Simple mode: three big buttons and the latest quotes, nothing else.
        if (Branding::simpleMode()) {
            return view('simple_home', [
                'quotes' => SalesQuote::with(['client', 'invoice', 'order'])->latest()->limit(10)->get(),
            ]);
        }

        $finance = (bool) auth()->user()?->can('manage-finance');

        return view('dashboard', [
            'finance' => $finance,
            'month' => $finance ? $dashboard->month() : null,
            'trend' => $finance ? $dashboard->trend() : null,
            'todo' => $dashboard->todo($finance),
            'recent' => $dashboard->recentDocuments(),
            'lowStock' => $dashboard->lowStock(),
            'counts' => $dashboard->counts(),
        ]);
    }
}
