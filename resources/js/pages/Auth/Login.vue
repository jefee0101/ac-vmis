<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useInertiaLoading } from '@/composables/useInertiaLoading';
import Spinner from '@/components/ui/spinner/Spinner.vue';

const email = ref('');
const password = ref('');
const remember = ref(false);
const error = ref('');
const isSubmitting = ref(false);
const showPassword = ref(false);
const { isLoading } = useInertiaLoading();

function toWelcome() {
    router.visit('/');
}
function toRegister() {
    router.visit('Register');
}
function login() {
    if (isSubmitting.value) return;

    if (!email.value || !password.value) {
        error.value = 'Please fill in all fields.';
        return;
    }

    // Post to your login endpoint
    router.post('/login', {
        email: email.value,
        password: password.value,
        remember: remember.value,
    }, {
        onStart: () => {
            isSubmitting.value = true;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
        onError: (e: any) => {
            error.value = e.message || 'Login failed.';
        }
    });
}
</script>

<template>
    <Head title="Login" />

    <div class="login-page">
        <header class="login-header px-4 pt-4 pb-4 sm:px-6 sm:pt-6 lg:px-10">
            <div v-if="isLoading" class="top-loading" />
            <div class="mx-auto max-w-6xl login-nav">
                <div>
                    <p class="kicker">Asian College</p>
                    <p class="brand">Varsity Management Information System</p>
                </div>
                <button @click="toWelcome" class="btn-outline" :disabled="isLoading || isSubmitting">Back To Home</button>
            </div>
        </header>

        <main class="login-main px-4 py-8 sm:px-6 lg:px-10">
            <div class="mx-auto max-w-6xl login-grid">
                <section class="login-copy">
                    <p class="copy-kicker">Account Access</p>
                    <h1>Welcome Back</h1>
                    <p>
                        Sign in to continue with schedules, attendance, wellness monitoring,
                        academic tracking, and varsity announcements.
                    </p>
                </section>

                <section class="login-card">
                    <h2>Login</h2>

                    <div v-if="error" class="error-text">
                        {{ error }}
                    </div>

                    <div class="form-stack">
                        <input
                            v-model="email"
                            type="email"
                            placeholder="Email"
                            class="field-input"
                        />

                        <div class="relative">
                            <input
                                v-model="password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Password"
                                class="field-input pr-10"
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 3l18 18" />
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                    <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                                    <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                                </svg>
                                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>

                        <label class="remember-row">
                            <input v-model="remember" type="checkbox" class="remember-checkbox" />
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button @click="login" class="btn-fill login-btn" :disabled="isSubmitting">
                        <span class="inline-flex items-center gap-2">
                            <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                            {{ isSubmitting ? 'Signing in...' : 'Login' }}
                        </span>
                    </button>

                    <p class="register-note">
                        Don’t have an account?
                        <button @click="toRegister" class="register-link" :disabled="isSubmitting">Register</button>
                    </p>
                </section>
            </div>
        </main>

        <footer class="site-footer px-4 pb-5 sm:px-6 lg:px-10">
            <div class="mx-auto max-w-6xl footer-shell">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            One platform for varsity registration, team setup, schedules, attendance, wellness, academic tracking, and
                            announcements.
                        </p>
                    </section>

                    <nav class="footer-col" aria-label="Public Pages">
                        <p class="footer-heading">Public Pages</p>
                        <div class="footer-link-list">
                            <button @click="toWelcome" class="footer-link footer-link-btn">Home</button>
                            <button @click="toRegister" class="footer-link footer-link-btn">Register</button>
                            <button @click="toWelcome" class="footer-link footer-link-btn">How It Works</button>
                            <button @click="toWelcome" class="footer-link footer-link-btn">Contact</button>
                        </div>
                    </nav>
                </div>

                <div class="footer-bottom-row">
                    <p>v1.0 • © 2026 Asian College</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.login-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #1f2937;
    display: flex;
    flex-direction: column;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
}

.login-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(2px);
    border-bottom:  1px solid rgba(15, 23, 42, 0.56);
}

