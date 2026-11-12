<?php

namespace App\Notifications;

use App\Models\SimOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SimOrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SimOrder $simOrder
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $retailer = $this->simOrder->user;

        return (new MailMessage)
            ->subject('New SIM Card Order #'.$this->simOrder->id)
            ->greeting('New SIM Card Order Received')
            ->line("Retailer **{$retailer->shop_name}** ({$retailer->name}) has placed a new SIM card order.")
            ->line("**Order ID:** #{$this->simOrder->id}")
            ->line("**Quantity:** {$this->simOrder->quantity} SIM cards")
            ->action('View Order', url('/admin/sim-orders'))
            ->line('Please review and process this order.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sim_order_id' => $this->simOrder->id,
            'retailer_name' => $this->simOrder->user->name,
            'quantity' => $this->simOrder->quantity,
        ];
    }
}
