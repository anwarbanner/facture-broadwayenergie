<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The old invoicing module (invoices, invoices_details, invoices_attachments, legacy products screens) is kept
 * for consultation only: lists, details, PDF, exports and reports still work, but nothing can be created,
 * edited or deleted there any more. The ERP module is the only place where documents are written.
 */
class LegacyReadOnly
{
    private const FORM_ACTIONS = ['create', 'edit'];

    public function handle(Request $request, Closure $next)
    {
        $isWrite = !in_array($request->method(), ['GET', 'HEAD'], true);
        $isForm = in_array($request->route()?->getActionMethod(), self::FORM_ACTIONS, true);

        if ($isWrite || $isForm) {
            $message = 'L\'ancien module de facturation est en lecture seule. Utilisez le module ERP (Cycle ventes / Cycle achats).';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('erp.client-invoices.index')->with('error', $message);
        }

        return $next($request);
    }
}
