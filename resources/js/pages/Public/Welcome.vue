<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useInertiaLoading } from '@/composables/useInertiaLoading'

const currentYear = new Date().getFullYear()
const fallbackLogo = '/images/aclogo.svg'
const featureTitles = [
    'Account Onboarding',
    'Team Management',
    'Schedule Management',
    'Attendance Verification',
    'Wellness Monitoring',
    'Academic Eligibility',
    'Announcements & Alerts',
]
const selectedFeature = ref<number | null>(null)
const scrollY = ref(0)
const viewportWidth = ref(0)
const prefersReducedMotion = ref(false)
let motionQuery: MediaQueryList | null = null
let revealObserver: IntersectionObserver | null = null
const { isLoading } = useInertiaLoading()

function toLogin() {
    router.visit('Login')
}

function toRegister() {
    router.visit('Register')
}

function onSealError(event: Event) {
    const target = event.target as HTMLImageElement | null

    if (!target || target.src.endsWith(fallbackLogo)) {
        return
    }

    target.src = fallbackLogo
}

function onScroll() {
    scrollY.value = window.scrollY || 0
}

function onResize() {
    viewportWidth.value = window.innerWidth || 0
}

function onGlobalClick(event: MouseEvent) {
    const target = event.target as HTMLElement | null

    if (!target?.closest('.floating-feature-card')) {
        selectedFeature.value = null
    }
}

function onMotionPreferenceChange(event: MediaQueryListEvent) {
    prefersReducedMotion.value = event.matches
}

function cardFloatStyle(index: number, side: 'left' | 'right') {
    if (viewportWidth.value <= 1024 || prefersReducedMotion.value) {
        return { transform: 'none' }
    }

    const sideFactor = side === 'left' ? -1 : 1
    const vertical = Math.sin((scrollY.value / 120) + index) * 10
    const horizontal = sideFactor * (6 + (index % 2) * 4)
    const leftRotations = [-5, 4, -3]
    const rightRotations = [6, -4, 3, -6]
    const rotation = side === 'left'
        ? leftRotations[index] ?? -2
        : rightRotations[index - 3] ?? 2

    return {
        transform: `translate3d(${horizontal}px, ${vertical}px, 0) rotate(${rotation}deg)`,
    }
}

