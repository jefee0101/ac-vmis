<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useInertiaLoading } from '@/composables/useInertiaLoading';

const props = withDefaults(
    defineProps<{
        title: string;
        pageTitle: string;
        pageDescription?: string;
    }>(),
    {
        pageDescription: '',
    },
);

const page = usePage();
const currentYear = new Date().getFullYear();
const { isLoading } = useInertiaLoading();

const currentPath = computed(() => page.url.split('?')[0]);

function isActive(path: string) {
    return currentPath.value === path;
}

function toLogin() {
    router.visit('/Login');
}

function toRegister() {
    router.visit('/Register');
}

let observer: IntersectionObserver | null = null;

onMounted(() => {
    const targets = document.querySelectorAll<HTMLElement>('.reveal');
    if (!targets.length) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );

    targets.forEach((target) => observer?.observe(target));
});

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<template>
    <Head :title="title" />

    <div class="public-page min-h-screen">
        <div class="corner-badge" aria-hidden="true">
            <div class="logo-triangle">
                <img src="/images/aclogo.svg" alt="Asian College Logo" class="logo-inside-triangle" />
            </div>
        </div>

        <header class="site-header px-3 py-2 sm:px-4 lg:px-6">
            <div v-if="isLoading" class="loading-strip" />
            <div class="nav-shell mx-auto max-w-6xl">
                <div class="flex items-center gap-3">
                    <div>
                        <p class="kicker">Asian College</p>
                        <p class="brand">Varsity Management Information System</p>
                    </div>
                    <img src="/images/aclogo.svg" alt="Asian College Logo" class="mobile-nav-logo" />
                </div>

                <nav class="header-links" aria-label="Public pages">
                    <Link href="/" class="header-link" :class="{ active: isActive('/') }">Home</Link>
                    <Link href="/services" class="header-link" :class="{ active: isActive('/services') }">Services</Link>
                    <Link href="/about" class="header-link" :class="{ active: isActive('/about') }">About Us</Link>
                    <Link href="/how-it-works" class="header-link" :class="{ active: isActive('/how-it-works') }">How It Works</Link>
                    <Link href="/faq" class="header-link" :class="{ active: isActive('/faq') }">FAQ</Link>
                    <Link href="/policies" class="header-link" :class="{ active: isActive('/policies') }">Policies</Link>
                    <Link href="/contact" class="header-link" :class="{ active: isActive('/contact') }">Contact</Link>
                </nav>

                <div class="auth-actions grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2.5">
                    <button @click="toLogin" class="btn-outline" :disabled="isLoading">Login</button>
                    <button @click="toRegister" class="btn-fill" :disabled="isLoading">Register</button>
                </div>
            </div>
        </header>

        <main class="px-4 pb-10 pt-6 sm:px-6 lg:px-10">
            <section class="page-hero reveal mx-auto max-w-6xl">
                <p class="page-kicker">Public Information</p>
                <h1 class="page-title">{{ pageTitle }}</h1>
                <p v-if="pageDescription" class="page-description">{{ pageDescription }}</p>
            </section>

            <section class="mx-auto mt-5 grid max-w-6xl gap-4">
                <slot />
            </section>
        </main>

        <footer class="site-footer relative z-10 px-4 pb-5 sm:px-6 lg:px-10">
            <div class="footer-shell mx-auto max-w-6xl">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            One platform for varsity registration, team setup, schedules, attendance, wellness, academic tracking, and announcements.
                        </p>
                        <p class="footer-contact">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="contact-icon" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="M3 7l9 6 9-6" />
                            </svg>
                            <span>varsity.support@asiancollege.edu.ph</span>
                        </p>
                        <p class="footer-contact">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="contact-icon" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.6a2 2 0 0 1-.45 2.11L8 9.9a16 16 0 0 0 6.1 6.1l1.47-1.32a2 2 0 0 1 2.11-.45c.83.32 1.7.54 2.6.66A2 2 0 0 1 22 16.92z" />
                            </svg>
                            <span>+63 000 000 0000</span>
                        </p>
                    </section>

                    <nav class="footer-col" aria-label="Public Pages">
                        <p class="footer-heading">Public Pages</p>
                        <div class="footer-link-list">
                            <Link href="/" class="footer-link">Home</Link>
                            <Link href="/services" class="footer-link">Services</Link>
                            <Link href="/about" class="footer-link">About</Link>
                            <Link href="/how-it-works" class="footer-link">How It Works</Link>
                            <Link href="/faq" class="footer-link">FAQ</Link>
                            <Link href="/contact" class="footer-link">Contact</Link>
                        </div>
                    </nav>

                    <nav class="footer-col" aria-label="Legal Pages">
                        <p class="footer-heading">Legal</p>
                        <div class="footer-link-list">
                            <Link href="/policies" class="footer-link">Policies</Link>
                            <Link href="/privacy-policy" class="footer-link">Privacy Policy</Link>
                            <Link href="/terms-of-use" class="footer-link">Terms of Use</Link>
                        </div>
                    </nav>

                    <nav class="footer-col" aria-label="Access Links">
                        <p class="footer-heading">Access</p>
                        <div class="footer-link-list">
                            <button @click="toRegister" class="footer-link footer-link-btn">Register</button>
                            <button @click="toLogin" class="footer-link footer-link-btn">Login</button>
                        </div>
                    </nav>

                    <div class="footer-col social-col" aria-label="Social Links">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="social-icon" aria-hidden="true">
                                <path d="M15 3h-2a4 4 0 0 0-4 4v2H7v4h2v8h4v-8h2.5l.5-4H13V7a1 1 0 0 1 1-1h2V3z" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="footer-bottom-row">
                    <p>v1.0 • © {{ currentYear }} Asian College</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.public-page {
    --brand-blue: #1f2937;
    --brand-line: rgba(15, 23, 42, 0.66);
    --brand-line-soft: rgba(15, 23, 42, 0.5);
    background: #ffffff;
    color: #1f2937;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
}

