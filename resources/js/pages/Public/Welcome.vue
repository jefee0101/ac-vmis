<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useInertiaLoading } from '@/composables/useInertiaLoading'

const currentYear = new Date().getFullYear()
const fallbackLogo = '/images/aclogo.svg'
const featureTitles = [
    'Account Onboarding',
    'Team Management',
    'Schedule Management',
    'QR Attendance',
    'Wellness Monitoring',
    'Academic Eligibility',
    'Announcements & Alerts',
]
const prefersReducedMotion = ref(false)
let motionQuery: MediaQueryList | null = null
let revealObserver: IntersectionObserver | null = null
const { isLoading } = useInertiaLoading()
const mobileMenuOpen = ref(false)

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

function onMotionPreferenceChange(event: MediaQueryListEvent) {
    prefersReducedMotion.value = event.matches
}

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    prefersReducedMotion.value = motionQuery.matches

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
    motionQuery?.removeEventListener('change', onMotionPreferenceChange)
    revealObserver?.disconnect()
    document.body.style.overflow = ''
})

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen page">
        <header class="site-header px-3 py-0 sm:px-4 lg:px-6">
            <div class="mx-auto max-w-6xl nav-shell">
                <button
                    type="button"
                    class="mobile-menu-toggle"
                    aria-label="Open menu"
                    @click="mobileMenuOpen = true"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="flex items-center gap-3 header-actions">
                    <button @click="toLogin" class="btn-outline" :disabled="isLoading">Login</button>
                    <button @click="toRegister" class="btn-fill" :disabled="isLoading">Register</button>
                </div>

                <div class="header-logo-slot" aria-hidden="true">
                    <div class="corner-badge">
                        <svg class="logo-triangle" viewBox="0 0 260 130" aria-hidden="true" focusable="false">
                            <path
                                d="M46 12H214
                                   Q222 12 226 18
                                   L170 124
                                   Q166 128 160 128
                                   H100
                                   Q94 128 90 124
                                   L34 18
                                   Q38 12 46 12Z"
                            />

                        </svg>
                        <img
                            src="/images/aclogo.svg"
                            alt="Asian College Logo"
                            class="logo-inside-triangle"
                        />
                    </div>
                </div>

                <nav class="header-links header-actions" aria-label="Public pages">
                    <Link href="/" class="header-link">Home</Link>
                    <Link href="/how-it-works" class="header-link">How It Works</Link>
                    <Link href="/about" class="header-link">About Us</Link>
                    <Link href="/services" class="header-link">Services</Link>
                    <Link href="/faq" class="header-link">FAQ</Link>
                    <Link href="/policies" class="header-link">Policies</Link>
                    <Link href="/contact" class="header-link">Contact</Link>
                </nav>

                <div class="mobile-brand">
                    <img src="/images/aclogo.svg" alt="Asian College Logo" class="mobile-brand-logo" />
                </div>
            </div>
        </header>

        <div v-if="mobileMenuOpen" class="mobile-menu-overlay" @click="mobileMenuOpen = false"></div>
        <aside class="mobile-menu" :class="{ 'is-open': mobileMenuOpen }" aria-label="Mobile menu">
            <div class="mobile-menu-header">
                <span class="mobile-menu-title">Menu</span>
                <button type="button" class="mobile-menu-close" @click="mobileMenuOpen = false">Close</button>
            </div>
            <div class="mobile-menu-actions">
                <button @click="toLogin(); mobileMenuOpen = false" class="btn-outline w-full">Login</button>
                <button @click="toRegister(); mobileMenuOpen = false" class="btn-fill w-full">Register</button>
            </div>
            <nav class="mobile-menu-links">
                <Link href="/" class="mobile-menu-link" @click="mobileMenuOpen = false">Home</Link>
                <Link href="/how-it-works" class="mobile-menu-link" @click="mobileMenuOpen = false">How It Works</Link>
                <Link href="/about" class="mobile-menu-link" @click="mobileMenuOpen = false">About Us</Link>
                <Link href="/services" class="mobile-menu-link" @click="mobileMenuOpen = false">Services</Link>
                <Link href="/faq" class="mobile-menu-link" @click="mobileMenuOpen = false">FAQ</Link>
                <Link href="/policies" class="mobile-menu-link" @click="mobileMenuOpen = false">Policies</Link>
                <Link href="/contact" class="mobile-menu-link" @click="mobileMenuOpen = false">Contact</Link>
            </nav>
        </aside>

        <main class="pb-10">
            <section class="image-strip-hero" aria-label="Sports highlights">
                <div class="image-strip">
                    <div class="strip-col strip-col-1" aria-hidden="true"></div>
                    <div class="strip-col strip-col-2" aria-hidden="true"></div>
                    <div class="strip-col strip-col-3" aria-hidden="true"></div>
                    <div class="strip-col strip-col-4" aria-hidden="true"></div>
                    <div class="strip-col strip-col-5" aria-hidden="true"></div>
                </div>

                <div class="strip-overlay">
                    <div class="strip-overlay-inner">
                        <span class="strip-kicker">Asian College Varsity Management Information System</span>
                        <h1>Manage Your Varsity Day in One Place</h1>
                        <p>
                            AC-VMIS helps student-athletes and coaches handle daily varsity work faster. Use one system to check schedules,
                            verify attendance, monitor wellness, submit academic requirements, and receive updates.
                        </p>
                        <div class="strip-version">Version 2.0</div>
                    </div>
                </div>
            </section>

            <div class="hero-divider" aria-hidden="true"></div>

            <section class="role-strip-wrap section-shell welcome-reveal">
                <div class="mx-auto max-w-6xl role-strip">
                    <article class="role-card role-card-left">
                        <div class="role-icon student-icon" aria-hidden="true"></div>
                        <div>
                            <h3>Student-Athletes</h3>
                            <p>
                                Check your schedule, verify attendance, submit requirements, and track your wellness after every session.
                            </p>
                        </div>
                    </article>
                    <div class="coach-card-wrap">
                        <article class="role-card role-card-right">
                            <div class="role-icon coach-icon" aria-hidden="true"></div>
                            <div>
                                <h3>Coaches</h3>
                                <p>
                                    Manage team schedules, verify attendance, monitor player condition, and review athlete compliance status.
                                </p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mobile-first-wrap section-shell welcome-reveal">
                <div class="mx-auto max-w-6xl mobile-first">
                    <p class="mobile-first-kicker"><span class="title-chip">Mobile-First Experience</span></p>
                    <h2><span class="title-chip title-chip-blue">Built for quick use on your phone during training days.</span></h2>
                    <div class="mobile-first-media" aria-hidden="true"></div>
                    <p>
                        Open AC-VMIS from your mobile browser to view sessions, verify attendance, and log wellness right after practice or games.
                        The same flow also works on tablet and desktop.
                    </p>
                </div>
            </section>

            <div class="full-divider mobile-divider" aria-hidden="true"></div>

            <section class="pathway-wrap section-shell welcome-reveal">
                <div class="mx-auto max-w-6xl pathway-grid">
                    <div class="departments-showcase">
                        <p class="pathway-kicker"><span class="title-chip">Department Pathway</span></p>
                        <h2><span class="title-chip">From Senior High to College, managed in one unified varsity platform.</span></h2>

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

                <div class="mx-auto max-w-6xl pathway-footer">
                    <div class="pathway-footer-inner">
                        <div class="pathway-divider" aria-hidden="true"></div>
                        <div class="pathway-note">
                        <h3><span class="title-chip title-chip-blue">Eligibility Checklist</span></h3>
                            <div class="eligibility-media" aria-hidden="true"></div>
                            <p>Required docs, grades, and attendance thresholds—tracked in one place.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="features-wrap welcome-reveal">
                <div class="mx-auto max-w-6xl section-shell features-minimal">
                    <p class="features-kicker"><span class="title-chip">Core Features</span></p>
                    <h2><span class="title-chip">Everything you need to run varsity operations.</span></h2>

                    <div class="feature-list" role="list">
                        <div v-for="(title, idx) in featureTitles" :key="title" class="feature-item" role="listitem">
                            <span class="feature-chip">{{ idx + 1 }}</span>
                            <span class="feature-text">{{ title }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="register-cta-wrap section-shell welcome-reveal">
                <div class="mx-auto max-w-6xl register-cta">
                    <p class="cta-kicker"><span class="title-chip">Ready To Start?</span></p>
                    <h2><span class="title-chip title-chip-blue">Create your account and start using AC-VMIS today.</span></h2>
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

        <footer class="site-footer relative z-10 section-shell">
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
                        <p class="footer-heading"><span class="title-chip">Public Pages</span></p>
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
                        <p class="footer-heading"><span class="title-chip">Legal</span></p>
                        <div class="footer-link-list">
                            <Link href="/policies" class="footer-link">Policies</Link>
                            <Link href="/privacy-policy" class="footer-link">Privacy Policy</Link>
                            <Link href="/terms-of-use" class="footer-link">Terms of Use</Link>
                        </div>
                    </nav>

                    <nav class="footer-col" aria-label="Access Links">
                        <p class="footer-heading"><span class="title-chip">Access</span></p>
                        <div class="footer-link-list">
                            <button @click="toRegister" class="footer-link footer-link-btn">Register</button>
                            <button @click="toLogin" class="footer-link footer-link-btn">Login</button>
                        </div>
                    </nav>

                    <section class="footer-col" aria-label="Institution">
                        <p class="footer-heading"><span class="title-chip">Institution</span></p>
                        <div class="footer-info">
                            <div>
                                <p class="footer-info-title"><span class="title-chip">Vision</span></p>
                                <p class="footer-info-text">
                                    To be a transformative educational institution committed to the success of its graduates through quality instruction, relevant research, and strong community engagement.
                                </p>
                            </div>
                            <div>
                                <p class="footer-info-title"><span class="title-chip">Mission</span></p>
                                <p class="footer-info-text">To educate and develop globally competitive future leaders.</p>
                            </div>
                            <div>
                                <p class="footer-info-title"><span class="title-chip">Core Values</span></p>
                                <p class="footer-info-text">Academic Excellence</p>
                                <p class="footer-info-text">Integrity</p>
                                <p class="footer-info-text">Self-Leadership</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="footer-bottom-row">
                    <p>v2.0 • © {{ currentYear }} Asian College</p>
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
    --title-chip-bg: #ffffff;
    --title-chip-text: #0b1b2b;
    --feature-glow-1: rgba(96, 165, 250, 0.35);
    --feature-glow-2: rgba(3, 20, 40, 0.45);
    --feature-card-bg: var(--page-surface);
    --feature-card-text: var(--page-text-muted);
    --feature-card-title: var(--page-text);
    --space-page-x: clamp(1rem, 3.2vw, 2.5rem);
    --space-section-y: clamp(1.6rem, 4.5vw, 3.6rem);
    --title-xl: clamp(1.7rem, 3.4vw, 2.6rem);
    --title-lg: clamp(1.35rem, 2.6vw, 1.9rem);
    --body-md: clamp(0.95rem, 1.6vw, 1.05rem);
    background: var(--page-bg);
    color: var(--page-text);
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
    overflow-x: hidden;
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

.section-shell {
    padding-left: var(--space-page-x);
    padding-right: var(--space-page-x);
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
    position: absolute;
    top: -54px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 40;
    width: 260px;
    height: 105px;
    pointer-events: none;
}

.logo-triangle {
    position: relative;
    width: 100%;
    height: 100%;
}

.logo-triangle path {
    fill: #ffffff;
}

.logo-inside-triangle {
    position: absolute;
    top: 42px;
    left: 50%;
    transform: translateX(-50%);
    width: 58px;
    height: 58px;
    border-radius: 999px;
    background: #fff;
    padding: 5px;
    object-fit: contain;
    box-shadow: none;
}

.site-header {
    position: sticky;
    top: 0;
    z-index: 35;
    background: #ffffff;
    border-bottom: none;
    backdrop-filter: blur(2px);
}

.nav-shell {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 4px 14px;
}

.mobile-brand {
    display: none;
    align-items: center;
    gap: 0.5rem;
}

.mobile-brand-logo {
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #ffffff;
    padding: 4px;
    border: 1px solid var(--brand-line);
    object-fit: contain;
}

.header-actions {
    display: flex;
}

.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--brand-line);
    background: #ffffff;
}

