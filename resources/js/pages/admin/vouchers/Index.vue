<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    FileUp,
    Plus,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    AdminVoucher,
    Paginator,
    VoucherStatus,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Voucher', href: '/admin/vouchers' },
        ],
    },
});

const props = defineProps<{
    vouchers: Paginator<AdminVoucher>;
    filters: {
        status: string | null;
        search: string | null;
    };
    statusCounts: Record<VoucherStatus, number>;
}>();

const statusOptions: VoucherStatus[] = [
    'AVAILABLE',
    'RESERVED',
    'REDEEMED',
    'EXPIRED',
    'FAILED',
];

const filterState = reactive({
    status: props.filters.status ?? 'all',
    search: props.filters.search ?? '',
});

function applyFilters(): void {
    router.get(
        '/admin/vouchers',
        {
            status: filterState.status === 'all' ? undefined : filterState.status,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters(): void {
    filterState.status = 'all';
    filterState.search = '';
    applyFilters();
}

function destroy(voucher: AdminVoucher): void {
    router.delete(`/admin/vouchers/${voucher.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Inventori Voucher"
                description="Pantau status seluruh voucher fisik Telkomsel."
            />
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <a href="/admin/vouchers/bulk">
                        <FileUp class="size-4" />
                        Bulk Import
                    </a>
                </Button>
                <Button as-child>
                    <a href="/admin/vouchers/create">
                        <Plus class="size-4" />
                        Input Voucher
                    </a>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div
                v-for="status in statusOptions"
                :key="status"
                class="rounded-xl border p-3"
            >
                <p class="text-xs text-muted-foreground">{{ status }}</p>
                <p class="text-xl font-semibold">
                    {{ props.statusCounts[status] ?? 0 }}
                </p>
            </div>
        </div>

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <label class="text-xs text-muted-foreground" for="search">
                    Cari Serial / MSISDN
                </label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="filterState.search"
                        class="pl-8"
                        placeholder="Serial number atau nomor tujuan"
                    />
                </div>
            </div>

            <div class="grid gap-1">
                <label class="text-xs text-muted-foreground">Status</label>
                <Select v-model="filterState.status">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua status</SelectItem>
                        <SelectItem
                            v-for="status in statusOptions"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </SelectItem>
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
                        <th class="px-4 py-3 font-medium">Serial Number</th>
                        <th class="px-4 py-3 font-medium">Paket</th>
                        <th class="px-4 py-3 font-medium">Harga Jual</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Region</th>
                        <th class="px-4 py-3 font-medium">Kadaluarsa</th>
                        <th class="px-4 py-3 font-medium">MSISDN Tujuan</th>
                        <th class="px-4 py-3 font-medium">Ditambahkan</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="voucher in props.vouchers.data"
                        :key="voucher.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ voucher.serial_number }}
                        </td>
                        <td class="px-4 py-3">
                            {{ voucher.product_name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            {{
                                voucher.sell_price !== null
                                    ? formatRupiah(voucher.sell_price)
                                    : '-'
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="voucher.status" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ voucher.region ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ voucher.expired_date ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ voucher.redeemed_msisdn ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDateTime(voucher.created_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Dialog v-if="voucher.status === 'AVAILABLE'">
                                <DialogTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Hapus"
                                    >
                                        <Trash2
                                            class="size-4 text-destructive"
                                        />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Hapus voucher?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Voucher
                                            {{ voucher.serial_number }} akan
                                            dihapus dari inventori.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <DialogClose as-child>
                                            <Button variant="outline">
                                                Batal
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            @click="destroy(voucher)"
                                        >
                                            Hapus
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                            <span v-else class="text-xs text-muted-foreground">
                                -
                            </span>
                        </td>
                    </tr>
                    <tr v-if="props.vouchers.data.length === 0">
                        <td
                            colspan="9"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            Tidak ada voucher yang cocok dengan filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.vouchers" />
    </div>
</template>
