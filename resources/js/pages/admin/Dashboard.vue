<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Receipt,
    ShoppingCart,
    Ticket,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
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
import { formatDate, formatDateTime, formatRupiah, formatShortDay } from '@/lib/format';
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

const chartMetric = ref<'revenue' | 'orders'>('revenue');
const hoveredPoint = ref<AdminSalesPoint | null>(null);

const maxRevenue = computed(() => {
    const raw = Math.max(...props.salesChart.map((point) => point.revenue), 0);
    return raw > 0 ? raw : 100000;
});

const maxOrders = computed(() => {
    const raw = Math.max(...props.salesChart.map((point) => point.orders), 0);
    return raw > 0 ? raw : 10;
});

const total7DaysRevenue = computed(() => {
    return props.salesChart.reduce((sum, p) => sum + p.revenue, 0);
});

const total7DaysOrders = computed(() => {
    return props.salesChart.reduce((sum, p) => sum + p.orders, 0);
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Dashboard Admin"
            description="Ringkasan penjualan, stok voucher fisik, dan transaksi terbaru."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Wallet class="size-4" /> Omset Hari Ini
                    </CardDescription>
                    <CardTitle class="text-2xl">
                        {{ formatRupiah(props.stats.revenue_today) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Bulan ini: {{ formatRupiah(props.stats.revenue_this_month) }}
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <ShoppingCart class="size-4" /> Pesanan
                    </CardDescription>
                    <CardTitle class="text-2xl">
                        {{ props.stats.orders_today }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Total {{ props.stats.total_orders }} transaksi
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Ticket class="size-4" /> Stok Voucher
                    </CardDescription>
                    <CardTitle class="text-2xl">
                        {{ props.stats.available_vouchers }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    {{ props.stats.redeemed_vouchers }} voucher ter-redeem
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <AlertTriangle class="size-4" /> Perlu Perhatian
                    </CardDescription>
                    <CardTitle
                        class="text-2xl"
                        :class="
                            props.stats.failed_orders > 0
                                ? 'text-destructive'
                                : ''
                        "
                    >
                        {{ props.stats.failed_orders }}
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
                                v-for="point in props.salesChart"
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
                                v-for="point in props.salesChart"
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
                        {{ props.recentOrders.length }} pesanan terakhir.
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
                                v-for="order in props.recentOrders"
                                :key="order.order_no"
                                class="border-t"
                            >
                                <td class="py-2 font-mono text-xs">
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
                            <tr v-if="props.recentOrders.length === 0">
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
