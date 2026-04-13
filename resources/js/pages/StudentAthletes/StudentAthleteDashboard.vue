<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue'

import StudentBottomNav from '@/components/student/StudentBottomNav.vue'
import RoleFooter from '@/components/ui/RoleFooter.vue'
import UserAccountMenu from '@/components/UserAccountMenu.vue'
import { studentPrimaryNav, studentSecondaryNav } from '@/config/studentNav'


const slots = useSlots()
const page = usePage()
const unreadCount = computed(() => Number(page.props.auth?.announcements?.unread_count ?? 0))
const hasDefaultSlot = computed(() => Boolean(slots.default))
const currentPath = computed(() => String(page.url || ''))
const userName = computed(() => String(page.props.auth?.user?.name ?? 'Athlete'))
const academicAccess = computed(() => (page.props.auth as any)?.academic_access ?? null)
const isAcademicallyRestricted = computed(() => Boolean(academicAccess.value?.is_restricted))
const academicRestrictionMessage = computed(() =>
    String(
        academicAccess.value?.message
        ?? 'Your varsity access is temporarily limited. Please review your Academics module for your current status.'
    )
)
const dashboard = computed(() => (page.props as any)?.dashboard ?? {})
const kpis = computed(() => dashboard.value.kpis ?? {})
const charts = computed(() => dashboard.value.charts ?? {})
const upcomingSeries = computed(() => charts.value.upcoming_sessions ?? [])
const upcomingMax = computed(() => Math.max(1, ...upcomingSeries.value.map((item: any) => Number(item.count || 0))))
const wellnessSeries = computed(() => charts.value.wellness_trend ?? [])
const wellnessMax = computed(() => Math.max(1, ...wellnessSeries.value.map((item: any) => Number(item.value || 0))))
const attendanceBreakdown = computed(() => charts.value.attendance_breakdown ?? { present: 0, absent: 0, excused: 0, no_response: 0 })
const attendanceTotal = computed(() => {
    const values = attendanceBreakdown.value
    return Number(values.present || 0) + Number(values.absent || 0) + Number(values.excused || 0) + Number(values.no_response || 0)
})
const academicSubmissions = computed(() => charts.value.academic_submissions ?? { submitted: 0, pending: 0 })
const upcomingSessionsCount = computed(() =>
    upcomingSeries.value.reduce((sum: number, item: any) => sum + Number(item.count || 0), 0)
)
const submissionTotal = computed(() => Number(academicSubmissions.value.submitted || 0) + Number(academicSubmissions.value.pending || 0))
const submissionProgress = computed(() => {
    if (!submissionTotal.value) return 0
    return Math.round((Number(academicSubmissions.value.submitted || 0) / submissionTotal.value) * 100)
})
const hasActionItems = computed(() =>
    Number(kpis.value.pending_responses || 0) > 0
    || Number(academicSubmissions.value.pending || 0) > 0
    || Number(kpis.value.wellness_logs_30d || 0) === 0
    || isAcademicallyRestricted.value
)
const heroSummary = computed(() => {
    const parts: string[] = []

    if (upcomingSessionsCount.value > 0) {
        parts.push(`${upcomingSessionsCount.value} upcoming ${upcomingSessionsCount.value === 1 ? 'session' : 'sessions'} this week`)
    }

    if (Number(kpis.value.pending_responses || 0) > 0) {
        parts.push(`${kpis.value.pending_responses} pending attendance ${Number(kpis.value.pending_responses) === 1 ? 'response' : 'responses'}`)
    }

    if (Number(academicSubmissions.value.pending || 0) > 0) {
        parts.push(`${academicSubmissions.value.pending} academic ${Number(academicSubmissions.value.pending) === 1 ? 'submission is' : 'submissions are'} still pending`)
    }

    if (parts.length === 0) {
        return 'You are caught up for now. Keep an eye on your schedule, academics, and wellness updates.'
    }

    return `${parts.join(' • ')}.`
})

