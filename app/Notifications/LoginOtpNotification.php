<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MK Network Login OTP')
            ->greeting('Hello!')
            ->line('Use the following OTP to complete your login.')
            ->line("Your OTP is: {$this->otp}")
            ->line('This OTP expires in 5 minutes.')
            ->line('If you did not attempt to log in, please ignore this email.');
    }
}