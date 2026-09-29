<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';
import type { Paginator } from '@/types';

const props = defineProps<{
    paginator: Paginator<unknown>;
}>();

const previousUrl = () => props.paginator.links[0]?.url ?? null;
const nextUrl = () => props.paginator.links.at(-1)?.url ?? null;
</script>

<template>
    <div
        v-if="props.paginator.total > 0"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
    >
        <p class="text-sm text-muted-foreground">
            Menampilkan {{ props.paginator.from ?? 0 }}-{{
                props.paginator.to ?? 0
            }}
            dari {{ props.paginator.total }} data
        </p>

        <div class="flex items-center gap-2">
            <Link
                v-if="previousUrl()"
                :href="previousUrl() as string"
                preserve-scroll
                :class="cn(buttonVariants({ variant: 'outline', size: 'sm' }))"
            >
                <ChevronLeft class="size-4" />
                Sebelumnya
            </Link>
            <span
                v-else
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'pointer-events-none opacity-50',
                    )
                "
            >
                <ChevronLeft class="size-4" />
                Sebelumnya
            </span>

            <span class="text-sm text-muted-foreground">
                Halaman {{ props.paginator.current_page }} /
                {{ props.paginator.last_page }}
            </span>

            <Link
                v-if="nextUrl()"
                :href="nextUrl() as string"
                preserve-scroll
                :class="cn(buttonVariants({ variant: 'outline', size: 'sm' }))"
            >
                Berikutnya
                <ChevronRight class="size-4" />
            </Link>
            <span
                v-else
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'pointer-events-none opacity-50',
                    )
                "
            >
                Berikutnya
                <ChevronRight class="size-4" />
            </span>
        </div>
    </div>
</template>