onMounted(() => {
    onScroll()
    onResize()
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    prefersReducedMotion.value = motionQuery.matches

    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onResize, { passive: true })
    window.addEventListener('click', onGlobalClick)
    motionQuery.addEventListener('change', onMotionPreferenceChange)

    const revealTargets = document.querySelectorAll<HTMLElement>('.welcome-reveal')
    if (!prefersReducedMotion.value && revealTargets.length) {
        revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible')
                    }
                })
            },
            { threshold: 0.14, rootMargin: '0px 0px -48px 0px' },
        )

        revealTargets.forEach((target) => revealObserver?.observe(target))
    } else {
        revealTargets.forEach((target) => target.classList.add('is-visible'))
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onResize)
    window.removeEventListener('click', onGlobalClick)
    motionQuery?.removeEventListener('change', onMotionPreferenceChange)
    revealObserver?.disconnect()
})
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen page">
        <div class="corner-badge" aria-hidden="true">
            <div class="logo-triangle">
                <img
                    src="/images/aclogo.svg"
                    alt="Asian College Logo"
                    class="logo-inside-triangle"
                />
            </div>
        </div>

        <header class="site-header px-3 py-2 sm:px-4 lg:px-6">
            <div v-if="isLoading" class="loading-strip" />
            <div class="mx-auto max-w-6xl nav-shell">
                <div class="flex items-center gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.22em] text-slate-500">Asian College</p>
                        <p class="brand text-sm sm:text-base">Varsity Management Information System</p>
                    </div>
                    <img src="/images/aclogo.svg" alt="Asian College Logo" class="mobile-nav-logo" />
                </div>

                <nav class="header-links" aria-label="Public pages">
                    <Link href="/" class="header-link">Home</Link>
                    <Link href="/services" class="header-link">Services</Link>
                    <Link href="/about" class="header-link">About Us</Link>
                    <Link href="/how-it-works" class="header-link">How It Works</Link>
                    <Link href="/faq" class="header-link">FAQ</Link>
                    <Link href="/policies" class="header-link">Policies</Link>
                    <Link href="/contact" class="header-link">Contact</Link>
                </nav>

                <div class="auth-actions grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2.5">
                    <button @click="toLogin" class="btn-outline" :disabled="isLoading">Login</button>
                    <button @click="toRegister" class="btn-fill" :disabled="isLoading">Register</button>
                </div>
            </div>
        </header>

        <main class="pb-10">
            <section class="definition-hero welcome-reveal">
                <img
                    src="/images/f4787fe8-4054-4031-8bd3-bcb959e2723d.webp"
                    alt="Basketball close-up"
                    class="hero-image"
                />

                <div class="definition-copy">
                    <div class="hero-title">
                        <h1>Manage Your Varsity Day in One Place</h1>
                        <div class="hero-version">Version 1.0 · First Release</div>
                    </div>
                    <div class="hero-description">
                        <p>
                            AC-VMIS helps student-athletes and coaches handle daily varsity work faster. Use one system to check schedules,
                            verify attendance, monitor wellness, submit academic requirements, and receive updates.
                        </p>
                    </div>
                </div>
            </section>

            <section class="workspace-stats-wrap px-4 sm:px-6 lg:px-10 welcome-reveal">
                <div class="mx-auto grid max-w-6xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article class="workspace-stat-card">
                        <p class="workspace-stat-label">Unified Modules</p>
                        <p class="workspace-stat-value">7</p>
                        <p class="workspace-stat-desc">Operations, health, academics, and account lifecycle in one platform.</p>
                    </article>
                    <article class="workspace-stat-card">
                        <p class="workspace-stat-label">Attendance Flow</p>
                        <p class="workspace-stat-value">Real-Time</p>
                        <p class="workspace-stat-desc">Schedule-to-attendance visibility with verification and exceptions.</p>
                    </article>
                    <article class="workspace-stat-card">
                        <p class="workspace-stat-label">Health Monitoring</p>
                        <p class="workspace-stat-value">Daily</p>
                        <p class="workspace-stat-desc">Track injuries, fatigue, and athlete readiness after sessions.</p>
                    </article>
                    <article class="workspace-stat-card">
                        <p class="workspace-stat-label">Academic Compliance</p>
                        <p class="workspace-stat-value">Period-Based</p>
                        <p class="workspace-stat-desc">Submission windows, evaluations, and risk tracking by term.</p>
                    </article>
                </div>
            </section>

            <section class="role-strip-wrap px-4 sm:px-6 lg:px-10 welcome-reveal">
                <div class="mx-auto max-w-6xl role-strip">
                    <article class="role-card">
                        <div class="role-icon student-icon" aria-hidden="true"></div>
                        <div>
                            <h3>Student-Athletes</h3>
                            <p>
                                Check your schedule, verify attendance, submit requirements, and track your wellness after every session.
                            </p>
                        </div>
                    </article>

                    <article class="role-card">
                        <div class="role-icon coach-icon" aria-hidden="true"></div>
                        <div>
                            <h3>Coaches</h3>
                            <p>
                                Manage team schedules, verify attendance, monitor player condition, and review athlete compliance status.
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mobile-first-wrap px-4 sm:px-6 lg:px-10 welcome-reveal">
                <div class="mx-auto max-w-6xl mobile-first">
                    <p class="mobile-first-kicker">Mobile-First Experience</p>
                    <h2>Built for quick use on your phone during training days.</h2>
                    <p>
                        Open AC-VMIS from your mobile browser to view sessions, verify attendance, and log wellness right after practice or games.
                        The same flow also works on tablet and desktop.
                    </p>
                </div>
            </section>

            <div class="full-divider" aria-hidden="true"></div>

            <section class="pathway-wrap px-4 sm:px-6 lg:px-10 welcome-reveal">
                <div class="mx-auto max-w-6xl pathway-grid">
                    <div class="departments-showcase">
                        <p class="pathway-kicker">Department Pathway</p>
                        <h2>From Senior High to College, managed in one unified varsity platform.</h2>

                        <div class="department-logos" role="list" aria-label="Asian College Departments">
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/shs.png" alt="Senior High School Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Senior High School</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/ba.png" alt="College of Business Administration Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">College of Business Administration</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/cs.png" alt="College of Computer Studies and Engineering Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">College of Computer Studies and Engineering</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/dp.png" alt="Diploma Program Department Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Diploma Program Department</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/hm.png" alt="Hospitality and Tourism Management Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Hospitality and Tourism Management</div>
                            </div>
                        </div>

                        <p class="departments-desc">
                            Students from Senior High to College use the same platform, so schedules, attendance, wellness, and academic status
                            stay clear and updated in one place.
                        </p>
                    </div>
                </div>
            </section>

            <div class="full-divider features-divider" aria-hidden="true"></div>

            <section class="features-wrap welcome-reveal">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-10 features-inner">
                    <div class="features-scene">
                        <div class="feature-column">
                            <button
                                v-for="(title, idx) in featureTitles.slice(0, 3)"
                                :key="title"
                                type="button"
                                class="floating-feature-card"
                                :class="{ active: selectedFeature === idx }"
                                :style="cardFloatStyle(idx, 'left')"
                                @click="selectedFeature = selectedFeature === idx ? null : idx"
                            >
                                <span class="feature-number-id">{{ idx + 1 }}</span>
                                <transition name="fade-title">
                                    <span v-if="selectedFeature === idx" class="feature-card-title">{{ title }}</span>
                                </transition>
                            </button>
                        </div>

                        <div class="features-word" aria-label="Features">
                            <span>F</span>
                            <span>E</span>
                            <span>A</span>
                            <span>T</span>
                            <span>U</span>
                            <span>R</span>
                            <span>E</span>
                            <span>S</span>
                        </div>

                        <div class="feature-column">
                            <button
                                v-for="(title, idx) in featureTitles.slice(3)"
                                :key="title"
                                type="button"
                                class="floating-feature-card"
                                :class="{ active: selectedFeature === idx + 3 }"
                                :style="cardFloatStyle(idx + 3, 'right')"
                                @click="selectedFeature = selectedFeature === idx + 3 ? null : idx + 3"
                            >
                                <span class="feature-number-id">{{ idx + 4 }}</span>
                                <transition name="fade-title">
                                    <span v-if="selectedFeature === idx + 3" class="feature-card-title">{{ title }}</span>
                                </transition>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="register-cta-wrap px-4 pt-8 sm:px-6 lg:px-10 welcome-reveal">
                <div class="mx-auto max-w-6xl register-cta">
                    <p class="cta-kicker">Ready To Start?</p>
                    <h2>Create your account and start using AC-VMIS today.</h2>
                    <p>
                        Register as a student-athlete or coach, submit your required documents, then wait for approval.
                        Once approved, log in and use your daily varsity tools.
                    </p>

                    <div class="cta-actions">
                        <button @click="toRegister" class="btn-fill">Register Now</button>
                        <button @click="toLogin" class="btn-outline">I Already Have An Account</button>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer relative z-10 px-4 pb-5 sm:px-6 lg:px-10">
            <div class="mx-auto max-w-6xl footer-shell">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            One platform for student-athletes and coaches to handle schedules, attendance, wellness, academic eligibility, and announcements.
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
                        <div class="footer-socials" aria-label="Social Links">
                            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="social-icon" aria-hidden="true">
                                    <path d="M15 3h-2a4 4 0 0 0-4 4v2H7v4h2v8h4v-8h2.5l.5-4H13V7a1 1 0 0 1 1-1h2V3z" />
                                </svg>
                            </a>
                        </div>
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

                    <section class="footer-col" aria-label="Institution">
                        <p class="footer-heading">Institution</p>
                        <div class="footer-info">
                            <div>
                                <p class="footer-info-title">Vision</p>
                                <p class="footer-info-text">
                                    To be a transformative educational institution committed to the success of its graduates through quality instruction, relevant research, and strong community engagement.
                                </p>
                            </div>
                            <div>
                                <p class="footer-info-title">Mission</p>
                                <p class="footer-info-text">To educate and develop globally competitive future leaders.</p>
                            </div>
                            <div>
                                <p class="footer-info-title">Core Values</p>
                                <p class="footer-info-text">Academic Excellence</p>
                                <p class="footer-info-text">Integrity</p>
                                <p class="footer-info-text">Self-Leadership</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="footer-bottom-row">
                    <p>v1.0 • © {{ currentYear }} Asian College</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.page {
    --brand-blue: var(--blue-light-primary);
    --brand-line: rgba(3, 68, 133, 0.45);
    --brand-line-soft: rgba(3, 68, 133, 0.28);
    --page-bg: var(--blue-light-bg);
    --page-surface: var(--blue-light-surface);
    --page-surface-alt: var(--blue-light-surface-alt);
    --page-text: var(--blue-light-text);
    --page-text-muted: var(--blue-light-text-muted);
    --page-header-bg: rgba(247, 251, 255, 0.92);
    --page-accent: var(--blue-light-primary);
    --page-accent-strong: var(--blue-light-primary-strong);
    --page-accent-soft: #93c5fd;
    --page-btn-text: #ffffff;
    --page-hover-bg: rgba(3, 68, 133, 0.1);
    --page-card-shadow: rgba(3, 68, 133, 0.18);
    --page-card-shadow-strong: rgba(3, 68, 133, 0.28);
    --page-hero-overlay-strong: rgba(3, 20, 40, 0.68);
    --page-hero-overlay-mid: rgba(3, 20, 40, 0.52);
    --page-hero-overlay-soft: rgba(3, 20, 40, 0.38);
    --page-hero-glow-1: rgba(255, 255, 255, 0.2);
    --page-hero-glow-2: rgba(147, 197, 253, 0.2);
    --page-hero-glow-3: rgba(14, 116, 253, 0.16);
    --feature-bg-1: #0b2f5f;
    --feature-bg-2: #0f3b73;
    --feature-bg-3: #145aa6;
    --feature-bg-4: #1b6ec2;
    --feature-glow-1: rgba(96, 165, 250, 0.35);
    --feature-glow-2: rgba(3, 20, 40, 0.45);
    --feature-card-bg: var(--page-surface);
    --feature-card-text: var(--page-text-muted);
    --feature-card-title: var(--page-text);
    background: var(--page-bg);
    color: var(--page-text);
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
}

