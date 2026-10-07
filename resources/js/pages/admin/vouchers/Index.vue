<script setup lang="ts">
import { router, useForm, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    FileUp,
    Pencil,
    Plus,
    ScanSearch,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
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
import type {
    AdminVoucher,
    Paginator,
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

const editForm = useForm({
    serial_number: '',
    hrn: '',
    sell_price: '',
    margin_percentage: '2',
    status: 'AVAILABLE' as VoucherStatus,
    city_ids: [] as number[],
});

function openEdit(voucher: AdminVoucher): void {
    editingVoucher.value = voucher;
    editForm.clearErrors();
    editForm.serial_number = voucher.serial_number;
    editForm.hrn = '';
    editForm.sell_price = voucher.hpp_price != null ? String(voucher.hpp_price) : (voucher.sell_price != null ? String(voucher.sell_price) : '');
    editForm.margin_percentage = voucher.margin_percentage != null ? String(voucher.margin_percentage) : '2';
    editForm.status = voucher.status;
    editForm.city_ids = voucher.city_ids ? [...voucher.city_ids] : [];
    checkError.value = null;
    preview.value = null;
    showEditDialog.value = true;
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
    if (!editForm.serial_number) {
        checkError.value = 'Isi serial number terlebih dahulu.';
        return;
    }

    checkRequest.serial_number = editForm.serial_number;
    checkError.value = null;
    preview.value = null;

    try {
        const result = await checkRequest.post('/admin/vouchers/check');
        preview.value = result;

        if (!result.is_valid) {
            checkError.value = result.status_message;
        }
    } catch {
        checkError.value = 'Gagal menghubungi server. Silakan coba lagi.';
    }
}

function submitEdit(): void {
    if (!editingVoucher.value) return;

    editForm.put(`/admin/vouchers/${editingVoucher.value.id}`, {
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
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Edit Voucher"
                                    @click="openEdit(voucher)"
                                >
                                    <Pencil
                                        class="size-4 text-muted-foreground hover:text-foreground"
                                    />
                                </Button>
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
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        Edit Voucher
                    </DialogTitle>
                    <DialogDescription>
                        Perbarui detail voucher, harga jual, wilayah, atau status.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <Label for="edit_serial_number">Serial Number (12 digit)</Label>
                        <div class="flex gap-2">
                            <Input
                                id="edit_serial_number"
                                v-model="editForm.serial_number"
                                inputmode="numeric"
                                maxlength="12"
                                placeholder="300338120354"
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
                            v-model="editForm.hrn"
                            inputmode="numeric"
                            maxlength="17"
                            placeholder="Biarkan kosong jika tidak diubah"
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
                                    @keydown="(e) => { if (e.key === '-' || e.key === 'e' || e.key === 'E') e.preventDefault(); }"
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
