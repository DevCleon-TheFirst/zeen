<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerOrderReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Business $business,
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation #{$this->order->tracking_code} — {$this->business->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer_receipt',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