:global(html.theme-dark) .page {
    --brand-blue: var(--blue-dark-accent-strong);
    --brand-line: rgba(147, 197, 253, 0.35);
    --brand-line-soft: rgba(147, 197, 253, 0.22);
    --page-bg: var(--blue-dark-bg);
    --page-surface: var(--blue-dark-surface);
    --page-surface-alt: var(--blue-dark-surface-alt);
    --page-text: var(--blue-dark-text);
    --page-text-muted: var(--blue-dark-text-muted);
    --page-header-bg: rgba(11, 47, 95, 0.92);
    --page-accent: var(--blue-dark-accent);
    --page-accent-strong: var(--blue-dark-accent-strong);
    --page-accent-soft: #bfdbfe;
    --page-btn-text: #0b2f5f;
    --page-hover-bg: rgba(96, 165, 250, 0.16);
    --page-card-shadow: rgba(3, 20, 40, 0.5);
    --page-card-shadow-strong: rgba(3, 20, 40, 0.6);
    --page-hero-overlay-strong: rgba(2, 12, 28, 0.72);
    --page-hero-overlay-mid: rgba(2, 12, 28, 0.55);
    --page-hero-overlay-soft: rgba(2, 12, 28, 0.4);
    --page-hero-glow-1: rgba(96, 165, 250, 0.14);
    --page-hero-glow-2: rgba(96, 165, 250, 0.18);
    --page-hero-glow-3: rgba(96, 165, 250, 0.12);
    --feature-bg-1: #082244;
    --feature-bg-2: #0b2f5f;
    --feature-bg-3: #0f3b73;
    --feature-bg-4: #145aa6;
    --feature-glow-1: rgba(96, 165, 250, 0.32);
    --feature-glow-2: rgba(3, 20, 40, 0.5);
    --feature-card-bg: var(--blue-dark-surface);
    --feature-card-text: var(--blue-dark-text-muted);
    --feature-card-title: var(--blue-dark-text);
}

