<script setup lang="ts">
import { ChevronDown, Loader2, Search, X } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface RegionItem {
    id: number | string;
    name: string;
    code?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null;
        name: string;
        placeholder?: string;
        disabled?: boolean;
        required?: boolean;
        endpoint: string;
        parentParamName?: string;
        parentValue?: string | number | null;
        initialOptions?: string[] | RegionItem[];
        valueKey?: 'name' | 'id' | 'code';
        displayLabel?: string | null;
    }>(),
    {
        modelValue: '',
        placeholder: 'Pilih...',
        disabled: false,
        required: false,
        parentParamName: '',
        parentValue: null,
        initialOptions: () => [],
        valueKey: 'name',
        displayLabel: null,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number): void;
    (e: 'change', item: RegionItem | null): void;
}>();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const selectedLabel = ref<string | null>(null);

const searchQuery = ref('');
const items = ref<RegionItem[]>([]);
const currentPage = ref(1);
const totalItems = ref(0);
const hasMore = ref(false);
const isLoading = ref(false);
const isLoadingMore = ref(false);

onClickOutside(containerRef, () => {
    isOpen.value = false;
});

const displayValue = computed(() => {
    if (selectedLabel.value) return selectedLabel.value;
    if (props.displayLabel) return props.displayLabel;
    if (!props.modelValue) return '';
    return String(props.modelValue);
});

const isParentMissing = computed(() => {
    if (!props.parentParamName) return false;
    return props.parentValue === null || props.parentValue === undefined || props.parentValue === '';
});

const isDisabled = computed(() => props.disabled || isParentMissing.value);

