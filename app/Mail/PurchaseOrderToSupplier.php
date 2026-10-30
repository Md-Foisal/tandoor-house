<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderToSupplier extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly PurchaseOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('restaurant.contact.email'), config('restaurant.name'))],
            subject: __('automations.stock.supplier_mail.subject', ['reference' => $this->order->reference, 'restaurant' => config('restaurant.name')]),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['supplier', 'lines.ingredient']);

        return new Content(markdown: 'mail.purchase-order', with: [
            'order' => $this->order,
            'contact' => $this->order->supplier->contact_name ?: $this->order->supplier->name,
            'total' => Money::format($this->order->total),
            'restaurant' => config('restaurant'),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $this->order->loadMissing(['supplier', 'lines.ingredient']);
        $reference = $this->order->reference;

        return [
            Attachment::fromData(
                fn () => Pdf::loadView('pdf.purchase-order', ['order' => $this->order, 'restaurant' => config('restaurant')])->setPaper('a4')->output(),
                "{$reference}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
