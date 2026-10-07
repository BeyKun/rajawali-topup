<?php

namespace App\Jobs;

use App\Enums\OrderChannel;
use App\Enums\WhatsAppEvent;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes an order status change to the WhatsApp bot service.
 *
 * The bot owns the WhatsApp credentials, so the backend never talks to Meta
 * directly. Instead, when an order's status changes the backend posts a small,
 * signed-by-token event to the bot, which renders the customer-facing message.
 *
 * The job is a no-op for orders that did not originate from the WhatsApp
 * channel, so it can be dispatched unconditionally from the order pipeline.
 */
class NotifyWhatsAppChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Order $order, public WhatsAppEvent $event) {}

    /**
     * Dispatch only when the order belongs to the WhatsApp channel.
     */
    public static function dispatchFor(Order $order, WhatsAppEvent $event): void
    {
        if ($order->channel !== OrderChannel::WhatsApp || $order->channel_ref === null) {
            return;
        }

        if (trim((string) config('whatsapp.bot_base_url')) === '') {
            return;
        }

        self::dispatch($order, $event);
    }

    /**
     * Send the status event to the bot's internal callback endpoint.
     */
    public function handle(): void
    {
        $order = $this->order->fresh(['product']);

        if ($order === null || $order->channel !== OrderChannel::WhatsApp || $order->channel_ref === null) {
            return;
        }

        $url = rtrim((string) config('whatsapp.bot_base_url'), '/').'/internal/order-events';

        try {
            Http::withToken((string) config('whatsapp.bot_token'))
                ->timeout((int) config('whatsapp.timeout', 10))
                ->acceptJson()
                ->post($url, $this->payload($order));
        } catch (Throwable $exception) {
            Log::warning('Failed to notify the WhatsApp bot about an order event.', [
                'order_no' => $order->order_no,
                'event' => $this->event->value,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Order $order): array
    {
        return [
            'event_id' => 'evt_'.$order->order_no.'_'.$this->event->value,
            'order_no' => $order->order_no,
            'wa_id' => $order->channel_ref,
            'event_type' => $this->event->value,
            'product_name' => $order->product?->name,
            'msisdn' => $order->msisdn,
            'total_amount' => (int) $order->total_amount,
            'sn' => $this->serialNumber($order),
            'message' => $this->message($order),
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extract the Telkomsel serial number without leaking the secret HRN.
     */
    protected function serialNumber(Order $order): ?string
    {
        $raw = $order->redeem_response_raw;

        if (! is_array($raw)) {
            return null;
        }

        $sn = data_get($raw, 'data.sn');

        return is_string($sn) && $sn !== '' ? $sn : null;
    }

    /**
     * Human-readable status message shown to the WhatsApp customer.
     */
    protected function message(Order $order): string
    {
        return match ($this->event) {
            WhatsAppEvent::Paid => 'Pembayaran diterima, memproses paket data...',
            WhatsAppEvent::Redeeming => 'Sedang mengaktifkan paket data Anda...',
            WhatsAppEvent::Success => "Paket data berhasil diaktifkan ke {$order->msisdn}",
            WhatsAppEvent::Failed => 'Transaksi sedang dicek oleh operator',
            WhatsAppEvent::Expired => 'Pesanan kedaluwarsa. Silakan buat pesanan baru.',
            WhatsAppEvent::Canceled => 'Pesanan dibatalkan.',
        };
    }
}
