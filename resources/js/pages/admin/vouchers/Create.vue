<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ScanSearch,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCityMultiSelect, {
    type CityOption,
} from '@/components/SearchableCityMultiSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { VoucherCheckResult } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Voucher', href: '/admin/vouchers' },
            { title: 'Input Voucher', href: '/admin/vouchers/create' },
        ],
    },
});

const props = defineProps<{
    is_super_admin?: boolean;
    cities?: CityOption[];
}>();

const form = useForm({
    serial_number: '',
    hrn: '',
    sell_price: '',
    margin_percentage: '2',
    city_ids: [] as number[],
});

const calculatedFinalPrice = computed(() => {
    const base = Math.max(0, parseFloat(String(form.sell_price)) || 0);
    const margin = Math.max(0, parseFloat(String(form.margin_percentage)) || 0);
    return Math.round(base * (1 + margin / 100));
});

function onMarginInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    let val = target.value;
    if (val.includes('-')) {
        val = val.replace(/-/g, '');
        form.margin_percentage = val;
    }
    if (parseFloat(val) < 0) {
        form.margin_percentage = '0';
    }
}

const formattedFinalPrice = computed(() => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(calculatedFinalPrice.value);
});

const checkRequest = useHttp<{ serial_number: string }, VoucherCheckResult>({
    serial_number: '',
});

const preview = ref<VoucherCheckResult | null>(null);
const checkError = ref<string | null>(null);

async function checkSerial(): Promise<void> {
    if (!form.serial_number) {
        checkError.value = 'Isi serial number terlebih dahulu.';
        return;
    }

    checkRequest.serial_number = form.serial_number;
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

function submit(): void {
    form.post('/admin/vouchers');
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <a href="/admin/vouchers" aria-label="Kembali">
                    <ArrowLeft class="size-4" />
                </a>
            </Button>
            <Heading
                title="Input Voucher Fisik"
                description="Masukkan serial number dan HRN, lalu validasi ke Telkomsel."
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <form
                class="space-y-6 rounded-xl border p-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="serial_number">Serial Number (12 digit)</Label>
                    <div class="flex gap-2">
                        <Input
                            id="serial_number"
                            v-model="form.serial_number"
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
                    <InputError :message="form.errors.serial_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="hrn">HRN (17 digit)</Label>
                    <Input
                        id="hrn"
                        v-model="form.hrn"
                        inputmode="numeric"
                        maxlength="17"
                        placeholder="71125613431848001"
                    />
                    <InputError :message="form.errors.hrn" />
                    <p class="text-xs text-muted-foreground">
                        Kode rahasia disimpan terenkripsi dan tidak pernah
                        ditampilkan kembali.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="sell_price">Harga (Rp)</Label>
                        <Input
                            id="sell_price"
                            v-model="form.sell_price"
                            inputmode="numeric"
                            placeholder="10000"
                        />
                        <InputError :message="form.errors.sell_price" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="margin_percentage">Persentase Harga Jual (%)</Label>
                        <div class="relative">
                            <Input
                                id="margin_percentage"
                                v-model="form.margin_percentage"
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
                        <InputError :message="form.errors.margin_percentage" />
                    </div>
                </div>

                <div v-if="Number(form.sell_price) > 0" class="rounded-lg border bg-muted/40 p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Harga Jual di Aplikasi Mobile:</span>
                        <span class="text-base font-semibold text-emerald-600">
                            {{ formattedFinalPrice }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Kalkulasi: Rp {{ Number(form.sell_price).toLocaleString('id-ID') }} + {{ form.margin_percentage || 0 }}% margin
                    </p>
                </div>

                <div v-if="props.is_super_admin" class="grid gap-2">
                    <Label>Wilayah Berlaku</Label>
                    <SearchableCityMultiSelect
                        v-model="form.city_ids"
                        :cities="props.cities || []"
                        placeholder="Pilih satu atau beberapa wilayah..."
                    />
                    <InputError :message="form.errors.city_ids" />
                    <p class="text-xs text-muted-foreground">
                        Pilih kabupaten/kota yang dapat menjual voucher ini. Jika tidak memilih, sistem otomatis mendeteksi berdasarkan zona Telkomsel.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        Simpan Voucher
                    </Button>
                    <Button variant="outline" as-child>
                        <a href="/admin/vouchers">Batal</a>
                    </Button>
                </div>
            </form>

            <div class="rounded-xl border p-6">
                <h3 class="mb-4 font-medium">Preview Validasi Telkomsel</h3>

                <div
                    v-if="checkError"
                    class="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ checkError }}</span>
                </div>

                <div v-else-if="preview" class="space-y-3 text-sm">
                    <p
                        class="flex items-center gap-2 font-medium text-emerald-600"
                    >
                        <CheckCircle2 class="size-4" />
                        Voucher valid &amp; tersedia
                    </p>
                    <dl class="grid grid-cols-3 gap-y-2">
                        <dt class="text-muted-foreground">Nama Paket</dt>
                        <dd class="col-span-2">{{ preview.name || '-' }}</dd>
                        <dt class="text-muted-foreground">Kuota</dt>
                        <dd class="col-span-2">
                            {{ preview.description || '-' }}
                        </dd>
                        <dt class="text-muted-foreground">Masa Aktif</dt>
                        <dd class="col-span-2">
                            {{ preview.validity || '-' }} hari
                        </dd>
                        <dt class="text-muted-foreground">Kadaluarsa</dt>
                        <dd class="col-span-2">
                            {{ preview.expired_date || '-' }}
                        </dd>
                        <dt class="text-muted-foreground">Region</dt>
                        <dd class="col-span-2">{{ preview.region || '-' }}</dd>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="col-span-2">
                            {{ preview.status_message }} (kode
                            {{ preview.status_code }})
                        </dd>
                    </dl>
                </div>

                <div
                    v-else
                    class="flex h-40 items-center justify-center text-center text-sm text-muted-foreground"
                >
                    Klik "Cek" untuk memvalidasi serial number ke API
                    Telkomsel sebelum menyimpan.
                </div>
            </div>
        </div>
    </div>
</template>