async function fetchItems(page = 1, append = false): Promise<void> {
    if (isDisabled.value) return;

    if (append) {
        isLoadingMore.value = true;
    } else {
        isLoading.value = true;
    }

    try {
        const url = new URL(props.endpoint, window.location.origin);
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', '20');

        if (props.parentParamName && props.parentValue) {
            url.searchParams.set(props.parentParamName, String(props.parentValue));
        }

        if (searchQuery.value.trim() !== '') {
            url.searchParams.set('search', searchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch regions: ${response.statusText}`);
        }

        const json = await response.json();
        const rawData: Array<{ id: number | string; name: string; code?: string }> = json.data || [];

        const formatted = rawData.map((d) => ({
            id: d.id,
            name: d.name,
            code: d.code,
        }));

        if (append) {
            items.value = [...items.value, ...formatted];
        } else {
            items.value = formatted;
        }

        currentPage.value = json.current_page || 1;
        totalItems.value = json.total || formatted.length;
        hasMore.value = Boolean(json.has_more);
    } catch (error) {
        console.error('RegionSelect fetch error:', error);
    } finally {
        isLoading.value = false;
        isLoadingMore.value = false;
    }
}

const debouncedSearch = useDebounceFn(() => {
    fetchItems(1, false);
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

// When parent changes, reload list if open. Only clear selection if parent is wiped.
watch(
    () => props.parentValue,
    (newVal, oldVal) => {
        if (oldVal !== undefined && newVal !== oldVal) {
            items.value = [];
            currentPage.value = 1;
            hasMore.value = false;
            totalItems.value = 0;
            searchQuery.value = '';

            if (!newVal) {
                selectedLabel.value = null;
                emit('update:modelValue', '');
                emit('change', null);
            } else {
                fetchItems(1, false);
            }
        }
    },
);

function toggleDropdown(): void {
    if (isDisabled.value) return;

    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        if (items.value.length === 0) {
            fetchItems(1, false);
        }
        nextTick(() => {
            searchInputRef.value?.focus();
        });
    }
}

function selectItem(item: RegionItem): void {
    selectedLabel.value = item.name;
    const value = props.valueKey === 'id' ? item.id : props.valueKey === 'code' ? (item.code ?? item.name) : item.name;
    emit('update:modelValue', value);
    emit('change', item);
    isOpen.value = false;
}

function clearSelection(e: MouseEvent): void {
    e.stopPropagation();
    if (isDisabled.value) return;
    selectedLabel.value = null;
    emit('update:modelValue', '');
    emit('change', null);
}

function loadMore(): void {
    if (!hasMore.value || isLoadingMore.value) return;
    fetchItems(currentPage.value + 1, true);
}

function isSelected(item: RegionItem): boolean {
    if (selectedLabel.value) {
        return item.name === selectedLabel.value;
    }

    return String(item.name).toLowerCase() === String(props.modelValue ?? '').toLowerCase();
}

onMounted(() => {
    // If initial options provided and no parent, use them
    if (props.initialOptions && props.initialOptions.length > 0 && !props.parentParamName) {
        items.value = props.initialOptions.map((opt, i) =>
            typeof opt === 'string' ? { id: i + 1, name: opt } : opt,
        );
        totalItems.value = items.value.length;
    } else if (!isDisabled.value && props.modelValue) {
        fetchItems(1, false);
    }
});
</script>

<template>
    <div ref="containerRef" class="relative w-full text-left">
        <!-- Hidden input for form submission -->
        <input
            :id="name"
            type="hidden"
            :name="name"
            :value="modelValue ?? ''"
            :required="required"
            :disabled="isDisabled"
        />

        <!-- Trigger Box -->
        <div
            role="combobox"
            :aria-expanded="isOpen"
            :tabindex="isDisabled ? -1 : 0"
            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full min-w-0 items-center justify-between rounded-md border px-3 py-2 text-sm shadow-xs transition select-none cursor-pointer focus-visible:ring-2 focus-visible:outline-none"
            :class="[
                isDisabled ? 'cursor-not-allowed bg-neutral-100 opacity-60' : '',
                isOpen ? 'ring-ring ring-2' : '',
                !modelValue ? 'text-muted-foreground' : 'text-foreground',
            ]"
            @click="toggleDropdown"
            @keydown.enter.prevent="toggleDropdown"
            @keydown.space.prevent="toggleDropdown"
        >
            <span class="truncate font-normal min-w-0 flex-1">
                {{ displayValue || placeholder }}
            </span>
            <div class="flex items-center gap-1.5 pl-2 text-muted-foreground shrink-0">
                <span
                    v-if="modelValue && !isDisabled"
                    role="button"
                    tabindex="0"
                    class="rounded-full p-0.5 hover:bg-neutral-100 hover:text-foreground inline-flex items-center justify-center cursor-pointer"
                    title="Hapus pilihan"
                    @click.stop="clearSelection"
                >
                    <X class="size-3.5" />
                </span>
                <ChevronDown
                    class="size-4 shrink-0 transition-transform duration-200"
                    :class="isOpen ? 'rotate-180' : ''"
                />
            </div>
        </div>

        <!-- Dropdown Popover -->
        <div
            v-if="isOpen"
            class="bg-popover text-popover-foreground absolute left-0 top-full z-50 mt-1 max-h-72 w-full min-w-[200px] overflow-hidden rounded-md border bg-white shadow-lg dark:bg-neutral-900"
        >
            <!-- Search Input Box -->
            <div class="border-b p-2">
                <div class="relative flex items-center">
                    <Search class="text-muted-foreground absolute left-2.5 size-4" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        class="placeholder:text-muted-foreground focus-visible:ring-ring flex h-8 w-full rounded-md border bg-transparent pr-3 pl-8 text-xs focus-visible:ring-1 focus-visible:outline-none"
                        placeholder="Cari..."
                    />
                </div>
            </div>

            <!-- Options List -->
            <div class="max-h-48 overflow-y-auto p-1 text-sm">
                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="text-muted-foreground flex items-center justify-center py-6 text-xs"
                >
                    <Loader2 class="size-4 animate-spin mr-2" />
                    Memuat data...
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="items.length === 0"
                    class="text-muted-foreground py-6 text-center text-xs"
                >
                    Tidak ada data ditemukan.
                </div>

                <!-- Item list -->
                <template v-else>
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        class="hover:bg-accent hover:text-accent-foreground relative flex w-full cursor-pointer select-none items-center rounded-sm px-2.5 py-1.5 text-xs text-left outline-none transition"
                        :class="[
                            isSelected(item)
                                ? 'bg-neutral-100 font-semibold text-neutral-900'
                                : '',
                        ]"
                        @click="selectItem(item)"
                    >
                        <span class="truncate">{{ item.name }}</span>
                    </button>

                    <!-- Pagination / Load More Button -->
                    <div v-if="hasMore" class="border-t pt-1 mt-1 text-center">
                        <button
                            type="button"
                            :disabled="isLoadingMore"
                            class="text-muted-foreground hover:text-foreground text-xs py-1.5 px-3 w-full font-medium inline-flex items-center justify-center gap-1 transition"
                            @click="loadMore"
                        >
                            <Loader2 v-if="isLoadingMore" class="size-3 animate-spin" />
                            <span>{{ isLoadingMore ? 'Memuat...' : 'Muat lebih banyak...' }}</span>
                        </button>
                    </div>

                    <!-- Total Counter -->
                    <div class="text-muted-foreground px-2 py-1 text-[11px] text-right border-t border-neutral-100 mt-1">
                        Menampilkan {{ items.length }} dari {{ totalItems }}
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
