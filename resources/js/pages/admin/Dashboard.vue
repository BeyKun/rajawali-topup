<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Radio,
    Receipt,
    ShoppingCart,
    Ticket,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useEcho } from '@/composables/useEcho';
import { formatDate, formatDateTime, formatRupiah, formatShortDay } from '@/lib/format';
import { playNotificationSound } from '@/lib/notificationSound';
import type {
    AdminDashboardStats,
    AdminLowStockItem,
    AdminRecentOrder,
    AdminSalesPoint,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
        ],
    },
});

const props = defineProps<{
    stats: AdminDashboardStats;
    salesChart: AdminSalesPoint[];
    lowStock: AdminLowStockItem[];
    recentOrders: AdminRecentOrder[];
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as { city_id?: number | null; role?: string } | undefined);

// Local reactive state for realtime updates
const stats = ref<AdminDashboardStats>({ ...props.stats });
const recentOrders = ref<AdminRecentOrder[]>([...props.recentOrders]);
const salesChart = ref<AdminSalesPoint[]>([...props.salesChart]);
const isLiveConnected = ref(false);
const highlightedOrderNo = ref<string | null>(null);

// Keep synchronized if props change (e.g. Inertia visit)
watch(() => props.stats, (newVal) => { stats.value = { ...newVal }; });
watch(() => props.recentOrders, (newVal) => { recentOrders.value = [...newVal]; });
watch(() => props.salesChart, (newVal) => { salesChart.value = [...newVal]; });

const chartMetric = ref<'revenue' | 'orders'>('revenue');
const hoveredPoint = ref<AdminSalesPoint | null>(null);

const maxRevenue = computed(() => {
    const raw = Math.max(...salesChart.value.map((point) => point.revenue), 0);
    return raw > 0 ? raw : 100000;
});

const maxOrders = computed(() => {
    const raw = Math.max(...salesChart.value.map((point) => point.orders), 0);
    return raw > 0 ? raw : 10;
});

const total7DaysRevenue = computed(() => {
    return salesChart.value.reduce((sum, p) => sum + p.revenue, 0);
});

const total7DaysOrders = computed(() => {
    return salesChart.value.reduce((sum, p) => sum + p.orders, 0);
});

function triggerHighlight(orderNo: string): void {
    highlightedOrderNo.value = orderNo;
    setTimeout(() => {
        if (highlightedOrderNo.value === orderNo) {
            highlightedOrderNo.value = null;
        }
    }, 4000);
}

