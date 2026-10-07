<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Products with available stock at or below this value are flagged low stock. */
    private const LOW_STOCK_THRESHOLD = 5;

    /** Number of recent orders shown on the dashboard. */
    private const RECENT_ORDERS_LIMIT = 10;

    /** Number of days included in the sales chart. */
    private const SALES_CHART_DAYS = 7;

    /**
     * Show the admin analytics dashboard.
     */
    public function index(Request $request): Response
    {
        $cityId = $request->user()?->isKabupatenAdmin() ? $request->user()->city_id : null;
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        $revenueToday = $this->paidOrders($cityId)->where('paid_at', '>=', $today)->sum('total_amount');
        $revenueThisMonth = $this->paidOrders($cityId)->where('paid_at', '>=', $startOfMonth)->sum('total_amount');

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'total_orders' => Order::query()->forCity($cityId)->count(),
                'orders_today' => Order::query()->forCity($cityId)->where('created_at', '>=', $today)->count(),
                'revenue_today' => (float) $revenueToday,
                'revenue_this_month' => (float) $revenueThisMonth,
                'available_vouchers' => Voucher::query()->forCity($cityId)->where('status', VoucherStatus::Available)->count(),
                'redeemed_vouchers' => Voucher::query()->forCity($cityId)->where('status', VoucherStatus::Redeemed)->count(),
                'failed_orders' => Order::query()
                    ->forCity($cityId)
                    ->where(fn (Builder $query) => $query
                        ->where('redeem_status', RedeemStatus::Failed)
                        ->orWhere('payment_status', PaymentStatus::Failed))
                    ->count(),
            ],
            'salesChart' => $this->salesChart($cityId),
            'lowStock' => $this->lowStock($cityId),
            'recentOrders' => $this->recentOrders($cityId),
        ]);
    }

    /**
     * Query builder for orders that count as realised revenue.
     *
     * An order counts once its QRIS payment is PAID or its voucher has been
     * successfully redeemed.
     *
     * @return Builder<Order>
     */
    private function paidOrders(?int $cityId = null): Builder
    {
        return Order::query()
            ->forCity($cityId)
            ->where(function (Builder $query): void {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->orWhere('redeem_status', RedeemStatus::Success);
            });
    }

    /**
     * Build the last N days of revenue and order counts.
     *
     * @return array<int, array{date: string, revenue: float, orders: int}>
     */
    private function salesChart(?int $cityId = null): array
    {
        $start = Carbon::today()->subDays(self::SALES_CHART_DAYS - 1);

        $revenueByDate = $this->paidOrders($cityId)
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'total_amount'])
            ->groupBy(fn (Order $order): string => $order->paid_at?->toDateString() ?? '')
            ->map(fn ($orders): float => (float) $orders->sum('total_amount'));

        $ordersByDate = Order::query()
            ->forCity($cityId)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at?->toDateString() ?? '')
            ->map(fn ($orders): int => $orders->count());

        $chart = [];

        for ($offset = 0; $offset < self::SALES_CHART_DAYS; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();

            $chart[] = [
                'date' => $date,
                'revenue' => $revenueByDate[$date] ?? 0.0,
                'orders' => $ordersByDate[$date] ?? 0,
            ];
        }

        return $chart;
    }

    /**
     * Active products whose available stock is at or below the threshold.
     *
     * @return array<int, array{id: int, name: string, available_stock: int}>
     */
    private function lowStock(?int $cityId = null): array
    {
        return Product::query()
            ->forCity($cityId)
            ->where('is_active', true)
            ->withCount([
                'vouchers as available_stock' => fn (Builder $query) => $query
                    ->where('status', VoucherStatus::Available)
                    ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId)),
            ])
            ->orderBy('available_stock')
            ->get()
            ->filter(fn (Product $product): bool => $product->available_stock < self::LOW_STOCK_THRESHOLD)
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'available_stock' => (int) $product->available_stock,
            ])
            ->values()
            ->all();
    }

    /**
     * The latest orders with the fields the dashboard table needs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(?int $cityId = null): array
    {
        return Order::query()
            ->forCity($cityId)
            ->with(['product:id,name'])
            ->latest('created_at')
            ->limit(self::RECENT_ORDERS_LIMIT)
            ->get()
            ->map(fn (Order $order): array => [
                'order_no' => $order->order_no,
                'product_name' => $order->product?->name,
                'msisdn' => $order->msisdn,
                'total_amount' => (float) $order->total_amount,
                'payment_status' => $order->payment_status->value,
                'redeem_status' => $order->redeem_status->value,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