.welcome-reveal {
    opacity: 0;
    transform: translateY(14px);
    transition: opacity 420ms cubic-bezier(0.22, 1, 0.36, 1), transform 420ms cubic-bezier(0.22, 1, 0.36, 1);
}

.welcome-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.brand {
    font-family: inherit;
    font-weight: 800;
}

.text-slate-500,
.text-slate-600,
.text-slate-700 {
    color: var(--page-text-muted);
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
    background: var(--brand-blue);
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
    background: var(--page-header-bg);
    border-bottom:  1px solid var(--brand-line);
    backdrop-filter: blur(2px);
}

.loading-strip {
    height: 3px;
    width: 100%;
    border-radius: 999px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, var(--page-accent) 0%, var(--page-accent-strong) 45%, var(--page-accent-soft) 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1s linear infinite;
}

.nav-shell {
    border: 1px solid var(--brand-line);
    border-radius: 9999px;
    background: var(--page-surface);
}

.nav-shell {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px 14px;
}

.header-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    justify-content: center;
}

.header-link {
    color: var(--page-text-muted);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.header-link:hover {
    color: var(--page-accent);
}

.mobile-nav-logo {
    display: none;
    width: 40px;
    height: 40px;
    margin-left: auto;
    border-radius: 999px;
    background: var(--page-surface);
    border: 1px solid var(--brand-line);
    padding: 4px;
    object-fit: contain;
}

.btn-fill,
.btn-outline {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
}

.btn-fill {
    color: var(--page-btn-text);
    border: 1px solid var(--page-accent);
    background: var(--page-accent);
}

.btn-outline {
    color: var(--page-accent);
    border: 1px solid var(--brand-line);
    background: var(--page-surface);
}

.btn-fill:disabled,
.btn-outline:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.definition-hero {
    position: relative;
    min-height: calc(100vh - 210px);
    overflow: hidden;
    border-top: 1px solid var(--brand-line);
    border-bottom:  1px solid var(--brand-line);
    display: flex;
    align-items: center;
}

.definition-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: linear-gradient(90deg, var(--page-hero-overlay-strong) 0%, var(--page-hero-overlay-mid) 55%, var(--page-hero-overlay-soft) 100%);
}

