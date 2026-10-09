<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactUsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $data
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Contact Form Submission')
            ->greeting('New Contact Inquiry')
            ->line('A new contact form has been submitted.')
            ->line('Name: ' . $this->data['name'])
            ->line('Email: ' . $this->data['email'])
            ->line('Phone: ' . ($this->data['phone'] ?? '-'))
            ->line('Subject: ' . $this->data['subject'])
            ->line('Message:')
            ->line($this->data['message']);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}