.mobile-menu-toggle span {
    width: 18px;
    height: 2px;
    background: var(--brand-blue);
    border-radius: 999px;
}

.header-logo-slot {
    position: relative;
    flex: 0 0 260px;
    height: 0;
    display: flex;
    justify-content: center;
    pointer-events: none;
}

.header-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    justify-content: center;
    width: auto;
}

.header-link {
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    padding: 6px 14px;
    border: none;
    border-radius: 999px;
    background: var(--brand-blue);
    white-space: nowrap;
}

.header-link:hover {
    color: #ffffff;
    background: var(--page-accent-strong);
}

.mobile-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(3, 20, 40, 0.45);
    z-index: 60;
}

.mobile-menu {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: min(86vw, 340px);
    background: #ffffff;
    border-right: 1px solid var(--brand-line-soft);
    padding: 1.2rem 1rem;
    z-index: 70;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transform: translateX(-100%);
    transition: transform 220ms ease;
}

.mobile-menu.is-open {
    transform: translateX(0);
}

.mobile-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-menu-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0b1b2b;
}

.mobile-menu-close {
    border: 1px solid var(--brand-line);
    background: #ffffff;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--brand-blue);
}

.mobile-menu-actions {
    display: grid;
    gap: 0.5rem;
}

.mobile-menu-links {
    display: grid;
    gap: 0.4rem;
}