onMounted(() => {
    const echo = useEcho();
    if (!echo) {
        return;
    }

    const channel = echo.private('admin.orders');

    channel.subscribed(() => {
        isLiveConnected.value = true;
    });

    // 1. OrderCreated Event
    channel.listen(
        '.OrderCreated',
        (event: {
            order: AdminRecentOrder & { id: number; city_id?: number | null };
            message?: string;
        }) => {
            const incoming = event.order;

            // If user is a kabupaten admin, only accept orders matching their city
            if (currentUser.value?.role === 'kabupaten_admin') {
                if (currentUser.value.city_id && incoming.city_id !== currentUser.value.city_id) {
                    return;
                }
            }

            // Play notification sound
            playNotificationSound();

            // Notify user
            toast.success(`Pesanan Baru: ${incoming.order_no}`, {
                description: `${incoming.product_name ?? 'Paket'} - ${formatRupiah(incoming.total_amount)}`,
            });

            // Update stats
            stats.value.orders_today++;
            stats.value.total_orders++;

            // Update sales chart for today (last point in the array)
            if (salesChart.value.length > 0) {
                const todayPoint = salesChart.value[salesChart.value.length - 1];
                todayPoint.orders += 1;
            }

            // Prepend to recent orders
            recentOrders.value = [
                {
                    order_no: incoming.order_no,
                    product_name: incoming.product_name,
                    msisdn: incoming.msisdn,
                    total_amount: incoming.total_amount,
                    payment_status: incoming.payment_status,
                    redeem_status: incoming.redeem_status,
                    created_at: incoming.created_at,
                },
                ...recentOrders.value.slice(0, 9),
            ];

            triggerHighlight(incoming.order_no);
        },
    );

    // 2. OrderStatusUpdated Event
    channel.listen(
        '.OrderStatusUpdated',
        (event: {
            order: AdminRecentOrder & { id: number; city_id?: number | null };
            message?: string;
        }) => {
            const updated = event.order;

            if (currentUser.value?.role === 'kabupaten_admin') {
                if (currentUser.value.city_id && updated.city_id !== currentUser.value.city_id) {
                    return;
                }
            }

            // Find order in recent orders
            const index = recentOrders.value.findIndex((o) => o.order_no === updated.order_no);
            if (index !== -1) {
                const prev = recentOrders.value[index];
                const wasPaid = prev.payment_status === 'PAID';
                const isPaidNow = updated.payment_status === 'PAID';

                recentOrders.value[index] = {
                    ...prev,
                    payment_status: updated.payment_status,
                    redeem_status: updated.redeem_status,
                };

                // If freshly marked paid, update revenue stats
                if (!wasPaid && isPaidNow) {
                    stats.value.revenue_today += updated.total_amount;
                    stats.value.revenue_this_month += updated.total_amount;

                    if (salesChart.value.length > 0) {
                        const todayPoint = salesChart.value[salesChart.value.length - 1];
                        todayPoint.revenue += updated.total_amount;
                    }

                    toast.success(`Pembayaran Diterima: ${updated.order_no}`, {
                        description: `Total: ${formatRupiah(updated.total_amount)}`,
                    });
                }

                // If redeem became SUCCESS
                if (prev.redeem_status !== 'SUCCESS' && updated.redeem_status === 'SUCCESS') {
                    stats.value.redeemed_vouchers++;
                    if (stats.value.available_vouchers > 0) {
                        stats.value.available_vouchers--;
                    }
                }

                // If order failed
                if (
                    (prev.payment_status !== 'FAILED' && updated.payment_status === 'FAILED') ||
                    (prev.redeem_status !== 'FAILED' && updated.redeem_status === 'FAILED')
                ) {
                    stats.value.failed_orders++;
                }

                triggerHighlight(updated.order_no);
            }
        },
    );
});

