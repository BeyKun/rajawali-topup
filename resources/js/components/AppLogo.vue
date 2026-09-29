<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = computed(() => {
    const serverName = page.props.name as string | undefined;
    if (serverName && serverName !== 'Laravel') {
        return serverName;
    }
    return (
        (import.meta.env.VITE_APP_NAME as string | undefined) || 'Rajawali Topup'
    );
});
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-full"
    >
        <AppLogoIcon class="size-8" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
