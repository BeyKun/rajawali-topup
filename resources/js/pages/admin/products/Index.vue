<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Eye,
    FileSearch,
    FileText,
    Image as ImageIcon,
    Package,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatRupiah } from '@/lib/format';
import type {
    AdminProductRow,
    AdminProductSummary,
    Paginator,
} from '@/types';

export type OcrLogItem = {
    id: number;
    user_name: string;
    user_email?: string | null;
    user_role?: string | null;
    city_name: string;
    image_url: string;
    original_filename?: string | null;
    is_success: boolean;
    vouchers_count: number;
    extracted_vouchers: Array<{
        serial_number: string;
        hrn: string;
        confidence?: number;
        serial_status?: string;
        hrn_status?: string;
    }>;
    raw_texts: string[];
    error_message?: string | null;
    created_at?: string | null;
};

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
    is_super_admin?: boolean;
    ocr_logs?: Paginator<OcrLogItem> | null;
}>();

const activeTab = ref<'products' | 'ocr_logs'>('products');
const selectedLog = ref<OcrLogItem | null>(null);
const showDetailDialog = ref(false);

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

function destroy(product: AdminProductRow): void {
    router.delete(`/admin/products/${product.id}`, { preserveScroll: true });
}

function openLogDetail(log: OcrLogItem): void {
    selectedLog.value = log;
    showDetailDialog.value = true;
}

