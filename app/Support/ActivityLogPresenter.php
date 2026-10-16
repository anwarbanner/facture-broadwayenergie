<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a raw activity log entry (route, method, status) into plain French for the "Journal d'activite" page:
 * what kind of action, on which record (with its number or name), how it ended, from which device.
 */
class ActivityLogPresenter
{
    /** category => [label, icon, css tone] */
    public const CATEGORIES = [
        'create' => ['Creation', 'fe-plus-circle', 'success'],
        'update' => ['Modification', 'fe-edit-2', 'primary'],
        'delete' => ['Suppression', 'fe-trash-2', 'danger'],
        'convert' => ['Transformation', 'fe-repeat', 'info'],
        'finance' => ['Finance', 'fe-dollar-sign', 'purple'],
        'download' => ['Telechargement', 'fe-download', 'teal'],
        'login' => ['Connexion', 'fe-log-in', 'secondary'],
        'logout' => ['Deconnexion', 'fe-log-out', 'secondary'],
        'login_failed' => ['Connexion refusee', 'fe-alert-triangle', 'danger'],
        'view' => ['Consultation', 'fe-eye', 'light'],
        'auth_form' => ['Formulaire de connexion', 'fe-key', 'light'],
        'other' => ['Autre', 'fe-activity', 'light'],
    ];

    /** Kinds hidden unless asked: page views (old entries) and duplicates of the login / logout events. */
    public const HIDDEN_BY_DEFAULT = ['view', 'auth_form'];

    /** URL / route segment => [noun, model, label attributes (first filled one wins)] */
    public const RESOURCES = [
        'sales-quotes' => ['Devis client', \App\Models\SalesQuote::class, ['quote_number']],
        'sales-orders' => ['Commande client', \App\Models\SalesOrder::class, ['order_number']],
        'deliveries' => ['Livraison', \App\Models\Delivery::class, ['delivery_number']],
        'client-invoices' => ['Facture client', \App\Models\ClientInvoice::class, ['invoice_number']],
        'client-statements' => ['Releve client', \App\Models\ClientStatement::class, ['statement_number', 'id']],
        'purchase-quotes' => ['Demande d\'achat', \App\Models\PurchaseQuote::class, ['quote_number']],
        'purchase-orders' => ['Commande d\'achat', \App\Models\PurchaseOrder::class, ['order_number']],
        'delivery-notes' => ['Bon de reception', \App\Models\DeliveryNote::class, ['receipt_number']],
        'supplier-invoices' => ['Facture fournisseur', \App\Models\SupplierInvoice::class, ['invoice_number']],
        'supplier-statements' => ['Releve fournisseur', \App\Models\SupplierStatement::class, ['statement_number', 'id']],
        'contacts' => ['Contact', \App\Models\Contact::class, ['company_name']],
        'products' => ['Produit', \App\Models\Products::class, ['product_name']],
        'services' => ['Service', \App\Models\Service::class, ['service_name']],
        'categories' => ['Categorie', \App\Models\Category::class, ['name', 'category_name']],
        'payments' => ['Reglement', \App\Models\Payment::class, ['reference', 'id']],
        'users' => ['Utilisateur', \App\Models\User::class, ['name']],
        'roles' => ['Role', null, []],
        'accounting' => ['Comptabilite', null, []],
    ];

    // ---- Classification (stored in activity_logs.category / outcome) ------------------------------------

    public static function classify(?string $method, ?string $routeName, ?string $action, ?string $url): string
    {
        $method = strtoupper((string) $method);
        $route = (string) $routeName;
        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');

        if ($method === 'AUTH') {
            return match (true) {
                str_contains((string) $action, 'login_failed') => 'login_failed',
                str_contains((string) $action, 'logout') => 'logout',
                default => 'login',
            };
        }

        return match (true) {
            // Login / logout form posts: the Login / Logout events record the same thing, more precisely.
            $method === 'POST' && in_array($path, ['login', 'logout'], true) => 'auth_form',
            $route === 'erp.payments.store' => 'finance',
            str_ends_with($route, '.store') || ($method === 'POST' && $path === 'register') => 'create',
            str_ends_with($route, '.update') => 'update',
            str_ends_with($route, '.destroy') => 'delete',
            str_contains($route, '.convert-to-') => 'convert',
            str_ends_with($route, '.validate') || str_ends_with($route, '.payments') || str_ends_with($route, '.credit-note') || $route === 'erp.payments.void' => 'finance',
            str_ends_with($route, '.pdf') || str_ends_with($route, '.excel') || str_ends_with($route, '.export') || $route === 'print-invoice'
                || str_starts_with($path, 'download/') || str_starts_with($path, 'View_file/') || str_starts_with($path, 'export') || str_contains((string) $url, 'export=csv') => 'download',
            $method === 'GET' => 'view',
            default => 'other',
        };
    }

    // ---- Display ----------------------------------------------------------------------------------------

    /** @return array{category: string, label: string, icon: string, tone: string, title: string, subject: ?string, subject_url: ?string, detail: ?string, refused: bool, device: string, place: string} */
    public static function present(ActivityLog $log): array
    {
        $category = $log->category ?: self::classify($log->method, $log->route_name, $log->action, $log->url);
        [$label, $icon, $tone] = self::CATEGORIES[$category] ?? self::CATEGORIES['other'];
        $meta = (array) ($log->meta ?? []);
        [$resource, $verb] = self::routeParts($log->route_name);
        $noun = self::RESOURCES[$resource][0] ?? null;

        $subjectLabel = $meta['subject_label'] ?? self::subjectLabel($log->subject_type, $log->subject_id);
        $subject = $noun
            ? trim($noun . ($subjectLabel ? ' ' . $subjectLabel : ''))
            : ($subjectLabel ?: null);

        return [
            'category' => $category,
            'label' => $label,
            'icon' => $icon,
            'tone' => $tone,
            'title' => self::title($category, $verb, $resource, $log, $meta),
            'subject' => $subject,
            'subject_url' => $meta['subject_url'] ?? self::subjectUrl($resource, $log->subject_id, $category),
            'detail' => self::detail($category, $meta),
            'refused' => $log->outcome === 'refused' || $category === 'login_failed',
            'device' => self::device((string) $log->user_agent),
            'place' => self::place((string) $log->ip_address),
        ];
    }

