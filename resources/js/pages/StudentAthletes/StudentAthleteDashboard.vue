<script setup lang="ts">
import AnnouncementBellButton from '@/components/AnnouncementBellButton.vue'
import UserAccountMenu from '@/components/UserAccountMenu.vue'
import StudentBottomNav from '@/components/student/StudentBottomNav.vue'
import RoleFooter from '@/components/ui/RoleFooter.vue'
import { studentPrimaryNav, studentSecondaryNav } from '@/config/studentNav'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue'

const slots = useSlots()
const page = usePage()
const unreadCount = computed(() => Number(page.props.auth?.announcements?.unread_count ?? 0))
const hasDefaultSlot = computed(() => Boolean(slots.default))
const currentPath = computed(() => String(page.url || ''))
const userName = computed(() => String(page.props.auth?.user?.name ?? 'Athlete'))

const mobileMenuOpen = ref(false)
const primaryItems = studentPrimaryNav
const secondaryItems = studentSecondaryNav
const isDarkTheme = ref(false)

let themeObserver: MutationObserver | null = null

const footerLinks = [
    { label: 'Dashboard', href: '/StudentAthleteDashboard' },
    { label: 'My Schedule', href: '/MySchedule' },
    { label: 'My Team', href: '/MyTeam' },
    { label: 'Wellness', href: '/WellnessHistory' },
    { label: 'Academics', href: '/AcademicSubmissions' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
]

const activeLabel = computed(() => {
    const all = [...primaryItems, ...secondaryItems]
    const found = all.find((item) => isActive(item.route))
    return found?.label ?? 'Athlete Workspace'
})

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`)
}

function go(route: string) {
    mobileMenuOpen.value = false
    router.get(route)
}

function logout() {
    mobileMenuOpen.value = false
    router.post('/logout')
}

function iconPath(icon: string) {
    if (icon === 'layout-grid') return 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'
    if (icon === 'users') return 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6'
    if (icon === 'calendar') return 'M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'
    if (icon === 'heart-pulse') return 'M22 12h-4l-3 8-4-16-3 8H2'
    if (icon === 'graduation-cap') return 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5'
    if (icon === 'bell') return 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0'
    if (icon === 'user') return 'M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'
    if (icon === 'settings') return 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'
    return 'M12 2v20M2 12h20'
}

function onEsc(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        mobileMenuOpen.value = false
    }
}

onMounted(() => {
    window.addEventListener('keydown', onEsc)
    if (typeof document !== 'undefined') {
        const updateTheme = () => {
            const root = document.documentElement
            isDarkTheme.value = root.classList.contains('theme-dark')
        }
        updateTheme()
        if (typeof MutationObserver !== 'undefined') {
            themeObserver = new MutationObserver(updateTheme)
            themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] })
        }
    }
})

onUnmounted(() => {
    window.removeEventListener('keydown', onEsc)
    document.body.style.overflow = ''
    themeObserver?.disconnect()
    themeObserver = null
})

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
    <div class="student-shell min-h-screen bg-slate-50 text-slate-900">
        <div class="student-shell__glow pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(15, 23, 42,0.10),transparent_45%)]" />

        <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-slate-900/25 md:hidden" @click="mobileMenuOpen = false" />

        <div class="transition-all duration-300 ease-out">
            <header class="student-shell__topbar sticky top-0 z-50 border-b border-slate-200 backdrop-blur">
                <div class="mx-auto flex w-full max-w-[1600px] items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="student-shell__nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border md:hidden"
                        @click="mobileMenuOpen = true"
                        aria-label="Open menu"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 6h18M3 12h18M3 18h18" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="student-shell__brand-title truncate text-xs font-extrabold uppercase tracking-[0.1em] text-[#1f2937]">AC VMIS Student</p>
                        <p class="student-shell__brand-subtitle truncate text-xs text-slate-500">{{ activeLabel }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <AnnouncementBellButton :unread-count="unreadCount" :dark="isDarkTheme" @click="go('/announcements')" />
                        <UserAccountMenu :dark="false" menu-placement="bottom" compact />
                    </div>
                </div>

                <div class="student-shell__tabbar hidden border-t border-slate-200 bg-white md:block">
                    <div class="mx-auto flex w-full max-w-[1600px] items-center gap-2 px-4 py-2 sm:px-6 lg:px-8">
                        <button
                            v-for="item in primaryItems"
                            :key="item.key"
                            type="button"
                            class="student-top-tab"
                            :class="isActive(item.route) ? 'student-top-tab--active' : ''"
                            @click="go(item.route)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path :d="iconPath(item.icon)" />
                            </svg>
                            <span>{{ item.label }}</span>
                        </button>
                    </div>
                </div>
            </header>

            <aside
                class="fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r border-slate-200 bg-white p-4 transition md:hidden"
                :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold text-[#1f2937]">Student Menu</p>
                    <button type="button" class="rounded border border-slate-200 px-2 py-1 text-xs text-slate-500" @click="mobileMenuOpen = false">Close</button>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="item in [...primaryItems, ...secondaryItems]"
                        :key="item.key"
                        type="button"
                        class="student-side-link w-full text-slate-700"
                        :class="isActive(item.route) ? 'student-side-link--active' : ''"
                        @click="go(item.route)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path :d="iconPath(item.icon)" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </button>
                </div>

                <div class="mt-4 border-t border-slate-200 pt-4">
                    <button type="button" class="student-side-link w-full text-rose-600" @click="logout">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span>Logout</span>
                    </button>
                </div>
            </aside>

            <main class="mx-auto w-full max-w-[1600px] px-4 py-4 pb-[calc(env(safe-area-inset-bottom,0px)+5.5rem)] sm:px-6 lg:px-8">
                <template v-if="hasDefaultSlot">
                    <slot />
                </template>
                <template v-else>
                    <div class="space-y-6">
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Athlete Home</p>
                                    <h1 class="text-2xl font-bold text-slate-900">Welcome back, {{ userName }}</h1>
                                    <p class="text-sm text-slate-500 mt-1">Stay on top of schedules, attendance, and wellness updates.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button @click="go('/MySchedule0')" class="rounded-lg bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white hover:bg-[#334155]">
                                        View Schedule
                                    </button>
                                    <button @click="go('/AcademicSubmissions0')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        Submit Academics
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p class="text-xs text-slate-500">Unread announcements</p>
                                <p class="text-2xl font-semibold text-[#1f2937] mt-1">{{ unreadCount }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p class="text-xs text-slate-500">Today focus</p>
                                <p class="text-sm font-semibold text-slate-900 mt-1">Confirm attendance</p>
                                <p class="text-xs text-slate-500 mt-1">Check your next session and respond.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p class="text-xs text-slate-500">Compliance</p>
                                <p class="text-sm font-semibold text-slate-900 mt-1">Wellness + Academics</p>
                                <p class="text-xs text-slate-500 mt-1">Keep submissions up to date.</p>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h2 class="text-sm font-semibold text-slate-900">Quick Actions</h2>
                                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <button @click="go('/MySchedule0')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        Open My Schedule
                                    </button>
                                    <button @click="go('/WellnessHistory0')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        View Wellness
                                    </button>
                                    <button @click="go('/MyTeam0')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        My Team
                                    </button>
                                    <button @click="go('/announcements0')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        Announcements
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h2 class="text-sm font-semibold text-slate-900">Checklist</h2>
                                <div class="mt-3 space-y-2 text-sm text-slate-600">
                                    <div class="flex gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-[#1f2937]"></span>
                                        <span>Review upcoming sessions and confirm attendance.</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-emerald-500"></span>
                                        <span>Check wellness notes after each practice.</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-amber-500"></span>
                                        <span>Submit semester grades before the deadline.</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>

            </main>

            <RoleFooter
                title="Student-Athlete Workspace"
                description="Stay on top of schedules, attendance, wellness logs, and academic submissions."
                :links="footerLinks"
                :bottom-nav="true"
            />
        </div>

        <StudentBottomNav :items="primaryItems" :is-active="isActive" />
    </div>
</template>

<style scoped>
.student-side-link {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.55rem;
    border-radius: 0.7rem;
    padding: 0.55rem 0.65rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 120ms ease, color 120ms ease;
}

.student-side-link:hover {
    background: #f1f5f9;
}

.student-side-link--active {
    background: rgba(15, 23, 42, 0.1);
    color: #1f2937;
}

.student-top-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    transition: background-color 120ms ease, color 120ms ease;
}

.student-top-tab:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.student-top-tab--active {
    background: rgba(15, 23, 42, 0.1);
    color: #1f2937;
}
</style>
