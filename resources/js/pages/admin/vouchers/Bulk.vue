<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Camera,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    FileText,
    FileUp,
    ImageIcon,
    Loader2,
    Plus,
    RefreshCw,
    Sparkles,
    Trash2,
    UploadCloud,
    Wand2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCityMultiSelect, {
    type CityOption,
} from '@/components/SearchableCityMultiSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
}>();

const activeTab = ref<'photo' | 'csv'>('photo');

// -----------------------------------------------------------------------------
// Photo OCR State & Handlers
// -----------------------------------------------------------------------------
const photoFile = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);
const isScanning = ref(false);
const autoContrast = ref<boolean>(true);
const rawOcrText = ref<string>('');
const showRawOcr = ref<boolean>(false);
const scanProgress = ref<OcrProgress>({ status: '', progress: 0 });
const scannedRows = ref<ScannedVoucherRow[]>([]);
const defaultBatchPrice = ref<number>(25000);
const marginPercentage = ref<number>(0);
const selectedCityIds = ref<number[]>([]);

function onPhotoSelected(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    photoFile.value = file;
    photoPreviewUrl.value = URL.createObjectURL(file);
    startOcrScan(file);
}

async function startOcrScan(file: File): Promise<void> {
    isScanning.value = true;
    scanProgress.value = { status: 'Memulai OCR & optimasi gambar...', progress: 0.05 };
    rawOcrText.value = '';

    try {
        const rows = await scanVoucherImage(
            file,
            (p) => {
                scanProgress.value = p;
            },
            {
                autoContrast: autoContrast.value,
                scaleIfNeeded: true,
                rawTextCallback: (txt) => {
                    rawOcrText.value = txt;
                },
            },
        );

        if (rows.rawText) {
            rawOcrText.value = rows.rawText;
        }

        // Set default price and format with spaces for easy reading
        scannedRows.value = rows.map((r) => ({
            ...r,
            serial_number: formatSerialNumber(r.serial_number),
            hrn: formatHrn(r.hrn),
            sell_price: defaultBatchPrice.value,
        }));

        if (scannedRows.value.length > 0) {
            toast.success(`Berhasil mendeteksi ${scannedRows.value.length} voucher dari foto!`);
        } else {
            toast.warning('Tidak ada voucher yang terdeteksi secara otomatis. Silakan periksa ketajaman foto atau tambah baris manual.');
        }
    } catch (err) {
        console.error('OCR Error:', err);
        toast.error('Gagal memindai foto voucher. Pastikan gambar jelas dan tidak buram.');
    } finally {
        isScanning.value = false;
    }
}

function reScan(): void {
    if (photoFile.value) {
        startOcrScan(photoFile.value);
    }
}

function applyDefaultPriceToAll(): void {
    const price = Number(defaultBatchPrice.value) || 0;
    scannedRows.value.forEach((r) => {
        r.sell_price = price;
    });
    toast.info(`Harga Rp ${price.toLocaleString('id-ID')} diterapkan ke semua baris.`);
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
        row.serial_number = val;
    });
}

function onHrnInput(row: ScannedVoucherRow, event: Event): void {
    handleFormattedInput(event, formatHrn, (val) => {
        row.hrn = val;
    });
}

function removeRow(index: number): void {
    scannedRows.value.splice(index, 1);
}

function clearAllScanned(): void {
    scannedRows.value = [];
    photoFile.value = null;
    rawOcrText.value = '';
    showRawOcr.value = false;
    if (photoPreviewUrl.value) {
        URL.revokeObjectURL(photoPreviewUrl.value);
        photoPreviewUrl.value = null;
    }
}

const validVouchersCount = computed(() => {
    return scannedRows.value.filter(
        (r) => r.serial_number.replace(/\s+/g, '').length >= 12 && r.hrn.replace(/\s+/g, '').length >= 14,
    ).length;
});

const incompleteVouchersCount = computed(() => {
    return scannedRows.value.length - validVouchersCount.value;
});

const isPhotoSubmitValid = computed(() => {
    return (
        scannedRows.value.length > 0 &&
        scannedRows.value.every(
            (r) => cleanDigits(r.serial_number) !== '' && cleanDigits(r.hrn) !== '' && r.sell_price > 0,
        )
    );
});

