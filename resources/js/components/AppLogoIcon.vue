<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/app-logo.png"
        alt="Rajawali Topup"
        :class="['rounded-full select-none object-contain shrink-0', className]"
        v-bind="$attrs"
    />
</template>
