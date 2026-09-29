<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Boxes, Package, Pencil, Search, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const editing = ref<AdminProductRow | null>(null);

const form = useForm({
    sell_price: '' as string | number,
    is_active: true as boolean,
    sort_order: 0 as number,
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

function openEdit(product: AdminProductRow): void {
    editing.value = product;
    form.clearErrors();
    form.sell_price = product.sell_price;
    form.is_active = product.is_active;
    form.sort_order = product.sort_order;
}

function submitEdit(): void {
    if (editing.value === null) {
        return;
    }

    form.put(`/admin/products/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function stockClass(available: number): string {
    if (available === 0) {
        return 'text-red-600';
    }

    if (available < 5) {
        return 'text-amber-600';
    }

    return 'text-emerald-600';
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Katalog Paket"
            description="Paket dikelompokkan otomatis dari voucher. Atur harga jual dan ketersediaan yang tampil di aplikasi."
        />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Total Paket</p>
                <p class="text-2xl font-semibold">
                    {{ props.summary.total_products }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Paket Aktif</p>
                <p class="text-2xl font-semibold">
                    {{ props.summary.active_products }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Stok Tersedia</p>
                <p class="text-2xl font-semibold">
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
                        placeholder="Nama paket, kuota, atau region"
                    />
                </div>
            </div>

            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">Stok</label>
                <Select v-model="filterState.stock">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Semua stok" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua stok</SelectItem>
                        <SelectItem value="low">Stok menipis</SelectItem>
                        <SelectItem value="empty">Stok kosong</SelectItem>
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
                        <th class="px-4 py-3 font-medium">Paket</th>
                        <th class="px-4 py-3 font-medium">Kuota</th>
                        <th class="px-4 py-3 font-medium">Masa Aktif</th>
                        <th class="px-4 py-3 font-medium">Region</th>
                        <th class="px-4 py-3 font-medium">Harga Jual</th>
                        <th class="px-4 py-3 font-medium">Stok</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
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
                        <td class="px-4 py-3 text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Edit"
                                @click="openEdit(product)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.products.data.length === 0">
                        <td
                            colspan="8"
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

    <Dialog
        :open="editing !== null"
        @update:open="(value: boolean) => !value && (editing = null)"
    >
        <DialogContent v-if="editing">
            <DialogHeader>
                <DialogTitle>Edit Paket</DialogTitle>
                <DialogDescription>
                    {{ editing.name }} &middot;
                    {{ editing.quota_description }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="sell_price">Harga Jual (Rp)</Label>
                    <Input
                        id="sell_price"
                        v-model="form.sell_price"
                        inputmode="numeric"
                    />
                    <p
                        v-if="form.errors.sell_price"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.sell_price }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="sort_order">Urutan Tampil</Label>
                    <Input
                        id="sort_order"
                        v-model="form.sort_order"
                        inputmode="numeric"
                    />
                    <p
                        v-if="form.errors.sort_order"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.sort_order }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Angka lebih kecil tampil lebih dulu di aplikasi.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Status</Label>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :variant="form.is_active ? 'default' : 'outline'"
                            size="sm"
                            @click="form.is_active = true"
                        >
                            <Boxes class="size-4" />
                            Aktif
                        </Button>
                        <Button
                            type="button"
                            :variant="!form.is_active ? 'default' : 'outline'"
                            size="sm"
                            @click="form.is_active = false"
                        >
                            Nonaktif
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Paket nonaktif tidak muncul di katalog aplikasi.
                    </p>
                </div>

                <div
                    class="rounded-lg border bg-muted/40 p-3 text-xs text-muted-foreground"
                >
                    Stok tersedia saat ini:
                    <span class="font-semibold text-foreground">
                        {{ editing.available_stock }}
                    </span>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="editing = null">
                    Batal
                </Button>
                <Button :disabled="form.processing" @click="submitEdit">
                    Simpan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