const photoForm = useForm<{
    rows: Array<{ serial_number: string; hrn: string; sell_price: number }>;
    city_ids: number[];
    margin_percentage?: number;
}>({
    rows: [],
    city_ids: [],
    margin_percentage: 0,
});

function submitPhotoRows(): void {
    photoForm.rows = scannedRows.value.map((r) => {
        let serial = cleanDigits(r.serial_number);
        // Trim last 3 digits if 15 digits (keep only standard 12 digits)
        if (serial.length >= 15) {
            serial = serial.slice(0, 12);
        }
        return {
            serial_number: serial,
            hrn: cleanDigits(r.hrn),
            sell_price: Number(r.sell_price),
        };
    });
    photoForm.city_ids = selectedCityIds.value;
    photoForm.margin_percentage = marginPercentage.value || undefined;

    photoForm.post('/admin/vouchers/bulk', {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            toast.error(firstErr as string);
        },
    });
}

// -----------------------------------------------------------------------------
// CSV Form State & Handlers
// -----------------------------------------------------------------------------
const csvForm = useForm<{
    file: File | null;
    city_ids: number[];
    margin_percentage?: number;
}>({
    file: null,
    city_ids: [],
    margin_percentage: 0,
});

function onCsvChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    csvForm.file = target.files?.[0] ?? null;
}

function submitCsv(): void {
    csvForm.city_ids = selectedCityIds.value;
    csvForm.margin_percentage = marginPercentage.value || undefined;
    csvForm.post('/admin/vouchers/bulk', { forceFormData: true });
}

