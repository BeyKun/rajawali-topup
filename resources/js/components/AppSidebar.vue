<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    MapPin,
    Package,
    Receipt,
    ShieldCheck,
    Ticket,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

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
            },
        ];

        if (isSuperAdmin.value) {
            items.push(
                {
                    title: 'Admin Wilayah',
                    href: '/admin/admins',
                    icon: ShieldCheck,
                },
                {
                    title: 'Mapping Area',
                    href: '/admin/areas',
                    icon: MapPin,
                },
            );
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