.corner-badge {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 40;
    width: 138px;
    height: 138px;
    pointer-events: none;
}

.logo-triangle {
    position: relative;
    width: 138px;
    height: 138px;
    background: #1f2937;
    clip-path: polygon(0 0, 100% 0, 0 100%);
}

.logo-inside-triangle {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 58px;
    height: 58px;
    border-radius: 999px;
    background: #fff;
    padding: 6px;
    object-fit: contain;
}

.site-header {
    position: sticky;
    top: 0;
    z-index: 35;
    background: rgba(255, 255, 255, 0.88);
    border-bottom:  1px solid var(--brand-line);
    backdrop-filter: blur(2px);
}

.loading-strip {
    height: 3px;
    width: 100%;
    border-radius: 999px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #1f2937 0%, #475569 40%, #94a3b8 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1s linear infinite;
}

.nav-shell {
    border:  1px solid var(--brand-line);
    border-radius: 9999px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px 14px;
}

.kicker {
    font-size: 0.625rem;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: #94a3b8;
}

.brand {
    font-weight: 800;
    color: #1f2937;
    font-size: 0.95rem;
}

.header-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    justify-content: center;
}

.header-link {
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
}

.header-link.active,
.header-link:hover {
    color: #1f2937;
}

.mobile-nav-logo {
    display: none;
    width: 40px;
    height: 40px;
    margin-left: auto;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid var(--brand-line);
    padding: 4px;
    object-fit: contain;
}

.btn-fill,
.btn-outline {
    padding: 0.625rem 0.875rem;
    border-radius: 0.625rem;
    font-size: 0.875rem;
    font-weight: 700;
}

.btn-fill:disabled,
.btn-outline:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.btn-fill {
    color: #ffffff;
    border: 1px solid var(--brand-blue);
    background: var(--brand-blue);
}

.btn-outline {
    color: var(--brand-blue);
    border: 1px solid var(--brand-line);
    background: #ffffff;
}

.page-hero {
    border:  1px solid var(--brand-line);
    border-radius: 1rem;
    background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
    padding: 1rem;
}

.page-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    font-weight: 700;
}

.page-title {
    margin-top: 0.35rem;
    font-size: 1.5rem;
    line-height: 1.2;
    color: #1f2937;
    font-weight: 800;
}

.page-description {
    margin-top: 0.65rem;
    color: #64748b;
    max-width: 72ch;
}

.site-footer {
    margin-top: 0.75rem;
    border-top:  1px solid var(--brand-line);
    background: linear-gradient(180deg, #f5f9ff 0%, #eef5ff 100%);
    padding-top: 1rem;
}

.footer-shell {
    padding-top: 0.2rem;
    color: #64748b;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr 1fr 1fr;
    gap: 1rem;
}

.footer-col-brand {
    max-width: 36ch;
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

.footer-contact {
    margin-top: 0.3rem;
    color: #64748b;
    font-size: 0.86rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.contact-icon {
    width: 0.9rem;
    height: 0.9rem;
    color: #1f2937;
    flex-shrink: 0;
}

.social-col {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding-top: 1.45rem;
}

.social-icon-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    border: 1px solid rgba(15, 23, 42, 0.35);
    color: #1f2937;
    text-decoration: none;
}

.social-icon-link:hover {
    background: rgba(15, 23, 42, 0.08);
}

.social-icon {
    width: 1.05rem;
    height: 1.05rem;
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
    margin-top: 1rem;
    padding-top: 0.7rem;
    border-top: 1px solid rgba(15, 23, 42, 0.22);
    color: #6f879f;
    font-size: 0.82rem;
}

@media (min-width: 640px) {
    .nav-shell {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem;
        padding: 10px 18px;
    }

    .header-links {
        flex: 1;
    }

    .page-hero {
        padding: 1.2rem 1.25rem;
    }

    .page-title {
        font-size: 1.8rem;
    }
}

@media (max-width: 900px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .corner-badge {
        display: none;
    }

    .mobile-nav-logo {
        display: block;
    }

    .nav-shell {
        border-radius: 20px;
    }

    .footer-grid {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 1024px) {
    .public-page {
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
