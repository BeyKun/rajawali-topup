<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessVoucherRedeemJob;
use App\Models\Order;
use App\Models\TelkomselApiLog;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a filtered, paginated list of orders.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'order_no' => $request->string('order_no')->toString() ?: null,
            'msisdn' => $request->string('msisdn')->toString() ?: null,
            'payment_status' => $request->string('payment_status')->toString() ?: null,
            'redeem_status' => $request->string('redeem_status')->toString() ?: null,
            'date_from' => $request->date('date_from')?->toDateString(),
            'date_to' => $request->date('date_to')?->toDateString(),
        ];

        $orders = Order::query()
            ->with(['user:id,name', 'product:id,name'])
            ->when($filters['order_no'], fn (Builder $query, string $orderNo) => $query->where('order_no', 'like', "%{$orderNo}%"))
            ->when($filters['msisdn'], fn (Builder $query, string $msisdn) => $query->where('msisdn', 'like', "%{$msisdn}%"))
            ->when($filters['payment_status'], fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when($filters['redeem_status'], fn (Builder $query, string $status) => $query->where('redeem_status', $status))
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user_name' => $order->user?->name,
                'product_name' => $order->product?->name,
                'msisdn' => $order->msisdn,
                'total_amount' => (float) $order->total_amount,
                'payment_status' => $order->payment_status->value,
                'redeem_status' => $order->redeem_status->value,
                'retry_count' => $order->retry_count,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'paymentStatuses' => $this->statusValues(PaymentStatus::cases()),
            'redeemStatuses' => $this->statusValues(RedeemStatus::cases()),
        ]);
    }

    /**
     * Show the full order detail with voucher and Telkomsel trace information.
     */
    public function show(Order $order): Response
    {
        $order->load(['user:id,name,email', 'product:id,name', 'voucher']);

        $apiLogs = TelkomselApiLog::query()
            ->where(function (Builder $query) use ($order): void {
                $query->where('order_id', $order->id);

                if ($order->voucher_id !== null) {
                    $query->orWhere('voucher_id', $order->voucher_id);
                }
            })
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (TelkomselApiLog $log): array => [
                'id' => $log->id,
                'endpoint' => $log->endpoint,
                'response_code' => $log->response_code,
                'duration_ms' => $log->duration_ms,
                'created_at' => $log->created_at?->toIso8601String(),
                'request_payload' => $log->request_payload,
                'response_payload' => $log->response_payload,
            ])
            ->all();

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user' => [
                    'name' => $order->user?->name,
                    'email' => $order->user?->email,
                ],
                'product_name' => $order->product?->name,
                'msisdn' => $order->msisdn,
                'amount' => (float) $order->amount,
                'admin_fee' => (float) $order->admin_fee,
                'total_amount' => (float) $order->total_amount,
                'payment_channel' => $order->payment_channel,
                'payment_status' => $order->payment_status->value,
                'redeem_status' => $order->redeem_status->value,
                'retry_count' => $order->retry_count,
                'qris_string' => $order->qris_string,
                'qris_url' => $order->qris_url,
                'qris_expired_at' => $order->qris_expired_at?->toIso8601String(),
                'payment_ref_id' => $order->payment_ref_id,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'redeem_response_code' => $order->redeem_response_code,
                'redeem_response_raw' => $order->redeem_response_raw,
                'voucher_serial_number' => $order->voucher?->serial_number,
                'voucher_status' => $order->voucher?->status->value,
                'refund_amount' => $order->refund_amount ? (float) $order->refund_amount : null,
                'refund_ref_id' => $order->refund_ref_id,
                'refund_reason' => $order->refund_reason,
                'refunded_at' => $order->refunded_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
            ],
            'apiLogs' => $apiLogs,
        ]);
    }

    /**
     * Re-dispatch the redeem job for a paid order that has not succeeded yet.
     */
    public function retryRedeem(Order $order): RedirectResponse
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => 'Hanya pesanan berstatus LUNAS yang dapat dicoba ulang.',
            ]);
        }

        if ($order->redeem_status === RedeemStatus::Success) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan ini sudah berhasil di-redeem.',
            ]);
        }

        if ($order->voucher_id === null) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan ini tidak memiliki voucher untuk di-redeem.',
            ]);
        }

        $order->update(['redeem_status' => RedeemStatus::Pending]);

        ProcessVoucherRedeemJob::dispatch($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan ulang redeem telah diantrikan.']);

        return back();
    }

    /**
     * Manually cancel an order and release its reserved voucher.
     */
    public function cancel(Order $order): RedirectResponse
    {
        if ($order->redeem_status === RedeemStatus::Success) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan yang sudah sukses tidak dapat dibatalkan.',
            ]);
        }

        $order->update([
            'payment_status' => PaymentStatus::Canceled,
            'redeem_status' => RedeemStatus::Canceled,
        ]);

        if ($order->voucher_id !== null) {
            Voucher::query()
                ->whereKey($order->voucher_id)
                ->where('status', VoucherStatus::Reserved->value)
                ->update([
                    'status' => VoucherStatus::Available->value,
                    'reserved_at' => null,
                ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pesanan dibatalkan dan voucher dikembalikan ke stok.']);

        return back();
    }

    /**
     * Manually mark a REFUND_PENDING order as REFUNDED.
     */
    public function markRefunded(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status !== PaymentStatus::RefundPending) {
            throw ValidationException::withMessages([
                'order' => 'Hanya pesanan berstatus REFUND_PENDING yang dapat ditandai selesai refund.',
            ]);
        }

        $validated = $request->validate([
            'refund_ref_id' => ['nullable', 'string', 'max:100'],
            'refund_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order->update([
            'payment_status' => PaymentStatus::Refunded,
            'refund_amount' => $order->total_amount,
            'refund_ref_id' => $validated['refund_ref_id'] ?? ('MANUAL-'.strtoupper(Str::random(8))),
            'refund_reason' => $validated['refund_reason'] ?? $order->refund_reason ?? 'Manual refund completed by admin',
            'refunded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pesanan {$order->order_no} berhasil ditandai selesai di-refund."]);

        return back();
    }

    /**
     * Flatten enum cases into `[{value, label}]` options.
     *
     * @param  array<int, PaymentStatus|RedeemStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function statusValues(array $cases): array
    {
        return array_map(fn (PaymentStatus|RedeemStatus $case): array => [
            'value' => $case->value,
            'label' => $case->value,
        ], $cases);
    }
}
