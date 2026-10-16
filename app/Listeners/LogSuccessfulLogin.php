<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'role_name' => $user?->getRoleNames()->first(),
            'action' => 'auth: login_success',
            'method' => 'AUTH',
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
            'meta' => [
                'remember' => (bool) $event->remember,
            ],
        ]);
    }
}