    /** Label of a record for the log: its number / name, kept in meta when the action happens. */
    public static function labelFor(Model $model): ?string
    {
        foreach (self::RESOURCES as [$noun, $class, $attributes]) {
            if ($class && $model instanceof $class) {
                foreach ($attributes as $attribute) {
                    $value = $model->getAttribute($attribute);
                    if ($value !== null && $value !== '') {
                        if (str_starts_with((string) $value, 'BROUILLON-')) {
                            return 'brouillon #' . $model->getKey();
                        }

                        return $attribute === 'id' ? '#' . $value : (string) $value;
                    }
                }
            }
        }

        return '#' . $model->getKey();
    }

    /** "erp/sales-orders/12/edit" -> [SalesOrder model, url of its page] when it is an ERP record. */
    public static function recordFromUrl(?string $url): ?array
    {
        if (!$url || !preg_match('#/erp/([a-z-]+)/(\d+)#', $url, $m) || empty(self::RESOURCES[$m[1]][1])) {
            return null;
        }
        $class = self::RESOURCES[$m[1]][1];
        $model = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()->find((int) $m[2])
            : $class::find((int) $m[2]);

        return $model ? ['model' => $model, 'resource' => $m[1], 'url' => url("erp/{$m[1]}/{$m[2]}")] : null;
    }

    private static function routeParts(?string $routeName): array
    {
        $parts = explode('.', (string) $routeName);
        if (($parts[0] ?? '') === 'erp') {
            array_shift($parts);
        }

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private static function title(string $category, string $verb, string $resource, ActivityLog $log, array $meta): string
    {
        $nounLower = mb_strtolower(self::RESOURCES[$resource][0] ?? 'element');

        return match ($category) {
            'create' => $resource === '' ? 'Creation d\'un compte' : 'Creation : ' . $nounLower,
            'update' => 'Modification : ' . $nounLower,
            'delete' => 'Suppression : ' . $nounLower,
            'convert' => match ($verb) {
                'convert-to-order' => 'Transformation en commande',
                'convert-to-delivery' => $resource === 'purchase-orders' ? 'Transformation en bon de reception' : 'Transformation en livraison',
                'convert-to-invoice' => 'Transformation en facture',
                default => 'Transformation',
            },
            'finance' => match (true) {
                $verb === 'validate' => 'Validation de la facture',
                $verb === 'payments' => 'Paiement enregistre',
                $verb === 'credit-note' => 'Avoir cree',
                $verb === 'void' => 'Reglement annule',
                $resource === 'payments' => 'Reglement enregistre',
                default => 'Operation financiere',
            },
            'download' => str_contains((string) $log->url, 'export') || str_contains((string) $log->url, '/excel') ? 'Export Excel' : 'Telechargement PDF',
            'login' => 'Connexion a l\'application',
            'logout' => 'Deconnexion',
            'login_failed' => 'Mot de passe ou e-mail incorrect',
            'auth_form' => 'Envoi du formulaire de connexion',
            'view' => 'Consultation : ' . ($resource !== '' ? $nounLower : (parse_url((string) $log->url, PHP_URL_PATH) ?: 'page')),
            default => 'Action',
        };
    }

    private static function detail(string $category, array $meta): ?string
    {
        if ($category === 'login_failed') {
            return !empty($meta['email']) ? 'Identifiant saisi : ' . $meta['email'] : null;
        }
        $parts = array_filter([
            $meta['message'] ?? null,
            !empty($meta['result_label']) ? 'Resultat : ' . $meta['result_label'] : null,
        ]);

        return $parts ? implode(' - ', $parts) : null;
    }

    private static function subjectLabel(?string $type, $id): ?string
    {
        if (!$type || !$id || !class_exists($type)) {
            return $id ? '#' . $id : null;
        }
        $model = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($type), true)
            ? $type::withTrashed()->find($id)
            : $type::find($id);

        return $model ? self::labelFor($model) : '#' . $id . ' (supprime)';
    }

    private static function subjectUrl(string $resource, $id, string $category): ?string
    {
        if (!$id || $category === 'delete' || empty(self::RESOURCES[$resource][1])) {
            return null;
        }

        return url("erp/{$resource}/{$id}");
    }

    /** "Chrome · Windows", "Telephone Android", "Outil (curl)" */
    public static function device(string $agent): string
    {
        if ($agent === '') {
            return '-';
        }
        if (preg_match('/curl|wget|python|postman|symfony/i', $agent)) {
            return 'Outil automatique';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };
        $system = match (true) {
            (bool) preg_match('/iPhone|iPad/', $agent) => 'iPhone / iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };
        $mobile = (bool) preg_match('/Mobile|Android|iPhone/', $agent);

        return trim(($mobile ? 'Telephone ' : '') . $browser . ($system ? ' · ' . $system : ''));
    }

    /** Where the request came from, in words. */
    public static function place(string $ip): string
    {
        return match (true) {
            $ip === '' => '-',
            in_array($ip, ['127.0.0.1', '::1'], true) => 'Ce poste',
            (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $ip) => 'Reseau local (' . $ip . ')',
            default => 'Internet (' . $ip . ')',
        };
    }
}
