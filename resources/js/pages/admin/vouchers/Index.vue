<script setup lang="ts">
import { router, useForm, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    ChevronDown,
    FileUp,
    Pencil,
    Plus,
    ScanSearch,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCityMultiSelect, {
    type CityOption,
} from '@/components/SearchableCityMultiSelect.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatRupiah } from '@/lib/format';
import {
    cleanDigits,
    formatHrn,
    formatSerialNumber,
    handleFormattedInput,
} from '@/lib/voucherFormat';
import type {
    AdminVoucher,
    Paginator,
    ProductCatalogOption,
    VoucherCheckResult,
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
    is_super_admin?: boolean;
    cities?: CityOption[];
    products?: ProductCatalogOption[];
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

const editingVoucher = ref<AdminVoucher | null>(null);
const showEditDialog = ref(false);
const selectedProductOption = ref('manual');
const isProductSelectOpen = ref(false);
const productSelectRef = ref<HTMLElement | null>(null);
const productSearchInputRef = ref<HTMLInputElement | null>(null);
const productSearchQuery = ref('');

onClickOutside(productSelectRef, () => {
    isProductSelectOpen.value = false;
});

function toggleProductSelect(): void {
    isProductSelectOpen.value = !isProductSelectOpen.value;
    if (isProductSelectOpen.value) {
        productSearchQuery.value = '';
        nextTick(() => {
            productSearchInputRef.value?.focus();
        });
    }
}

function onProductOptionChange(val: string): void {
    selectedProductOption.value = val;
    if (val !== 'manual') {
        const product = props.products?.find((p) => String(p.id) === val);
        if (product) {
            editForm.product_id = product.id;
            editForm.title = product.name;
            editForm.description = product.quota_description;
        }
    } else {
        editForm.product_id = null;
    }
}

function selectProductOption(val: string): void {
    onProductOptionChange(val);
    isProductSelectOpen.value = false;
}

function formatProductLabel(product: ProductCatalogOption): string {
    const desc = product.quota_description ? ` — ${product.quota_description}` : '';
    const full = `${product.name}${desc}`;
    return full.length > 55 ? `${full.slice(0, 52)}...` : full;
}

const filteredProducts = computed(() => {
    const q = productSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.products || [];
    }
    return (props.products || []).filter((p) => {
        return (
            p.name.toLowerCase().includes(q) ||
            (p.quota_description || '').toLowerCase().includes(q)
        );
    });
});

const selectedProductDisplayLabel = computed(() => {
    if (selectedProductOption.value === 'manual') {
        return 'Edit Manual';
    }
    const product = props.products?.find((p) => String(p.id) === selectedProductOption.value);
    if (product) {
        return formatProductLabel(product);
    }
    return 'Pilih Paket atau Edit Manual';
});

const editForm = useForm({
    product_id: null as number | null,
    title: '',
    description: '',
    serial_number: '',
    hrn: '',
    sell_price: '',
    margin_percentage: '2',
    status: 'AVAILABLE' as VoucherStatus,
    city_ids: [] as number[],
});

function openEdit(voucher: AdminVoucher): void {
    if (voucher.status === 'REDEEMED') {
        return;
    }
    editingVoucher.value = voucher;
    editForm.clearErrors();
    isProductSelectOpen.value = false;
    productSearchQuery.value = '';
    const currentTitle = voucher.title ?? voucher.product_name ?? '';
    const currentDesc = voucher.description ?? '';
    editForm.title = currentTitle;
    editForm.description = currentDesc;

    const matchedProduct =
        (voucher.product_id != null
            ? props.products?.find((p) => p.id === voucher.product_id)
            : undefined) ??
        props.products?.find(
            (p) => p.name === currentTitle && p.quota_description === currentDesc,
        );
    if (matchedProduct) {
        selectedProductOption.value = String(matchedProduct.id);
        editForm.product_id = matchedProduct.id;
    } else {
        selectedProductOption.value = 'manual';
        editForm.product_id = null;
    }

    editForm.serial_number = formatSerialNumber(voucher.serial_number);
    editForm.hrn = '';
    editForm.sell_price = voucher.hpp_price != null ? String(voucher.hpp_price) : (voucher.sell_price != null ? String(voucher.sell_price) : '');
    editForm.margin_percentage = voucher.margin_percentage != null ? String(voucher.margin_percentage) : '2';
    editForm.status = voucher.status;
    editForm.city_ids = voucher.city_ids ? [...voucher.city_ids] : [];
    checkError.value = null;
    preview.value = null;
    showEditDialog.value = true;
}

function onEditSerialInput(event: Event): void {
    handleFormattedInput(event, formatSerialNumber, (val) => {
        editForm.serial_number = val;
    });
}

function onEditHrnInput(event: Event): void {
    handleFormattedInput(event, formatHrn, (val) => {
        editForm.hrn = val;
    });
}

const calculatedFinalPrice = computed(() => {
    const base = Math.max(0, parseFloat(String(editForm.sell_price)) || 0);
    const margin = Math.max(0, parseFloat(String(editForm.margin_percentage)) || 0);
    return Math.round(base * (1 + margin / 100));
});

const formattedFinalPrice = computed(() => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(calculatedFinalPrice.value);
});

function onMarginInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    let val = target.value;
    if (val.includes('-')) {
        val = val.replace(/-/g, '');
        editForm.margin_percentage = val;
    }
    if (parseFloat(val) < 0) {
        editForm.margin_percentage = '0';
    }
}

const checkRequest = useHttp<{ serial_number: string }, VoucherCheckResult>({
    serial_number: '',
});
const preview = ref<VoucherCheckResult | null>(null);
const checkError = ref<string | null>(null);

async function checkSerial(): Promise<void> {
    const cleanSerial = cleanDigits(editForm.serial_number);
    if (!cleanSerial) {
        checkError.value = 'Isi serial number terlebih dahulu.';
        return;
    }

    checkRequest.serial_number = cleanSerial;
    checkError.value = null;
    preview.value = null;

    try {
        const result = await checkRequest.post('/admin/vouchers/check');
        preview.value = result;

        if (!result.is_valid) {
            checkError.value = result.status_message;
        } else {
            if (!editForm.title && result.name) {
                editForm.title = result.name;
            }
            if (!editForm.description && result.description) {
                editForm.description = result.description;
            }
        }
    } catch {
        checkError.value = 'Gagal menghubungi server. Silakan coba lagi.';
    }
}

function submitEdit(): void {
    if (!editingVoucher.value) return;

    const price = Number(editForm.sell_price);
    if (isNaN(price) || price <= 0) {
        editForm.setError('sell_price', 'Harga voucher tidak boleh 0 atau kurang.');
        return;
    }

    editForm.transform((data) => ({
        ...data,
        product_id: selectedProductOption.value !== 'manual' ? data.product_id : null,
        title: data.title ? data.title.trim() : '',
        description: data.description ? data.description.trim() : '',
        serial_number: cleanDigits(data.serial_number),
        hrn: cleanDigits(data.hrn),
    })).put(`/admin/vouchers/${editingVoucher.value.serial_number}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editingVoucher.value = null;
        },
    });
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
                        <th class="px-4 py-3 font-medium">Judul & Deskripsi</th>
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
                            {{ formatSerialNumber(voucher.serial_number) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground">
                                {{ voucher.title || voucher.product_name || '-' }}
                            </div>
                            <div
                                v-if="voucher.description"
                                class="text-xs text-muted-foreground line-clamp-2 max-w-xs"
                                :title="voucher.description"
                            >
                                {{ voucher.description }}
                            </div>
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
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="voucher.status !== 'REDEEMED'"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Edit Voucher"
                                    @click="openEdit(voucher)"
                                >
                                    <Pencil
                                        class="size-4 text-muted-foreground hover:text-foreground"
                                    />
                                </Button>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground italic px-2"
                                    title="Voucher yang sudah diredeem tidak dapat diedit atau dihapus"
                                >
                                    Terkunci
                                </span>
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
                            </div>
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

        <!-- Dialog Edit Voucher -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent class="max-w-xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        Edit Voucher
                    </DialogTitle>
                    <DialogDescription>
                        Perbarui detail voucher, judul, deskripsi, harga jual, wilayah, atau status.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <!-- Searchable Dropdown Pilihan Paket / Edit Manual -->
                    <div class="grid gap-2">
                        <Label for="edit_product_select">Katalog Paket (Judul & Deskripsi)</Label>
                        <div ref="productSelectRef" class="relative">
                            <button
                                type="button"
                                id="edit_product_select"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
                                @click="toggleProductSelect"
                            >
                                <span
                                    v-if="selectedProductOption === 'manual'"
                                    class="flex min-w-0 items-center gap-2 font-medium text-primary"
                                >
                                    <Pencil class="size-4 shrink-0" />
                                    <span class="truncate">Edit Manual</span>
                                </span>
                                <span
                                    v-else
                                    class="min-w-0 truncate text-left"
                                    :class="{ 'text-muted-foreground': !selectedProductOption }"
                                    :title="editForm.title ? `${editForm.title} — ${editForm.description}` : ''"
                                >
                                    {{ selectedProductDisplayLabel }}
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 opacity-50 transition-transform"
                                    :class="{ 'rotate-180': isProductSelectOpen }"
                                />
                            </button>

                            <!-- Dropdown Menu / Popover -->
                            <div
                                v-if="isProductSelectOpen"
                                class="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-72 w-full rounded-md border shadow-md outline-none animate-in fade-in-0 zoom-in-95"
                            >
                                <!-- Search Input -->
                                <div class="border-b p-2">
                                    <div class="relative flex items-center">
                                        <Search class="text-muted-foreground absolute left-2.5 size-4" />
                                        <input
                                            ref="productSearchInputRef"
                                            v-model="productSearchQuery"
                                            type="text"
                                            placeholder="Cari nama paket atau kuota..."
                                            class="border-input bg-background focus:ring-ring h-8 w-full rounded-md border pl-8 pr-7 text-xs focus:ring-1 focus:outline-none"
                                        />
                                        <button
                                            v-if="productSearchQuery"
                                            type="button"
                                            class="text-muted-foreground hover:text-foreground absolute right-2"
                                            @click="productSearchQuery = ''"
                                        >
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Options List -->
                                <div class="max-h-56 overflow-y-auto p-1 text-sm">
                                    <!-- Option: Edit Manual (dengan Lucide icon Pencil) -->
                                    <button
                                        type="button"
                                        class="hover:bg-accent hover:text-accent-foreground flex w-full cursor-pointer items-center justify-between gap-2 rounded-sm px-2.5 py-1.5 text-left text-sm font-medium text-primary"
                                        @click="selectProductOption('manual')"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Pencil class="size-4 shrink-0 text-primary" />
                                            <span>Edit Manual</span>
                                        </div>
                                        <Check
                                            v-if="selectedProductOption === 'manual'"
                                            class="size-4 shrink-0 text-primary"
                                        />
                                    </button>

                                    <div class="my-1 border-t"></div>

                                    <!-- List of filtered products -->
                                    <div
                                        v-if="filteredProducts.length === 0"
                                        class="py-4 text-center text-xs text-muted-foreground"
                                    >
                                        Tidak ada paket yang cocok dengan "{{ productSearchQuery }}"
                                    </div>
                                    <button
                                        v-for="product in filteredProducts"
                                        :key="product.id"
                                        type="button"
                                        class="hover:bg-accent hover:text-accent-foreground flex w-full min-w-0 cursor-pointer items-center justify-between gap-2 rounded-sm px-2.5 py-1.5 text-left text-sm"
                                        :title="`${product.name} — ${product.quota_description}`"
                                        @click="selectProductOption(String(product.id))"
                                    >
                                        <span class="min-w-0 flex-1 truncate">
                                            {{ formatProductLabel(product) }}
                                        </span>
                                        <Check
                                            v-if="selectedProductOption === String(product.id)"
                                            class="size-4 shrink-0 text-primary"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Input manual judul & deskripsi: hanya muncul jika 'Edit Manual' dipilih -->
                    <div
                        v-if="selectedProductOption === 'manual'"
                        class="space-y-4 rounded-lg border bg-muted/20 p-3.5"
                    >
                        <div class="grid gap-2">
                            <Label for="edit_title">Judul Voucher</Label>
                            <Input
                                id="edit_title"
                                v-model="editForm.title"
                                placeholder="Contoh: Voucher Fisik Telkomsel - 1 Hari"
                            />
                            <InputError :message="editForm.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_description">Deskripsi Voucher</Label>
                            <textarea
                                id="edit_description"
                                :value="editForm.description"
                                rows="2"
                                placeholder="Contoh: 4 GB & 3 GB Local Internet, 20 Menit Telepon"
                                class="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-none"
                                @input="editForm.description = ($event.target as HTMLTextAreaElement).value"
                            ></textarea>
                            <InputError :message="editForm.errors.description" />
                        </div>
                    </div>

                    <!-- Ringkasan paket yang dipilih dari dropdown -->
                    <div
                        v-else
                        class="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground"
                    >
                        <div class="font-medium text-foreground">
                            {{ editForm.title || '-' }}
                        </div>
                        <div class="mt-0.5">
                            {{ editForm.description || '-' }}
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_serial_number">Serial Number (12 digit)</Label>
                        <div class="flex gap-2">
                            <Input
                                id="edit_serial_number"
                                :model-value="editForm.serial_number"
                                inputmode="numeric"
                                maxlength="14"
                                placeholder="3003 3812 0354"
                                @input="onEditSerialInput"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="checkRequest.processing"
                                @click="checkSerial"
                            >
                                <Spinner v-if="checkRequest.processing" />
                                <ScanSearch v-else class="size-4" />
                                Cek
                            </Button>
                        </div>
                        <InputError :message="editForm.errors.serial_number" />

                        <div
                            v-if="checkError"
                            class="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-2 text-xs text-destructive"
                        >
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            <span>{{ checkError }}</span>
                        </div>
                        <div
                            v-else-if="preview"
                            class="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-2 text-xs text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5 shrink-0" />
                            <span>{{ preview.name }} ({{ preview.validity }} hari) - {{ preview.status_message }}</span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_hrn">HRN (17 digit)</Label>
                        <Input
                            id="edit_hrn"
                            :model-value="editForm.hrn"
                            inputmode="numeric"
                            maxlength="20"
                            placeholder="Contoh: 71125 61343 18480 01 (kosongkan jika tidak diubah)"
                            @input="onEditHrnInput"
                        />
                        <InputError :message="editForm.errors.hrn" />
                        <p class="text-xs text-muted-foreground">
                            Kosongkan jika tidak ingin mengubah kode HRN yang tersimpan.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_sell_price">Harga (Rp)</Label>
                            <Input
                                id="edit_sell_price"
                                v-model="editForm.sell_price"
                                inputmode="numeric"
                                placeholder="10000"
                            />
                            <InputError :message="editForm.errors.sell_price" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_margin_percentage">Persentase Harga Jual (%)</Label>
                            <div class="relative">
                                <Input
                                    id="edit_margin_percentage"
                                    v-model="editForm.margin_percentage"
                                    type="number"
                                    min="0"
                                    step="any"
                                    placeholder="2"
                                    class="pr-8"
                                    @keydown="(e: KeyboardEvent) => { if (e.key === '-' || e.key === 'e' || e.key === 'E') e.preventDefault(); }"
                                    @input="onMarginInput"
                                />
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                            </div>
                            <InputError :message="editForm.errors.margin_percentage" />
                        </div>
                    </div>

                    <div v-if="Number(editForm.sell_price) > 0" class="rounded-lg border bg-muted/40 p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Harga Jual di Aplikasi Mobile:</span>
                            <span class="text-base font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ formattedFinalPrice }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Kalkulasi: Rp {{ Number(editForm.sell_price).toLocaleString('id-ID') }} + {{ editForm.margin_percentage || 0 }}% margin
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_status">Status</Label>
                        <Select v-model="editForm.status">
                            <SelectTrigger id="edit_status">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statusOptions"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="editForm.errors.status" />
                    </div>

                    <div v-if="props.is_super_admin" class="grid gap-2">
                        <Label>Wilayah Berlaku</Label>
                        <SearchableCityMultiSelect
                            v-model="editForm.city_ids"
                            :cities="props.cities || []"
                            placeholder="Pilih satu atau beberapa wilayah..."
                        />
                        <InputError :message="editForm.errors.city_ids" />
                        <p class="text-xs text-muted-foreground">
                            Pilih kabupaten/kota yang dapat menjual voucher ini.
                        </p>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            Batal
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            Simpan Perubahan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
