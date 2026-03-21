<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';

interface Option {
    id: number;
    name: string;
}

const props = defineProps<{
    modelValue: number | null;
    options: Option[];
    placeholder?: string;
    loading?: boolean;
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
    <div ref="rootRef" class="relative w-full">
        <!-- Selected Badge -->
        <div v-if="selectedOption" class="flex items-center gap-2 mb-2">
            <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-full text-sm">
                {{ selectedOption.name }}
            </span>
            <button @click="removeSelection" class="text-red-500 font-bold">
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
            class="absolute z-10 bg-white border rounded w-full mt-1 p-2 shadow"
        >
            <Skeleton class="h-8 mb-2" />
            <Skeleton class="h-8" />
        </div>

        <div
            v-else-if="isOpen && filteredOptions.length"
            class="absolute z-10 bg-white border rounded w-full mt-1 max-h-40 overflow-y-auto shadow"
        >
            <div
                v-for="option in filteredOptions"
                :key="option.id"
                @click="selectOption(option)"
                class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
            >
                {{ option.name }}
            </div>
        </div>

        <div
            v-else-if="isOpen && !loading"
            class="absolute z-10 bg-white border rounded w-full mt-1 p-2 text-sm text-gray-500 shadow"
        >
            No results found.
        </div>
    </div>
</template>
