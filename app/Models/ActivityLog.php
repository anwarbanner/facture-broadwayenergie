<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'role_name',
        'action',
        'category',
        'outcome',
        'method',
        'route_name',
        'url',
        'ip_address',
        'user_agent',
        'status_code',
        'subject_type',
        'subject_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /** Every entry (middleware, login / logout listeners) gets its readable kind and outcome. */
    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log) {
            $log->category ??= \App\Support\ActivityLogPresenter::classify($log->method, $log->route_name, $log->action, $log->url);
            $log->outcome ??= ($log->category === 'login_failed' || (int) $log->status_code >= 400) ? 'refused' : 'ok';
        });
    }
}