.mobile-menu-link {
    padding: 0.6rem 0.75rem;
    border-radius: 999px;
    border: 1px solid var(--brand-line-soft);
    color: var(--brand-blue);
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: background 150ms ease, border-color 150ms ease;
}

.mobile-menu-link:hover {
    background: rgba(3, 68, 133, 0.08);
    border-color: var(--brand-blue);
}


.btn-fill,
.btn-outline {
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: var(--brand-blue);
    background: #ffffff;
    border: 1px solid var(--brand-blue);
}

.btn-fill {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: #ffffff;
}

.btn-outline {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: #ffffff;
}

.btn-fill:disabled,
.btn-outline:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.image-strip-hero {
    position: relative;
    min-height: 72vh;
    border-top: 1px solid var(--brand-line);
    border-bottom: 1px solid var(--brand-line);
    overflow: hidden;
    background: #0f172a;
    margin: 0 clamp(0.75rem, 2.6vw, 1.5rem);
    border-radius: 18px;
    scrollbar-width: none;
}

.image-strip-hero::-webkit-scrollbar {
    display: none;
}

.hero-divider {
    height: 1px;
    background: rgba(3, 68, 133, 0.45);
    width: 50%;
    margin-left: auto;
    margin-right: auto;
    margin-top: 0.85rem;
    margin-bottom: 1.1rem;
}

