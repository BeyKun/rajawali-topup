<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Package, Search, X } from '@lucide/vue';
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
import { formatRupiah } from '@/lib/format';
import type {
    AdminProductRow,
    AdminProductSummary,
    Paginator,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Katalog Paket', href: '/admin/products' },
        ],
    },
});

const props = defineProps<{
    products: Paginator<AdminProductRow>;
    filters: {
        search: string | null;
        stock: string | null;
    };
    summary: AdminProductSummary;
}>();

const filterState = reactive({
    search: props.filters.search ?? '',
    stock: props.filters.stock ?? 'all',
});

function applyFilters(): void {
    router.get(
        '/admin/products',
        {
            search: filterState.search || undefined,
            stock: filterState.stock === 'all' ? undefined : filterState.stock,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters(): void {
    filterState.search = '';
    filterState.stock = 'all';
    applyFilters();
}

function stockClass(stock: number): string {
    if (stock === 0) {
        return 'text-destructive';
    }

    if (stock <= 5) {
        return 'text-amber-600';
    }

    return 'text-foreground';
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Katalog Paket"
            description="Katalog paket voucher dan ketersediaan stok di sistem."
        />

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Total Paket</p>
                <p class="mt-1 text-2xl font-bold">
                    {{ props.summary.total_products }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Paket Aktif</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">
                    {{ props.summary.active_products }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Total Stok Siap Jual</p>
                <p class="mt-1 text-2xl font-bold">
                    {{ props.summary.available_stock }}
                </p>
            </div>
        </div>

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <label class="text-xs text-muted-foreground" for="search">
                    Cari Paket
                </label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="filterState.search"
                        class="pl-8"
                        placeholder="Nama paket, kuota, atau wilayah"
                    />
                </div>
            </div>

            <div class="grid min-w-36 gap-1">
                <label class="text-xs text-muted-foreground">Filter Stok</label>
                <Select v-model="filterState.stock">
                    <SelectTrigger>
                        <SelectValue placeholder="Semua Stok" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Stok</SelectItem>
                        <SelectItem value="low">Menipis (&le; 5)</SelectItem>
                        <SelectItem value="empty">Habis (0)</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button type="submit">
                <Search class="size-4" />
                Terapkan
            </Button>
            <Button type="button" variant="outline" @click="resetFilters">
                <X class="size-4" />
                Reset
            </Button>
        </form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama Paket</th>
                        <th class="px-4 py-3 font-medium">Deskripsi Kuota</th>
                        <th class="px-4 py-3 font-medium">Masa Aktif</th>
                        <th class="px-4 py-3 font-medium">Wilayah</th>
                        <th class="px-4 py-3 font-medium">Harga Jual</th>
                        <th class="px-4 py-3 font-medium">Stok Tersedia</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in props.products.data"
                        :key="product.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ product.name }}
                        </td>
                        <td class="max-w-64 px-4 py-3 text-muted-foreground">
                            {{ product.quota_description }}
                        </td>
                        <td class="px-4 py-3">
                            {{ product.validity_days }} hari
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ product.region ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium">
                            {{ formatRupiah(product.sell_price) }}
                        </td>
                        <td
                            class="px-4 py-3 font-semibold"
                            :class="stockClass(product.available_stock)"
                        >
                            {{ product.available_stock }}
                            <span
                                v-if="product.reserved_stock > 0"
                                class="text-xs font-normal text-amber-600"
                            >
                                (+{{ product.reserved_stock }} ditahan)
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="product.is_active ? 'ACTIVE' : 'INACTIVE'"
                            />
                        </td>
                    </tr>
                    <tr v-if="props.products.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <Package class="mx-auto mb-2 size-8 opacity-40" />
                            Belum ada paket. Tambahkan voucher untuk membuat
                            paket otomatis.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.products" />
    </div>
</template>
