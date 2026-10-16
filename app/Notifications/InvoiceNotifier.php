<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceNotifier extends Notification
{
    use Queueable;
    
    private $invoice_id;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($_invoice_id)
    {
        $this->invoice_id = $_invoice_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = 'http://localhost:8000/InvoicesDetails/' . $this->invoice_id;
        /*return (new MailMessage)
                    ->subject('Nouvelle facture ajoutee')
                    ->action('Voir la facture', url($url))
                    ->line("Merci d'utiliser notre application.");*/

        // Sender: MAIL_FROM_ADDRESS / MAIL_FROM_NAME from .env (no hard-coded template sender).
        return (new MailMessage)
                ->greeting('Bonjour,')
                ->line('Une de vos factures a ete payee.')
                ->action('Voir la facture', $url)
                ->line("Merci d'utiliser notre application.");
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