.image-strip {
    display: flex;
    width: 100%;
    height: 100%;
    min-height: 72vh;
}

.strip-col {
    flex: 1 1 0%;
    min-height: 72vh;
    background-size: cover;
    background-position: center;
    filter: saturate(1.05) contrast(1.02);
    transform: scale(1.04);
    opacity: 0.82;
    animation: heroStripReveal 900ms ease forwards;
    will-change: transform, opacity;
}

.strip-col-1 {
    background-image: url('/images/hero-basketball.webp');
    animation-delay: 60ms;
}

.strip-col-2 {
    background-image: url('/images/hero-volleyball.webp');
    animation-delay: 120ms;
}

.strip-col-3 {
    background-image: url('/images/hero-soccer.webp');
    animation-delay: 180ms;
}

.strip-col-4 {
    background-image: url('/images/hero-shuttlecock.jpg');
    animation-delay: 240ms;
}

.strip-col-5 {
    background-image: url('/images/hero-tabletennis.webp');
    animation-delay: 300ms;
}

.strip-overlay {
    position: absolute;
    inset: 0;
    display: grid;
    align-items: center;
    padding: clamp(1.6rem, 4vw, 2.6rem) clamp(1rem, 3vw, 1.75rem);
    background: linear-gradient(90deg, rgba(3, 20, 40, 0.75) 0%, rgba(3, 20, 40, 0.2) 60%, rgba(3, 20, 40, 0.05) 100%);
}

.strip-overlay-inner {
    max-width: min(100%, 560px);
    color: #fff;
    opacity: 0;
    transform: translateY(12px);
    animation: heroTextReveal 700ms ease forwards;
    animation-delay: 260ms;
    will-change: transform, opacity;
}

.strip-kicker {
    display: inline-flex;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 700;
}

.strip-overlay-inner h1 {
    margin: 0.9rem 0 0.6rem;
    font-size: var(--title-xl);
    line-height: 1.15;
    font-weight: 800;
}

.strip-overlay-inner p {
    margin: 0;
    font-size: var(--body-md);
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.88);
}

.strip-version {
    margin-top: 1rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 700;
}

