<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    BadgePercent,
    ImageIcon,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/format';
import type { AdminPromoBanner, AdminPromoVoucher, DiscountType } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Promo & Voucher', href: '/admin/promos' },
        ],
    },
});

const props = defineProps<{
    banners: AdminPromoBanner[];
    promoVouchers: AdminPromoVoucher[];
    discountTypes: DiscountType[];
}>();

const activeTab = ref<'banners' | 'vouchers'>('banners');

/* ----------------------------- Banner CRUD ----------------------------- */

const bannerDialog = ref(false);
const editingBanner = ref<AdminPromoBanner | null>(null);

const bannerForm = useForm<{
    title: string;
    description: string;
    image: File | null;
    popup_image: File | null;
    target_url: string;
    is_active: boolean;
    is_featured: boolean;
    sort_order: number;
}>({
    title: '',
    description: '',
    image: null,
    popup_image: null,
    target_url: '',
    is_active: true,
    is_featured: false,
    sort_order: 0,
});

function openBannerCreate(): void {
    editingBanner.value = null;
    bannerForm.reset();
    bannerForm.clearErrors();
    bannerDialog.value = true;
}

function openBannerEdit(banner: AdminPromoBanner): void {
    editingBanner.value = banner;
    bannerForm.clearErrors();
    bannerForm.title = banner.title;
    bannerForm.description = banner.description ?? '';
    bannerForm.image = null;
    bannerForm.popup_image = null;
    bannerForm.target_url = banner.target_url ?? '';
    bannerForm.is_active = banner.is_active;
    bannerForm.is_featured = banner.is_featured ?? false;
    bannerForm.sort_order = banner.sort_order;
    bannerDialog.value = true;
}

function onBannerFile(event: Event): void {
    const target = event.target as HTMLInputElement;
    bannerForm.image = target.files?.[0] ?? null;
}

function onPopupBannerFile(event: Event): void {
    const target = event.target as HTMLInputElement;
    bannerForm.popup_image = target.files?.[0] ?? null;
}

function submitBanner(): void {
    if (editingBanner.value === null) {
        bannerForm.post('/admin/promos/banners', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                bannerDialog.value = false;
                bannerForm.reset();
            },
        });

        return;
    }

    // The update endpoint accepts multipart POST (with the `{banner}` route
    // parameter) because Laravel cannot parse file uploads on PUT.
    bannerForm.post(`/admin/promos/banners/${editingBanner.value.id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            bannerDialog.value = false;
            editingBanner.value = null;
        },
    });
}

function destroyBanner(banner: AdminPromoBanner): void {
    if (!confirm(`Hapus banner "${banner.title}"?`)) return;
    router.delete(`/admin/promos/banners/${banner.id}`, { preserveScroll: true });
}

/* ---------------------------- Voucher CRUD ----------------------------- */

const voucherDialog = ref(false);
const editingVoucher = ref<AdminPromoVoucher | null>(null);

const voucherForm = useForm({
    code: '',
    title: '',
    description: '',
    discount_type: 'fixed' as DiscountType,
    discount_value: '' as string | number,
    max_discount_amount: '' as string | number,
    min_spend: 0 as string | number,
    usage_limit: '' as string | number,
    start_date: '',
    end_date: '',
    is_active: true,
});

const isPercentage = computed(() => voucherForm.discount_type === 'percentage');

const discountPreview = computed(() => {
    if (voucherForm.discount_type === 'percentage') {
        return `${voucherForm.discount_value || 0}% per transaksi`;
    }

    return formatRupiah(Number(voucherForm.discount_value) || 0);
});

function openVoucherCreate(): void {
    editingVoucher.value = null;
    voucherForm.reset();
    voucherForm.clearErrors();
    voucherForm.start_date = new Date().toISOString().slice(0, 10);
    voucherForm.end_date = new Date(Date.now() + 30 * 86400000)
        .toISOString()
        .slice(0, 10);
    voucherDialog.value = true;
}

function openVoucherEdit(voucher: AdminPromoVoucher): void {
    editingVoucher.value = voucher;
    voucherForm.clearErrors();
    voucherForm.code = voucher.code;
    voucherForm.title = voucher.title;
    voucherForm.description = voucher.description ?? '';
    voucherForm.discount_type = voucher.discount_type;
    voucherForm.discount_value = voucher.discount_value;
    voucherForm.max_discount_amount = voucher.max_discount_amount ?? '';
    voucherForm.min_spend = voucher.min_spend;
    voucherForm.usage_limit = voucher.usage_limit ?? '';
    voucherForm.start_date = voucher.start_date;
    voucherForm.end_date = voucher.end_date;
    voucherForm.is_active = voucher.is_active;
    voucherDialog.value = true;
}

function submitVoucher(): void {
    if (editingVoucher.value === null) {
        voucherForm.post('/admin/promos/vouchers', {
            preserveScroll: true,
            onSuccess: () => {
                voucherDialog.value = false;
                voucherForm.reset();
            },
        });

        return;
    }

    voucherForm.put(`/admin/promos/vouchers/${editingVoucher.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            voucherDialog.value = false;
            editingVoucher.value = null;
        },
    });
}

