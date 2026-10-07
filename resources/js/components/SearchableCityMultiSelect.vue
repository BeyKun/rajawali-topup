<script setup lang="ts">
import { Check, ChevronDown, Search, X } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, ref } from 'vue';

export interface CityOption {
    id: number;
    name: string;
    province_name?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: number[];
        cities: CityOption[];
        placeholder?: string;
        disabled?: boolean;
    }>(),
    {
        modelValue: () => [],
        cities: () => [],
        placeholder: 'Pilih wilayah / kabupaten-kota...',
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number[]): void;
}>();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');

onClickOutside(containerRef, () => {
    isOpen.value = false;
});

const filteredCities = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.cities;
    }
    return props.cities.filter((c) => {
        const cityName = c.name.toLowerCase();
        const provName = (c.province_name || '').toLowerCase();
        return cityName.includes(q) || provName.includes(q);
    });
});

const selectedCities = computed(() => {
    const set = new Set(props.modelValue);
    return props.cities.filter((c) => set.has(c.id));
});

function toggleOpen(): void {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            searchInputRef.value?.focus();
        });
    }
}

function toggleCity(cityId: number): void {
    const current = [...props.modelValue];
    const index = current.indexOf(cityId);
    if (index === -1) {
        current.push(cityId);
    } else {
        current.splice(index, 1);
    }
    emit('update:modelValue', current);
}

function removeCity(cityId: number): void {
    const next = props.modelValue.filter((id) => id !== cityId);
    emit('update:modelValue', next);
}

function selectAllFiltered(): void {
    const currentSet = new Set(props.modelValue);
    filteredCities.value.forEach((c) => currentSet.add(c.id));
    emit('update:modelValue', Array.from(currentSet));
}

function clearAll(): void {
    emit('update:modelValue', []);
}
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger Button -->
        <button
            type="button"
            :disabled="disabled"
            class="flex min-h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            @click="toggleOpen"
        >
            <span v-if="modelValue.length === 0" class="text-muted-foreground">
                {{ placeholder }}
            </span>
            <span v-else class="font-medium text-foreground">
                {{ modelValue.length }} wilayah dipilih
            </span>
            <ChevronDown class="size-4 opacity-50 transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <!-- Dropdown Popover -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 max-h-80 w-full rounded-md border bg-popover text-popover-foreground shadow-md outline-none animate-in fade-in-0 zoom-in-95"
        >
            <!-- Search bar & actions -->
            <div class="border-b p-2">
                <div class="relative flex items-center">
                    <Search class="absolute left-2.5 size-4 text-muted-foreground" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama kota atau provinsi..."
                        class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                </div>
                <div class="mt-2 flex items-center justify-between px-1 text-xs text-muted-foreground">
                    <button
                        type="button"
                        class="hover:text-primary hover:underline"
                        @click="selectAllFiltered"
                    >
                        Pilih semua yang tampil ({{ filteredCities.length }})
                    </button>
                    <button
                        v-if="modelValue.length > 0"
                        type="button"
                        class="text-destructive hover:underline"
                        @click="clearAll"
                    >
                        Reset pilihan ({{ modelValue.length }})
                    </button>
                </div>
            </div>

            <!-- List of cities -->
            <div class="max-h-52 overflow-y-auto p-1">
                <div
                    v-if="filteredCities.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Tidak ada wilayah yang cocok.
                </div>
                <div
                    v-for="city in filteredCities"
                    :key="city.id"
                    class="flex cursor-pointer items-center justify-between rounded-sm px-2.5 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                    @click="toggleCity(city.id)"
                >
                    <div class="flex flex-col">
                        <span class="font-medium">{{ city.name }}</span>
                        <span v-if="city.province_name" class="text-xs text-muted-foreground">
                            {{ city.province_name }}
                        </span>
                    </div>
                    <div
                        class="flex size-4 items-center justify-center rounded border"
                        :class="
                            modelValue.includes(city.id)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-muted-foreground/30'
                        "
                    >
                        <Check v-if="modelValue.includes(city.id)" class="size-3 stroke-[3]" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected Tags/Badges Preview -->
        <div v-if="selectedCities.length > 0" class="mt-2 flex flex-wrap gap-1.5">
            <span
                v-for="city in selectedCities"
                :key="city.id"
                class="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
            >
                {{ city.name }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-muted"
                    @click="removeCity(city.id)"
                >
                    <X class="size-3" />
                </button>
            </span>
        </div>
    </div>
</template>