@keyframes heroStripReveal {
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes heroTextReveal {
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .strip-col,
    .strip-overlay-inner {
        animation: none;
        transform: none;
        opacity: 1;
    }
}

.title-chip {
    display: inline-block;
    padding: 0.22rem 0.7rem;
    border-radius: 999px;
    background: var(--title-chip-bg);
    color: var(--title-chip-text);
    line-height: 1.2;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

.title-chip-blue {
    background: var(--brand-blue);
    color: #ffffff;
}




.role-strip-wrap {
    margin-top: 1rem;
    min-height: auto;
}

.role-strip {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 2rem;
    width: 100%;
}

.coach-card-wrap {
    display: flex;
    align-items: flex-start;
    margin-top: 2.4rem;
    margin-left: auto;
    flex: 1;
}

.role-card {
    display: flex;
    gap: 0.85rem;
    padding: clamp(1rem, 2.6vw, 1.35rem) clamp(1.1rem, 3vw, 1.6rem);
    align-items: flex-start;
    justify-content: flex-start;
    min-height: 0;
    background: #0b2f5f;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 18px;
    box-shadow: 0 14px 26px -20px rgba(3, 20, 40, 0.6);
    transition: transform 180ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 180ms cubic-bezier(0.22, 1, 0.36, 1);
    flex: 1;
    max-width: 520px;
}

.role-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px var(--page-card-shadow);
}

.role-card-right {
    margin-top: 0;
}

.role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
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
    border:  1px solid rgba(255, 255, 255, 0.8);
    border-radius: 999px;
    top: 6px;
    left: 11px;
}

.student-icon::after {
    width: 18px;
    height: 10px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-top: none;
    border-radius: 0 0 10px 10px;
    bottom: 6px;
    left: 9px;
}

.coach-icon::before {
    width: 18px;
    height: 2px;
    background: rgba(255, 255, 255, 0.8);
    transform: rotate(-24deg);
    top: 13px;
    left: 10px;
}

.coach-icon::after {
    width: 8px;
    height: 8px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 999px;
    bottom: 7px;
    left: 15px;
}

.role-card h3 {
    font-size: clamp(1rem, 2.2vw, 1.2rem);
    color: #ffffff;
    font-weight: 700;
}

.role-card p {
    margin-top: 0.3rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
}

.mobile-first-wrap {
    padding-top: var(--space-section-y);
}

.mobile-first {
    padding-top: 1rem;
    margin: 1.5rem 0;
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
    font-size: var(--title-lg);
    color: var(--page-text);
    line-height: 1.25;
    font-weight: 800;
}

.mobile-first p {
    margin-top: 0.6rem;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 72ch;
    font-size: var(--body-md);
}

.mobile-first-media {
    margin-top: 0.9rem;
    border-radius: 18px;
    border: 1px solid var(--brand-blue);
    background-image: url('/images/mobile-messaging-modern-communication-technology-online-chatting-sms-texting-modern-leisure-activity-guy-checking-email-inbox-with-smartphone_335657-3526.avif');
    background-size: cover;
    background-position: center;
    position: relative;
    overflow: hidden;
    width: min(240px, 70vw);
    aspect-ratio: 1 / 1;
}


.full-divider {
    width: 100%;
    height: 2px;
    background: var(--brand-line);
    margin-top: 1.25rem;
}

.mobile-divider {
    width: 75%;
    height: 1px;
    background: rgba(3, 68, 133, 0.45);
    margin: 1.6rem 0 0 1.25rem;
}

.pathway-wrap {
    padding-top: clamp(1rem, 2vw, 1.5rem);
}

.pathway-grid {
    display: block;
    border: 1px solid var(--brand-line);
    border-radius: 18px;
    background: var(--brand-blue);
    padding: clamp(1.6rem, 3.6vw, 2.6rem) clamp(1.4rem, 4vw, 2.6rem);
    margin: clamp(2rem, 5vw, 3rem) auto;
    width: min(100%, 980px);
}

.pathway-footer {
    width: min(100%, 980px);
    margin: 1.5rem auto 3.2rem;
}

.pathway-footer-inner {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding-right: 2.4rem;
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
    font-size: var(--title-lg);
    line-height: 1.25;
    color: #ffffff;
    font-weight: 800;
    text-align: center;
}

.departments-showcase {
    text-align: center;
    color: #ffffff;
}

