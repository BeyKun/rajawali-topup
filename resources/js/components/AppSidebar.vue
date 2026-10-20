<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Package,
    Receipt,
    ShieldCheck,
    Ticket,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useEcho } from '@/composables/useEcho';
import { playNotificationSound } from '@/lib/notificationSound';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const incomingOrdersCount = ref(0);

const isAdmin = computed(() => {
    const role = (page.props.auth?.user as { role?: string } | undefined)?.role;

    return (
        role === 'super_admin' ||
        role === 'operator' ||
        role === 'kabupaten_admin'
    );
});

const isSuperAdmin = computed(() => {
    const role = (page.props.auth?.user as { role?: string } | undefined)?.role;

    return role === 'super_admin';
});

watch(
    () => page.url,
    (url) => {
        if (url.startsWith('/admin/orders')) {
            incomingOrdersCount.value = 0;
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (!isAdmin.value) {
        return;
    }

    const echo = useEcho();
    if (!echo) {
        return;
    }

    echo.private('admin.orders').listen(
        '.OrderCreated',
        (event: {
            order: { order_no: string; product_name?: string };
            message?: string;
        }) => {
            incomingOrdersCount.value++;
            playNotificationSound();
            toast.info(event.message || `Pesanan baru: ${event.order.order_no}`, {
                description: event.order.product_name || 'Menunggu pembayaran',
            });
        },
    );
});

onUnmounted(() => {
    const echo = useEcho();
    echo?.leave('admin.orders');
});

const mainNavItems = computed<NavItem[]>(() => {
    if (isAdmin.value) {
        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: '/admin/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Katalog Paket',
                href: '/admin/products',
                icon: Package,
            },
            {
                title: 'Voucher',
                href: '/admin/vouchers',
                icon: Ticket,
            },
            {
                title: 'Transaksi',
                href: '/admin/orders',
                icon: Receipt,
                badge:
                    incomingOrdersCount.value > 0
                        ? `+${incomingOrdersCount.value}`
                        : undefined,
                badgeVariant: 'destructive',
            },
        ];

        if (isSuperAdmin.value) {
            items.push({
                title: 'Admin Wilayah',
                href: '/admin/admins',
                icon: ShieldCheck,
            });
        }

        return items;
    }

    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="isAdmin ? '/admin/dashboard' : dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