function downloadTemplate(): void {
    const csv = 'serial_number,hrn,sell_price\n300338120354,71125613431848001,25000\n';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'template-import-voucher.csv';
    link.click();
    URL.revokeObjectURL(url);
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
                Scan Foto Voucher (OCR Otomatis)
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
                    <strong>No Seri (3003...)</strong> dan <strong>Kode Voucher HRN</strong> di area garukan.
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

                <!-- Global Settings (Pricing, Margin, City) -->
                <div class="rounded-xl border bg-card p-4 space-y-4">
                    <h4 class="text-sm font-semibold flex items-center gap-2">
                        <Wand2 class="size-4 text-primary" /> Pengaturan Batch Voucher
                    </h4>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="default-price" class="text-xs">Harga Jual Dasar (Rp)</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="default-price"
                                    v-model.number="defaultBatchPrice"
                                    type="number"
                                    min="0"
                                    placeholder="Contoh: 25000"
                                    class="h-9 text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    class="h-9 shrink-0 text-xs"
                                    @click="applyDefaultPriceToAll"
                                >
                                    Terapkan
                                </Button>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="margin-pct" class="text-xs">Margin Tambahan (%)</Label>
                            <Input
                                id="margin-pct"
                                v-model.number="marginPercentage"
                                type="number"
                                min="0"
                                step="0.1"
                                placeholder="0"
                                class="h-9 text-xs"
                            />
                        </div>

                        <div v-if="props.is_super_admin" class="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                            <Label class="text-xs">Wilayah Berlaku</Label>
                            <SearchableCityMultiSelect
                                v-model="selectedCityIds"
                                :cities="props.cities || []"
                                placeholder="Pilih wilayah..."
                            />
                        </div>
                    </div>
                </div>

                <!-- Review & Edit Extracted Vouchers Table -->
                <div class="rounded-xl border bg-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/30 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-semibold">Hasil Deteksi Voucher</h4>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                {{ scannedRows.length }} Baris
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

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="rawOcrText"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs"
                                @click="showRawOcr = !showRawOcr"
                            >
                                <FileText class="size-3.5" />
                                {{ showRawOcr ? 'Sembunyikan Raw OCR' : 'Lihat Teks Mentah (Raw OCR)' }}
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs"
                                @click="addManualRow"
                            >
                                <Plus class="size-3.5" />
                                Tambah Baris Manual
                            </Button>
                        </div>
                    </div>

                    <!-- Collapsible Raw OCR Output for Inspection -->
                    <div
                        v-if="showRawOcr && rawOcrText"
                        class="border-b bg-muted/20 p-4 space-y-1.5"
                    >
                        <div class="flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Teks Mentah Hasil Pemindaian Tesseract:</span>
                            <span>{{ rawOcrText.length }} karakter</span>
                        </div>
                        <pre class="max-h-48 overflow-y-auto rounded-lg border bg-background/80 p-3 font-mono text-[11px] leading-relaxed select-all whitespace-pre-wrap text-foreground/90">{{ rawOcrText }}</pre>
                        <p class="text-[10px] text-muted-foreground">
                            Tip: Anda dapat memeriksa teks di atas jika nomor seri atau kode voucher pada fisik kartu tertutup noda atau buram.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/40 text-left text-muted-foreground border-b">
                                <tr>
                                    <th class="py-2.5 px-3 font-medium w-10">No</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[200px]">No. Seri Voucher</th>
                                    <th class="py-2.5 px-3 font-medium min-w-[220px]">Kode Voucher (HRN)</th>
                                    <th class="py-2.5 px-3 font-medium w-36">Harga Jual (Rp)</th>
                                    <th class="py-2.5 px-3 font-medium w-28">Status</th>
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
                                                class="h-8 font-mono text-xs"
                                                @input="onSerialInput(row, $event)"
                                            />
                                            <div class="flex items-center gap-1.5 text-[10px]">
                                                <span
                                                    :class="row.serial_number.replace(/\s+/g, '').length === 12
                                                        ? 'text-emerald-600 dark:text-emerald-400 font-medium'
                                                        : 'text-amber-600 dark:text-amber-400'"
                                                >
                                                    {{ row.serial_number.replace(/\s+/g, '').length }}/12 digit
                                                </span>
                                                <span v-if="row.serial_number.replace(/\s+/g, '').length === 15" class="text-muted-foreground">
                                                    (akan disimpan 12 digit)
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
                                                    :class="row.hrn.replace(/\s+/g, '').length >= 14 && row.hrn.replace(/\s+/g, '').length <= 17
                                                        ? 'text-emerald-600 dark:text-emerald-400 font-medium'
                                                        : 'text-amber-600 dark:text-amber-400 font-medium'"
                                                >
                                                    {{ row.hrn.replace(/\s+/g, '').length }} digit
                                                </span>
                                                <span v-if="row.hrn.replace(/\s+/g, '').length === 17" class="text-muted-foreground text-[10px]">
                                                    (17-digit Telkomsel)
                                                </span>
                                                <span v-else-if="row.hrn.replace(/\s+/g, '').length === 16" class="text-muted-foreground text-[10px]">
                                                    (16-digit standar)
                                                </span>
                                                <span v-else-if="row.hrn.replace(/\s+/g, '').length === 14" class="text-muted-foreground text-[10px]">
                                                    (14-digit Indosat)
                                                </span>
                                                <span v-else-if="row.hrn.trim() && row.hrn.replace(/\s+/g, '').length < 14" class="text-amber-600 dark:text-amber-400">
                                                    (kurang {{ 14 - row.hrn.replace(/\s+/g, '').length }})
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <Input
                                            v-model.number="row.sell_price"
                                            type="number"
                                            min="0"
                                            class="h-8 font-mono text-xs"
                                        />
                                    </td>
                                    <td class="py-2 px-3">
                                        <span
                                            v-if="row.serial_number.replace(/\s+/g, '').length >= 12 && row.hrn.replace(/\s+/g, '').length >= 14"
                                            class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                                        >
                                            <CheckCircle2 class="size-3.5" /> Siap
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                                        >
                                            <AlertCircle class="size-3.5" />
                                            {{ !row.serial_number.trim() ? 'SN Kosong' : (!row.hrn.trim() ? 'Kode Kosong' : 'Perlu Cek') }}
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
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        Belum ada baris voucher yang dipindai.
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

                <div class="grid gap-2">
                    <Label for="csv-margin">Margin Tambahan (%)</Label>
                    <Input
                        id="csv-margin"
                        v-model.number="marginPercentage"
                        type="number"
                        min="0"
                        step="0.1"
                        placeholder="0"
                    />
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
                        Mulai Import
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
                    class="mt-3"
                    @click="downloadTemplate"
                >
                    <Download class="size-4" />
                    Unduh Template
                </Button>
            </div>
        </div>
    </div>
</template>
