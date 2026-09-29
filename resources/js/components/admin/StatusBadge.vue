<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
}>();

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const variantMap: Record<string, BadgeVariant> = {
    AVAILABLE: 'default',
    RESERVED: 'secondary',
    REDEEMED: 'default',
    EXPIRED: 'outline',
    FAILED: 'destructive',
    UNPAID: 'outline',
    PAID: 'default',
    PENDING: 'secondary',
    PROCESSING: 'secondary',
    SUCCESS: 'default',
    ACTIVE: 'default',
    INACTIVE: 'outline',
    CANCELED: 'destructive',
    CANCELLED: 'destructive',
};

const classMap: Record<string, string> = {
    AVAILABLE: 'bg-emerald-600 text-white hover:bg-emerald-600/90',
    REDEEMED: 'bg-blue-600 text-white hover:bg-blue-600/90',
    PAID: 'bg-emerald-600 text-white hover:bg-emerald-600/90',
    SUCCESS: 'bg-emerald-600 text-white hover:bg-emerald-600/90',
    PROCESSING: 'bg-amber-500 text-white hover:bg-amber-500/90',
    RESERVED: 'bg-amber-500 text-white hover:bg-amber-500/90',
    PENDING: 'bg-amber-500 text-white hover:bg-amber-500/90',
    FAILED: 'bg-red-600 text-white hover:bg-red-600/90',
    CANCELED: 'bg-red-600 text-white hover:bg-red-600/90',
    CANCELLED: 'bg-red-600 text-white hover:bg-red-600/90',
    EXPIRED: 'text-muted-foreground',
    UNPAID: 'text-muted-foreground',
    ACTIVE: 'bg-emerald-600 text-white hover:bg-emerald-600/90',
};

const variant = computed<BadgeVariant>(() => variantMap[props.status] ?? 'outline');
const extraClass = computed(() => classMap[props.status] ?? '');
const displayLabel = computed(() => {
    if (props.status === 'CANCELED' || props.status === 'CANCELLED') {
        return 'Canceled';
    }
    return props.status;
});
</script>

<template>
    <Badge :variant="variant" :class="extraClass">{{ displayLabel }}</Badge>
</template>
