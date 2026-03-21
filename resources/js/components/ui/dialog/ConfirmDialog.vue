<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  showCancel?: boolean
  confirmVariant?: 'default' | 'destructive'
  loading?: boolean
}>(), {
  description: '',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  showCancel: true,
  confirmVariant: 'default',
  loading: false,
})

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void
  (event: 'confirm'): void
}>()

const confirmButtonClass = computed(() =>
  props.confirmVariant === 'destructive'
    ? 'inline-flex items-center justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60'
    : 'inline-flex items-center justify-center rounded-md bg-[#1f2937] px-3 py-2 text-sm font-semibold text-white hover:bg-[#334155] disabled:cursor-not-allowed disabled:opacity-60',
)

function close() {
  if (props.loading) return
  emit('update:open', false)
}

function onConfirm() {
  if (props.loading) return
  emit('confirm')
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/45 p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="title"
    @click.self="close"
  >
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl">
      <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
      <p v-if="description" class="mt-2 text-sm text-slate-600">{{ description }}</p>

      <div v-if="$slots.default" class="mt-3">
        <slot />
      </div>

      <div class="mt-5 flex items-center justify-end gap-2">
        <button
          v-if="showCancel"
          type="button"
          class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loading"
          @click="close"
        >
          {{ cancelText }}
        </button>
        <button type="button" :class="confirmButtonClass" :disabled="loading" @click="onConfirm">
          {{ confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>