.login-nav {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 14px;
    background: #ffffff;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.top-loading {
    height: 3px;
    width: 100%;
    border-radius: 999px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #1f2937 0%, #475569 40%, #94a3b8 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1s linear infinite;
}

.kicker {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: #94a3b8;
}

.brand {
    margin-top: 2px;
    font-size: 1rem;
    font-weight: 800;
    color: #1f2937;
}

.btn-fill,
.btn-outline {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    transition: 0.16s ease;
}

.btn-fill {
    color: #ffffff;
    border: 1px solid #1f2937;
    background: #1f2937;
}

.btn-fill:hover {
    background: #334155;
}

.btn-fill:disabled,
.btn-outline:disabled,
.register-link:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.btn-outline {
    color: #1f2937;
    border: 1px solid rgba(15, 23, 42, 0.56);
    background: #ffffff;
}

.btn-outline:hover {
    background: #f2f8ff;
}

.login-main {
    display: flex;
    align-items: center;
    min-height: calc(100vh - 240px);
}

.login-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1rem;
    align-items: center;
}

.login-copy {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 16px;
    background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
    padding: 1.25rem;
}

.copy-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    font-weight: 700;
}

.login-copy h1 {
    margin-top: 0.45rem;
    font-size: 2rem;
    line-height: 1.1;
    color: #1f2937;
    font-weight: 800;
}

.login-copy p {
    margin-top: 0.75rem;
    color: #64748b;
    line-height: 1.65;
    max-width: 52ch;
}

.login-card {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 16px;
    background: #ffffff;
    padding: 1.25rem;
}

.login-card h2 {
    font-size: 1.35rem;
    color: #1f2937;
    font-weight: 800;
}

.error-text {
    margin-top: 0.65rem;
    font-size: 0.88rem;
    color: #b91c1c;
}

.form-stack {
    margin-top: 0.8rem;
    display: grid;
    gap: 0.65rem;
}

.field-input {
    width: 100%;
    border: 1px solid rgba(15, 23, 42, 0.42);
    border-radius: 10px;
    background: #ffffff;
    padding: 0.72rem 0.78rem;
    color: #1f2937;
}

.field-input:focus {
    outline: none;
    border-color: #1f2937;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.14);
}

.remember-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: #64748b;
    font-size: 0.9rem;
}

.remember-checkbox {
    accent-color: #1f2937;
}

.login-btn {
    width: 100%;
    margin-top: 0.85rem;
}

.register-note {
    margin-top: 0.75rem;
    text-align: center;
    color: #64748b;
    font-size: 0.9rem;
}

.register-link {
    border: none;
    background: none;
    color: #1f2937;
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

.register-link:hover {
    text-decoration: underline;
}

.site-footer {
    margin-top: auto;
    border-top:  1px solid rgba(15, 23, 42, 0.56);
    background: linear-gradient(180deg, #f8fafc 0%, #f8fafc 100%);
    padding-top: 1rem;
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: #64748b;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1rem;
}

.footer-col-brand {
    max-width: 52ch;
}

.footer-brand {
    color: #1f2937;
    font-size: 1rem;
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.5rem;
    color: #64748b;
    line-height: 1.55;
    font-size: 0.9rem;
}

.footer-heading {
    color: #1f2937;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.74rem;
    font-weight: 800;
}

.footer-link-list {
    margin-top: 0.55rem;
    display: grid;
    gap: 0.42rem;
}

.footer-link {
    color: #64748b;
    text-decoration: none;
    font-size: 0.9rem;
}

.footer-link:hover {
    color: #1f2937;
}

.footer-link-btn {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
}

.footer-bottom-row {
    margin-top: 0.8rem;
    border-top: 1px solid rgba(15, 23, 42, 0.36);
    padding-top: 0.65rem;
    color: #475569;
    font-size: 0.82rem;
}

@media (max-width: 900px) {
    .login-grid {
        grid-template-columns: 1fr;
    }

    .footer-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .brand {
        font-size: 0.9rem;
    }

    .login-main {
        min-height: auto;
    }

    .login-copy h1 {
        font-size: 1.6rem;
    }
}

@media (min-width: 1024px) {
    .login-page {
        font-size: 1.125rem;
    }
}

@keyframes loading-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
