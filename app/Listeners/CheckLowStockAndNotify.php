<?php

namespace App\Listeners;

use App\Events\ProductStockChanged;
use App\Models\User;
use App\Notifications\LowStockNotification;

class CheckLowStockAndNotify
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProductStockChanged $event): void
    {
        $product = $event->product;

        if ((float) $product->stock_quantity > (float) $product->min_stock_alert_level) {
            return;
        }

        $users = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Admin');
            })
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })
                ->get();
        }

        foreach ($users as $user) {
            $user->notify(new LowStockNotification($product));
        }
    }
}
