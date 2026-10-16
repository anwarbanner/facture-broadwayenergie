<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** "Journal d'activite" (Admin): who did what, on which record, when, from where - in plain French. */
class ActivityLogController extends Controller
{
    public const PERIODS = [
        'today' => 'Aujourd\'hui',
        'yesterday' => 'Hier',
        '7d' => '7 derniers jours',
        '30d' => '30 derniers jours',
        'all' => 'Tout',
        'custom' => 'Dates choisies',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:' . implode(',', array_keys(self::PERIODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
            'views' => ['nullable', 'boolean'],
        ]);
        $period = $filters['period'] ?? (isset($filters['from']) || isset($filters['to']) ? 'custom' : '7d');
        $withViews = (bool) ($filters['views'] ?? false);

        // Period + user + search: base of the figures; the kind filter narrows the table only.
        $base = ActivityLog::query()
            ->tap(fn (Builder $q) => $this->applyPeriod($q, $period, $filters['from'] ?? null, $filters['to'] ?? null))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $q, $term) {
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('user_name', 'like', "%{$term}%")
                        ->orWhere('meta', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%");
                });
            })
            ->when(!$withViews, fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('category')->orWhereNotIn('category', ActivityLogPresenter::HIDDEN_BY_DEFAULT)));

        $counts = (clone $base)->toBase()->selectRaw('category, COUNT(*) AS n')->groupBy('category')->pluck('n', 'category');
        $stats = [
            'total' => (int) $counts->sum(),
            'create' => (int) ($counts['create'] ?? 0),
            'update' => (int) ($counts['update'] ?? 0),
            'delete' => (int) ($counts['delete'] ?? 0),
            'finance' => (int) ($counts['finance'] ?? 0) + (int) ($counts['convert'] ?? 0),
            'login' => (int) ($counts['login'] ?? 0),
            'refused' => (clone $base)->where('outcome', 'refused')->count(),
            'users' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
        ];

        $category = $filters['category'] ?? null;
        $logs = (clone $base)
            ->when($category === 'refused', fn (Builder $q) => $q->where('outcome', 'refused'))
            ->when($category && $category !== 'refused', fn (Builder $q) => $q->where('category', $category))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('activity_logs.index', [
            'logs' => $logs,
            'rows' => $logs->getCollection()->map(fn (ActivityLog $log) => ['log' => $log] + ActivityLogPresenter::present($log)),
            'stats' => $stats,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'periods' => self::PERIODS,
            'period' => $period,
            'categories' => collect(ActivityLogPresenter::CATEGORIES)->except($withViews ? [] : ActivityLogPresenter::HIDDEN_BY_DEFAULT),
            'withViews' => $withViews,
        ]);
    }

    private function applyPeriod(Builder $query, string $period, ?string $from, ?string $to): void
    {
        match ($period) {
            'today' => $query->where('created_at', '>=', now()->startOfDay()),
            'yesterday' => $query->whereBetween('created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()]),
            '7d' => $query->where('created_at', '>=', now()->subDays(6)->startOfDay()),
            '30d' => $query->where('created_at', '>=', now()->subDays(29)->startOfDay()),
            'custom' => $query
                ->when($from, fn (Builder $q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay()))
                ->when($to, fn (Builder $q) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay())),
            default => null,
        };
    }
}