onUnmounted(() => {
    const echo = useEcho();
    echo?.leave('admin.orders');
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <Heading
                title="Dashboard Admin"
                description="Ringkasan penjualan, stok voucher fisik, dan transaksi terbaru."
            />

            <!-- Realtime connection status badge -->
            <div class="flex items-center gap-2 self-start sm:self-auto rounded-full border bg-background/80 px-3 py-1 shadow-xs">
                <span class="relative flex size-2">
                    <span
                        class="absolute inline-flex size-full animate-ping rounded-full opacity-75"
                        :class="isLiveConnected ? 'bg-emerald-400' : 'bg-amber-400'"
                    ></span>
                    <span
                        class="relative inline-flex size-2 rounded-full"
                        :class="isLiveConnected ? 'bg-emerald-500' : 'bg-amber-500'"
                    ></span>
                </span>
                <span class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                    <Radio class="size-3" />
                    {{ isLiveConnected ? 'Realtime Aktif' : 'Menghubungkan...' }}
                </span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Wallet class="size-4" /> Omset Hari Ini
                    </CardDescription>
                    <CardTitle class="text-2xl transition-all">
                        {{ formatRupiah(stats.revenue_today) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Bulan ini: {{ formatRupiah(stats.revenue_this_month) }}
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <ShoppingCart class="size-4" /> Pesanan
                    </CardDescription>
                    <CardTitle class="text-2xl transition-all">
                        {{ stats.orders_today }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Total {{ stats.total_orders }} transaksi
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Ticket class="size-4" /> Stok Voucher
                    </CardDescription>
                    <CardTitle class="text-2xl transition-all">
                        {{ stats.available_vouchers }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    {{ stats.redeemed_vouchers }} voucher ter-redeem
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <AlertTriangle class="size-4" /> Perlu Perhatian
                    </CardDescription>
                    <CardTitle
                        class="text-2xl transition-all"
                        :class="
                            stats.failed_orders > 0
                                ? 'text-destructive'
                                : ''
                        "
                    >
                        {{ stats.failed_orders }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Pesanan gagal / manual review
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-2 pb-2">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="size-4 text-primary" /> Statistik 7 Hari Terakhir
                        </CardTitle>
                        <CardDescription>
                            Tren omset pendapatan dan volume transaksi harian.
                        </CardDescription>
                    </div>

                    <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="chartMetric === 'revenue' ? 'default' : 'ghost'"
                            class="h-7 px-2.5 text-xs font-medium"
                            @click="chartMetric = 'revenue'"
                        >
                            Omset (Rp)
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="chartMetric === 'orders' ? 'default' : 'ghost'"
                            class="h-7 px-2.5 text-xs font-medium"
                            @click="chartMetric = 'orders'"
                        >
                            Transaksi ({{ total7DaysOrders }})
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="pt-2">
                    <!-- Chart container with Y-axis grid and interactive bars -->
                    <div class="relative pt-6">
                        <!-- Y-axis guidelines -->
                        <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pb-6 pr-2">
                            <div class="flex items-center justify-between border-b border-dashed border-border/60 text-[10px] text-muted-foreground pb-1">
                                <span>{{ chartMetric === 'revenue' ? formatRupiah(maxRevenue) : `${maxOrders} pesanan` }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-dashed border-border/60 text-[10px] text-muted-foreground pb-1">
                                <span>{{ chartMetric === 'revenue' ? formatRupiah(maxRevenue / 2) : `${Math.ceil(maxOrders / 2)} pesanan` }}</span>
                            </div>
                            <div class="border-b border-border text-[10px] text-muted-foreground">
                                <span>0</span>
                            </div>
                        </div>

                        <!-- Bar chart columns -->
                        <div class="relative z-10 flex h-48 items-end justify-between gap-2 sm:gap-3 px-2 sm:px-4 pb-6">
                            <div
                                v-for="point in salesChart"
                                :key="point.date"
                                class="group relative flex flex-1 flex-col items-center h-full justify-end cursor-pointer"
                                @mouseenter="hoveredPoint = point"
                                @mouseleave="hoveredPoint = null"
                            >
                                <!-- Value badge above bar -->
                                <div class="mb-1.5 text-center min-h-[18px]">
                                    <span
                                        v-if="chartMetric === 'revenue' && point.revenue > 0"
                                        class="rounded bg-emerald-500/10 px-1 py-0.5 font-mono text-[9px] sm:text-[10px] font-bold text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-500/20"
                                    >
                                        {{ formatRupiah(point.revenue) }}
                                    </span>
                                    <span
                                        v-else-if="chartMetric === 'orders' && point.orders > 0"
                                        class="rounded bg-blue-500/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-blue-600 dark:text-blue-400 group-hover:bg-blue-500/20"
                                    >
                                        {{ point.orders }}x
                                    </span>
                                </div>

                                <!-- The Bar Column -->
                                <div class="w-full max-w-[42px] flex items-end justify-center h-full">
                                    <div
                                        class="w-full rounded-t-md transition-all duration-300"
                                        :class="[
                                            chartMetric === 'revenue'
                                                ? point.revenue > 0
                                                    ? 'bg-gradient-to-t from-emerald-600 to-teal-500 group-hover:from-emerald-500 group-hover:to-teal-400 shadow-xs'
                                                    : 'bg-muted/80 group-hover:bg-muted-foreground/30'
                                                : point.orders > 0
                                                    ? 'bg-gradient-to-t from-blue-600 to-indigo-500 group-hover:from-blue-500 group-hover:to-indigo-400 shadow-xs'
                                                    : 'bg-muted/80 group-hover:bg-muted-foreground/30'
                                        ]"
                                        :style="{
                                            height: chartMetric === 'revenue'
                                                ? `${point.revenue > 0 ? Math.max((point.revenue / maxRevenue) * 100, 10) : 4}%`
                                                : `${point.orders > 0 ? Math.max((point.orders / maxOrders) * 100, 10) : 4}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- X Axis dates -->
                        <div class="flex justify-between gap-2 sm:gap-3 px-2 sm:px-4 pt-1 border-t">
                            <div
                                v-for="point in salesChart"
                                :key="point.date"
                                class="flex-1 text-center"
                            >
                                <span class="text-[10px] sm:text-[11px] font-medium text-muted-foreground">
                                    {{ formatShortDay(point.date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Tooltip Card when hovering -->
                    <div
                        v-if="hoveredPoint"
                        class="mt-3 flex items-center justify-between rounded-lg border bg-card/90 p-2.5 shadow-xs text-xs animate-in fade-in-50"
                    >
                        <div class="font-medium text-foreground">
                            📅 {{ formatDate(hoveredPoint.date) }}
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4">
                            <span class="text-muted-foreground">
                                Omset: <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ formatRupiah(hoveredPoint.revenue) }}</strong>
                            </span>
                            <span class="text-muted-foreground">
                                Pesanan: <strong class="text-blue-600 dark:text-blue-400 font-semibold">{{ hoveredPoint.orders }} Transaksi</strong>
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-3 flex items-center justify-between rounded-lg border bg-muted/20 p-2.5 text-xs text-muted-foreground"
                    >
                        <span>Arahkan kursor ke grafik untuk melihat detail harian</span>
                        <div class="flex items-center gap-3">
                            <span>Total Omset: <strong class="text-foreground">{{ formatRupiah(total7DaysRevenue) }}</strong></span>
                            <span>•</span>
                            <span>Total Pesanan: <strong class="text-foreground">{{ total7DaysOrders }} Transaksi</strong></span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Boxes class="size-4" /> Stok Menipis
                    </CardTitle>
                    <CardDescription>
                        Produk dengan stok kurang dari 5 voucher.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="item in props.lowStock"
                        :key="item.id"
                        class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
                    >
                        <span class="truncate text-sm">{{ item.name }}</span>
                        <span
                            class="shrink-0 text-sm font-semibold"
                            :class="
                                item.available_stock === 0
                                    ? 'text-destructive'
                                    : 'text-amber-600'
                            "
                        >
                            {{ item.available_stock }}
                        </span>
                    </div>
                    <p
                        v-if="props.lowStock.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Semua stok produk aman.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="flex-row items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Receipt class="size-4" /> Transaksi Terbaru
                    </CardTitle>
                    <CardDescription>
                        {{ recentOrders.length }} pesanan terakhir (diperbarui otomatis secara realtime).
                    </CardDescription>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <a href="/admin/orders">
                        Lihat Semua
                        <ArrowRight class="size-4" />
                    </a>
                </Button>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2 font-medium">No. Order</th>
                                <th class="py-2 font-medium">Produk</th>
                                <th class="py-2 font-medium">MSISDN</th>
                                <th class="py-2 font-medium">Total</th>
                                <th class="py-2 font-medium">Bayar</th>
                                <th class="py-2 font-medium">Redeem</th>
                                <th class="py-2 font-medium">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in recentOrders"
                                :key="order.order_no"
                                class="border-t transition-colors duration-700"
                                :class="
                                    highlightedOrderNo === order.order_no
                                        ? 'bg-primary/15 dark:bg-primary/25'
                                        : ''
                                "
                            >
                                <td class="py-2 font-mono text-xs font-semibold">
                                    {{ order.order_no }}
                                </td>
                                <td class="py-2">{{ order.product_name ?? '-' }}</td>
                                <td class="py-2">{{ order.msisdn }}</td>
                                <td class="py-2">
                                    {{ formatRupiah(order.total_amount) }}
                                </td>
                                <td class="py-2">
                                    <StatusBadge
                                        :status="order.payment_status"
                                    />
                                </td>
                                <td class="py-2">
                                    <StatusBadge
                                        :status="order.redeem_status"
                                    />
                                </td>
                                <td class="py-2 text-xs text-muted-foreground">
                                    {{ formatDateTime(order.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="recentOrders.length === 0">
                                <td
                                    colspan="7"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    Belum ada transaksi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
