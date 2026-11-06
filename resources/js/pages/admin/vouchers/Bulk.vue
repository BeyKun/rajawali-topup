<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Camera,
    CheckCircle2,
    DollarSign,
    Download,
    FileSpreadsheet,
    FileUp,
    ImageIcon,
    Loader2,
    Plus,
    RefreshCw,
    Sparkles,
    Trash2,
    UploadCloud,
    Wand2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCityMultiSelect, {
    type CityOption,
} from '@/components/SearchableCityMultiSelect.vue';
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
import { formatRupiah, formatThousands, parseThousands } from '@/lib/format';
import {
    type OcrProgress,
    type ScannedVoucherRow,
    scanVoucherImage,
} from '@/lib/voucherOcr';
import {
    cleanDigits,
    formatHrn,
    formatSerialNumber,
    handleFormattedInput,
} from '@/lib/voucherFormat';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Voucher', href: '/admin/vouchers' },
            { title: 'Bulk Import', href: '/admin/vouchers/bulk' },
        ],
    },
});

const props = defineProps<{
    is_super_admin?: boolean;
    cities?: CityOption[];
    default_margin?: number;
}>();

const fixedMargin = computed(() => props.default_margin ?? 2);

const activeTab = ref<'photo' | 'csv'>('photo');

// -----------------------------------------------------------------------------
// Photo OCR State & Handlers
// -----------------------------------------------------------------------------
const photoFile = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);
const isScanning = ref(false);
const autoContrast = ref<boolean>(true);
const scanProgress = ref<OcrProgress>({ status: '', progress: 0 });
const scannedRows = ref<ScannedVoucherRow[]>([]);
const defaultBatchPrice = ref<number>(0);
const selectedCityIds = ref<number[]>([]);

// -----------------------------------------------------------------------------
// Modals State
// -----------------------------------------------------------------------------
// 1. Set Satu Harga Modal
const showSetPriceDialog = ref(false);
const bulkPriceInput = ref('');
const bulkPriceError = ref('');

function openSetPriceDialog(): void {
    bulkPriceInput.value = defaultBatchPrice.value > 0 ? formatThousands(defaultBatchPrice.value) : '';
    bulkPriceError.value = '';
    showSetPriceDialog.value = true;
}

function applyBulkPrice(): void {
    const parsed = parseThousands(bulkPriceInput.value);
    if (parsed <= 0) {
        bulkPriceError.value = 'Harga voucher tidak boleh 0 atau kosong.';
        return;
    }
    bulkPriceError.value = '';
    defaultBatchPrice.value = parsed;
    scannedRows.value.forEach((r) => {
        r.sell_price = parsed;
    });
    showSetPriceDialog.value = false;
    toast.success(`Harga dasar Rp ${formatThousands(parsed)} diterapkan ke semua voucher.`);
}

// 2. Download Template Modal
const showDownloadTemplateDialog = ref(false);

function triggerTemplateDownload(): void {
    const csv = 'serial_number,hrn,sell_price\n300338120354,71125613431848001,25000\n';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'template-import-voucher.csv';
    link.click();
    URL.revokeObjectURL(url);
    showDownloadTemplateDialog.value = false;
    toast.success('Template CSV berhasil diunduh.');
}

// 3. Submit Progress & Result Modal
const showSubmitModal = ref(false);
const submitStatus = ref<'processing' | 'success' | 'error'>('processing');
const submitErrorMessage = ref('');
const submitSuccessCount = ref(0);

function onPhotoSelected(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    photoFile.value = file;
    photoPreviewUrl.value = URL.createObjectURL(file);
    startOcrScan(file);
}

function getXsrfToken(): string {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(?:XSRF-TOKEN)=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : '';
}

