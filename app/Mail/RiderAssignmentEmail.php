<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderAssignmentEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public Rider $rider;

    public function __construct(Order $order, Rider $rider)
    {
        $this->order = $order;
        $this->rider = $rider;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Order Assignment - #' . $this->order->order_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.rider_assignment',
            with: [
                'order' => $this->order,
                'rider' => $this->rider,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}