.definition-hero::before {
    content: '';
    position: absolute;
    inset: -12%;
    z-index: 1;
    pointer-events: none;
    background:
        radial-gradient(circle at 20% 20%, var(--page-hero-glow-1), transparent 45%),
        radial-gradient(circle at 80% 30%, var(--page-hero-glow-2), transparent 50%),
        radial-gradient(circle at 40% 80%, var(--page-hero-glow-3), transparent 55%);
    opacity: 0.55;
    mix-blend-mode: screen;
    animation: heroGlow 18s ease-in-out infinite;
}

.hero-image {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    filter: saturate(1.05) contrast(1.02);
    animation: heroDrift 18s ease-in-out infinite;
    will-change: transform;
}

.workspace-stats-wrap {
    margin-top: 1.15rem;
}

.workspace-stat-card {
    border-radius: 14px;
    border: 1px solid var(--brand-line-soft);
    background: var(--page-surface);
    padding: 14px 14px 13px;
    box-shadow: 0 10px 20px -16px var(--page-card-shadow);
}

.workspace-stat-label {
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--page-text-muted);
}

.workspace-stat-value {
    margin-top: 3px;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1.18rem;
    font-weight: 700;
    color: var(--page-text);
}

.workspace-stat-desc {
    margin-top: 4px;
    font-size: 0.82rem;
    line-height: 1.42;
    color: var(--page-text-muted);
}

.definition-copy {
    position: relative;
    z-index: 3;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    gap: 2.5rem;
    width: min(1100px, 100%);
    margin: 0 auto;
    padding: 2.4rem 1.5rem 3.2rem;
    background: transparent;
    color: #ffffff;
    text-align: left;
    animation: heroCopyIn 520ms cubic-bezier(0.22, 1, 0.36, 1) 120ms both;
}

.hero-title h1 {
    margin: 0;
    color: #ffffff !important;
}

.hero-title {
    max-width: 480px;
}

.hero-version {
    margin-top: 0.65rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #e2e8f0;
    font-weight: 700;
    text-shadow: 0 10px 20px rgba(3, 20, 40, 0.35);
}

.hero-description {
    justify-self: end;
    max-width: 520px;
}

.definition-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #e2e8f0;
    font-weight: 700;
}

.definition-copy h1 {
    margin-top: 0.4rem;
    font-size: 2.35rem;
    line-height: 1.2;
    color: #ffffff !important;
    font-weight: 800;
    text-shadow: 0 10px 24px rgba(3, 20, 40, 0.35);
}

.definition-copy p {
    margin-top: 0.8rem;
    color: #ffffff !important;
    line-height: 1.6;
    text-shadow: 0 10px 22px rgba(3, 20, 40, 0.28);
}

.role-strip-wrap {
    margin-top: 1rem;
    min-height: auto;
}

.role-strip {
    border-top: 1px solid var(--brand-line);
    border-bottom: 1px solid var(--brand-line);
    background: var(--page-surface);
    display: grid;
    grid-template-columns: 1fr;
    width: 100%;
}

.role-card {
    display: flex;
    gap: 0.85rem;
    padding: 1.25rem 0.25rem;
    align-items: flex-start;
    justify-content: center;
    min-height: 0;
    transition: transform 180ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.role-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px var(--page-card-shadow);
}

.role-card + .role-card {
    border-top: 1px solid var(--brand-line-soft);
}

.role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid var(--brand-line);
    background: var(--page-surface);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
}

.role-icon::before,
.role-icon::after {
    content: '';
    position: absolute;
}

.student-icon::before {
    width: 14px;
    height: 14px;
    border:  1px solid var(--brand-blue);
    border-radius: 999px;
    top: 6px;
    left: 11px;
}

.student-icon::after {
    width: 18px;
    height: 10px;
    border: 1px solid var(--brand-blue);
    border-top: none;
    border-radius: 0 0 10px 10px;
    bottom: 6px;
    left: 9px;
}

.coach-icon::before {
    width: 18px;
    height: 2px;
    background: var(--brand-blue);
    transform: rotate(-24deg);
    top: 13px;
    left: 10px;
}

.coach-icon::after {
    width: 8px;
    height: 8px;
    border: 1px solid var(--brand-blue);
    border-radius: 999px;
    bottom: 7px;
    left: 15px;
}

.role-card h3 {
    font-size: 1.1rem;
    color: var(--page-text);
    font-weight: 700;
}

.role-card p {
    margin-top: 0.3rem;
    color: var(--page-text-muted);
    line-height: 1.5;
}

.mobile-first-wrap {
    padding-top: 1.25rem;
}

.mobile-first {
    padding-top: 1rem;
}

.mobile-first-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.mobile-first h2 {
    margin-top: 0.4rem;
    font-size: 1.45rem;
    color: var(--page-text);
    line-height: 1.25;
    font-weight: 800;
}

