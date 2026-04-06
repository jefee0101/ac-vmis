<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';

interface Option {
    id: number;
    name: string;
    meta?: string | null;
    disabled?: boolean;
    unavailable_reason?: string | null;
}

const props = defineProps<{
    modelValue: number | null;
    options: Option[];
    placeholder?: string;
    loading?: boolean;
    badgeClass?: string;
    removeClass?: string;
}>();

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const selectedOption = computed(() => {
    if (props.modelValue === null) return null;
    return props.options.find(o => o.id === props.modelValue) || null;
});

const filteredOptions = computed(() => {
    return props.options.filter(option =>
        option.name.toLowerCase().includes(search.value.toLowerCase())
    );
});

function selectOption(option: Option) {
    if (option.disabled) return;
    emit('update:modelValue', option.id);
    search.value = '';
    isOpen.value = false;
}

function removeSelection() {
    emit('update:modelValue', null);
    search.value = '';
    isOpen.value = false;
}

watch(() => props.modelValue, (newVal) => {
    if (newVal === null) {
        search.value = '';
        isOpen.value = false;
    }
});

function onDocumentClick(event: MouseEvent) {
    const target = event.target as Node | null;
    if (!rootRef.value || !target) return;
    if (!rootRef.value.contains(target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="relative w-full overflow-visible">
        <!-- Selected Badge -->
        <div v-if="selectedOption" class="mb-2 flex flex-wrap items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm" :class="badgeClass || 'bg-slate-100 text-slate-700'">
                {{ selectedOption.name }}
            </span>
            <span v-if="selectedOption.meta" class="text-xs text-slate-500">
                {{ selectedOption.meta }}
            </span>
            <button @click="removeSelection" class="font-bold" :class="removeClass || 'text-red-500'">
                ×
            </button>
        </div>

        <!-- Search Input -->
        <input
            v-model="search"
            @focus="isOpen = true"
            type="text"
            :placeholder="placeholder || 'Search...'"
            :disabled="loading"
            class="w-full border rounded px-3 py-2 disabled:opacity-60 disabled:cursor-not-allowed"
        />

        <!-- Dropdown -->
        <div
            v-if="isOpen && loading"
            class="absolute z-[80] bg-white border rounded w-full mt-1 p-2 shadow-lg"
        >
            <Skeleton class="h-8 mb-2" />
            <Skeleton class="h-8" />
        </div>

        <div
            v-else-if="isOpen && filteredOptions.length"
            class="absolute z-[80] bg-white border rounded w-full mt-1 max-h-52 overflow-y-auto shadow-lg"
        >
            <div
                v-for="option in filteredOptions"
                :key="option.id"
                @click="selectOption(option)"
                class="px-3 py-2"
                :class="option.disabled ? 'cursor-not-allowed bg-slate-50 opacity-70' : 'cursor-pointer hover:bg-gray-100'"
            >
                <p class="text-sm font-medium text-slate-800">{{ option.name }}</p>
                <p v-if="option.meta" class="text-xs text-slate-500">{{ option.meta }}</p>
                <p v-if="option.disabled" class="text-xs font-medium text-amber-700">
                    {{ option.unavailable_reason || 'Unavailable for assignment' }}
                </p>
            </div>
        </div>

        <div
            v-else-if="isOpen && !loading"
            class="absolute z-[80] bg-white border rounded w-full mt-1 p-2 text-sm text-gray-500 shadow-lg"
        >
            No results found.
        </div>
    </div>
</template>