const mobileMenuOpen = ref(false)
const primaryItems = studentPrimaryNav
const secondaryItems = studentSecondaryNav
const notificationsOpen = ref(false)
const notificationsCloseTimer = ref<number | null>(null)
const bellProcessingIds = ref<number[]>([])

const studentNotifications = ref<Array<{
    id: number
    title: string
    message: string
    type: string
    is_read: boolean
    published_at: string | null
}>>([])

watch(
    () => (page.props.auth as any)?.student_notifications?.recent,
    (items) => {
        studentNotifications.value = Array.isArray(items)
            ? items.map((item: any) => ({ ...item }))
            : []
    },
    { immediate: true }
)

const notificationsCount = computed(() => {
    if (studentNotifications.value.length) {
        return studentNotifications.value.filter((item) => !item.is_read).length
    }
    return unreadCount.value
})

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

function openNotifications() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value)
        notificationsCloseTimer.value = null
    }
    notificationsOpen.value = true
}

function scheduleNotificationsClose() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value)
    }
    notificationsCloseTimer.value = window.setTimeout(() => {
        notificationsOpen.value = false
        notificationsCloseTimer.value = null
    }, 180)
}

function isBellProcessing(id: number) {
    return bellProcessingIds.value.includes(id)
}

function markBellRead(item: { id: number; is_read: boolean }) {
    if (item.is_read || isBellProcessing(item.id)) return
    const previous = item.is_read
    item.is_read = true
    bellProcessingIds.value = [...bellProcessingIds.value, item.id]

    router.put(`/announcements/${item.id}/read`, {}, {
        preserveScroll: true,
        onError: () => {
            item.is_read = previous
        },
        onFinish: () => {
            bellProcessingIds.value = bellProcessingIds.value.filter((id) => id !== item.id)
        },
    })
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
})

