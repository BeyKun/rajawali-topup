<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    Check,
    Clock,
    Copy,
    ExternalLink,
    QrCode,
    RefreshCw,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatDateTime, formatRupiah } from '@/lib/format';
import type { AdminApiLog, AdminOrderDetail } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin/dashboard' },
            { title: 'Transaksi', href: '/admin/orders' },
            { title: 'Detail', href: '#' },
        ],
    },
});

const props = defineProps<{
    order: AdminOrderDetail;
    apiLogs: AdminApiLog[];
}>();

const processing = ref(false);

function canRetry(): boolean {
    return (
        props.order.payment_status === 'PAID' &&
        props.order.redeem_status !== 'SUCCESS'
    );
}

function canCancel(): boolean {
    return props.order.redeem_status !== 'SUCCESS';
}

function canMarkRefunded(): boolean {
    return props.order.payment_status === 'REFUND_PENDING';
}

const refundRefId = ref('');
const refundReason = ref('');

function retryRedeem(): void {
    processing.value = true;
    router.post(
        `/admin/orders/${props.order.id}/retry-redeem`,
        {},
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}

function cancelOrder(): void {
    processing.value = true;
    router.post(
        `/admin/orders/${props.order.id}/cancel`,
        {},
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}

function markRefunded(): void {
    processing.value = true;
    router.post(
        `/admin/orders/${props.order.id}/mark-refunded`,
        {
            refund_ref_id: refundRefId.value || undefined,
            refund_reason: refundReason.value || undefined,
        },
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}

const copied = ref(false);

async function copyQrisUrl(): Promise<void> {
    if (!props.order.qris_url) return;
    try {
        await navigator.clipboard.writeText(props.order.qris_url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // fallback clipboard
        const input = document.createElement('input');
        input.value = props.order.qris_url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}

function json(value: unknown): string {
    return JSON.stringify(value ?? {}, null, 2);
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon-sm" as-child>
                    <a href="/admin/orders" aria-label="Kembali">
                        <ArrowLeft class="size-4" />
                    </a>
                </Button>
                <div>
                    <Heading
                        :title="`Pesanan ${props.order.order_no}`"
                        :description="
                            order.payment_status === 'PAID' ||
                            order.redeem_status !== 'SUCCESS'
                                ? 'Detail lengkap transaksi dan jejak Telkomsel.'
                                : 'Detail lengkap transaksi.'
                        "
                    />
                    <div class="mt-1 flex gap-2">
                        <StatusBadge :status="props.order.payment_status" />
                        <StatusBadge :status="props.order.redeem_status" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="canRetry()"
                    :disabled="processing"
                    @click="retryRedeem"
                >
                    <RefreshCw class="size-4" />
                    Coba Ulang Redeem
                </Button>

                <Button
                    v-if="props.order.qris_url && props.order.payment_status === 'UNPAID'"
                    variant="outline"
                    as-child
                >
                    <a
                        href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1.5"
                    >
                        <ExternalLink class="size-4" />
                        Bayar via Simulator Midtrans
                    </a>
                </Button>

                <Dialog v-if="canMarkRefunded()">
                    <DialogTrigger as-child>
                        <Button variant="default" class="bg-purple-600 hover:bg-purple-700 text-white">
                            <Check class="size-4 mr-1.5" />
                            Tandai Refund Selesai
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tandai Refund Telah Selesai?</DialogTitle>
                            <DialogDescription>
                                Gunakan aksi ini jika pengembalian dana telah berhasil ditransfer secara manual ke pelanggan atau diproses di portal Midtrans.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-3 py-2">
                            <div>
                                <label class="text-xs font-medium text-muted-foreground">ID Referensi Refund (Opsional)</label>
                                <input
                                    v-model="refundRefId"
                                    type="text"
                                    placeholder="Contoh: REF-12345678 / No. Resi Bank"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-muted-foreground">Catatan / Alasan (Opsional)</label>
                                <input
                                    v-model="refundReason"
                                    type="text"
                                    placeholder="Contoh: Ditransfer manual ke rekening customer BCA"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Batal</Button>
                            </DialogClose>
                            <Button
                                class="bg-purple-600 hover:bg-purple-700 text-white"
                                :disabled="processing"
                                @click="markRefunded"
                            >
                                Ya, Simpan Refund
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog v-if="canCancel()">
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <Ban class="size-4" />
                            Batalkan
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Batalkan pesanan?</DialogTitle>
                            <DialogDescription>
                                Pesanan akan ditandai GAGAL dan voucher yang
                                masih tersimpan akan dikembalikan ke stok.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Batal</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="cancelOrder">
                                Ya, Batalkan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Refund Information Banner/Card -->
        <Card
            v-if="order.payment_status === 'REFUNDED' || order.payment_status === 'REFUND_PENDING' || order.refund_amount"
            class="border-purple-200 dark:border-purple-900 bg-purple-50/30 dark:bg-purple-950/20"
        >
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-purple-700 dark:text-purple-300">
                    <RefreshCw class="size-4" /> Informasi Pengembalian Dana (Refund)
                </CardTitle>
                <CardDescription>
                    Status refund untuk pesanan yang mengalami kegagalan aktivasi voucher.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground text-xs">Status Refund</dt>
                        <dd class="mt-1">
                            <StatusBadge :status="order.payment_status" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Nominal Refund</dt>
                        <dd class="mt-1 font-semibold text-foreground">
                            {{ formatRupiah(order.refund_amount ?? order.total_amount) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Ref. ID Refund</dt>
                        <dd class="mt-1 font-mono text-xs text-foreground">
                            {{ order.refund_ref_id ?? '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Waktu Refund</dt>
                        <dd class="mt-1 text-foreground">
                            {{ order.refunded_at ? formatDateTime(order.refunded_at) : '-' }}
                        </dd>
                    </div>
                    <div class="col-span-2 md:col-span-4">
                        <dt class="text-muted-foreground text-xs">Alasan Refund</dt>
                        <dd class="mt-1 text-sm text-foreground bg-background/60 p-2.5 rounded border">
                            {{ order.refund_reason ?? '-' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Informasi Pesanan</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-3 gap-y-3 text-sm">
                        <dt class="text-muted-foreground">Produk</dt>
                        <dd class="col-span-2">
                            {{ props.order.product_name ?? '-' }}
                        </dd>
                        <dt class="text-muted-foreground">Pengguna</dt>
                        <dd class="col-span-2">
                            {{ props.order.user.name ?? '-' }}
                            <span class="text-muted-foreground">
                                ({{ props.order.user.email ?? '-' }})
                            </span>
                        </dd>
                        <dt class="text-muted-foreground">MSISDN</dt>
                        <dd class="col-span-2">{{ props.order.msisdn }}</dd>
                        <dt class="text-muted-foreground">Nominal</dt>
                        <dd class="col-span-2">
                            {{ formatRupiah(props.order.amount) }}
                        </dd>
                        <dt class="text-muted-foreground">Biaya Admin</dt>
                        <dd class="col-span-2">
                            {{ formatRupiah(props.order.admin_fee) }}
                        </dd>
                        <dt class="text-muted-foreground">Total</dt>
                        <dd class="col-span-2 font-semibold">
                            {{ formatRupiah(props.order.total_amount) }}
                        </dd>
                        <dt class="text-muted-foreground">Kanal</dt>
                        <dd class="col-span-2">
                            {{ props.order.payment_channel }}
                        </dd>
                        <dt class="text-muted-foreground">Retry</dt>
                        <dd class="col-span-2">{{ props.order.retry_count }}x</dd>
                        <dt class="text-muted-foreground">Dibuat</dt>
                        <dd class="col-span-2">
                            {{ formatDateTime(props.order.created_at) }}
                        </dd>
                        <dt class="text-muted-foreground">Dibayar</dt>
                        <dd class="col-span-2">
                            {{ formatDateTime(props.order.paid_at) }}
                        </dd>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <QrCode class="size-4" /> Pembayaran &amp; Voucher
                    </CardTitle>
                    <CardDescription>
                        Kode voucher hanya menampilkan serial number.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-3 gap-y-3 text-sm">
                        <dt class="text-muted-foreground">Ref. Bayar</dt>
                        <dd class="col-span-2">
                            {{ props.order.payment_ref_id ?? '-' }}
                        </dd>
                        <dt class="text-muted-foreground">Pesanan Kadaluarsa</dt>
                        <dd class="col-span-2">
                            {{ formatDateTime(props.order.qris_expired_at) }}
                        </dd>
                        <dt class="text-muted-foreground">Serial Voucher</dt>
                        <dd class="col-span-2 font-mono text-xs">
                            {{ props.order.voucher_serial_number ?? '-' }}
                        </dd>
                        <dt class="text-muted-foreground">Status Voucher</dt>
                        <dd class="col-span-2">
                            <StatusBadge
                                v-if="props.order.voucher_status"
                                :status="props.order.voucher_status"
                            />
                            <span v-else>-</span>
                        </dd>
                        <dt class="text-muted-foreground">Kode Redeem</dt>
                        <dd class="col-span-2">
                            {{ props.order.redeem_response_code ?? '-' }}
                        </dd>
                        <dt class="text-muted-foreground">URL QRIS</dt>
                        <dd class="col-span-2">
                            <div v-if="props.order.qris_url" class="space-y-1.5">
                                <a
                                    :href="props.order.qris_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-mono text-xs text-primary underline underline-offset-4 hover:opacity-80 break-all"
                                >
                                    <ExternalLink class="size-3 shrink-0" />
                                    {{ props.order.qris_url }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="copyQrisUrl"
                                    >
                                        <component :is="copied ? Check : Copy" class="size-3.5 mr-1" />
                                        {{ copied ? 'Tersalin!' : 'Salin URL' }}
                                    </Button>
                                    <Button
                                        v-if="props.order.payment_status === 'UNPAID'"
                                        type="button"
                                        size="sm"
                                        as-child
                                    >
                                        <a
                                            href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink class="size-3.5 mr-1" />
                                            Bayar via Simulator Midtrans
                                        </a>
                                    </Button>
                                </div>
                            </div>
                            <span v-else class="text-muted-foreground">-</span>
                        </dd>
                    </dl>

                    <div
                        v-if="props.order.qris_url"
                        class="mt-4 rounded-xl border bg-muted/30 p-3.5 flex flex-col sm:flex-row items-center gap-4"
                    >
                        <div class="rounded-lg bg-white p-2 border shadow-xs shrink-0">
                            <img
                                :src="props.order.qris_url"
                                alt="QR Code QRIS"
                                class="size-28 object-contain"
                            />
                        </div>
                        <div class="space-y-1.5 text-center sm:text-left">
                            <p class="text-xs font-semibold text-foreground">
                                Scan atau Bayar via Simulator Midtrans
                            </p>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                URL gambar QRIS di atas dapat disalin lalu ditempelkan pada Midtrans QRIS Simulator untuk menyelesaikan pembayaran transaksi ini dari admin.
                            </p>
                            <div class="pt-1 flex flex-wrap gap-2 justify-center sm:justify-start">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="copyQrisUrl"
                                >
                                    <component :is="copied ? Check : Copy" class="size-3.5 mr-1" />
                                    {{ copied ? 'URL Tersalin!' : 'Salin URL QRIS' }}
                                </Button>
                                <Button
                                    v-if="props.order.payment_status === 'UNPAID'"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink class="size-3.5 mr-1" />
                                        Buka Simulator QRIS
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Clock class="size-4" /> Jejak API Telkomsel
                </CardTitle>
                <CardDescription>
                    {{ props.apiLogs.length }} panggilan terakhir untuk pesanan
                    ini.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="log in props.apiLogs"
                    :key="log.id"
                    class="rounded-lg border p-3"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium uppercase">
                            {{ log.endpoint }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            HTTP {{ log.response_code }} · {{ log.duration_ms }}ms
                            · {{ formatDateTime(log.created_at) }}
                        </span>
                    </div>
                    <details class="mt-2">
                        <summary
                            class="cursor-pointer text-xs text-muted-foreground"
                        >
                            Lihat payload
                        </summary>
                        <div class="mt-2 grid gap-2 md:grid-cols-2">
                            <pre
                                class="overflow-x-auto rounded-md bg-muted p-2 text-[11px]"
                            >{{ json(log.request_payload) }}</pre>
                            <pre
                                class="overflow-x-auto rounded-md bg-muted p-2 text-[11px]"
                            >{{ json(log.response_payload) }}</pre>
                        </div>
                    </details>
                </div>

                <p
                    v-if="props.apiLogs.length === 0"
                    class="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground"
                >
                    <AlertTriangle class="size-4" />
                    Belum ada jejak API Telkomsel untuk pesanan ini.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
