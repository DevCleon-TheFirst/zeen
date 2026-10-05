<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cart;
    public $amount;
    public $currency;
    public $checkoutUrl;
    public $businessName;

    /**
     * Create a new message instance.
     */
    public function __construct($cart, $amount, $currency, $checkoutUrl, $businessName)
    {
        $this->cart = $cart;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->checkoutUrl = $checkoutUrl;
        $this->businessName = $businessName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Summary & Checkout - ' . $this->businessName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.summary',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
