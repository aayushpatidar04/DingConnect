<?php

namespace App\Notifications;

use App\Models\SimOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SimOrderStatusUpdatedNotification extends Notification
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
        $statusLabels = [
            SimOrder::STATUS_PROCESSING => 'Processing',
            SimOrder::STATUS_DISPATCHED => 'Dispatched',
            SimOrder::STATUS_DELIVERED => 'Delivered',
            SimOrder::STATUS_REJECTED => 'Rejected',
        ];

        $label = $statusLabels[$this->simOrder->status] ?? ucfirst($this->simOrder->status);
        $mail = (new MailMessage)
            ->subject("SIM Order #{$this->simOrder->id} — {$label}")
            ->greeting('SIM Card Order Update')
            ->line("Your SIM card order **#{$this->simOrder->id}** has been updated.")
            ->line("**Status:** {$label}")
            ->line("**Total Quantity:** {$this->simOrder->quantity} SIM cards")
            ->line('Itemized Breakdown:');

        foreach ($this->simOrder->items()->with(['operator', 'product'])->get() as $item) {
            $providerName = $item->product?->provider_name ?? $item->operator?->name ?? 'Unknown network';
            $unitPrice = $item->unit_price === null ? '' : " at £{$item->unit_price} each";
            $mail->line("- **{$providerName}** ({$item->category}): {$item->quantity} SIMs{$unitPrice}");
        }

        if ($this->simOrder->tracking_notes) {
            $mail->line("**Notes:** {$this->simOrder->tracking_notes}");
        }

        return $mail
            ->action('View Your Orders', url('/retailer/sim-orders'))
            ->line('Thank you for your order!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sim_order_id' => $this->simOrder->id,
            'status' => $this->simOrder->status,
        ];
    }
}