function scanPhotoWithServerOcr(
    file: File,
    onProgress: (p: OcrProgress) => void,
): Promise<{ vouchers: ScannedVoucherRow[]; raw_texts?: string[] }> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/vouchers/scan-photo');
        xhr.responseType = 'json';

        const token = getXsrfToken();
        if (token) {
            xhr.setRequestHeader('X-XSRF-TOKEN', token);
        }
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const pct = 0.1 + (e.loaded / e.total) * 0.4;
                onProgress({
                    status: 'Mengunggah foto voucher ke server OCR...',
                    progress: Math.min(pct, 0.5),
                });
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                const res = xhr.response;
                if (res && res.success) {
                    resolve({
                        vouchers: res.vouchers || [],
                        raw_texts: res.raw_texts || [],
                    });
                } else {
                    reject(new Error(res?.message || 'Gagal memproses gambar pada server OCR.'));
                }
            } else {
                const errDetail = xhr.response?.message || `Server OCR error (HTTP ${xhr.status})`;
                reject(new Error(errDetail));
            }
        };

        xhr.onerror = () => {
            reject(new Error('Koneksi jaringan ke endpoint OCR gagal.'));
        };

        xhr.onreadystatechange = () => {
            if (xhr.readyState === XMLHttpRequest.LOADING) {
                onProgress({
                    status: 'Memindai angka & nomor seri dengan RapidOCR...',
                    progress: 0.8,
                });
            }
        };

        const formData = new FormData();
        formData.append('image', file);
        onProgress({ status: 'Mempersiapkan pengunggahan...', progress: 0.1 });
        xhr.send(formData);
    });
}

async function startOcrScan(file: File): Promise<void> {
    isScanning.value = true;
    scanProgress.value = { status: 'Memulai OCR server...', progress: 0.05 };

    try {
        // Step 1: Attempt Server-side RapidOCR (High precision)
        const serverResult = await scanPhotoWithServerOcr(file, (p) => {
            scanProgress.value = p;
        });

        scannedRows.value = serverResult.vouchers.map((r) => {
            let serial = cleanDigits(r.serial_number);
            return {
                ...r,
                serial_number: formatSerialNumber(serial),
                hrn: formatHrn(r.hrn),
                sell_price: defaultBatchPrice.value,
            };
        });

        scanProgress.value = { status: 'Selesai!', progress: 1 };

        if (scannedRows.value.length > 0) {
            toast.success(`Berhasil mendeteksi ${scannedRows.value.length} voucher dengan RapidOCR!`);
        } else {
            toast.warning('Tidak ada voucher yang terdeteksi secara otomatis. Silakan periksa ketajaman foto atau tambah voucher manual.');
        }
    } catch (serverErr) {
        console.warn('Server OCR failed or unavailable, falling back to local Tesseract:', serverErr);
        toast.info('Layanan OCR server tidak merespon, beralih ke scanner browser lokal...');

        try {
            const rows = await scanVoucherImage(
                file,
                (p) => {
                    scanProgress.value = p;
                },
                {
                    autoContrast: autoContrast.value,
                    scaleIfNeeded: true,
                },
            );

            scannedRows.value = rows.map((r) => {
                let serial = cleanDigits(r.serial_number);
                return {
                    ...r,
                    serial_number: formatSerialNumber(serial),
                    hrn: formatHrn(r.hrn),
                    sell_price: defaultBatchPrice.value,
                };
            });

            if (scannedRows.value.length > 0) {
                toast.success(`Berhasil mendeteksi ${scannedRows.value.length} voucher dari foto!`);
            } else {
                toast.warning('Tidak ada voucher yang terdeteksi secara otomatis. Silakan periksa ketajaman foto atau tambah voucher manual.');
            }
        } catch (localErr) {
            console.error('All OCR attempts failed:', localErr);
            toast.error('Gagal memindai foto voucher. Pastikan gambar jelas dan tidak buram.');
        }
    } finally {
        isScanning.value = false;
    }
}

function reScan(): void {
    if (photoFile.value) {
        startOcrScan(photoFile.value);
    }
}

function addManualRow(): void {
    scannedRows.value.push({
        id: `manual-${Date.now()}`,
        serial_number: '',
        hrn: '',
        sell_price: defaultBatchPrice.value,
        serial_status: 'warning',
        hrn_status: 'warning',
    });
}