onUnmounted(() => {
    window.removeEventListener('keydown', onEsc)
    document.body.style.overflow = ''
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
            <header class="student-shell__topbar sticky top-0 z-50 border-b border-[#034485]/20 backdrop-blur">
                <div class="mx-auto flex w-full max-w-[1600px] items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="student-shell__nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#034485]/30 md:hidden"
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
                        <div
                            class="relative"
                            @mouseenter="openNotifications"
                            @mouseleave="scheduleNotificationsClose"
                            @focusin="openNotifications"
                            @focusout="scheduleNotificationsClose"
                        >
                            <button
                                type="button"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#034485]/30 bg-white text-[#034485] transition hover:border-[#034485]/50 hover:text-[#033a70]"
                                aria-label="Open announcements"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" />
                                    <path d="M9 17a3 3 0 0 0 6 0" />
                                </svg>
                                <span
                                    v-if="notificationsCount > 0"
                                    class="absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ notificationsCount }}
                                </span>
                            </button>

                            <div
                                v-if="notificationsOpen"
                                class="absolute right-0 mt-2 w-72 overflow-hidden rounded-xl border border-[#034485]/30 bg-white shadow-lg"
                            >
                                <div class="flex items-center justify-between border-b border-[#034485]/20 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Announcements
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ notificationsCount }}</span>
                                </div>
                                <div class="max-h-72 overflow-y-auto">
                                    <button
                                        v-for="item in studentNotifications"
                                        :key="item.id ?? item.title"
                                        type="button"
                                        class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition"
                                        :class="item.is_read ? 'border-b border-slate-100 text-slate-700 hover:bg-slate-50' : 'border-b border-white/10 bg-[#034485] text-white hover:bg-[#033a70]'"
                                        @click="markBellRead(item); go('/announcements')"
                                    >
                                        <span class="mt-1 inline-flex h-2 w-2 shrink-0 rounded-full" :class="item.is_read ? 'bg-[#034485]' : 'bg-white'" />
                                        <span class="flex-1">
                                            <span class="block font-semibold" :class="item.is_read ? 'text-slate-800' : 'text-white'">{{ item.title }}</span>
                                            <span class="block text-xs" :class="item.is_read ? 'text-slate-500' : 'text-white/80'">{{ item.message }}</span>
                                        </span>
                                        <span class="ml-auto text-[10px] font-semibold" :class="item.is_read ? 'text-slate-400' : 'text-white/70'">{{ item.published_at ?? '' }}</span>
                                    </button>
                                    <div v-if="studentNotifications.length === 0" class="px-3 py-4 text-xs text-slate-500">
                                        No announcements right now.
                                    </div>
                                </div>
                                <div class="border-t border-[#034485]/20 px-3 py-2">
                                    <button
                                        type="button"
                                        class="w-full rounded-full border border-[#034485]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] transition hover:border-[#034485]/50 hover:bg-[#034485]/5"
                                        @click="go('/announcements')"
                                    >
                                        View all
                                    </button>
                                </div>
                            </div>
                        </div>
                        <UserAccountMenu :dark="false" menu-placement="bottom" compact />
                    </div>
                </div>

                <div class="student-shell__tabbar hidden border-t border-[#034485]/20 bg-white md:block">
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
                class="fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r border-[#034485]/20 bg-white p-4 transition md:hidden"
                :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold text-[#1f2937]">Student Menu</p>
                    <button type="button" class="rounded border border-[#034485]/30 px-2 py-1 text-xs text-slate-500" @click="mobileMenuOpen = false">Close</button>
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

                <div class="mt-4 border-t border-[#034485]/20 pt-4">
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
                <section
                    v-if="isAcademicallyRestricted"
                    class="mb-4 overflow-hidden rounded-3xl border border-amber-200 bg-[linear-gradient(135deg,rgba(255,251,235,0.98),rgba(255,255,255,0.96))] shadow-sm"
                >
                    <div class="flex flex-col gap-4 px-4 py-4 sm:px-5 sm:py-5 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 9v4" />
                                    <path d="M12 17h.01" />
                                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                </svg>
                            </span>
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">Academic access restriction active</p>
                                    <span class="rounded-full border border-amber-200 bg-white/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700">
                                        Academics only
                                    </span>
                                </div>
                                <p class="max-w-3xl text-sm leading-6 text-slate-600">
                                    {{ academicRestrictionMessage }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#033a70]"
                                @click="go('/AcademicSubmissions')"
                            >
                                Open Academics
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#034485]/5"
                                @click="go('/account/help')"
                            >
                                Get Help
                            </button>
                        </div>
                    </div>
                </section>

                <template v-if="hasDefaultSlot">
                    <slot />
                </template>
                <template v-else>
                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-[2rem] border border-[#034485]/35 bg-[linear-gradient(135deg,rgba(255,255,255,1),rgba(239,246,255,0.96))] p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-5">
                                <div class="max-w-3xl">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Student-Athlete Home</p>
                                    <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Welcome back, {{ userName }}</h1>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        {{ heroSummary }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl border border-[#034485]/20 bg-white/90 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">This Week</p>
                                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ upcomingSessionsCount }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Upcoming training or event sessions.</p>
                                </div>
                                <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-700">Pending Responses</p>
                                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ kpis.pending_responses ?? 0 }}</p>
                                    <p class="mt-1 text-xs text-amber-800/80">Attendance confirmations waiting for you.</p>
                                </div>
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Wellness Activity</p>
                                    <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ kpis.wellness_logs_30d ?? 0 }}</p>
                                    <p class="mt-1 text-xs text-emerald-800/80">Wellness logs recorded in the last 30 days.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Academic Standing</p>
                                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ kpis.academic_status ? String(kpis.academic_status).replace('_', ' ') : 'No record yet' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Latest eligibility evaluation on file.</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-3xl border border-[#034485]/30 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900">Action Needed</h2>
                                    <p class="mt-1 text-sm text-slate-500">Your highest-priority items are listed here first.</p>
                                </div>
                                <span v-if="hasActionItems" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-700">
                                    Needs attention
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-amber-900">Attendance Response</p>
                                            <p class="mt-1 text-xs leading-5 text-amber-800/80">
                                                {{ Number(kpis.pending_responses || 0) > 0
                                                    ? 'You still have attendance responses waiting in your schedule.'
                                                    : 'You are caught up on attendance confirmations.' }}
                                            </p>
                                        </div>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-amber-800">{{ kpis.pending_responses ?? 0 }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-4 rounded-full bg-amber-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-950"
                                        @click="go('/MySchedule')"
                                    >
                                        Open Schedule
                                    </button>
                                </article>

                                <article class="rounded-2xl border border-[#034485]/20 bg-slate-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Academic Submission Status</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-600">
                                                {{ Number(academicSubmissions.pending || 0) > 0
                                                    ? `${academicSubmissions.pending} submission${Number(academicSubmissions.pending) === 1 ? '' : 's'} still pending this active period.`
                                                    : 'All currently open academic submission requirements are complete.' }}
                                            </p>
                                        </div>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-[#034485]">
                                            {{ academicSubmissions.submitted }}/{{ submissionTotal || 0 }}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-4 rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#033a70]"
                                        @click="go('/AcademicSubmissions')"
                                    >
                                        Go to Academics
                                    </button>
                                </article>

                                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-emerald-900">Wellness Check-In</p>
                                            <p class="mt-1 text-xs leading-5 text-emerald-800/80">
                                                {{ Number(kpis.wellness_logs_30d || 0) === 0
                                                    ? 'No recent wellness logs found. Add one to keep your status updated.'
                                                    : 'Keep your recovery and readiness records current.' }}
                                            </p>
                                        </div>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ kpis.wellness_logs_30d ?? 0 }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-4 rounded-full bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800"
                                        @click="go('/WellnessHistory')"
                                    >
                                        Open Wellness
                                    </button>
                                </article>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                                <p class="text-xs text-slate-500">Attendance Rate (30d)</p>
                                <p class="mt-1 text-2xl font-semibold text-[#1f2937]">
                                    {{ kpis.attendance_rate != null ? `${kpis.attendance_rate}%` : '—' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">Present and excused sessions combined.</p>
                            </div>
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                                <p class="text-xs text-slate-500">Academic Standing</p>
                                <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ kpis.academic_status ? String(kpis.academic_status).replace('_', ' ') : '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Most recent evaluation result.</p>
                            </div>
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                                <p class="text-xs text-slate-500">Submission Progress</p>
                                <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ submissionProgress }}%</p>
                                <p class="mt-1 text-xs text-slate-500">{{ academicSubmissions.submitted }} of {{ submissionTotal || 0 }} requirements completed.</p>
                            </div>
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                                <p class="text-xs text-slate-500">Announcements</p>
                                <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ notificationsCount }}</p>
                                <p class="mt-1 text-xs text-slate-500">Unread notices from admin and system updates.</p>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-slate-900">Upcoming Sessions</h2>
                                        <p class="mt-1 text-sm text-slate-500">Your scheduled team activities for the next seven days.</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-full border border-[#034485]/25 px-3 py-1.5 text-xs font-semibold text-[#034485] transition hover:bg-[#034485]/5"
                                        @click="go('/MySchedule')"
                                    >
                                        View Full Schedule
                                    </button>
                                </div>
                                <div class="mt-4 flex items-end gap-2 overflow-x-auto pb-1">
                                    <div v-for="point in upcomingSeries" :key="point.label" class="flex min-w-10 flex-col items-center gap-2">
                                        <div class="flex h-20 w-8 items-end rounded-full bg-[#034485]/10 p-1">
                                            <div
                                                class="w-full rounded-full bg-[#034485]/75"
                                                :style="{ height: `${6 + (Number(point.count || 0) / upcomingMax) * 64}px` }"
                                            ></div>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-[10px] font-semibold text-slate-900">{{ point.count }}</p>
                                            <span class="text-[10px] text-slate-500">{{ point.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-slate-900">Attendance Breakdown</h2>
                                        <p class="mt-1 text-sm text-slate-500">A 30-day summary of your attendance history.</p>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500">{{ attendanceTotal }} total</span>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <div class="flex h-3 overflow-hidden rounded-full border border-[#034485]/20">
                                        <span class="bg-emerald-500" :style="{ width: `${attendanceTotal ? (attendanceBreakdown.present / attendanceTotal) * 100 : 0}%` }"></span>
                                        <span class="bg-rose-500" :style="{ width: `${attendanceTotal ? (attendanceBreakdown.absent / attendanceTotal) * 100 : 0}%` }"></span>
                                        <span class="bg-amber-400" :style="{ width: `${attendanceTotal ? (attendanceBreakdown.excused / attendanceTotal) * 100 : 0}%` }"></span>
                                        <span class="bg-slate-300" :style="{ width: `${attendanceTotal ? (attendanceBreakdown.no_response / attendanceTotal) * 100 : 0}%` }"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">
                                        <div class="rounded-xl bg-emerald-50 px-3 py-2"><span class="font-semibold text-emerald-700">Present:</span> {{ attendanceBreakdown.present }}</div>
                                        <div class="rounded-xl bg-rose-50 px-3 py-2"><span class="font-semibold text-rose-700">Absent:</span> {{ attendanceBreakdown.absent }}</div>
                                        <div class="rounded-xl bg-amber-50 px-3 py-2"><span class="font-semibold text-amber-700">Excused:</span> {{ attendanceBreakdown.excused }}</div>
                                        <div class="rounded-xl bg-slate-100 px-3 py-2"><span class="font-semibold text-slate-700">No response:</span> {{ attendanceBreakdown.no_response }}</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-slate-900">Academic Submission Progress</h2>
                                        <p class="mt-1 text-sm text-slate-500">Track how many active academic requirements you have already completed.</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-full border border-[#034485]/25 px-3 py-1.5 text-xs font-semibold text-[#034485] transition hover:bg-[#034485]/5"
                                        @click="go('/AcademicSubmissions')"
                                    >
                                        Open Academics
                                    </button>
                                </div>

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex items-end justify-between gap-3">
                                        <div>
                                            <p class="text-3xl font-semibold text-slate-900">{{ submissionProgress }}%</p>
                                            <p class="mt-1 text-sm text-slate-500">
                                                You have completed {{ academicSubmissions.submitted }} of {{ submissionTotal || 0 }} required submissions.
                                            </p>
                                        </div>
                                        <div class="text-right text-xs text-slate-500">
                                            <p>Submitted: {{ academicSubmissions.submitted }}</p>
                                            <p>Pending: {{ academicSubmissions.pending }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-[#034485]" :style="{ width: `${submissionProgress}%` }"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-[#034485]/35 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-slate-900">Wellness Trend</h2>
                                        <p class="mt-1 text-sm text-slate-500">Recent wellness entries over the last seven days.</p>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500">Last {{ wellnessSeries.length }}</span>
                                </div>
                                <div class="mt-4 flex items-end gap-2 overflow-x-auto pb-1">
                                    <div v-for="point in wellnessSeries" :key="point.label" class="flex min-w-10 flex-col items-center gap-2">
                                        <div class="flex h-20 w-8 items-end rounded-full bg-emerald-100 p-1">
                                            <div
                                                class="w-full rounded-full bg-emerald-500/80"
                                                :style="{ height: `${6 + (Number(point.value || 0) / wellnessMax) * 64}px` }"
                                            ></div>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-[10px] font-semibold text-slate-900">{{ point.value }}</p>
                                            <span class="text-[10px] text-slate-500">{{ point.label }}</span>
                                        </div>
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
    background: rgba(3, 68, 133, 0.08);
}

.student-side-link--active {
    background: rgba(3, 68, 133, 0.14);
    color: #034485;
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
    background: rgba(3, 68, 133, 0.08);
    color: #033a70;
}

.student-top-tab--active {
    background: rgba(3, 68, 133, 0.14);
    color: #034485;
}
</style>
