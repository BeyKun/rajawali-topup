<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleAppearance(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-2">
            <Button
                variant="ghost"
                size="icon"
                class="size-9 rounded-md text-muted-foreground hover:text-foreground"
                :title="resolvedAppearance === 'dark' ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap'"
                @click="toggleAppearance"
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                <Moon v-else class="size-4" />
                <span class="sr-only">Toggle theme</span>
            </Button>
        </div>
    </header>
</template>
