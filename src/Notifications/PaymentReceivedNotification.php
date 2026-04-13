<?php

namespace Fahipay\Gateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Fahipay\Gateway\Models\FahipayPayment;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected FahipayPayment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Payment Received - ' . $this->payment->transaction_id)
            ->greeting('Hello!')
            ->line('We have received your payment.')
            ->line('Transaction ID: ' . $this->payment->transaction_id)
            ->line('Amount: MVR ' . number_format($this->payment->amount, 2))
            ->line('Status: ' . $this->payment->status->label())
            ->line('Thank you for your payment!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'status' => $this->payment->status->value,
            'message' => 'Payment received successfully',
        ];
    }
}