.department-logos {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: clamp(0.6rem, 2.4vw, 1.05rem);
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
    border: 1px solid rgba(255, 255, 255, 0.8);
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
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
    max-width: 76ch;
    text-align: center;
    font-size: var(--body-md);
}

.pathway-divider {
    height: 1px;
    width: 70%;
    margin: 1.4rem 0 1.6rem;
    background: rgba(3, 68, 133, 0.6);
}

.pathway-note {
    max-width: 320px;
    text-align: right;
}

.pathway-note h3 {
    margin: 0;
    font-size: clamp(1.1rem, 2.4vw, 1.3rem);
    font-weight: 700;
    color: #0b1b2b;
}

.pathway-note p {
    margin-top: 0.4rem;
    font-size: var(--body-md);
    line-height: 1.5;
    color: rgba(11, 27, 43, 0.75);
}

.eligibility-media {
    margin-top: 0.85rem;
    width: min(240px, 70vw);
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    border: 1px solid var(--brand-blue);
    background-image: url('/images/checking-inventory-flat-style-design-vector-illustration-stock-illustration_357500-43.avif');
    background-size: cover;
    background-position: center;
}

.features-divider {
    margin-top: 1.5rem;
}

.features-wrap {
    padding: clamp(2rem, 5vw, 3rem) 0;
    background: var(--brand-blue);
    color: #ffffff;
    margin: 0 0.75rem;
    border-radius: 18px;
    overflow: hidden;
    --title-chip-bg: #ffffff;
    --title-chip-text: #0b1b2b;
    background-image: url('/images/Maximizing-Oracle-Apps-Technical-Tips-and-Tricks-for-Developers.webp');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

.features-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(3, 20, 40, 0.72);
    z-index: 0;
}

.features-minimal {
    position: relative;
    z-index: 1;
}

.features-minimal {
    display: grid;
    gap: 1.5rem;
}

.features-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
}

.features-minimal h2 {
    margin: 0;
    font-size: var(--title-lg);
    line-height: 1.2;
    font-weight: 800;
    color: #ffffff;
}

.feature-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.85rem 1.2rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
}

.feature-chip {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.6);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: #ffffff;
    flex-shrink: 0;
}

.feature-text {
    font-size: clamp(0.92rem, 1.6vw, 1rem);
    font-weight: 600;
    color: #ffffff;
}

.register-cta-wrap {
    padding-top: clamp(1.6rem, 4vw, 2.5rem);
    padding-bottom: 1rem;
}

.register-cta {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    max-width: 720px;
    transition: none;
}

.register-cta:hover {
    box-shadow: none;
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
    font-size: var(--title-lg);
    line-height: 1.25;
    color: var(--page-text);
    font-weight: 800;
}

.register-cta p {
    margin: 0.75rem 0 0;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 60ch;
    font-size: var(--body-md);
}

.cta-actions {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: clamp(0.5rem, 1.8vw, 0.8rem);
}

.site-footer {
    margin-top: 1rem;
    padding-top: clamp(1rem, 3vw, 1.5rem);
    padding-bottom: 1.3rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    background: #0b2f5f;
    border-radius: 18px 18px 0 0;
    margin-left: 1.5rem;
    margin-right: 1.5rem;
    overflow: hidden;
    color: #ffffff;
    --title-chip-bg: #ffffff;
    --title-chip-text: #0b1b2b;
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: rgba(255, 255, 255, 0.75);
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
    color: #ffffff;
    font-size: 0.96rem;
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.55rem;
    color: rgba(255, 255, 255, 0.72);
    line-height: 1.6;
    font-size: 0.92rem;
}

