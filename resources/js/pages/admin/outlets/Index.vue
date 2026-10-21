<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Search, Store, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/admin/Pagination.vue';
import RegionSelect from '@/components/RegionSelect.vue';
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
import { formatDateTime } from '@/lib/format';
import type { AdminOutletRow, Paginator } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'User Outlet', href: '/admin/outlets' },
        ],
    },
});

const props = defineProps<{
    outlets: Paginator<AdminOutletRow>;
    filters: { search: string | null; city_id: number | null };
    defaultPassword: string;
}>();

const filterState = reactive({
    search: props.filters.search ?? '',
});

const editing = ref<AdminOutletRow | null>(null);
const showDialog = ref(false);

const form = useForm({
    name: '',
    phone: '',
    outlet_name: '',
    whatsapp: '',
    province_id: '' as string | number,
    city_id: '' as string | number,
    district_id: '' as string | number,
    village_id: '' as string | number,
    address: '',
});

const resetTarget = ref<AdminOutletRow | null>(null);
const resetForm = useForm({ password: '' });

function applyFilters(): void {
    router.get(
        '/admin/outlets',
        { search: filterState.search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(outlet: AdminOutletRow): void {
    editing.value = outlet;
    form.clearErrors();
    form.name = outlet.name;
    form.phone = outlet.phone ?? '';
    form.outlet_name = outlet.outlet_name ?? '';
    form.whatsapp = outlet.whatsapp ?? '';
    form.province_id = outlet.province_id ?? '';
    form.city_id = outlet.city_id ?? '';
    form.district_id = outlet.district_id ?? '';
    form.village_id = outlet.village_id ?? '';
    form.address = outlet.address ?? '';
    showDialog.value = true;
}

function submit(): void {
    if (editing.value === null) {
        form.post('/admin/outlets', {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });

        return;
    }

    form.put(`/admin/outlets/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            editing.value = null;
        },
    });
}

function openReset(outlet: AdminOutletRow): void {
    resetTarget.value = outlet;
    resetForm.reset();
    resetForm.clearErrors();
}

function submitReset(): void {
    if (!resetTarget.value) return;

    resetForm.post(`/admin/outlets/${resetTarget.value.id}/reset-password`, {
        preserveScroll: true,
        onSuccess: () => {
            resetTarget.value = null;
            resetForm.reset();
        },
    });
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                title="User Outlet"
                description="Kelola akun outlet aplikasi mobile: buat akun baru, perbarui data, dan reset password."
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Outlet
            </Button>
        </div>

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <label class="text-xs text-muted-foreground" for="search">
                    Cari Outlet
                </label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="filterState.search"
                        class="pl-8"
                        placeholder="Nama, nomor HP, atau nama outlet"
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
                        <th class="px-4 py-3 font-medium">Nama Outlet</th>
                        <th class="px-4 py-3 font-medium">Pemilik</th>
                        <th class="px-4 py-3 font-medium">No HP</th>
                        <th class="px-4 py-3 font-medium">Kabupaten/Kota</th>
                        <th class="px-4 py-3 font-medium">Bergabung</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="outlet in props.outlets.data"
                        :key="outlet.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ outlet.outlet_name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">{{ outlet.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ outlet.phone ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ outlet.city_name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDateTime(outlet.created_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Reset Password"
                                    @click="openReset(outlet)"
                                >
                                    <KeyRound class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Edit"
                                    @click="openEdit(outlet)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.outlets.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <Store class="mx-auto mb-2 size-8 opacity-40" />
                            Belum ada user outlet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.outlets" />

        <!-- Dialog Tambah/Edit Outlet -->
        <Dialog v-model:open="showDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? 'Edit Outlet' : 'Tambah User Outlet' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? 'Perbarui data akun dan profil outlet.'
                                : `Akun baru akan memakai password default: ${props.defaultPassword}`
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="name">Nama Pemilik</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Nomor HP (untuk login)</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            inputmode="tel"
                            placeholder="08xxxxxxxxxx"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="outlet_name">Nama Outlet</Label>
                        <Input id="outlet_name" v-model="form.outlet_name" />
                        <InputError :message="form.errors.outlet_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="whatsapp">Nomor WhatsApp (opsional)</Label>
                        <Input
                            id="whatsapp"
                            v-model="form.whatsapp"
                            inputmode="tel"
                            placeholder="08xxxxxxxxxx"
                        />
                        <InputError :message="form.errors.whatsapp" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Provinsi</Label>
                        <RegionSelect
                            id="outlet_province"
                            name="province_id"
                            v-model="form.province_id"
                            endpoint="/admin/api/regions/provinces"
                            value-key="id"
                            :display-label="editing?.province_name ?? null"
                            placeholder="Pilih Provinsi"
                            @change="
                                form.city_id = '';
                                form.district_id = '';
                                form.village_id = '';
                            "
                        />
                        <InputError :message="form.errors.province_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Kabupaten/Kota</Label>
                        <RegionSelect
                            id="outlet_city"
                            name="city_id"
                            v-model="form.city_id"
                            endpoint="/admin/api/regions/cities"
                            value-key="id"
                            :display-label="editing?.city_name ?? null"
                            parent-param-name="province"
                            :parent-value="form.province_id"
                            placeholder="Pilih Kabupaten/Kota"
                            @change="
                                form.district_id = '';
                                form.village_id = '';
                            "
                        />
                        <InputError :message="form.errors.city_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Kecamatan</Label>
                        <RegionSelect
                            id="outlet_district"
                            name="district_id"
                            v-model="form.district_id"
                            endpoint="/admin/api/regions/districts"
                            value-key="id"
                            parent-param-name="city"
                            :parent-value="form.city_id"
                            placeholder="Pilih Kecamatan"
                            @change="form.village_id = ''"
                        />
                        <InputError :message="form.errors.district_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Kelurahan/Desa</Label>
                        <RegionSelect
                            id="outlet_village"
                            name="village_id"
                            v-model="form.village_id"
                            endpoint="/admin/api/regions/villages"
                            value-key="id"
                            parent-param-name="district"
                            :parent-value="form.district_id"
                            placeholder="Pilih Kelurahan/Desa"
                        />
                        <InputError :message="form.errors.village_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Alamat Lengkap</Label>
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="3"
                            class="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Jl. contoh no. 12, RT/RW, patokan"
                        ></textarea>
                        <InputError :message="form.errors.address" />
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

        <!-- Dialog Reset Password -->
        <Dialog
            :open="resetTarget !== null"
            @update:open="(open: boolean) => !open && (resetTarget = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reset Password</DialogTitle>
                    <DialogDescription>
                        Reset password untuk
                        {{ resetTarget?.name }}. Kosongkan untuk memakai
                        password default ({{ props.defaultPassword }}).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="reset_password">Password Baru (opsional)</Label>
                    <Input
                        id="reset_password"
                        v-model="resetForm.password"
                        type="text"
                        :placeholder="props.defaultPassword"
                    />
                    <InputError :message="resetForm.errors.password" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="resetTarget = null">
                        Batal
                    </Button>
                    <Button
                        :disabled="resetForm.processing"
                        @click="submitReset"
                    >
                        Reset Password
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
