<?php

namespace App\Services;

use App\Enums\StageKey;
use App\Models\ArtworkVersion;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notify staff (role or all staff) about something.
     */
    public function notifyStaff(string $role, string $title, string $body, ?string $url = null): void
    {
        try {
            $users = User::role($role)->get();
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            $users = collect();
        }

        if ($users->isEmpty()) {
            $users = User::where('is_staff', true)->get();
        }

        foreach ($users as $user) {
            $user->notify(new StaffNotification($title, $body, $url));
        }
    }

    public function stageChanged(Order $order, StageKey $from, StageKey $to, User $actor): void
    {
        // Staff in-app record
        $this->notifyStaff(
            'super_admin',
            "Order {$order->number} moved to {$to->value}",
            "{$actor->name} moved the order from {$from->value} to {$to->value}.",
            "/admin/orders/{$order->id}"
        );
        // (notifyStaff handles the no-role fallback internally)

        // Also notify the customer portal users if the order is now with the customer (approval needed)
        if ($to === StageKey::Artwork) {
            $order->customer->users->each(
                fn ($u) => $this->inApp([$u], "Artwork approval needed for {$order->number}", 'Your order has artwork awaiting your approval.', "/portal/orders/{$order->id}")
            );
        }
    }

    public function artworkAwaitingApproval(ArtworkVersion $version): void
    {
        $order = $version->order;
        $order->customer->users->each(
            fn ($u) => $this->inApp([$u], "Artwork approval needed for {$order->number}", "Version {$version->version_number} is ready for your review.", "/portal/orders/{$order->id}")
        );
    }

    public function artworkApproved(ArtworkVersion $version): void
    {
        $order = $version->order;
        $this->notifyStaff(
            'artworker',
            "Artwork approved: {$order->number}",
            "Customer approved artwork v{$version->version_number}.",
            "/admin/orders/{$order->id}"
        );
    }

    public function artworkRejected(ArtworkVersion $version, string $reason): void
    {
        $order = $version->order;
        $this->notifyStaff(
            'artworker',
            "Artwork changes requested: {$order->number}",
            "Customer requested changes on v{$version->version_number}: {$reason}",
            "/admin/orders/{$order->id}"
        );
    }

    public function stockShort(Order $order, int $shortfall): void
    {
        $this->notifyStaff(
            'storekeeper',
            "Stock shortfall on {$order->number}",
            "Order {$order->number} has a shortfall of {$shortfall} units. Raise a purchase order or adjust stock.",
            "/admin/orders/{$order->id}"
        );
    }

    public function lowStock(StockItem $item): void
    {
        $this->notifyStaff(
            'storekeeper',
            "Low stock: {$item->name}",
            "Only {$item->qty_on_hand} {$item->unit} left (reorder level: {$item->reorder_level}).",
            "/admin/stock-items/{$item->id}"
        );
    }

    public function purchaseOrderReceived(PurchaseOrder $po): void
    {
        $this->notifyStaff(
            'storekeeper',
            "Purchase order {$po->number} fully received",
            "All lines on {$po->number} have been received and stock updated.",
            "/admin/purchase-orders/{$po->id}"
        );
    }

    private function inApp(iterable $users, string $title, string $body, string $url): void
    {
        foreach ($users as $user) {
            $user->notify(new StaffNotification($title, $body, $url));
        }
    }
}

class StaffNotification extends \Illuminate\Notifications\Notification
{
    public function __construct(private string $title, private string $body, private ?string $url = null) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $mail = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if ($this->url) {
            $mail->action('View', url($this->url));
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
