<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import RegionSelect from '@/components/RegionSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/admin/Pagination.vue';
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
import type { AdminAreaRow, Paginator } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Mapping Area', href: '/admin/areas' },
        ],
    },
});

const props = defineProps<{
    areas: Paginator<AdminAreaRow>;
    filters: { search: string | null };
}>();

const filterState = reactive({
    search: props.filters.search ?? '',
});

const editing = ref<AdminAreaRow | null>(null);
const showDialog = ref(false);
const selectedProvince = ref<string | number>('');

const form = useForm({
    region: '',
    city_id: '' as string | number,
});

function applyFilters(): void {
    router.get(
        '/admin/areas',
        { search: filterState.search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function openCreate(): void {
    editing.value = null;
    selectedProvince.value = '';
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(area: AdminAreaRow): void {
    editing.value = area;
    selectedProvince.value = area.province_id ?? '';
    form.clearErrors();
    form.region = area.region;
    form.city_id = area.city_id;
    showDialog.value = true;
}

function submit(): void {
    if (editing.value === null) {
        form.post('/admin/areas', {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });

        return;
    }

    form.put(`/admin/areas/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            editing.value = null;
        },
    });
}

function destroy(area: AdminAreaRow): void {
    if (!confirm(`Hapus mapping zona "${area.region}"?`)) {
        return;
    }

    router.delete(`/admin/areas/${area.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                title="Mapping Area Telkomsel"
                description="Petakan kode zona Telkomsel (mis. SIKKA) ke kabupaten/kota agar katalog paket tampil sesuai wilayah outlet."
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Mapping
            </Button>
        </div>

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <label class="text-xs text-muted-foreground" for="search">
                    Cari Zona / Kabupaten
                </label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="filterState.search"
                        class="pl-8"
                        placeholder="Zona atau nama kabupaten"
                    />
                </div>
            </div>
            <Button type="submit">
                <Search class="size-4" />
                Terapkan
            </Button>
            <Button
                type="button"
                variant="outline"
                @click="
                    filterState.search = '';
                    applyFilters();
                "
            >
                <X class="size-4" />
                Reset
            </Button>
        </form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Zona Telkomsel</th>
                        <th class="px-4 py-3 font-medium">Provinsi</th>
                        <th class="px-4 py-3 font-medium">Kabupaten/Kota</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="area in props.areas.data" :key="area.id" class="border-t">
                        <td class="px-4 py-3 font-medium">{{ area.region }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ area.province_name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">{{ area.city_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Edit"
                                @click="openEdit(area)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Hapus"
                                @click="destroy(area)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.areas.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <MapPin class="mx-auto mb-2 size-8 opacity-40" />
                            Belum ada mapping area.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.areas" />
    </div>

    <Dialog v-model:open="showDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? 'Edit Mapping Area' : 'Tambah Mapping Area' }}
                </DialogTitle>
                <DialogDescription>
                    Zona Telkomsel dipetakan ke satu kabupaten/kota.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="region">Kode Zona Telkomsel</Label>
                    <Input
                        id="region"
                        v-model="form.region"
                        placeholder="Contoh: SIKKA"
                    />
                    <InputError :message="form.errors.region" />
                </div>

                <div class="grid gap-2">
                    <Label>Provinsi</Label>
                    <RegionSelect
                        id="area_province"
                        name="province"
                        v-model="selectedProvince"
                        endpoint="/admin/api/regions/provinces"
                        value-key="id"
                        :display-label="editing?.province_name ?? null"
                        placeholder="Pilih Provinsi"
                        @change="form.city_id = ''"
                    />
                </div>

                <div class="grid gap-2">
                    <Label>Kabupaten/Kota</Label>
                    <RegionSelect
                        id="area_city"
                        name="city_id"
                        v-model="form.city_id"
                        endpoint="/admin/api/regions/cities"
                        value-key="id"
                        :display-label="editing?.city_name ?? null"
                        parent-param-name="province"
                        :parent-value="selectedProvince"
                        placeholder="Pilih Kabupaten/Kota"
                    />
                    <InputError :message="form.errors.city_id" />
                    <p class="text-xs text-muted-foreground">
                        Gunakan pencarian untuk memilih kabupaten dengan cepat.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showDialog = false">
                    Batal
                </Button>
                <Button :disabled="form.processing" @click="submit">
                    Simpan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