function formatDateTime(isoString?: string | null): string {
    if (!isoString) return '-';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return isoString;
    }
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Katalog Paket"
                description="Katalog paket voucher dan ketersediaan stok di sistem."
            />

            <!-- Tab Switcher for Super Admin -->
            <div v-if="props.is_super_admin" class="flex items-center rounded-lg border bg-muted/30 p-1">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        activeTab === 'products'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'products'"
                >
                    <Package class="size-3.5" />
                    <span>Daftar Paket</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                    :class="
                        activeTab === 'ocr_logs'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'ocr_logs'"
                >
                    <FileSearch class="size-3.5" />
                    <span>Log Scan OCR Voucher</span>
                    <Badge
                        v-if="props.ocr_logs?.total"
                        variant="secondary"
                        class="px-1.5 py-0 text-[10px]"
                    >
                        {{ props.ocr_logs.total }}
                    </Badge>
                </button>
            </div>
        </div>

        <!-- TAB 1: DAFTAR PAKET -->
        <template v-if="activeTab === 'products'">
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
                                <Dialog v-if="product.available_stock === 0">
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Hapus Paket"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Hapus Katalog Paket?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Katalog {{ product.name }} akan dihapus. Jika sudah ada riwayat transaksi, katalog akan diarsipkan.
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
                                                @click="destroy(product)"
                                            >
                                                Hapus
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    title="Paket dengan stok aktif tidak dapat dihapus"
                                >
                                    -
                                </span>
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
        </template>

        <!-- TAB 2: LOG SCAN OCR VOUCHER (SUPER ADMIN) -->
        <template v-else-if="props.is_super_admin">
            <div class="rounded-xl border bg-muted/20 p-4">
                <div class="flex items-center gap-2">
                    <FileSearch class="size-5 text-primary" />
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Log Pengecekan Foto Scan OCR</h3>
                        <p class="text-xs text-muted-foreground">
                            Histori foto fisik voucher yang diunggah oleh pengguna/admin wilayah beserta hasil pembacaan OCR untuk memastikan kecocokan hasil ekstraksi.
                        </p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Waktu Upload</th>
                            <th class="px-4 py-3 font-medium">Pengunggah</th>
                            <th class="px-4 py-3 font-medium">Wilayah</th>
                            <th class="px-4 py-3 font-medium">Foto Voucher</th>
                            <th class="px-4 py-3 font-medium">Status Scan</th>
                            <th class="px-4 py-3 font-medium">Terdeteksi</th>
                            <th class="px-4 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in props.ocr_logs?.data || []"
                            :key="log.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                {{ formatDateTime(log.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">{{ log.user_name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ log.user_email || log.user_role || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ log.city_name }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="group relative size-14 cursor-pointer overflow-hidden rounded-lg border bg-muted"
                                    @click="openLogDetail(log)"
                                >
                                    <img
                                        :src="log.image_url"
                                        :alt="log.original_filename || 'Voucher Photo'"
                                        class="size-full object-cover transition-transform group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Eye class="size-4 text-white" />
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="log.is_success"
                                    variant="outline"
                                    class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="mr-1 size-3" />
                                    Berhasil
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-destructive/30 bg-destructive/10 text-destructive"
                                >
                                    <AlertCircle class="mr-1 size-3" />
                                    Gagal
                                </Badge>
                            </td>
                            <td class="px-4 py-3 font-semibold">
                                {{ log.vouchers_count }} voucher
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1.5 text-xs"
                                    @click="openLogDetail(log)"
                                >
                                    <Eye class="size-3.5" />
                                    Bandingkan Hasil
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!props.ocr_logs?.data || props.ocr_logs.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <FileSearch class="mx-auto mb-2 size-8 opacity-40" />
                                Belum ada riwayat upload foto scan OCR.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination v-if="props.ocr_logs" :paginator="props.ocr_logs" />
        </template>

        <!-- MODAL DETAIL BANDINGKAN FOTO VS HASIL OCR -->
        <Dialog v-model:open="showDetailDialog">
            <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span>Pemeriksaan Hasil Scan OCR Voucher</span>
                        <Badge
                            v-if="selectedLog?.is_success"
                            variant="outline"
                            class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            Berhasil
                        </Badge>
                        <Badge
                            v-else
                            variant="outline"
                            class="border-destructive/30 bg-destructive/10 text-destructive"
                        >
                            Gagal
                        </Badge>
                    </DialogTitle>
                    <DialogDescription>
                        Bandingkan foto fisik voucher yang diunggah dengan hasil ekstraksi teks dan kode voucher.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedLog" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Sisi Kiri: Foto Asli -->
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between text-xs font-semibold text-muted-foreground">
                            <span>FOTO FISIK DIUNGGAH</span>
                            <a
                                :href="selectedLog.image_url"
                                target="_blank"
                                class="text-primary hover:underline"
                            >
                                Buka Ukuran Penuh
                            </a>
                        </div>
                        <div class="overflow-hidden rounded-xl border bg-black/5 dark:bg-black/40">
                            <img
                                :src="selectedLog.image_url"
                                :alt="selectedLog.original_filename || 'Voucher'"
                                class="max-h-[480px] w-full object-contain"
                            />
                        </div>
                        <p class="text-[11px] text-muted-foreground truncate">
                            File: {{ selectedLog.original_filename || '-' }}
                        </p>
                    </div>

                    <!-- Sisi Kanan: Hasil Ekstraksi -->
                    <div class="flex flex-col gap-4">
                        <div class="rounded-lg border bg-muted/30 p-3 text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Pengunggah:</span>
                                <span class="font-medium text-foreground">{{ selectedLog.user_name }} ({{ selectedLog.city_name }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Waktu Upload:</span>
                                <span class="font-medium text-foreground">{{ formatDateTime(selectedLog.created_at) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Total Voucher Ditemukan:</span>
                                <span class="font-semibold text-foreground">{{ selectedLog.vouchers_count }} baris</span>
                            </div>
                        </div>

                        <!-- Error Message jika Gagal -->
                        <div
                            v-if="!selectedLog.is_success && selectedLog.error_message"
                            class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                        >
                            <p class="font-semibold">Kendala Scan OCR:</p>
                            <p class="mt-0.5">{{ selectedLog.error_message }}</p>
                        </div>

                        <!-- Tabel Hasil Voucher -->
                        <div>
                            <h4 class="mb-2 text-xs font-semibold text-muted-foreground">
                                HASIL EKSTRAKSI KODE VOUCHER ({{ selectedLog.extracted_vouchers.length }})
                            </h4>
                            <div class="max-h-60 overflow-y-auto rounded-lg border">
                                <table class="w-full text-xs">
                                    <thead class="bg-muted/50 text-left">
                                        <tr>
                                            <th class="px-2.5 py-2 font-medium">#</th>
                                            <th class="px-2.5 py-2 font-medium">Nomor Seri</th>
                                            <th class="px-2.5 py-2 font-medium">HRN (Kode Gosok)</th>
                                            <th class="px-2.5 py-2 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(v, idx) in selectedLog.extracted_vouchers"
                                            :key="idx"
                                            class="border-t"
                                        >
                                            <td class="px-2.5 py-2 text-muted-foreground">{{ idx + 1 }}</td>
                                            <td class="px-2.5 py-2 font-mono font-medium text-foreground">
                                                {{ v.serial_number || '-' }}
                                            </td>
                                            <td class="px-2.5 py-2 font-mono text-muted-foreground">
                                                {{ v.hrn || '-' }}
                                            </td>
                                            <td class="px-2.5 py-2">
                                                <Badge
                                                    v-if="v.serial_status === 'valid' && v.hrn_status === 'valid'"
                                                    variant="outline"
                                                    class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 text-[10px]"
                                                >
                                                    Valid
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="outline"
                                                    class="border-amber-500/30 bg-amber-500/10 text-amber-600 text-[10px]"
                                                >
                                                    Warning
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="selectedLog.extracted_vouchers.length === 0">
                                            <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                                                Tidak ada voucher yang berhasil diekstrak.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Teks Mentah (Raw Texts) -->
                        <div v-if="selectedLog.raw_texts && selectedLog.raw_texts.length > 0">
                            <h4 class="mb-1 text-xs font-semibold text-muted-foreground flex items-center gap-1.5">
                                <FileText class="size-3.5" />
                                <span>TEKS MENTAH HASIL DETEKSI OCR</span>
                            </h4>
                            <div class="max-h-36 overflow-y-auto rounded-lg border bg-muted/40 p-2.5 text-[11px] font-mono leading-relaxed space-y-1">
                                <div
                                    v-for="(text, tIdx) in selectedLog.raw_texts"
                                    :key="tIdx"
                                    class="text-foreground"
                                >
                                    {{ text }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