.mobile-first p {
    margin-top: 0.6rem;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 72ch;
}

.full-divider {
    width: 100%;
    height: 2px;
    background: var(--brand-line);
    margin-top: 1.25rem;
}

.pathway-wrap {
    padding-top: 1.25rem;
}

.pathway-grid {
    display: block;
    border: 1px solid var(--brand-line);
    border-radius: 18px;
    background: linear-gradient(180deg, var(--page-surface) 0%, var(--page-surface-alt) 100%);
    padding: 1rem;
}

.pathway-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.departments-showcase h2 {
    margin-top: 0.4rem;
    font-size: 1.55rem;
    line-height: 1.25;
    color: var(--page-text);
    font-weight: 800;
    text-align: center;
}

.department-logos {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.8rem 1.05rem;
}

.dept-item {
    position: relative;
    width: 104px;
    height: 104px;
    border-radius: 999px;
    border:  1px solid var(--brand-line);
    background: var(--page-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem;
    box-shadow: 0 10px 20px var(--page-card-shadow);
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    cursor: default;
}

.dept-item:hover,
.dept-item:focus-visible {
    transform: translateY(-2px);
    border-color: var(--brand-blue);
    box-shadow: 0 14px 24px var(--page-card-shadow-strong);
    outline: none;
}

.dept-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.dept-tooltip {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    width: max-content;
    max-width: 220px;
    padding: 0.4rem 0.55rem;
    border-radius: 9px;
    background: var(--page-accent);
    color: #ffffff;
    font-size: 0.74rem;
    line-height: 1.25;
    text-align: center;
    white-space: normal;
    overflow-wrap: anywhere;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.16s ease, transform 0.16s ease;
    box-shadow: 0 10px 18px var(--page-card-shadow-strong);
    z-index: 5;
}

.dept-item:hover .dept-tooltip,
.dept-item:focus-visible .dept-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.departments-desc {
    margin: 1rem auto 0;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 76ch;
    text-align: center;
}

.features-divider {
    margin-top: 1.5rem;
}

.features-wrap {
    position: relative;
    padding-top: 1rem;
    overflow: hidden;
    background: linear-gradient(135deg, var(--feature-bg-1) 0%, var(--feature-bg-2) 35%, var(--feature-bg-3) 70%, var(--feature-bg-4) 100%);
    background-size: 260% 260%;
    animation: featureBlueShift 8s ease-in-out infinite;
}

.features-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 12% 22%, var(--feature-glow-1), transparent 34%),
        radial-gradient(circle at 84% 76%, var(--feature-glow-2), transparent 40%);
    z-index: 0;
    animation: featureGlowDrift 10s ease-in-out infinite alternate;
}

.features-wrap::after {
    content: '';
    position: absolute;
    inset: -10% -5%;
    pointer-events: none;
    background:
        repeating-linear-gradient(
            120deg,
            transparent 0 28px,
            rgba(203, 213, 225, 0.08) 28px 31px,
            transparent 31px 60px
        );
    z-index: 0;
    animation: featureLineSweep 7s linear infinite;
}

.features-inner {
    position: relative;
    z-index: 1;
}

.features-scene {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1rem;
    align-items: center;
    min-height: 460px;
}



.feature-column {
    position: relative;
    min-height: 420px;
}

