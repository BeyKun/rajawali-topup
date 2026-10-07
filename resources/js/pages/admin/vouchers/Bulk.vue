<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileUp, UploadCloud } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCityMultiSelect, {
    type CityOption,
} from '@/components/SearchableCityMultiSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

const form = useForm<{ file: File | null; city_ids: number[] }>({
    file: null,
    city_ids: [],
});

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit(): void {
    form.post('/admin/vouchers/bulk', { forceFormData: true });
}

function downloadTemplate(): void {
    const csv =
        'serial_number,hrn,sell_price\n300338120354,71125613431848001,25000\n';
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
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <a href="/admin/vouchers" aria-label="Kembali">
                    <ArrowLeft class="size-4" />
                </a>
            </Button>
            <Heading
                title="Bulk Import Voucher"
                description="Unggah file CSV berisi banyak serial number sekaligus."
            />
        </div>

        <div class="grid max-w-3xl gap-4">
            <form
                class="space-y-6 rounded-xl border p-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="file">File CSV</Label>
                    <Input
                        id="file"
                        type="file"
                        accept=".csv,.txt"
                        @change="onFileChange"
                    />
                    <InputError :message="form.errors.file" />
                </div>

                <div v-if="props.is_super_admin" class="grid gap-2">
                    <Label>Wilayah Berlaku</Label>
                    <SearchableCityMultiSelect
                        v-model="form.city_ids"
                        :cities="props.cities || []"
                        placeholder="Pilih satu atau beberapa wilayah untuk seluruh batch..."
                    />
                    <InputError :message="form.errors.city_ids" />
                    <p class="text-xs text-muted-foreground">
                        Wilayah yang dipilih akan diterapkan ke seluruh voucher yang diimpor dari file CSV ini.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
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
                    <UploadCloud class="size-4" /> Format File
                </h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    File CSV boleh tanpa header atau dengan header. Kolom
                    dipisahkan koma, titik koma, atau tab dengan urutan:
                    <span class="font-mono"
                        >serial_number, hrn, sell_price</span
                    >.
                </p>
                <pre
                    class="overflow-x-auto rounded-md bg-muted p-3 text-xs"
                >serial_number,hrn,sell_price
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
