<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, ListFilter, Search, X } from '@lucide/vue';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, formatRupiah } from '@/lib/format';
import type {
    AdminOrderRow,
    Paginator,
    PaymentStatus,
    RedeemStatus,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Transaksi', href: '/admin/orders' },
        ],
    },
});

const props = defineProps<{
    orders: Paginator<AdminOrderRow>;
    filters: {
        order_no: string | null;
        msisdn: string | null;
        payment_status: string | null;
        redeem_status: string | null;
        date_from: string | null;
        date_to: string | null;
    };
    paymentStatuses: { value: PaymentStatus; label: string }[];
    redeemStatuses: { value: RedeemStatus; label: string }[];
}>();

const filterState = reactive({
    order_no: props.filters.order_no ?? '',
    msisdn: props.filters.msisdn ?? '',
    payment_status: props.filters.payment_status ?? 'all',
    redeem_status: props.filters.redeem_status ?? 'all',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

function applyFilters(): void {
    router.get(
        '/admin/orders',
        {
            order_no: filterState.order_no || undefined,
            msisdn: filterState.msisdn || undefined,
            payment_status:
                filterState.payment_status === 'all'
                    ? undefined
                    : filterState.payment_status,
            redeem_status:
                filterState.redeem_status === 'all'
                    ? undefined
                    : filterState.redeem_status,
            date_from: filterState.date_from || undefined,
            date_to: filterState.date_to || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters(): void {
    filterState.order_no = '';
    filterState.msisdn = '';
    filterState.payment_status = 'all';
    filterState.redeem_status = 'all';
    filterState.date_from = '';
    filterState.date_to = '';
    applyFilters();
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Monitoring Transaksi"
            description="Pantau pesanan masuk dan lakukan intervensi manual bila perlu."
        />

        <form
            class="grid gap-3 rounded-xl border p-4 md:grid-cols-3 lg:grid-cols-6"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">No. Order</label>
                <Input v-model="filterState.order_no" placeholder="RJW-..." />
            </div>
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">MSISDN</label>
                <Input v-model="filterState.msisdn" placeholder="628..." />
            </div>
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground"
                    >Status Bayar</label
                >
                <Select v-model="filterState.payment_status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Semua" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua</SelectItem>
                        <SelectItem
                            v-for="status in props.paymentStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground"
                    >Status Redeem</label
                >
                <Select v-model="filterState.redeem_status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Semua" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua</SelectItem>
                        <SelectItem
                            v-for="status in props.redeemStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">Dari</label>
                <Input v-model="filterState.date_from" type="date" />
            </div>
            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">Sampai</label>
                <Input v-model="filterState.date_to" type="date" />
            </div>

            <div class="flex items-center gap-2 md:col-span-3 lg:col-span-6">
                <Button type="submit">
                    <Search class="size-4" />
                    Terapkan
                </Button>
                <Button type="button" variant="outline" @click="resetFilters">
                    <X class="size-4" />
                    Reset
                </Button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">No. Order</th>
                        <th class="px-4 py-3 font-medium">Pengguna</th>
                        <th class="px-4 py-3 font-medium">Produk</th>
                        <th class="px-4 py-3 font-medium">MSISDN</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium">Bayar</th>
                        <th class="px-4 py-3 font-medium">Redeem</th>
                        <th class="px-4 py-3 font-medium">Retry</th>
                        <th class="px-4 py-3 font-medium">Waktu</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="order in props.orders.data"
                        :key="order.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ order.order_no }}
                        </td>
                        <td class="px-4 py-3">{{ order.user_name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            {{ order.product_name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">{{ order.msisdn }}</td>
                        <td class="px-4 py-3">
                            {{ formatRupiah(order.total_amount) }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="order.payment_status" />
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="order.redeem_status" />
                        </td>
                        <td class="px-4 py-3">{{ order.retry_count }}</td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ formatDateTime(order.created_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <a
                                    :href="`/admin/orders/${order.id}`"
                                    aria-label="Detail"
                                >
                                    <Eye class="size-4" />
                                </a>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.orders.data.length === 0">
                        <td
                            colspan="10"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <ListFilter
                                class="mx-auto mb-2 size-8 opacity-40"
                            />
                            Tidak ada transaksi yang cocok dengan filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.orders" />
    </div>
</template>
