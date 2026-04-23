<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PublicLayout from '@/components/Public/PublicLayout.vue';
import FieldError from '@/components/ui/form/FieldError.vue';
import FormAlert from '@/components/ui/form/FormAlert.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';

const props = defineProps<{
    email: string;
    token: string;
}>();

const page = usePage();
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    email: props.email ?? '',
    token: props.token ?? '',
    password: '',
    password_confirmation: '',
});

const generalError = computed(() => String((page.props as any)?.errors?.message ?? ''));

function submit() {
    form.post('/reset-password', {
        preserveScroll: true,
    });
}

function toLogin() {
    router.visit('/Login');
}
</script>

<template>
    <PublicLayout title="Reset Password" page-title="Reset Password" page-description="Set a new password to restore access to your account.">
        <section class="login-shell">
            <div class="login-grid">
                <section class="public-card login-copy">
                    <p class="copy-kicker">Account Recovery</p>
                    <h1>Create a New Password</h1>
                    <p>Enter and confirm your new password to complete the password reset process.</p>
                </section>

                <section class="public-card login-card">
                    <h2>Set New Password</h2>

                    <form @submit.prevent="submit" class="login-form">
                        <FormAlert tone="error" :message="generalError" />

                        <div class="form-stack">
                            <input v-model="form.email" type="email" class="field-input" readonly />

                            <div class="password-field">
                                <div class="password-field__control">
                                    <input
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="New password"
                                        :class="['field-input', 'pr-10', { 'is-error': !!form.errors.password }]"
                                        :aria-invalid="form.errors.password ? 'true' : 'false'"
                                        aria-describedby="reset-password-error"
                                    />
                                    <button
                                        type="button"
                                        class="toggle-eye absolute top-1/2 right-3 -translate-y-1/2"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        @click="showPassword = !showPassword"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <FieldError id="reset-password-error" :message="form.errors.password" />
                            </div>

                            <div class="password-field">
                                <div class="password-field__control">
                                    <input
                                        v-model="form.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        placeholder="Confirm new password"
                                        :class="['field-input', 'pr-10', { 'is-error': !!form.errors.password_confirmation }]"
                                        :aria-invalid="form.errors.password_confirmation ? 'true' : 'false'"
                                        aria-describedby="reset-password-confirmation-error"
                                    />
                                    <button
                                        type="button"
                                        class="toggle-eye absolute top-1/2 right-3 -translate-y-1/2"
                                        :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <FieldError id="reset-password-confirmation-error" :message="form.errors.password_confirmation" />
                            </div>
                        </div>

                        <button type="submit" class="login-btn mt-3" :disabled="form.processing">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="form.processing" class="h-4 w-4 text-[#034485]" />
                                {{ form.processing ? 'Saving...' : 'Reset Password' }}
                            </span>
                        </button>

                        <p class="register-note">
                            Return to account access:
                            <button type="button" @click="toLogin" class="register-link">Go to Sign In</button>
                        </p>
                    </form>
                </section>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.login-shell {
    padding: 1.4rem 0 2.2rem;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
}

.login-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1.25rem;
    align-items: center;
}

.login-copy {
    display: grid;
    gap: 0.75rem;
}

.copy-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.login-copy h1 {
    margin-top: 0.2rem;
    font-size: 2rem;
    line-height: 1.1;
    color: #ffffff;
    font-weight: 800;
}

.login-copy p {
    margin-top: 0.35rem;
    color: rgba(255, 255, 255, 0.86);
    line-height: 1.65;
    max-width: 56ch;
}

.login-card {
    display: grid;
    gap: 0.75rem;
    min-width: 0;
}

.login-card h2 {
    font-size: 1.35rem;
    color: #ffffff;
    font-weight: 800;
}

.form-stack {
    margin-top: 0.4rem;
    display: grid;
    gap: 0.65rem;
}

.password-field {
    display: grid;
    gap: 0.35rem;
}

.password-field__control {
    position: relative;
}

.field-input {
    width: 100%;
    border-radius: 12px;
    border: 1px solid rgba(3, 68, 133, 0.25);
    background: #ffffff;
    color: #0b1b2b;
    padding: 0.7rem 0.85rem;
    font-size: 0.98rem;
}

.field-input.is-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
}

.field-input:focus {
    outline: none;
    border-color: rgba(3, 68, 133, 0.45);
    box-shadow: 0 0 0 2px rgba(3, 68, 133, 0.15);
}

.login-btn {
    width: 100%;
    min-height: 56px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    border: 1px solid #ffffff;
    background: #ffffff;
    color: #034485;
}

.register-note {
    margin-top: 0.35rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.9rem;
    line-height: 1.6;
}

.register-link {
    border: none;
    background: none;
    color: #ffffff;
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

.register-link:hover {
    text-decoration: underline;
}

.toggle-eye {
    color: rgba(3, 68, 133, 0.65);
}

@media (max-width: 900px) {
    .login-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 640px) {
    .login-shell {
        padding: 0.85rem 0 1.8rem;
    }

    .login-copy,
    .login-card {
        padding-inline: 1.15rem;
    }

    .login-copy h1 {
        font-size: 1.6rem;
    }
}
</style>