function destroyVoucher(voucher: AdminPromoVoucher): void {
    if (!confirm(`Hapus voucher promo "${voucher.code}"?`)) return;
    router.delete(`/admin/promos/vouchers/${voucher.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Promo & Voucher"
            description="Kelola banner promo aplikasi mobile dan kode voucher diskon transaksi."
        />

        <!-- Tabs -->
        <div class="flex gap-2 border-b">
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="
                    activeTab === 'banners'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'banners'"
            >
                <ImageIcon class="mr-1.5 inline size-4" />
                Banner Promo
            </button>
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="
                    activeTab === 'vouchers'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'vouchers'"
            >
                <BadgePercent class="mr-1.5 inline size-4" />
                Voucher Promo
            </button>
        </div>

        <!-- Banner Tab -->
        <div v-if="activeTab === 'banners'" class="flex flex-col gap-4">
            <div class="flex justify-end">
                <Button @click="openBannerCreate">
                    <Plus class="size-4" />
                    Tambah Banner
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="banner in props.banners"
                    :key="banner.id"
                    class="flex flex-col justify-between overflow-hidden rounded-xl border bg-card"
                >
                    <div>
                        <div class="relative aspect-[16/7] bg-muted">
                            <img
                                :src="banner.image_url"
                                :alt="banner.title"
                                class="size-full object-cover"
                            />
                            <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                                <span
                                    v-if="banner.is_featured"
                                    class="flex items-center gap-1 rounded-md bg-amber-500/90 px-2 py-0.5 text-xs font-semibold text-white shadow-sm backdrop-blur-xs"
                                >
                                    ⭐ Featured / Popup
                                </span>
                                <span
                                    v-if="banner.popup_image_url"
                                    class="flex items-center gap-1 rounded-md bg-sky-600/90 px-2 py-0.5 text-xs font-semibold text-white shadow-sm backdrop-blur-xs"
                                >
                                    📱 Portrait Ready
                                </span>
                            </div>
                        </div>
                        <div class="p-3">
                            <p class="truncate text-sm font-semibold">
                                {{ banner.title }}
                            </p>
                            <p
                                v-if="banner.description"
                                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ banner.description }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t bg-muted/20 p-3">
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Urutan {{ banner.sort_order }} ·
                                <span :class="banner.is_active ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                                    {{ banner.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Edit"
                                @click="openBannerEdit(banner)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Hapus"
                                @click="destroyBanner(banner)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </div>
                </div>
                <div
                    v-if="props.banners.length === 0"
                    class="col-span-full rounded-xl border py-12 text-center text-muted-foreground"
                >
                    <ImageIcon class="mx-auto mb-2 size-8 opacity-40" />
                    Belum ada banner promo.
                </div>
            </div>
        </div>

        <!-- Voucher Tab -->
        <div v-else class="flex flex-col gap-4">
            <div class="flex justify-end">
                <Button @click="openVoucherCreate">
                    <Plus class="size-4" />
                    Tambah Voucher
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Kode</th>
                            <th class="px-4 py-3 font-medium">Judul</th>
                            <th class="px-4 py-3 font-medium">Tipe</th>
                            <th class="px-4 py-3 font-medium">Diskon</th>
                            <th class="px-4 py-3 font-medium">Min. Belanja</th>
                            <th class="px-4 py-3 font-medium">Kuota</th>
                            <th class="px-4 py-3 font-medium">Periode</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="voucher in props.promoVouchers"
                            :key="voucher.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-bold">
                                {{ voucher.code }}
                            </td>
                            <td class="px-4 py-3">{{ voucher.title }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        voucher.discount_type === 'percentage'
                                            ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
                                            : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                    "
                                >
                                    {{
                                        voucher.discount_type === 'percentage'
                                            ? 'Persentase'
                                            : 'Nominal'
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="voucher.discount_type === 'percentage'">
                                    {{ voucher.discount_value }}%
                                    <span
                                        v-if="voucher.max_discount_amount"
                                        class="text-xs text-muted-foreground"
                                    >
                                        (maks
                                        {{ formatRupiah(voucher.max_discount_amount) }})
                                    </span>
                                </span>
                                <span v-else>
                                    {{ formatRupiah(voucher.discount_value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatRupiah(voucher.min_spend) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ voucher.used_count }} /
                                {{ voucher.usage_limit ?? '∞' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ voucher.start_date }} → {{ voucher.end_date }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        voucher.is_usable
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ voucher.is_usable ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Edit"
                                        @click="openVoucherEdit(voucher)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Hapus"
                                        @click="destroyVoucher(voucher)"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.promoVouchers.length === 0">
                            <td
                                colspan="9"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <BadgePercent class="mx-auto mb-2 size-8 opacity-40" />
                                Belum ada voucher promo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dialog Banner -->
        <Dialog v-model:open="bannerDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingBanner ? 'Edit Banner' : 'Tambah Banner Promo' }}
                    </DialogTitle>
                    <DialogDescription>
                        Banner promo untuk carousel beranda dan popup utama aplikasi mobile.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="banner_title">Judul Banner</Label>
                        <Input
                            id="banner_title"
                            v-model="bannerForm.title"
                            placeholder="Contoh: Promo Spesial Weekend"
                        />
                        <InputError :message="bannerForm.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="banner_description">Deskripsi Promo (opsional)</Label>
                        <textarea
                            id="banner_description"
                            v-model="bannerForm.description"
                            rows="3"
                            placeholder="Tuliskan keterangan lengkap promo, syarat & ketentuan, atau keuntungan penawaran ini..."
                            class="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-none"
                        ></textarea>
                        <InputError :message="bannerForm.errors.description" />
                    </div>

                    <!-- Upload Gambar 1: Banner Slider (Landscape) -->
                    <div class="grid gap-2 rounded-lg border bg-muted/20 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="banner_image" class="font-semibold">
                                Gambar Slider
                                <span
                                    v-if="editingBanner"
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    (kosongkan bila tidak diubah)
                                </span>
                            </Label>
                            <span class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                                Disarankan Landscape (16:9 / 2:1)
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Ditampilkan di carousel slider halaman beranda. Ukuran rekomendasi: 1200 x 600 px (Landscape).
                        </p>
                        <Input
                            id="banner_image"
                            type="file"
                            accept="image/*"
                            @change="onBannerFile"
                        />
                        <InputError :message="bannerForm.errors.image" />
                        <div
                            v-if="editingBanner?.image_url"
                            class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span>Preview saat ini:</span>
                            <img
                                :src="editingBanner.image_url"
                                alt="Slider Preview"
                                class="h-10 w-20 rounded border object-cover"
                            />
                        </div>
                    </div>

                    <!-- Upload Gambar 2: Banner Popup (Portrait) -->
                    <div class="grid gap-2 rounded-lg border bg-muted/20 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="banner_popup_image" class="font-semibold">
                                Gambar Popup / Featured (opsional)
                                <span
                                    v-if="editingBanner"
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    (kosongkan bila tidak diubah)
                                </span>
                            </Label>
                            <span class="rounded bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                                Diharapkan Portrait (9:16 / 3:4)
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Ditampilkan saat dialog popup featured muncul di aplikasi mobile. Ukuran rekomendasi: 720 x 1280 px (Portrait).
                        </p>
                        <Input
                            id="banner_popup_image"
                            type="file"
                            accept="image/*"
                            @change="onPopupBannerFile"
                        />
                        <InputError :message="bannerForm.errors.popup_image" />
                        <div
                            v-if="editingBanner?.popup_image_url"
                            class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span>Preview saat ini:</span>
                            <img
                                :src="editingBanner.popup_image_url"
                                alt="Popup Preview"
                                class="h-14 w-9 rounded border object-cover"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="banner_target">Tautan Tujuan (opsional)</Label>
                        <Input
                            id="banner_target"
                            v-model="bannerForm.target_url"
                            placeholder="https://..."
                        />
                        <InputError :message="bannerForm.errors.target_url" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="banner_sort">Urutan Tampil</Label>
                            <Input
                                id="banner_sort"
                                v-model="bannerForm.sort_order"
                                type="number"
                                min="0"
                            />
                            <InputError :message="bannerForm.errors.sort_order" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="banner_active">Status</Label>
                            <Select
                                :model-value="bannerForm.is_active ? '1' : '0'"
                                @update:model-value="
                                    (v) => (bannerForm.is_active = v === '1')
                                "
                            >
                                <SelectTrigger id="banner_active">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Aktif</SelectItem>
                                    <SelectItem value="0">Nonaktif</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border bg-muted/40 p-3">
                        <input
                            id="banner_featured"
                            v-model="bannerForm.is_featured"
                            type="checkbox"
                            class="text-primary focus:ring-primary mt-1 size-4 cursor-pointer rounded border-gray-300"
                        />
                        <div
                            class="grid cursor-pointer gap-0.5"
                            @click="bannerForm.is_featured = !bannerForm.is_featured"
                        >
                            <Label for="banner_featured" class="cursor-pointer font-medium">
                                Tampilkan sebagai Popup Utama di Mobile (Featured)
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Jika diaktifkan, banner ini akan muncul sebagai popup dialog saat pengguna pertama kali membuka aplikasi. Banner featured lainnya otomatis dinonaktifkan.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="bannerDialog = false">
                        Batal
                    </Button>
                    <Button :disabled="bannerForm.processing" @click="submitBanner">
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog Voucher -->
        <Dialog v-model:open="voucherDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingVoucher ? 'Edit Voucher Promo' : 'Tambah Voucher Promo' }}
                    </DialogTitle>
                    <DialogDescription>
                        Diskon otomatis dipotong dari total tagihan saat customer memasukkan kode.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="voucher_code">Kode Promo</Label>
                            <Input
                                id="voucher_code"
                                v-model="voucherForm.code"
                                class="font-mono uppercase"
                                placeholder="HEMAT5RB"
                            />
                            <InputError :message="voucherForm.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="voucher_title">Judul</Label>
                            <Input id="voucher_title" v-model="voucherForm.title" />
                            <InputError :message="voucherForm.errors.title" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="voucher_description">Deskripsi (opsional)</Label>
                        <textarea
                            id="voucher_description"
                            v-model="voucherForm.description"
                            rows="2"
                            class="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-none"
                        ></textarea>
                        <InputError :message="voucherForm.errors.description" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="voucher_type">Tipe Potongan Diskon</Label>
                            <Select v-model="voucherForm.discount_type">
                                <SelectTrigger id="voucher_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fixed">Potongan Nominal (Rp)</SelectItem>
                                    <SelectItem value="percentage">Potongan Persentase (%)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="voucherForm.errors.discount_type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="voucher_value">
                                {{ isPercentage ? 'Persen Diskon (%)' : 'Nominal Diskon (Rp)' }}
                            </Label>
                            <Input
                                id="voucher_value"
                                v-model="voucherForm.discount_value"
                                inputmode="numeric"
                                :placeholder="isPercentage ? '10 (1-100%)' : '5000'"
                            />
                            <InputError :message="voucherForm.errors.discount_value" />
                        </div>
                    </div>

                    <div v-if="isPercentage" class="grid gap-2">
                        <Label for="voucher_max">Maksimal Diskon (Rp, opsional)</Label>
                        <Input
                            id="voucher_max"
                            v-model="voucherForm.max_discount_amount"
                            inputmode="numeric"
                            placeholder="10000"
                        />
                        <InputError
                            :message="voucherForm.errors.max_discount_amount"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="voucher_min">Minimal Belanja (Rp)</Label>
                            <Input
                                id="voucher_min"
                                v-model="voucherForm.min_spend"
                                inputmode="numeric"
                                placeholder="0"
                            />
                            <InputError :message="voucherForm.errors.min_spend" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="voucher_limit">Kuota Pemakaian (opsional)</Label>
                            <Input
                                id="voucher_limit"
                                v-model="voucherForm.usage_limit"
                                inputmode="numeric"
                                placeholder="Tanpa batas"
                            />
                            <InputError :message="voucherForm.errors.usage_limit" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="voucher_start">Tanggal Mulai</Label>
                            <Input
                                id="voucher_start"
                                v-model="voucherForm.start_date"
                                type="date"
                            />
                            <InputError :message="voucherForm.errors.start_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="voucher_end">Tanggal Berakhir</Label>
                            <Input
                                id="voucher_end"
                                v-model="voucherForm.end_date"
                                type="date"
                            />
                            <InputError :message="voucherForm.errors.end_date" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="voucher_active">Status</Label>
                        <Select
                            :model-value="voucherForm.is_active ? '1' : '0'"
                            @update:model-value="
                                (v) => (voucherForm.is_active = v === '1')
                            "
                        >
                            <SelectTrigger id="voucher_active">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Aktif</SelectItem>
                                <SelectItem value="0">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="rounded-lg border bg-muted/40 p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Ringkasan Diskon:</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ discountPreview }}
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="voucherDialog = false">
                        Batal
                    </Button>
                    <Button
                        :disabled="voucherForm.processing"
                        @click="submitVoucher"
                    >
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
