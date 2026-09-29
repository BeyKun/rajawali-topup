<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ScanSearch,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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

const form = useForm({
    serial_number: '',
    hrn: '',
    sell_price: '',
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

                <div class="grid gap-2">
                    <Label for="sell_price">Harga Jual (Rp)</Label>
                    <Input
                        id="sell_price"
                        v-model="form.sell_price"
                        inputmode="numeric"
                        placeholder="25000"
                    />
                    <InputError :message="form.errors.sell_price" />
                    <p class="text-xs text-muted-foreground">
                        Harga ini dipakai untuk paket sesuai hasil validasi
                        Telkomsel di samping.
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
