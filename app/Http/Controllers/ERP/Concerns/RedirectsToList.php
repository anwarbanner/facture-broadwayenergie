<?php

namespace App\Http\Controllers\ERP\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * After a creation, back to the list with the new row highlighted in blue for a few seconds
 * (layouts/flash exposes the URL, public/assets/js/erp-ui.js finds the row whose links point to it).
 */
trait RedirectsToList
{
    /** @param string $resource URL / route segment, e.g. 'sales-quotes' */
    protected function createdRedirect(string $resource, Model $model, string $message, array $query = []): RedirectResponse
    {
        return redirect()->route("erp.{$resource}.index", $query)
            ->with('success', $message)
            ->with('highlight', url("erp/{$resource}/{$model->getKey()}"));
    }
}