.floating-feature-card {
    position: absolute;
    min-height: 66px;
    width: 124px;
    border-radius: 14px;
    border: 1px solid var(--brand-line);
    background: var(--feature-card-bg);
    color: var(--feature-card-text);
    box-shadow: 0 10px 18px var(--page-card-shadow);
    padding: 0.4rem 0.45rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.floating-feature-card:hover {
    border-color: var(--brand-blue);
    box-shadow: 0 14px 22px var(--page-card-shadow-strong);
}

.floating-feature-card.active {
    border-color: var(--brand-blue);
    box-shadow: 0 14px 24px var(--page-card-shadow-strong);
}

.feature-number-id {
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1;
}

.feature-card-title {
    margin-top: 0.42rem;
    font-size: 0.92rem;
    line-height: 1.25;
    font-weight: 800;
    color: var(--feature-card-title);
    max-width: 18ch;
}

.floating-feature-card.active .feature-number-id {
    display: none;
}

.feature-column:first-child .floating-feature-card:nth-child(1) {
    top: 8px;
    left: 10px;
}

.feature-column:first-child .floating-feature-card:nth-child(2) {
    top: 145px;
    right: 14px;
}

.feature-column:first-child .floating-feature-card:nth-child(3) {
    top: 290px;
    left: 38px;
}

.feature-column:last-child .floating-feature-card:nth-child(1) {
    top: 24px;
    right: 26px;
}

.feature-column:last-child .floating-feature-card:nth-child(2) {
    top: 128px;
    left: 18px;
}

.feature-column:last-child .floating-feature-card:nth-child(3) {
    top: 242px;
    right: 4px;
}

.feature-column:last-child .floating-feature-card:nth-child(4) {
    top: 336px;
    left: 44px;
}

.fade-title-enter-active,
.fade-title-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.fade-title-enter-from,
.fade-title-leave-to {
    opacity: 0;
    transform: translateY(3px);
}

@keyframes featureBlueShift {
    0% {
        background-position: 0% 35%;
    }
    50% {
        background-position: 100% 65%;
    }
    100% {
        background-position: 0% 35%;
    }
}

@keyframes featureGlowDrift {
    0% {
        transform: translate3d(-1.5%, -1%, 0) scale(1);
    }
    100% {
        transform: translate3d(1.5%, 1%, 0) scale(1.04);
    }
}

@keyframes featureLineSweep {
    0% {
        transform: translateX(-2.5%);
        opacity: 0.55;
    }
    50% {
        opacity: 0.9;
    }
    100% {
        transform: translateX(2.5%);
        opacity: 0.55;
    }
}

@keyframes heroCopyIn {
    0% {
        opacity: 0;
        transform: translateY(12px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes heroDrift {
    0% {
        transform: scale(1.04) translate3d(0, 0, 0);
    }
    50% {
        transform: scale(1.08) translate3d(-1.5%, -1%, 0);
    }
    100% {
        transform: scale(1.04) translate3d(1.5%, 1%, 0);
    }
}

@keyframes heroGlow {
    0% {
        transform: translate3d(-2%, -1%, 0) scale(1);
        opacity: 0.45;
    }
    50% {
        transform: translate3d(2%, 1.5%, 0) scale(1.04);
        opacity: 0.6;
    }
    100% {
        transform: translate3d(-1%, 1%, 0) scale(1.02);
        opacity: 0.5;
    }
}

.features-word {
    display: grid;
    justify-items: center;
    gap: 0.5rem;
    color: #ffffff;
    font-size: 3.2rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1;
    padding: 0.4rem 1rem;
    text-shadow: 0 10px 24px rgba(2, 23, 46, 0.35);
}

.register-cta-wrap {
    padding-bottom: 1rem;
}

.register-cta {
    border: 1px solid var(--brand-line);
    border-radius: 16px;
    background: linear-gradient(180deg, var(--page-surface) 0%, var(--page-surface-alt) 100%);
    padding: 1.2rem;
    text-align: center;
    transition: box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.register-cta:hover {
    box-shadow: 0 14px 28px var(--page-card-shadow-strong);
}

.cta-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.register-cta h2 {
    margin-top: 0.45rem;
    font-size: 1.55rem;
    line-height: 1.25;
    color: var(--page-text);
    font-weight: 800;
}

.register-cta p {
    margin: 0.75rem auto 0;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 74ch;
}

.cta-actions {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.6rem;
}

.site-footer {
    margin-top: 1rem;
    padding-top: 1.2rem;
    border-top: 1px solid var(--brand-line);
    background: linear-gradient(180deg, var(--page-surface) 0%, var(--page-surface-alt) 100%);
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: var(--page-text-muted);
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr 1fr 1fr 1.1fr;
    gap: 1.2rem;
}

.footer-col {
    min-width: 0;
}

.footer-col-brand {
    max-width: 42ch;
}

.footer-brand {
    color: var(--page-text);
    font-size: 0.96rem;
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.55rem;
    color: var(--page-text-muted);
    line-height: 1.6;
    font-size: 0.92rem;
}

.footer-contact {
    margin-top: 0.45rem;
    color: var(--page-text-muted);
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.contact-icon {
    width: 0.92rem;
    height: 0.92rem;
    color: var(--page-accent);
    flex-shrink: 0;
}

.social-col {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding-top: 1.45rem;
}

.footer-socials {
    margin-top: 0.85rem;
    display: flex;
    gap: 0.5rem;
}

.social-icon-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    border: 1px solid var(--brand-line);
    color: var(--page-accent);
    text-decoration: none;
}

.social-icon-link:hover {
    background: var(--page-hover-bg);
}

.social-icon {
    width: 1.05rem;
    height: 1.05rem;
}

.footer-heading {
    color: var(--page-text);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.72rem;
    font-weight: 800;
}

.footer-link-list {
    margin-top: 0.65rem;
    display: grid;
    gap: 0.5rem;
    overflow-wrap: anywhere;
}

.footer-link {
    color: var(--page-text-muted);
    text-decoration: none;
    font-size: 0.9rem;
    overflow-wrap: anywhere;
}

.footer-link:hover {
    color: var(--page-accent);
}

.footer-link-btn {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
}

.footer-info {
    margin-top: 0.65rem;
    display: grid;
    gap: 0.7rem;
    color: var(--page-text-muted);
    font-size: 0.84rem;
    line-height: 1.5;
}

.footer-info-title {
    color: var(--page-text);
    font-weight: 700;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.footer-info-text {
    margin-top: 0.25rem;
}

.footer-bottom-row {
    margin-top: 1rem;
    border-top: 1px solid var(--brand-line-soft);
    padding-top: 0.75rem;
    color: var(--page-text-muted);
    font-size: 0.78rem;
}

@media (min-width: 640px) {
    .nav-shell {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto minmax(180px, 1fr);
        align-items: center;
        gap: 10px;
        padding: 10px 18px;
    }

    .header-links {
        grid-column: 2;
        margin: 0;
    }

    .nav-shell > :first-child {
        grid-column: 1;
        justify-self: start;
    }

    .nav-shell > :last-child {
        grid-column: 3;
        justify-self: end;
    }

    .footer-bottom-row {
        display: flex;
        justify-content: flex-end;
    }
}

@media (min-width: 1024px) {
    .page {
        font-size: 1.125rem;
    }
}

@media (min-width: 1024px) {
    .nav-shell {
        margin-left: 96px;
    }
}

@media (max-width: 1024px) {
    .definition-copy {
        grid-template-columns: 1fr;
        align-items: center;
        gap: 1.2rem;
    }

    .hero-description {
        justify-self: start;
    }

    .features-scene {
        grid-template-columns: 1fr;
        gap: 0.8rem;
        min-height: auto;
    }

    .feature-column {
        min-height: auto;
        display: grid;
        gap: 0.5rem;
    }

    .feature-column:first-child {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .feature-column:last-child {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .floating-feature-card {
        position: static;
        width: 100%;
        min-height: 60px;
        transform: none !important;
        padding: 0.45rem 0.35rem;
    }

    .feature-card-title {
        font-size: 0.78rem;
    }

    .footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .logo-triangle,
    .corner-badge {
        width: 116px;
        height: 116px;
    }

    .logo-inside-triangle {
        top: 8px;
        left: 8px;
        width: 46px;
        height: 46px;
        padding: 5px;
    }

    .definition-hero {
        min-height: 72vh;
    }

    .definition-copy {
        width: 100%;
        padding: 1.8rem 1.2rem 2.4rem;
        text-align: left;
    }

    .definition-copy h1 {
        font-size: 1.7rem;
    }

    .hero-title,
    .hero-description {
        max-width: none;
    }

    .hero-image {
        object-position: 60% 50%;
        animation-duration: 22s;
    }
}

@media (min-width: 900px) {
    .role-strip {
        grid-template-columns: 1fr 1fr;
    }

    .role-card + .role-card {
        border-top: none;
        border-left: 1px solid var(--brand-line-soft);
    }

    .pathway-grid {
        padding: 1.25rem;
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

    .corner-badge,
    .logo-triangle {
        width: 96px;
        height: 96px;
    }

    .logo-inside-triangle {
        top: 8px;
        left: 8px;
        width: 34px;
        height: 34px;
        padding: 4px;
    }

    .dept-item {
        width: 88px;
        height: 88px;
    }

    .features-word {
        font-size: 2.1rem;
        gap: 0.35rem;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .footer-bottom-row {
        justify-content: flex-start;
    }

    .register-cta h2 {
        font-size: 1.25rem;
    }

    .feature-card-title {
        font-size: 0.76rem;
    }
    .definition-copy {
        padding: 1.6rem 1rem 2.2rem;
    }
}

@media (max-width: 480px) {
    .definition-hero {
        min-height: 68vh;
    }

    .definition-copy h1 {
        font-size: 1.55rem;
    }
}

@media (max-width: 400px) {
    .header-links {
        gap: 4px 8px;
    }

    .header-link {
        font-size: 11px;
    }

    .btn-fill,
    .btn-outline {
        padding: 9px 10px;
        font-size: 13px;
    }

    .definition-copy p,
    .departments-desc,
    .register-cta p {
        font-size: 0.9rem;
    }

    .feature-number-id {
        font-size: 0.85rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-reveal,
    .features-wrap,
    .features-wrap::before,
    .features-wrap::after,
    .definition-hero::before,
    .hero-image,
    .definition-copy,
    .role-card,
    .floating-feature-card,
    .register-cta,
    .dept-item,
    .fade-title-enter-active,
    .fade-title-leave-active {
        animation: none !important;
        transition: none !important;
        transform: none !important;
    }

    .welcome-reveal {
        opacity: 1 !important;
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
