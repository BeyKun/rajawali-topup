<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $eventDescription = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.orders'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->order->loadMissing(['product:id,name,city_id']);

        return [
            'order' => [
                'id' => $this->order->id,
                'order_no' => $this->order->order_no,
                'product_name' => $this->order->product?->name,
                'msisdn' => $this->order->msisdn,
                'total_amount' => (float) $this->order->total_amount,
                'payment_status' => $this->order->payment_status->value,
                'redeem_status' => $this->order->redeem_status->value,
                'paid_at' => $this->order->paid_at?->toIso8601String(),
                'refunded_at' => $this->order->refunded_at?->toIso8601String(),
                'created_at' => $this->order->created_at?->toIso8601String(),
                'city_id' => $this->order->product?->city_id,
            ],
            'message' => $this->eventDescription ?? "Status pesanan {$this->order->order_no} diperbarui.",
        ];
    }
}
