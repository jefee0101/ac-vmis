<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { computed, watch } from 'vue'

const page = usePage()
const toast = useToast()
let lastMessageKey = ''

const loginSuccessMessage = computed(() => String((page.props as any)?.flash?.login_success ?? '').trim())

watch(
    loginSuccessMessage,
    (message) => {
        if (!message) return

        const key = `${page.url}::${message}`
        if (key === lastMessageKey) return
        lastMessageKey = key

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: message,
            life: 2200,
        })
    },
    { immediate: true },
)
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>
