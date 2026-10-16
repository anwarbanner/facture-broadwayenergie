<?php

namespace App\Http\Controllers\ERP\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** List pages of the commercial documents: one search box (number or client / supplier name) + a status filter. */
trait FiltersDocuments
{
    protected function filterDocuments(Builder $query, string $numberColumn, string $partyRelation): Builder
    {
        return $query
            ->when(trim((string) request('q')), function (Builder $q, string $term) use ($numberColumn, $partyRelation) {
                $q->where(function (Builder $inner) use ($term, $numberColumn, $partyRelation) {
                    $inner->where($numberColumn, 'like', "%{$term}%")
                        ->orWhereHas($partyRelation, fn (Builder $party) => $party->where('company_name', 'like', "%{$term}%"));
                });
            })
            ->when(request('status'), fn (Builder $q, string $status) => $q->where('status', $status));
    }
}
