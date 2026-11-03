<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, ShieldCheck, Trash2, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/admin/Pagination.vue';
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
import type { AdminKabupatenAdminRow, Paginator } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Admin Wilayah', href: '/admin/admins' },
        ],
    },
});

const props = defineProps<{
    admins: Paginator<AdminKabupatenAdminRow>;
    filters: { search: string | null };
    cities?: CityOption[];
    is_super_admin?: boolean;
}>();

const filterState = reactive({
    search: props.filters.search ?? '',
});

const editing = ref<AdminKabupatenAdminRow | null>(null);
const showDialog = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    city_ids: [] as number[],
});

function applyFilters(): void {
    router.get(
        '/admin/admins',
        { search: filterState.search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.city_ids = [];
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(admin: AdminKabupatenAdminRow): void {
    editing.value = admin;
    form.clearErrors();
    form.name = admin.name;
    form.email = admin.email;
    form.password = '';
    form.city_ids = admin.city_ids && admin.city_ids.length > 0
        ? [...admin.city_ids]
        : (admin.city_id ? [admin.city_id] : []);
    showDialog.value = true;
}

function submit(): void {
    if (editing.value === null) {
        form.post('/admin/admins', {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });

        return;
    }

    form.put(`/admin/admins/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            editing.value = null;
        },
    });
}

function destroy(admin: AdminKabupatenAdminRow): void {
    if (!confirm(`Hapus admin ${admin.name}?`)) {
        return;
    }

    router.delete(`/admin/admins/${admin.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <Heading
                title="Admin Wilayah"
                description="Akun admin per wilayah/kabupaten. Voucher yang mereka input otomatis ter-map ke wilayahnya."
            />
            <Button v-if="props.is_super_admin" @click="openCreate">
                <Plus class="size-4" />
                Tambah Admin
            </Button>
        </div>

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <label class="text-xs text-muted-foreground" for="search">
                    Cari Admin
                </label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="filterState.search"
                        class="pl-8"
                        placeholder="Nama atau email"
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
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Wilayah Kabupaten/Kota</th>
                        <th v-if="props.is_super_admin" class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="admin in props.admins.data"
                        :key="admin.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-medium">{{ admin.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ admin.email }}
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="admin.city_names" class="inline-block max-w-md truncate" :title="admin.city_names">
                                {{ admin.city_names }}
                            </span>
                            <span v-else-if="admin.city_name">
                                {{ admin.city_name }}
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td v-if="props.is_super_admin" class="px-4 py-3 text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Edit"
                                @click="openEdit(admin)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Hapus"
                                @click="destroy(admin)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.admins.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <ShieldCheck class="mx-auto mb-2 size-8 opacity-40" />
                            Belum ada admin wilayah.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="props.admins" />
    </div>

    <Dialog v-model:open="showDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? 'Edit Admin Wilayah' : 'Tambah Admin Wilayah' }}
                </DialogTitle>
                <DialogDescription>
                    Admin hanya dapat mengelola voucher & transaksi wilayahnya.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">
                        Password
                        <span
                            v-if="editing"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            (kosongkan bila tidak diubah)
                        </span>
                    </Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label>Wilayah Kabupaten/Kota yang Dikelola</Label>
                    <SearchableCityMultiSelect
                        v-model="form.city_ids"
                        :cities="props.cities ?? []"
                        placeholder="Pilih satu atau lebih kabupaten/kota..."
                    />
                    <InputError :message="form.errors.city_ids" />
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
