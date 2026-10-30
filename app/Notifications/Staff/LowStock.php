<?php

namespace App\Notifications\Staff;

use App\Actions\Inventory\Quantity;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStock extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Ingredient $ingredient, public readonly ?PurchaseOrder $purchaseOrder = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ingredient = $this->ingredient;
        $unit = $ingredient->unit;

        return (new MailMessage)
            ->subject(__('automations.stock.mail.subject', ['ingredient' => $ingredient->name]))
            ->markdown('mail.low-stock', [
                'name' => MarkdownText::escape($notifiable instanceof User ? (explode(' ', trim($notifiable->name))[0]) : ''),
                'ingredient' => MarkdownText::escape($ingredient->name),
                'stock' => Quantity::format($ingredient->stock, $unit),
                'threshold' => Quantity::format($ingredient->low_threshold, $unit),
                'order' => $this->purchaseOrder,
                'url' => $this->purchaseOrder !== null
                    ? route('staff.inventory.purchase-orders.show', $this->purchaseOrder)
                    : route('staff.inventory.index'),
            ]);
    }
}
