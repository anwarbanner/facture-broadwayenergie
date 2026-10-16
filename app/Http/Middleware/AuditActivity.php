<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\ActivityLogPresenter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Activity log of writes and downloads (page views are not logged).
 * Each entry keeps what the "Journal d'activite" page needs to read it without technical codes:
 * the record concerned (number / name at that time), the record created or produced (conversion),
 * the message shown to the user and whether the action succeeded or was refused.
 */
class AuditActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $user = $request->user();
        $route = $request->route();

        // Record named in the URL (the document edited, validated, converted...).
        $subject = null;
        $subjectId = null;
        foreach ($route?->parameters() ?? [] as $param) {
            if ($param instanceof Model) {
                $subject = $param;
                break;
            }
            if (is_numeric($param)) {
                $subjectId = (int) $param;
            }
        }

        // Record created by this request (list highlight) or produced by it (redirect to the new document).
        $session = $request->hasSession() ? $request->session() : null;
        // Only what THIS request flashed (a message from the previous request is still readable once).
        $fresh = fn (string $key) => $session && in_array($key, (array) $session->get('_flash.new', []), true) ? $session->get($key) : null;
        $location = isset($response->headers) ? $response->headers->get('Location') : null;
        $created = ActivityLogPresenter::recordFromUrl($fresh('highlight'));
        $target = $created ?? ActivityLogPresenter::recordFromUrl($location);
        if (!$subject && $target) {
            $subject = $target['model'];
        }
        // Routes with a plain id (e.g. /erp/sales-quotes/12/excel): the record is in the URL.
        if (!$subject && ($own = ActivityLogPresenter::recordFromUrl($request->url()))) {
            $subject = $own['model'];
        }
        $producedOther = $target && $subject && !$target['model']->is($subject);

        // What the user saw: confirmation, refusal or first validation error.
        $errors = $fresh('errors');
        // ViewErrorBag::first() is a magic method (forwarded to the default bag): method_exists() cannot see it.
        $firstError = $errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag
            ? ($errors->first() ?: null)
            : null;
        $refused = (method_exists($response, 'getStatusCode') && $response->getStatusCode() >= 400)
            || $firstError !== null || $fresh('error') !== null;
        $message = $refused ? ($fresh('error') ?? $firstError) : $fresh('success');

        $method = strtoupper($request->method());
        $payload = $request->except(['password', 'password_confirmation', 'confirm-password', '_token', 'current_password']);

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'role_name' => $user?->getRoleNames()->first(),
            'action' => match ($method) {
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'read',
            } . ': ' . ($route?->getName() ?: $request->path()),
            'outcome' => $refused ? 'refused' : 'ok',
            'method' => $method,
            'route_name' => $route?->getName(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'status_code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey() ?? $subjectId,
            'meta' => array_filter([
                'subject_label' => $subject ? ActivityLogPresenter::labelFor($subject) : null,
                'subject_url' => $created['url'] ?? null,
                'result_label' => $producedOther ? ActivityLogPresenter::RESOURCES[$target['resource']][0] . ' ' . ActivityLogPresenter::labelFor($target['model']) : null,
                'result_url' => $producedOther ? $target['url'] : null,
                'message' => $message ? mb_substr(strip_tags((string) $message), 0, 300) : null,
                'query' => $request->query() ?: null,
                'payload_keys' => array_keys($payload) ?: null,
            ], fn ($value) => $value !== null),
        ]);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->is('assets/*') || $request->routeIs('ignition.*')) {
            return true;
        }

        // Login / logout forms: the Login / Logout events already record them (with the e-mail on failure).
        if ($request->is('login', 'logout')) {
            return true;
        }

        // Page views are not audited (they were ~85 % of the log and buried the useful entries).
        // Writes are, and so are downloads / exports / prints: they take data out of the system.
        if ($request->isMethodSafe()) {
            return !$this->isDownload($request);
        }

        return false;
    }

    private function isDownload(Request $request): bool
    {
        return $request->routeIs('*.pdf', '*.excel', '*.export', 'print-invoice')
            || $request->is('download/*', 'View_file/*', 'export_invoices', 'export_*')
            || $request->query('export') === 'csv';
    }
}
