<?php

namespace App\Notifications;

use App\Models\Products;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(public Products $product)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerte de stock bas')
            ->line("Un produit a atteint son seuil d'alerte de stock.")
            ->line('Reference : ' . ($this->product->reference ?? '-'))
            ->line('Produit : ' . $this->product->product_name)
            ->line('Quantite en stock : ' . $this->product->stock_quantity)
            ->line("Seuil d'alerte : " . $this->product->min_stock_alert_level)
            ->action('Voir les produits', url('/erp/products'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Alerte de stock bas',
            'product_id' => $this->product->id,
            'reference' => $this->product->reference,
            'product_name' => $this->product->product_name,
            'stock_quantity' => $this->product->stock_quantity,
            'min_stock_alert_level' => $this->product->min_stock_alert_level,
        ];
    }
}
