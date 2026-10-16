<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user?->id,
            'user_name' => $event->user?->name,
            'role_name' => $event->user?->getRoleNames()->first(),
            'action' => 'auth: login_failed',
            'method' => 'AUTH',
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
            'meta' => [
                'guard' => $event->guard,
                'email' => $event->credentials['email'] ?? null,
            ],
        ]);
    }
}
