<?php

namespace App\Services\Dashboard;

use App\Models\ClientInvoice;
use App\Models\Products;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingService;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Home page of the ERP: figures of the month, what needs attention today, last documents,
 * 6-month sales / purchases trend. Everything comes from the ERP (never the read-only legacy module).
 */
class DashboardService
{
    public function __construct(private AccountingService $accounting)
    {
    }

    /** Figures of the current month (finance users). */
    public function month(): array
    {
        return $this->accounting->summary(now()->startOfMonth(), now()->endOfMonth());
    }

    /** Things to do, with their link: only the non-empty ones are shown. */
    public function todo(bool $finance): Collection
    {
        $items = collect([
            [
                'label' => 'Factures clients en retard',
                'count' => ClientInvoice::overdue()->count(),
                'amount' => (float) ClientInvoice::overdue()->sum('balance_remaining'),
                'url' => route('erp.client-invoices.index', ['status' => 'overdue']),
                'tone' => 'danger', 'icon' => 'fe-alert-circle', 'finance' => true,
            ],
            [
                'label' => 'Brouillons de factures a valider',
                'count' => ClientInvoice::where('state', InvoiceLifecycle::STATE_DRAFT)->count() + SupplierInvoice::where('state', InvoiceLifecycle::STATE_DRAFT)->count(),
                'amount' => null,
                'url' => route('erp.client-invoices.index'),
                'tone' => 'warning', 'icon' => 'fe-file-text', 'finance' => true,
            ],
            [
                'label' => 'Devis envoyes sans reponse',
                'count' => SalesQuote::where('status', 'sent')->count(),
                'amount' => (float) SalesQuote::where('status', 'sent')->sum('total_ttc'),
                'url' => route('erp.sales-quotes.index', ['status' => 'sent']),
                'tone' => 'info', 'icon' => 'fe-send', 'finance' => false,
            ],
            [
                'label' => 'Commandes clients a livrer',
                'count' => SalesOrder::whereIn('status', ['confirmed', 'partial'])->count(),
                'amount' => null,
                'url' => route('erp.sales-orders.index', ['status' => 'confirmed']),
                'tone' => 'info', 'icon' => 'fe-truck', 'finance' => false,
            ],
            [
                'label' => 'Commandes d\'achat a recevoir',
                'count' => PurchaseOrder::whereIn('status', ['confirmed', 'partial'])->count(),
                'amount' => null,
                'url' => route('erp.purchase-orders.index', ['status' => 'confirmed']),
                'tone' => 'info', 'icon' => 'fe-package', 'finance' => false,
            ],
            [
                'label' => 'Produits en stock bas',
                'count' => $this->lowStockQuery()->count(),
                'amount' => null,
                'url' => route('erp.products.index'),
                'tone' => 'warning', 'icon' => 'fe-alert-triangle', 'finance' => false,
            ],
        ]);

        return $items->filter(fn ($item) => $item['count'] > 0 && ($finance || !$item['finance']))->values();
    }

    /** Last documents of the sales cycle, newest first. */
    public function recentDocuments(int $limit = 7): Collection
    {
        $quotes = SalesQuote::with('client')->latest('id')->take($limit)->get()->map(fn ($q) => [
            'type' => 'Devis', 'number' => $q->quote_number, 'contact' => $q->client?->company_name, 'amount' => (float) $q->total_ttc,
            'status' => $q->status, 'status_label' => null, 'date' => $q->created_at, 'url' => route('erp.sales-quotes.show', $q),
        ]);
        $orders = SalesOrder::with('client')->latest('id')->take($limit)->get()->map(fn ($o) => [
            'type' => 'Commande', 'number' => $o->order_number, 'contact' => $o->client?->company_name, 'amount' => (float) $o->total_ttc,
            'status' => $o->status, 'status_label' => null, 'date' => $o->created_at, 'url' => route('erp.sales-orders.show', $o),
        ]);
        $invoices = ClientInvoice::with('client')->latest('id')->take($limit)->get()->map(fn ($i) => [
            'type' => $i->documentLabel(), 'number' => $i->displayNumber(), 'contact' => $i->client?->company_name, 'amount' => (float) $i->total_ttc,
            'status' => $i->isDraft() ? 'draft' : $i->status, 'status_label' => $i->isDraft() ? 'Brouillon' : $i->statusLabel(),
            'date' => $i->created_at, 'url' => route('erp.client-invoices.show', $i),
        ]);

        return $quotes->concat($orders)->concat($invoices)->sortByDesc('date')->take($limit)->values();
    }

    /** Sales HT and purchases HT of the last 6 months (months without activity at 0). */
    public function trend(int $months = 6): array
    {
        $from = now()->subMonths($months - 1)->startOfMonth();
        $data = $this->accounting->vat($from, now()->endOfMonth(), 'month')->keyBy('key');
        $axis = collect(range($months - 1, 0))->map(fn ($offset) => now()->subMonths($offset)->startOfMonth());

        return [
            'labels' => $axis->map(fn (Carbon $m) => $this->accounting->periodLabel($m, 'month'))->all(),
            // short labels for the chart axis (no tilted text): "sept. 26"
            'short' => $axis->map(fn (Carbon $m) => $m->copy()->locale('fr')->isoFormat('MMM YY'))->all(),
            'sales' => $axis->map(fn (Carbon $m) => round((float) ($data[$m->format('Y-m')]['sales_ht'] ?? 0), 2))->all(),
            'purchases' => $axis->map(fn (Carbon $m) => round((float) ($data[$m->format('Y-m')]['purchases_ht'] ?? 0), 2))->all(),
        ];
    }

    public function lowStock(int $limit = 5): Collection
    {
        return $this->lowStockQuery()->orderByRaw('stock_quantity - min_stock_alert_level')->take($limit)->get(['id', 'product_name', 'stock_quantity', 'min_stock_alert_level', 'unit_type']);
    }

    public function counts(): array
    {
        return [
            'clients' => \App\Models\Contact::clients()->where('status', 'active')->count(),
            'suppliers' => \App\Models\Contact::suppliers()->where('status', 'active')->count(),
            'products' => Products::active()->count(),
            'quotes_month' => SalesQuote::where('date', '>=', now()->startOfMonth()->toDateString())->count(),
        ];
    }

    private function lowStockQuery()
    {
        return Products::active()->where('min_stock_alert_level', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock_alert_level');
    }
}
