<script setup lang="ts">
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
import { formatRupiah, formatShortDay, formatDateTime } from '@/lib/format';
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

const maxRevenue = Math.max(...props.salesChart.map((point) => point.revenue), 1);
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
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="size-4" /> Omset 7 Hari Terakhir
                    </CardTitle>
                    <CardDescription>
                        Pendapatan harian dari transaksi lunas.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="flex h-44 items-end justify-between gap-2 border-b pt-4"
                    >
                        <div
                            v-for="point in props.salesChart"
                            :key="point.date"
                            class="flex flex-1 flex-col items-center gap-1"
                        >
                            <span class="text-[10px] text-muted-foreground">
                                {{ point.revenue > 0 ? formatRupiah(point.revenue) : '' }}
                            </span>
                            <div
                                class="w-full rounded-t bg-primary/80 transition-all"
                                :style="{
                                    height: `${Math.max((point.revenue / maxRevenue) * 100, 2)}%`,
                                }"
                                :title="`${point.orders} pesanan`"
                            ></div>
                        </div>
                    </div>
                    <div class="mt-1 flex justify-between gap-2">
                        <span
                            v-for="point in props.salesChart"
                            :key="point.date"
                            class="flex-1 text-center text-[11px] text-muted-foreground"
                        >
                            {{ formatShortDay(point.date) }}
                        </span>
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
