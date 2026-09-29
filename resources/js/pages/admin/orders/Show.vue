<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    Clock,
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
                    </dl>
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
