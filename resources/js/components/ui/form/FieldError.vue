<script setup lang="ts">
withDefaults(
    defineProps<{
        message?: string | null;
        id?: string;
    }>(),
    {
        message: '',
        id: undefined,
    },
);
</script>

<template>
    <p v-if="message" :id="id" class="field-error">{{ message }}</p>
</template>

<style scoped>
.field-error {
    margin-top: 0.35rem;
    font-size: 0.8rem;
    line-height: 1.35;
    color: #be123c !important;
    font-weight: 500;
    background: rgba(255, 241, 242, 0.92);
    border: 1px solid rgba(225, 29, 72, 0.28);
    border-radius: 8px;
    padding: 0.25rem 0.45rem;
    display: inline-block;
}
</style>
