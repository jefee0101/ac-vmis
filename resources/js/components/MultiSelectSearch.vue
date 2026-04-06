<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';

interface Option {
    id: number;
    name: string;
    [key: string]: any;
}

const props = defineProps<{
    modelValue: number[];
    options: Option[];
    placeholder?: string;
    loading?: boolean;
    tagStyle?: (option: Option) => Record<string, string>;
}>();

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const filteredOptions = computed(() => {
    return props.options.filter(option =>
        option.name.toLowerCase().includes(search.value.toLowerCase()) &&
        !props.modelValue.includes(option.id)
    );
});

const selectedOptions = computed(() =>
    props.modelValue
        .map((id) => props.options.find((option) => option.id === id))
        .filter((option): option is Option => Boolean(option))
);

function addOption(option: Option) {
    emit('update:modelValue', [...props.modelValue, option.id]);
    search.value = '';
}

function removeOption(id: number) {
    emit('update:modelValue', props.modelValue.filter(i => i !== id));
}

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
        <!-- Selected Tags -->
        <div class="flex flex-wrap gap-2 mb-2">
            <span
                v-for="option in selectedOptions"
                :key="option.id"
                class="flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
                :class="tagStyle ? 'border-white/30' : 'bg-green-100 text-green-700 border-green-200'"
                :style="tagStyle ? tagStyle(option) : undefined"
            >
                {{ option.name }}
                <button
                    @click="removeOption(option.id)"
                    class="font-bold"
                    :class="tagStyle ? 'opacity-70 hover:opacity-100' : 'text-red-500'"
                >
                    ×
                </button>
            </span>
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
        <div v-if="isOpen && loading"
             class="absolute z-[80] bg-white border rounded w-full mt-1 p-2 shadow-lg">
            <Skeleton class="h-8 mb-2" />
            <Skeleton class="h-8" />
        </div>

        <div v-else-if="isOpen && filteredOptions.length"
             class="absolute z-[80] bg-white border rounded w-full mt-1 max-h-52 overflow-y-auto shadow-lg">
            <div v-for="option in filteredOptions"
                 :key="option.id"
                 @click="addOption(option)"
                 class="px-3 py-2 hover:bg-gray-100 cursor-pointer">
                {{ option.name }}
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
