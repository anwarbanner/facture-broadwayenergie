<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'role_name' => $user?->getRoleNames()->first(),
            'action' => 'auth: logout',
            'method' => 'AUTH',
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
        ]);
    }
}
