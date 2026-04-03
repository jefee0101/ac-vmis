<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        message?: string | null;
        title?: string | null;
        tone?: 'error' | 'success' | 'warning' | 'info';
        compact?: boolean;
    }>(),
    {
        message: '',
        title: '',
        tone: 'error',
        compact: false,
    },
);

const alertClass = computed(() => {
    if (props.tone === 'success') return 'form-alert tone-success';
    if (props.tone === 'warning') return 'form-alert tone-warning';
    if (props.tone === 'info') return 'form-alert tone-info';
    return 'form-alert tone-error';
});

const role = computed(() => (props.tone === 'error' || props.tone === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div v-if="message" :class="[alertClass, { compact }]" :role="role" aria-live="assertive">
        <span class="form-alert-icon" aria-hidden="true">!</span>
        <div>
            <p v-if="title" class="form-alert-title">{{ title }}</p>
            <p class="form-alert-message">{{ message }}</p>
        </div>
    </div>
</template>

<style scoped>
.form-alert {
    border-radius: 12px;
    border: 1px solid;
    border-left-width: 4px;
    padding: 0.7rem 0.85rem;
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: start;
    gap: 0.45rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.form-alert.compact {
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
}

.form-alert-icon {
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1;
    margin-top: 0.02rem;
}

.form-alert-title {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: inherit !important;
}

.form-alert-message {
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.4;
    color: inherit !important;
}

.tone-error {
    background: #fef2f2;
    border-color: #ef4444;
    color: #7f1d1d !important;
}

.tone-error .form-alert-icon {
    background: #b91c1c;
    color: #ffffff;
}

.tone-success {
    background: #ecfdf5;
    border-color: #10b981;
    color: #065f46 !important;
}

.tone-success .form-alert-icon {
    background: #047857;
    color: #ffffff;
}

.tone-warning {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #92400e !important;
}

.tone-warning .form-alert-icon {
    background: #b45309;
    color: #ffffff;
}

.tone-info {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8 !important;
}

.tone-info .form-alert-icon {
    background: #1d4ed8;
    color: #ffffff;
}
</style>