function onSerialInput(row: ScannedVoucherRow, event: Event): void {
    handleFormattedInput(event, formatSerialNumber, (val) => {
        let clean = cleanDigits(val);
        row.serial_number = formatSerialNumber(clean);
    });
}

function onHrnInput(row: ScannedVoucherRow, event: Event): void {
    handleFormattedInput(event, formatHrn, (val) => {
        row.hrn = val;
    });
}

function onRowPriceInput(row: ScannedVoucherRow, event: Event): void {
    const target = event.target as HTMLInputElement;
    row.sell_price = parseThousands(target.value);
}

function removeRow(index: number): void {
    scannedRows.value.splice(index, 1);
}

function clearAllScanned(): void {
    scannedRows.value = [];
    photoFile.value = null;
    if (photoPreviewUrl.value) {
        URL.revokeObjectURL(photoPreviewUrl.value);
        photoPreviewUrl.value = null;
    }
}

const validVouchersCount = computed(() => {
    return scannedRows.value.filter(
        (r) => cleanDigits(r.serial_number).length >= 12 && cleanDigits(r.hrn).length >= 14 && Number(r.sell_price || 0) > 0,
    ).length;
});

const incompleteVouchersCount = computed(() => {
    return scannedRows.value.length - validVouchersCount.value;
});

const isPhotoSubmitValid = computed(() => {
    return (
        scannedRows.value.length > 0 &&
        scannedRows.value.every(
            (r) => cleanDigits(r.serial_number).length >= 12 && cleanDigits(r.hrn) !== '' && Number(r.sell_price || 0) > 0,
        )
    );
});

const photoForm = useForm<{
    rows: Array<{ serial_number: string; hrn: string; sell_price: number }>;
    city_ids: number[];
}>({
    rows: [],
    city_ids: [],
});

function parseFriendlyError(errors: Record<string, any>): string {
    const messages: string[] = [];
    for (const key of Object.keys(errors)) {
        const val = errors[key];
        if (typeof val === 'string') {
            messages.push(val);
        } else if (Array.isArray(val)) {
            messages.push(val.join(' '));
        }
    }
    return messages.join('\n') || 'Terjadi kesalahan validasi saat menyimpan data voucher.';
}

function submitPhotoRows(): void {
    const preparedRows = scannedRows.value.map((r) => {
        let serial = cleanDigits(r.serial_number);
        return {
            serial_number: serial,
            hrn: cleanDigits(r.hrn),
            sell_price: Number(r.sell_price) || 0,
        };
    });

    const zeroPriceRows = preparedRows.filter((r) => r.sell_price <= 0);
    if (zeroPriceRows.length > 0) {
        showSubmitModal.value = true;
        submitStatus.value = 'error';
        submitErrorMessage.value = `Terdapat ${zeroPriceRows.length} voucher dengan harga Rp 0. Semua voucher harus memiliki harga jual lebih dari 0. Anda dapat mengisi harga langsung pada tabel atau menggunakan tombol "Set Satu Harga".`;
        return;
    }

    photoForm.rows = preparedRows;
    photoForm.city_ids = selectedCityIds.value;

    showSubmitModal.value = true;
    submitStatus.value = 'processing';
    submitErrorMessage.value = '';
    submitSuccessCount.value = preparedRows.length;

    photoForm.post('/admin/vouchers/bulk', {
        preserveScroll: true,
        onSuccess: () => {
            submitStatus.value = 'success';
        },
        onError: (errors) => {
            submitStatus.value = 'error';
            submitErrorMessage.value = parseFriendlyError(errors);
        },
    });
}

// -----------------------------------------------------------------------------
// CSV Form State & Handlers
// -----------------------------------------------------------------------------
const csvForm = useForm<{
    file: File | null;
    city_ids: number[];
}>({
    file: null,
    city_ids: [],
});

function onCsvChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    csvForm.file = target.files?.[0] ?? null;
}

function submitCsv(): void {
    if (!csvForm.file) {
        toast.error('Pilih berkas CSV terlebih dahulu.');
        return;
    }

    csvForm.city_ids = selectedCityIds.value;

    showSubmitModal.value = true;
    submitStatus.value = 'processing';
    submitErrorMessage.value = '';
    submitSuccessCount.value = 0;

    csvForm.post('/admin/vouchers/bulk', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            submitStatus.value = 'success';
        },
        onError: (errors) => {
            submitStatus.value = 'error';
            submitErrorMessage.value = parseFriendlyError(errors);
        },
    });
}

function goToVoucherIndex(): void {
    showSubmitModal.value = false;
    router.visit('/admin/vouchers');
}

function resetAndUploadMore(): void {
    showSubmitModal.value = false;
    clearAllScanned();
    csvForm.reset();
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header & Back Button -->
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <a href="/admin/vouchers" aria-label="Kembali">
                    <ArrowLeft class="size-4" />
                </a>
            </Button>
            <Heading
                title="Bulk Import Voucher"
                description="Scan fisik voucher langsung dari foto (deteksi No Seri & Kode Voucher otomatis) atau unggah file CSV."
            />
        </div>

        <!-- Mode Navigation Tabs -->
        <div class="flex items-center gap-2 border-b pb-2">
            <Button
                type="button"
                :variant="activeTab === 'photo' ? 'default' : 'ghost'"
                size="sm"
                class="gap-2 font-medium"
                @click="activeTab = 'photo'"
            >
                <Camera class="size-4" />
                Scan Foto Voucher
            </Button>
            <Button
                type="button"
                :variant="activeTab === 'csv' ? 'default' : 'ghost'"
                size="sm"
                class="gap-2 font-medium"
                @click="activeTab = 'csv'"
            >
                <FileSpreadsheet class="size-4" />
                Unggah File CSV
            </Button>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 1: SCAN FOTO VOUCHER (OCR) -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'photo'" class="space-y-6">
            <!-- Dropzone / Upload Box -->
            <div
                v-if="!photoPreviewUrl"
                class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-muted-foreground/30 bg-muted/10 p-8 text-center transition-all hover:bg-muted/20"
            >
                <input
                    id="voucher-photo-input"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="absolute inset-0 cursor-pointer opacity-0"
                    @change="onPhotoSelected"
                />
                <div class="mb-4 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Camera class="size-7" />
                </div>
                <h3 class="text-base font-semibold">
                    Ambil Foto atau Pilih Gambar Lembaran Voucher
                </h3>
                <p class="mt-1 max-w-md text-xs text-muted-foreground">
                    Format didukung: JPG, PNG, WEBP. Sistem akan otomatis mendeteksi baris
                    <strong>No Seri (12 digit)</strong> dan <strong>Kode Voucher HRN</strong> di area garukan.
                </p>
                <Button type="button" variant="outline" size="sm" class="mt-4 gap-2 pointer-events-none">
                    <ImageIcon class="size-4" />
                    Pilih File Gambar
                </Button>
            </div>

            <!-- Preview & Scanning Progress Section -->
            <div v-else class="space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <img
                            :src="photoPreviewUrl"
                            alt="Preview Voucher"
                            class="size-16 rounded-lg object-cover border shadow-2xs"
                        />
                        <div>
                            <p class="text-sm font-semibold truncate max-w-xs">
                                {{ photoFile?.name || 'Foto Voucher' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Ukuran: {{ ((photoFile?.size || 0) / 1024).toFixed(1) }} KB
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer select-none border rounded-lg px-2.5 py-1.5 bg-background hover:bg-muted/50">
                            <input
                                v-model="autoContrast"
                                type="checkbox"
                                class="rounded border-input text-primary focus:ring-primary size-3.5"
                                :disabled="isScanning"
                            />
                            <Sparkles class="size-3.5 text-amber-500" />
                            <span>Auto-Enhance Kontras</span>
                        </label>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="isScanning"
                            class="gap-1.5"
                            @click="reScan"
                        >
                            <RefreshCw class="size-3.5" :class="isScanning ? 'animate-spin' : ''" />
                            Scan Ulang
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="isScanning"
                            class="text-destructive hover:text-destructive"
                            @click="clearAllScanned"
                        >
                            Ganti Foto
                        </Button>
                    </div>
                </div>

                <!-- Scanning Progress Indicator -->
                <div v-if="isScanning" class="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-2">
                    <div class="flex items-center justify-between text-xs font-medium">
                        <span class="flex items-center gap-2 text-primary">
                            <Loader2 class="size-4 animate-spin" />
                            {{ scanProgress.status }}
                        </span>
                        <span class="font-mono text-muted-foreground">
                            {{ Math.round(scanProgress.progress * 100) }}%
                        </span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-primary/20">
                        <div
                            class="h-full bg-primary transition-all duration-300"
                            :style="{ width: `${Math.round(scanProgress.progress * 100)}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Poin 4: Pengaturan Batch Voucher hanya tampil untuk super admin saja dan cuma input wilayah berlaku -->
                <div v-if="props.is_super_admin" class="rounded-xl border bg-card p-4 space-y-3">
                    <h4 class="text-sm font-semibold flex items-center gap-2">
                        <Wand2 class="size-4 text-primary" /> Pengaturan Batch Voucher
                    </h4>

                    <div class="grid gap-1.5">
                        <Label class="text-xs">Wilayah Berlaku</Label>
                        <SearchableCityMultiSelect
                            v-model="selectedCityIds"
                            :cities="props.cities || []"
                            placeholder="Pilih wilayah kabupaten/kota berlaku..."
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Wilayah yang dipilih akan diterapkan ke seluruh voucher hasil scan ini. Jika kosong, sistem otomatis mendeteksi berdasarkan zona voucher.
                        </p>
                    </div>
                </div>

                <!-- Review & Edit Extracted Vouchers Table -->
                <div class="rounded-xl border bg-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/30 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-semibold">Hasil Deteksi Voucher</h4>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                {{ scannedRows.length }} Voucher
                            </span>
                            <span
                                v-if="scannedRows.length > 0 && validVouchersCount > 0"
                                class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ validVouchersCount }} Siap
                            </span>
                            <span
                                v-if="incompleteVouchersCount > 0"
                                class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400"
                            >
                                {{ incompleteVouchersCount }} Perlu Cek
                            </span>
                        </div>

                        <!-- Poin 8: Hilangkan button raw OCR. Poin 9: Tambahkan button Set Satu Harga. Poin 10: Ganti text Tambah Voucher Manual -->
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs"
                                @click="openSetPriceDialog"
                            >
                                <DollarSign class="size-3.5" />
                                Set Satu Harga
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs"
                                @click="addManualRow"
                            >
                                <Plus class="size-3.5" />
                                Tambah Voucher Manual
                            </Button>
                        </div>
                    </div>

                    <!-- Poin 5: Kolom harga jual di pisah jadi 2: Harga Jual Dasar dan Total Harga Jual -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/40 text-left text-muted-foreground border-b">
                                <tr>
                                    <th class="py-2.5 px-3 font-medium w-10">No</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[190px]">No. Seri Voucher</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[210px]">Kode Voucher (HRN)</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[150px]">Harga Jual Dasar (Rp)</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[150px]">Total Harga Jual (+{{ fixedMargin }}%)</th>
                                    <th class="py-2.5 px-3 font-medium w-24">Status</th>
                                    <th class="py-2.5 px-3 font-medium w-12 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="(row, idx) in scannedRows"
                                    :key="row.id"
                                    class="hover:bg-muted/20 transition-colors"
                                >
                                    <td class="py-2 px-3 font-mono text-muted-foreground">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="space-y-1">
                                            <Input
                                                :model-value="row.serial_number"
                                                placeholder="Contoh: 3003 3812 0354"
                                                maxlength="24"
                                                class="h-8 font-mono text-xs"
                                                @input="onSerialInput(row, $event)"
                                            />
                                            <div class="flex items-center gap-1.5 text-[10px]">
                                                <span
                                                    :class="cleanDigits(row.serial_number).length >= 12
                                                        ? 'text-emerald-600 dark:text-emerald-400 font-medium'
                                                        : 'text-amber-600 dark:text-amber-400'"
                                                >
                                                    {{ cleanDigits(row.serial_number).length }} digit
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="space-y-1">
                                            <Input
                                                :model-value="row.hrn"
                                                placeholder="Contoh: 71125 61343 18480 01"
                                                class="h-8 font-mono text-xs"
                                                @input="onHrnInput(row, $event)"
                                            />
                                            <div class="flex items-center gap-1.5 text-[10px]">
                                                <span
                                                    :class="cleanDigits(row.hrn).length >= 14 && cleanDigits(row.hrn).length <= 17
                                                        ? 'text-emerald-600 dark:text-emerald-400 font-medium'
                                                        : 'text-amber-600 dark:text-amber-400 font-medium'"
                                                >
                                                    {{ cleanDigits(row.hrn).length }} digit
                                                </span>
                                                <span v-if="cleanDigits(row.hrn).length === 17" class="text-muted-foreground text-[10px]">
                                                    (Telkomsel)
                                                </span>
                                                <span v-else-if="cleanDigits(row.hrn).length === 16" class="text-muted-foreground text-[10px]">
                                                    (16 digit)
                                                </span>
                                                <span v-else-if="cleanDigits(row.hrn).length === 14" class="text-muted-foreground text-[10px]">
                                                    (Indosat)
                                                </span>
                                                <span v-else-if="row.hrn.trim() && cleanDigits(row.hrn).length < 14" class="text-amber-600 dark:text-amber-400">
                                                    (kurang {{ 14 - cleanDigits(row.hrn).length }})
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Poin 6: Format currency untuk input harga -->
                                    <td class="py-2 px-3">
                                        <div class="relative">
                                            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-muted-foreground">Rp</span>
                                            <Input
                                                :model-value="formatThousands(row.sell_price)"
                                                inputmode="numeric"
                                                placeholder="0"
                                                :class="[
                                                    'h-8 pl-8 font-mono text-xs',
                                                    Number(row.sell_price || 0) <= 0 ? 'border-destructive focus-visible:ring-destructive' : ''
                                                ]"
                                                @input="onRowPriceInput(row, $event)"
                                            />
                                        </div>
                                    </td>
                                    <!-- Poin 5: Total Harga Jual (+ margin) -->
                                    <td class="py-2 px-3">
                                        <div class="font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ formatRupiah(Math.round(Number(row.sell_price || 0) * (1 + fixedMargin / 100))) }}
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span
                                            v-if="cleanDigits(row.serial_number).length === 12 && cleanDigits(row.hrn).length >= 14 && Number(row.sell_price || 0) > 0"
                                            class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                                        >
                                            <CheckCircle2 class="size-3.5" /> Siap
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                                        >
                                            <AlertCircle class="size-3.5" />
                                            {{ !cleanDigits(row.serial_number) ? 'SN Kosong' : (cleanDigits(row.serial_number).length !== 12 ? 'SN != 12' : (!cleanDigits(row.hrn) ? 'Kode Kosong' : (Number(row.sell_price || 0) <= 0 ? 'Harga Rp 0' : 'Perlu Cek'))) }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-destructive hover:bg-destructive/10"
                                            @click="removeRow(idx)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="scannedRows.length === 0">
                                    <td colspan="7" class="py-8 text-center text-muted-foreground">
                                        Belum ada voucher yang dipindai.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Action Controls -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/10 p-4">
                        <p class="text-xs text-muted-foreground">
                            Pastikan nomor seri dan HRN sudah sesuai dengan fisik voucher sebelum memulai import.
                        </p>

                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="photoForm.processing"
                                as-child
                            >
                                <a href="/admin/vouchers">Batal</a>
                            </Button>
                            <Button
                                type="button"
                                :disabled="!isPhotoSubmitValid || photoForm.processing || isScanning"
                                class="gap-2"
                                @click="submitPhotoRows"
                            >
                                <Loader2 v-if="photoForm.processing" class="size-4 animate-spin" />
                                <FileUp v-else class="size-4" />
                                Mulai Import ({{ scannedRows.length }} Voucher)
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 2: UNGGAH FILE CSV -->
        <!-- =================================================================== -->
        <div v-else class="grid max-w-3xl gap-4">
            <form
                class="space-y-6 rounded-xl border p-6"
                @submit.prevent="submitCsv"
            >
                <div class="grid gap-2">
                    <Label for="file">File CSV</Label>
                    <Input
                        id="file"
                        type="file"
                        accept=".csv,.txt"
                        @change="onCsvChange"
                    />
                    <InputError :message="csvForm.errors.file" />
                </div>

                <div v-if="props.is_super_admin" class="grid gap-2">
                    <Label>Wilayah Berlaku</Label>
                    <SearchableCityMultiSelect
                        v-model="selectedCityIds"
                        :cities="props.cities || []"
                        placeholder="Pilih satu atau beberapa wilayah untuk seluruh batch..."
                    />
                    <InputError :message="csvForm.errors.city_ids" />
                    <p class="text-xs text-muted-foreground">
                        Wilayah yang dipilih akan diterapkan ke seluruh voucher yang diimpor dari file CSV ini.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="csvForm.processing">
                        <FileUp class="size-4" />
                        Mulai Import CSV
                    </Button>
                    <Button variant="outline" as-child>
                        <a href="/admin/vouchers">Batal</a>
                    </Button>
                </div>
            </form>

            <div class="rounded-xl border p-6">
                <h3 class="mb-2 flex items-center gap-2 font-medium">
                    <UploadCloud class="size-4" /> Format File CSV
                </h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    File CSV boleh tanpa header atau dengan header. Kolom
                    dipisahkan koma, titik koma, atau tab dengan urutan:
                    <span class="font-mono">serial_number, hrn, sell_price</span>.
                </p>
                <pre class="overflow-x-auto rounded-md bg-muted p-3 text-xs">serial_number,hrn,sell_price
300338120354,71125613431848001,25000
300338120355,71125613431848002,25000</pre>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-3 gap-2"
                    @click="showDownloadTemplateDialog = true"
                >
                    <Download class="size-4" />
                    Unduh Template CSV
                </Button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 1: SET SATU HARGA (Poin 9) -->
    <!-- =================================================================== -->
    <Dialog v-model:open="showSetPriceDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <DollarSign class="size-5 text-primary" />
                    Set Satu Harga Jual Dasar
                </DialogTitle>
                <DialogDescription>
                    Harga ini akan diterapkan ke semua baris voucher di dalam tabel sekaligus.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3 py-2">
                <Label for="modal-bulk-price">Harga Jual Dasar (Rp)</Label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rp</span>
                    <Input
                        id="modal-bulk-price"
                        v-model="bulkPriceInput"
                        inputmode="numeric"
                        placeholder="Contoh: 25.000"
                        :class="[
                            'pl-9 font-mono',
                            bulkPriceError ? 'border-destructive focus-visible:ring-destructive' : ''
                        ]"
                        @input="(e: Event) => {
                            const val = (e.target as HTMLInputElement).value;
                            bulkPriceInput = formatThousands(val);
                            bulkPriceError = '';
                        }"
                    />
                </div>
                <p v-if="bulkPriceError" class="text-xs text-destructive">
                    {{ bulkPriceError }}
                </p>
                <div v-if="parseThousands(bulkPriceInput) > 0" class="rounded-lg border bg-muted/40 p-2.5 text-xs text-muted-foreground">
                    Total Harga Jual di Mobile (+{{ fixedMargin }}% margin):
                    <span class="font-semibold">
                        {{ formatRupiah(Math.round(parseThousands(bulkPriceInput) * (1 + fixedMargin / 100))) }}
                    </span>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="showSetPriceDialog = false">
                    Batal
                </Button>
                <Button :disabled="parseThousands(bulkPriceInput) <= 0" @click="applyBulkPrice">
                    Terapkan ke Semua Baris
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- =================================================================== -->
    <!-- MODAL 2: UNDUH TEMPLATE POPUP (Poin 1) -->
    <!-- =================================================================== -->
    <Dialog v-model:open="showDownloadTemplateDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Download class="size-5 text-primary" />
                    Unduh Template CSV Voucher
                </DialogTitle>
                <DialogDescription>
                    File CSV yang diunduh dapat langsung diedit di Excel atau text editor dengan format:
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 py-2 text-xs">
                <div class="rounded-md border bg-muted/30 p-3 font-mono text-[11px]">
                    <div>serial_number,hrn,sell_price</div>
                    <div class="text-muted-foreground">300338120354,71125613431848001,25000</div>
                </div>
                <ul class="list-disc pl-4 space-y-1 text-muted-foreground">
                    <li><strong>serial_number</strong>: Nomor seri voucher (12 digit angka).</li>
                    <li><strong>hrn</strong>: Kode voucher rahasia / garukan (14-17 digit angka).</li>
                    <li><strong>sell_price</strong>: Harga jual dasar voucher dalam Rupiah (angka saja).</li>
                </ul>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="showDownloadTemplateDialog = false">
                    Tutup
                </Button>
                <Button class="gap-1.5" @click="triggerTemplateDownload">
                    <Download class="size-4" />
                    Unduh Sekarang (.csv)
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- =================================================================== -->
    <!-- MODAL 3: PROGRESS & HASIL UPLOAD VOUCHER (Poin 11 & Poin 1) -->
    <!-- =================================================================== -->
    <Dialog :open="showSubmitModal">
        <DialogContent class="sm:max-w-md [&>button]:hidden">
            <!-- 1. State Processing -->
            <div v-if="submitStatus === 'processing'" class="py-6 text-center space-y-4">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Loader2 class="size-8 animate-spin" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold">Memproses Import Voucher...</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                        Sistem sedang memvalidasi nomor seri, mendeteksi duplikasi, dan menyiapkan data ke dalam inventori.
                    </p>
                </div>
            </div>

            <!-- 2. State Success -->
            <div v-else-if="submitStatus === 'success'" class="py-6 text-center space-y-4">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="size-8" />
                </div>
                <div class="space-y-1.5">
                    <h3 class="text-base font-semibold text-foreground">Import Voucher Berhasil Disubmit!</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                        Data voucher telah berhasil dikirim ke antrean sistem dan akan diverifikasi ke Telkomsel di background.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 pt-2">
                    <Button variant="outline" class="w-full sm:w-auto text-xs" @click="resetAndUploadMore">
                        Upload Lagi
                    </Button>
                    <Button class="w-full sm:w-auto text-xs gap-1.5" @click="goToVoucherIndex">
                        Ke Daftar Voucher
                    </Button>
                </div>
            </div>

            <!-- 3. State Error -->
            <div v-else class="py-6 text-center space-y-4">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <AlertCircle class="size-8" />
                </div>
                <div class="space-y-2">
                    <h3 class="text-base font-semibold text-destructive">Gagal Menyimpan Voucher</h3>
                    <div class="max-h-48 overflow-y-auto rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-left text-xs leading-relaxed text-destructive/90 whitespace-pre-wrap">
                        {{ submitErrorMessage }}
                    </div>
                </div>
                <div class="pt-2">
                    <Button variant="outline" class="w-full text-xs" @click="showSubmitModal = false">
                        Tutup dan Perbaiki Data
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
