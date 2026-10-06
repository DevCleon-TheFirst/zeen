<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerSaleNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Business $business,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order Received: #{$this->order->tracking_code} — {$this->business->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.owner_sale_notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