.footer-contact {
    margin-top: 0.45rem;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.contact-icon {
    width: 0.92rem;
    height: 0.92rem;
    color: rgba(255, 255, 255, 0.85);
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
    border: 1px solid rgba(255, 255, 255, 0.35);
    color: #ffffff;
    text-decoration: none;
}

.social-icon-link:hover {
    background: rgba(255, 255, 255, 0.15);
}

.social-icon {
    width: 1.05rem;
    height: 1.05rem;
}

.footer-heading {
    color: rgba(255, 255, 255, 0.9);
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
    color: rgba(255, 255, 255, 0.72);
    text-decoration: none;
    font-size: 0.9rem;
    overflow-wrap: anywhere;
}

.footer-link:hover {
    color: #ffffff;
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
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.84rem;
    line-height: 1.5;
}

.footer-info-title {
    color: rgba(255, 255, 255, 0.9);
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
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.78rem;
}

@media (min-width: 640px) {
    .hero-divider {
        margin-top: 1rem;
        margin-bottom: 1.2rem;
    }

    .nav-shell {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 4px 18px;
    }

    .header-links {
        justify-content: flex-end;
        margin: 0;
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
    .hero-divider {
        margin-top: 1.2rem;
        margin-bottom: 1.4rem;
    }
}

@media (max-width: 1024px) {
    .strip-overlay {
        padding: 2rem 1.25rem;
    }

    .strip-overlay-inner {
        max-width: 460px;
    }

    .strip-overlay-inner h1 {
        font-size: 2.05rem;
    }

    .features-minimal h2 {
        font-size: 1.55rem;
    }

    .footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .nav-shell {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        min-height: 64px;
    }

    .header-actions {
        display: none;
    }

    .header-logo-slot {
        display: flex;
        position: absolute;
        left: 50%;
        top: 64%;
        transform: translate(-50%, -50%);
        height: 88px;
        width: min(240px, 70vw);
    }

    .mobile-brand {
        display: none;
    }

    .mobile-menu-toggle {
        display: inline-flex;
    }

    .corner-badge {
        top: 6px;
        width: 220px;
        height: 90px;
    }

    .header-logo-slot {
        flex-basis: 200px;
    }

    .logo-inside-triangle {
        top: 30px;
        width: 46px;
        height: 46px;
        padding: 4px;
    }

    .image-strip-hero,
    .image-strip,
    .strip-col {
        min-height: 60vh;
    }

    .strip-overlay-inner h1 {
        font-size: 1.7rem;
    }

    .strip-overlay-inner p {
        font-size: 0.95rem;
    }

    .strip-kicker,
    .strip-version {
        font-size: 0.7rem;
    }
}

@media (min-width: 900px) {
    .role-strip {
        flex-direction: row;
    }

    .role-card + .role-card {
        border-top: none;
        border-left: none;
    }

    .pathway-grid {
        padding: 1.25rem;
    }
}

@media (max-width: 640px) {
    .image-strip-hero {
        margin: 0 1rem;
        border-radius: 16px;
    }

    .features-wrap {
        margin: 0 1rem;
        border-radius: 16px;
    }

    .site-footer {
        border-radius: 16px 16px 0 0;
        margin-left: 1.25rem;
        margin-right: 1.25rem;
    }

    .mobile-divider {
        margin-left: 1.5rem;
    }

    .hero-divider {
        margin-top: 0.9rem;
        margin-bottom: 1.1rem;
    }
    .corner-badge {
        top: 8px;
        width: 200px;
        height: 82px;
    }

    .header-logo-slot {
        flex-basis: 190px;
    }

    .nav-shell {
        border-radius: 20px;
    }

    .logo-inside-triangle {
        top: 26px;
        width: 42px;
        height: 42px;
        padding: 4px;
        }

    .dept-item {
        width: 88px;
        height: 88px;
    }

    .pathway-divider {
        width: 85%;
        margin: 1.2rem auto 1.4rem 0;
    }

    .pathway-note {
        margin-left: 0;
        text-align: left;
        max-width: none;
    }

    .pathway-footer-inner {
        align-items: flex-start;
        padding-right: 0;
        padding-left: 1rem;
    }

    .feature-list {
        grid-template-columns: 1fr;
    }

    .features-minimal h2 {
        font-size: 1.4rem;
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

    .role-strip {
        flex-direction: column;
        gap: 1rem;
    }

    .coach-card-wrap {
        margin-top: 0;
    }

    .image-strip-hero {
        overflow-x: auto;
    }

    .image-strip {
        min-width: 900px;
    }

    .strip-overlay {
        padding: 1.6rem 1rem;
        background: linear-gradient(180deg, rgba(3, 20, 40, 0.85) 0%, rgba(3, 20, 40, 0.35) 70%, rgba(3, 20, 40, 0.1) 100%);
    }
}

@media (max-width: 480px) {
    .strip-overlay-inner h1 {
        font-size: 1.5rem;
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

    .strip-overlay-inner p,
    .departments-desc,
    .register-cta p {
        font-size: 0.9rem;
    }

    .feature-chip {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-reveal,
    .features-wrap,
    .image-strip,
    .role-card,
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
