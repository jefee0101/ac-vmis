<script setup lang="ts">
import Spinner from '@/components/ui/spinner/Spinner.vue'
import RoleFooter from '@/components/ui/RoleFooter.vue'
import UserAccountMenu from '@/components/UserAccountMenu.vue'
import { useInertiaLoading } from '@/composables/useInertiaLoading'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue'

const SIDEBAR_PREF_KEY = 'ac-vmis-admin-sidebar-collapsed'

const slots = useSlots()
const page = usePage()
const { isLoading } = useInertiaLoading()

type DashboardPayload = {
    filters: {
        period: 'today' | 'week' | 'month'
        start_date: string
        end_date: string
    }
    kpis: {
        attendance_rate: number
        no_response: number
        expired_clearances: number
        academic_at_risk: number
        pending_approvals: number
    }
    trends: {
        labels: string[]
        attendance: {
            present: number[]
            late: number[]
            absent: number[]
            no_response: number[]
        }
        health_distribution: {
            fit: number
            fit_with_restrictions: number
            not_fit: number
            expired: number
        }
        academic_by_team: Array<{
            team_name: string
            eligible: number
            probation: number
            ineligible: number
            total: number
        }>
        heatmap: {
            days: string[]
            hours: number[]
            cells: Array<{ day: string; hour: number; late: number; no_response: number; value: number }>
        }
    }
    queues: {
        today_schedules: Array<{
            id: number
            title: string
            team_name: string
            start_time: string
            roster_total: number
            late: number
            absent: number
            no_response: number
        }>
        needs_attention: Array<{
            type: string
            title: string
            subtitle: string
            action_label: string
            action_url: string
            priority: number
        }>
    }
    activity_log: {
        items: Array<{
            id: string
            actor_id: number
            actor_name: string
            actor_role: string
            action_type: string
            description: string
            happened_at: string
        }>
        summary: {
            total: number
            students: number
            coaches: number
        }
    }
}

const unreadCount = computed(() => Number(page.props.auth?.announcements?.unread_count ?? 0))
const hasDefaultSlot = computed(() => Boolean(slots.default))
const currentPath = computed(() => String(page.url || ''))
const dashboard = computed(() => (page.props.dashboard as DashboardPayload | undefined) ?? null)
const mobileNavOpen = ref(false)
const sidebarCollapsed = ref(false)
const activityCollapsed = ref(false)
const activityMaximized = ref(false)

function logout() {
    router.post('/logout')
}

type NavEntry = {
    name: string
    route: string
    iconPaths: string[]
}

const pages: NavEntry[] = [
    { name: 'Dashboard', route: '/AdminDashboard', iconPaths: ['M3 13h8V3H3z', 'M13 21h8v-6h-8z', 'M13 11h8V3h-8z', 'M3 21h8v-6H3z'] },
    { name: 'People', route: '/people', iconPaths: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 8v6', 'M23 11h-6'] },
    { name: 'Teams', route: '/teams', iconPaths: ['M17 21v-2a4 4 0 0 0-3-3.87', 'M7 21v-2a4 4 0 0 1 3-3.87', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M5 8H4a2 2 0 0 0-2 2v2', 'M19 8h1a2 2 0 0 1 2 2v2'] },
    { name: 'Operations', route: '/operations', iconPaths: ['M3 3v18h18', 'M7 13l3-3 3 2 5-6'] },
    { name: 'Health & Clearance', route: '/health', iconPaths: ['M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z', 'M9 12l2 2 4-4'] },
    { name: 'Academics', route: '/academics', iconPaths: ['M4 19.5V6a2 2 0 0 1 2-2h9l5 5v10.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z', 'M14 4v5h5', 'M8 13h8', 'M8 17h5'] },
]

const footerLinks = [
    { label: 'Dashboard', href: '/AdminDashboard' },
    { label: 'People', href: '/people' },
    { label: 'Teams', href: '/teams' },
    { label: 'Operations', href: '/operations' },
    { label: 'Health', href: '/health' },
    { label: 'Academics', href: '/academics' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
]

const currentPageName = computed(() => {
    if (currentPath.value === '/people/queue' || currentPath.value.startsWith('/people/queue/')) {
        return 'People'
    }
    if (currentPath.value === '/operations/attendance' || currentPath.value.startsWith('/operations/attendance/')) {
        return 'Operations'
    }
    if (currentPath.value === '/health/wellness' || currentPath.value.startsWith('/health/wellness/')) {
        return 'Health & Clearance'
    }

    const match = pages.find((item) => currentPath.value === item.route || currentPath.value.startsWith(`${item.route}/`))
    return match?.name ?? 'Dashboard'
})

const selectedPeriod = computed(() => dashboard.value?.filters.period ?? 'week')

const attendanceMax = computed(() => {
    const values = dashboard.value
        ? [
            ...dashboard.value.trends.attendance.present,
            ...dashboard.value.trends.attendance.late,
            ...dashboard.value.trends.attendance.absent,
            ...dashboard.value.trends.attendance.no_response,
        ]
        : [0]

    return Math.max(1, ...values)
})

const weeklySummary = computed(() => {
    if (!dashboard.value) return []

    const labels = dashboard.value.trends.labels ?? []
    return labels.map((label, index) => {
        const onTime = dashboard.value?.trends.attendance.present[index] ?? 0
        const late = dashboard.value?.trends.attendance.late[index] ?? 0
        const noResponse = dashboard.value?.trends.attendance.no_response[index] ?? 0
        const total = onTime + late + noResponse

        return {
            label,
            onTime,
            late,
            noResponse,
            total,
        }
    })
})

const weeklyMax = computed(() => {
    const values = weeklySummary.value.map((item) => item.total)
    return Math.max(1, ...values)
})

function goTo(route: string) {
    mobileNavOpen.value = false
    router.get(route)
}

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`)
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem(SIDEBAR_PREF_KEY, sidebarCollapsed.value ? '1' : '0')
}

function closeMobileNav() {
    mobileNavOpen.value = false
}

function onEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeMobileNav()
    }
}

function setDashboardPeriod(period: 'today' | 'week' | 'month') {
    if (hasDefaultSlot.value) return
    router.get('/AdminDashboard', { period }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function buildPolyline(values: number[]) {
    if (values.length === 0) return ''

    const width = 620
    const height = 210
    const pad = 16
    const innerWidth = width - pad * 2
    const innerHeight = height - pad * 2
    const step = values.length > 1 ? innerWidth / (values.length - 1) : 0

    return values.map((value, index) => {
        const x = pad + index * step
        const y = pad + (innerHeight - ((value / attendanceMax.value) * innerHeight))
        return `${x},${y}`
    }).join(' ')
}

function healthPercent(value: number, total: number) {
    if (total <= 0) return 0
    return Math.round((value / total) * 100)
}

function academicSegment(value: number, total: number) {
    if (total <= 0) return 0
    return Math.max(4, Math.round((value / total) * 100))
}

function weeklyBarHeight(value: number) {
    if (value <= 0 || weeklyMax.value <= 0) return 0
    return Math.round((value / weeklyMax.value) * 160)
}

function formatActivityTime(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function roleTone(role: string) {
    if (role === 'coach') return 'bg-slate-100 text-slate-700'
    return 'bg-emerald-100 text-emerald-700'
}

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_PREF_KEY) === '1'
    window.addEventListener('keydown', onEscape)
})

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape)
    document.body.style.overflow = ''
})

watch(mobileNavOpen, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
})
</script>

<template>
    <div class="admin-shell min-h-screen bg-slate-50 text-slate-900">
        <div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(15, 23, 42,0.10),transparent_45%)]" />

        <div v-if="mobileNavOpen" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="closeMobileNav" />

        <aside
            class="fixed left-0 top-0 z-40 h-screen border-r border-slate-200 bg-white/95 backdrop-blur transition-all duration-300 ease-out"
            :class="[
                mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'w-[280px] max-w-[85vw] lg:w-[88px]' : 'w-[280px] max-w-[85vw] lg:w-[280px]',
                'lg:translate-x-0'
            ]"
        >
            <div class="flex h-full flex-col">
                <div class="admin-shell__sidebar-header border-b border-slate-200 px-4 py-5" :class="sidebarCollapsed ? 'lg:text-center' : ''">
                    <div class="mb-2 hidden items-center lg:flex" :class="sidebarCollapsed ? 'justify-center' : 'justify-end'">
                        <button
                            type="button"
                            class="admin-shell__nav-toggle inline-flex h-8 w-8 items-center justify-center rounded-md border transition"
                            @click="toggleSidebar"
                            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            aria-label="Toggle sidebar"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path v-if="sidebarCollapsed" d="M8 6l6 6-6 6" />
                                <path v-else d="M16 6l-6 6 6 6" />
                            </svg>
                        </button>
                    </div>
                    <p class="admin-shell__kicker text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" :class="sidebarCollapsed ? 'lg:sr-only' : ''">AC VMIS</p>
                    <h1 class="admin-shell__title mt-1 text-lg font-bold text-[#1f2937]">{{ sidebarCollapsed ? 'AC' : 'Admin Workspace' }}</h1>
                    <p v-if="!sidebarCollapsed" class="admin-shell__subtitle mt-1 text-xs text-slate-500">Operations, approvals, and analytics</p>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                    <p v-if="!sidebarCollapsed" class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        Workspace
                    </p>
                    <button
                        v-for="entry in pages"
                        :key="entry.name"
                        type="button"
                        @click="goTo(entry.route)"
                        class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="isActive(entry.route)
                            ? 'border-[#1f2937] bg-[#1f2937] text-white shadow-sm'
                            : 'border-transparent text-slate-700 hover:border-slate-200 hover:bg-slate-100'"
                        :title="sidebarCollapsed ? entry.name : ''"
                    >
                        <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path v-for="(path, idx) in entry.iconPaths" :key="`${entry.route}-icon-${idx}`" :d="path" />
                        </svg>
                        <span
                            class="ml-2 origin-left whitespace-nowrap transition-all duration-200"
                            :class="sidebarCollapsed ? 'max-w-[180px] scale-100 opacity-100 lg:max-w-0 lg:scale-95 lg:overflow-hidden lg:opacity-0' : 'max-w-[180px] scale-100 opacity-100'"
                        >
                            {{ entry.name }}
                        </span>
                    </button>
                </nav>

                <div class="border-t border-slate-200 px-3 py-3">
                    <button
                        type="button"
                        class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium text-rose-600 transition-all duration-200 hover:border-rose-200 hover:bg-rose-50"
                        :class="sidebarCollapsed && !mobileNavOpen ? 'justify-center' : ''"
                        @click="logout"
                    >
                        <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span v-if="!sidebarCollapsed || mobileNavOpen" class="ml-2">Logout</span>
                    </button>
                </div>
            </div>
        </aside>

        <div class="transition-all duration-300 ease-out" :class="sidebarCollapsed ? 'lg:pl-[88px]' : 'lg:pl-[280px]'">
            <header class="admin-shell__topbar sticky top-0 z-20 border-b border-slate-200 backdrop-blur">
                <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-2">
                        <button
                            type="button"
                            class="admin-shell__nav-toggle inline-flex h-9 w-9 items-center justify-center rounded-md border lg:hidden"
                            @click="mobileNavOpen = true"
                            aria-label="Open admin navigation"
                        >
                            <span class="space-y-1">
                                <span class="block h-0.5 w-4 bg-current"/>
                                <span class="block h-0.5 w-4 bg-current"/>
                                <span class="block h-0.5 w-4 bg-current"/>
                            </span>
                        </button>

                        <div class="min-w-0">
                            <p class="admin-shell__kicker truncate text-xs uppercase tracking-[0.16em] text-slate-500">Administrator</p>
                            <h2 class="admin-shell__title truncate text-base font-semibold text-[#1f2937] sm:text-lg">{{ currentPageName }}</h2>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div v-if="isLoading" class="admin-shell__loading-pill inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600">
                            <Spinner class="h-3.5 w-3.5 text-[#1f2937]" />
                            Loading
                        </div>
                        <UserAccountMenu :dark="false" menu-placement="bottom" compact />
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6">
                <slot v-if="hasDefaultSlot" />

                <div v-else-if="dashboard" class="space-y-5">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-[#1f2937]">Executive Dashboard</h3>
                                <p class="mt-1 text-sm text-slate-600">Cross-workspace health: Operations, Health, Academics, and People.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-full px-3 py-1 text-xs font-medium" :class="selectedPeriod === 'today' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'" @click="setDashboardPeriod('today')">Today</button>
                                <button type="button" class="rounded-full px-3 py-1 text-xs font-medium" :class="selectedPeriod === 'week' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'" @click="setDashboardPeriod('week')">This Week</button>
                                <button type="button" class="rounded-full px-3 py-1 text-xs font-medium" :class="selectedPeriod === 'month' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'" @click="setDashboardPeriod('month')">This Month</button>
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs text-slate-500">Attendance Rate</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ dashboard.kpis.attendance_rate }}%</p>
                        </article>
                        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                            <p class="text-xs text-amber-700">No Response</p>
                            <p class="mt-1 text-2xl font-bold text-amber-900">{{ dashboard.kpis.no_response }}</p>
                        </article>
                        <article class="rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                            <p class="text-xs text-rose-700">Expired Clearances</p>
                            <p class="mt-1 text-2xl font-bold text-rose-900">{{ dashboard.kpis.expired_clearances }}</p>
                        </article>
                        <article class="rounded-xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
                            <p class="text-xs text-orange-700">Academic At Risk</p>
                            <p class="mt-1 text-2xl font-bold text-orange-900">{{ dashboard.kpis.academic_at_risk }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                            <p class="text-xs text-slate-700">Pending Approvals</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ dashboard.kpis.pending_approvals }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs text-slate-500">Unread Announcements</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ unreadCount }}</p>
                        </article>
                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-800">Attendance Trend</h4>
                                <div class="flex gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500" />Present</span>
                                    <span class="inline-flex items-center gap-1 text-amber-700"><span class="h-2 w-2 rounded-full bg-amber-500" />Late</span>
                                    <span class="inline-flex items-center gap-1 text-red-700"><span class="h-2 w-2 rounded-full bg-red-500" />Absent</span>
                                    <span class="inline-flex items-center gap-1 text-slate-700"><span class="h-2 w-2 rounded-full bg-slate-500" />No Response</span>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 620 210" class="h-[220px] min-w-[620px] w-full rounded-lg bg-slate-50">
                                    <polyline :points="buildPolyline(dashboard.trends.attendance.present)" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" />
                                    <polyline :points="buildPolyline(dashboard.trends.attendance.late)" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" />
                                    <polyline :points="buildPolyline(dashboard.trends.attendance.absent)" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" />
                                    <polyline :points="buildPolyline(dashboard.trends.attendance.no_response)" fill="none" stroke="#64748b" stroke-width="2.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-1 text-[10px] text-slate-500 sm:grid-cols-8">
                                <span v-for="label in dashboard.trends.labels" :key="label" class="truncate">{{ label }}</span>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Health Risk Distribution</h4>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <div class="mb-1 flex justify-between"><span class="text-slate-600">Fit</span><span class="font-semibold text-slate-900">{{ dashboard.trends.health_distribution.fit }}</span></div>
                                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${healthPercent(dashboard.trends.health_distribution.fit, Object.values(dashboard.trends.health_distribution).reduce((a, b) => a + b, 0))}%` }" /></div>
                                </div>
                                <div>
                                    <div class="mb-1 flex justify-between"><span class="text-slate-600">Fit w/ Restriction</span><span class="font-semibold text-slate-900">{{ dashboard.trends.health_distribution.fit_with_restrictions }}</span></div>
                                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-amber-500" :style="{ width: `${healthPercent(dashboard.trends.health_distribution.fit_with_restrictions, Object.values(dashboard.trends.health_distribution).reduce((a, b) => a + b, 0))}%` }" /></div>
                                </div>
                                <div>
                                    <div class="mb-1 flex justify-between"><span class="text-slate-600">Not Fit</span><span class="font-semibold text-slate-900">{{ dashboard.trends.health_distribution.not_fit }}</span></div>
                                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-orange-500" :style="{ width: `${healthPercent(dashboard.trends.health_distribution.not_fit, Object.values(dashboard.trends.health_distribution).reduce((a, b) => a + b, 0))}%` }" /></div>
                                </div>
                                <div>
                                    <div class="mb-1 flex justify-between"><span class="text-slate-600">Expired</span><span class="font-semibold text-slate-900">{{ dashboard.trends.health_distribution.expired }}</span></div>
                                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-rose-500" :style="{ width: `${healthPercent(dashboard.trends.health_distribution.expired, Object.values(dashboard.trends.health_distribution).reduce((a, b) => a + b, 0))}%` }" /></div>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Academic Status by Team</h4>
                            <div v-if="dashboard.trends.academic_by_team.length === 0" class="text-sm text-slate-500">No evaluations found for current period.</div>
                            <div v-else class="space-y-2">
                                <div v-for="team in dashboard.trends.academic_by_team" :key="team.team_name" class="rounded-lg border border-slate-100 p-2">
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <span class="font-medium text-slate-700">{{ team.team_name }}</span>
                                        <span class="text-slate-500">{{ team.total }} athletes</span>
                                    </div>
                                    <div class="flex h-2 overflow-hidden rounded-full bg-slate-100">
                                        <span class="bg-emerald-500" :style="{ width: `${academicSegment(team.eligible, team.total)}%` }" />
                                        <span class="bg-amber-500" :style="{ width: `${academicSegment(team.probation, team.total)}%` }" />
                                        <span class="bg-red-500" :style="{ width: `${academicSegment(team.ineligible, team.total)}%` }" />
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-800">Weekly Response Summary</h4>
                                <div class="flex gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500" />On-time</span>
                                    <span class="inline-flex items-center gap-1 text-amber-700"><span class="h-2 w-2 rounded-full bg-amber-500" />Late</span>
                                    <span class="inline-flex items-center gap-1 text-slate-700"><span class="h-2 w-2 rounded-full bg-slate-500" />No Response</span>
                                </div>
                            </div>
                            <div v-if="weeklySummary.length === 0" class="text-sm text-slate-500">
                                No attendance response data found for this period.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <div class="min-w-[560px]">
                                    <div class="flex h-44 items-end gap-3">
                                        <div v-for="item in weeklySummary" :key="item.label" class="flex min-w-[36px] flex-1 flex-col items-stretch">
                                            <div
                                                class="flex h-40 flex-col-reverse overflow-hidden rounded-md bg-slate-100"
                                                :title="`On-time ${item.onTime} • Late ${item.late} • No Response ${item.noResponse}`"
                                            >
                                                <div class="bg-emerald-500" :style="{ height: `${weeklyBarHeight(item.onTime)}px` }" />
                                                <div class="bg-amber-500" :style="{ height: `${weeklyBarHeight(item.late)}px` }" />
                                                <div class="bg-slate-500" :style="{ height: `${weeklyBarHeight(item.noResponse)}px` }" />
                                            </div>
                                            <span class="mt-2 text-center text-[10px] text-slate-500">{{ item.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Today's Schedules</h4>
                            <div v-if="dashboard.queues.today_schedules.length === 0" class="text-sm text-slate-500">No schedules for today.</div>
                            <div v-else class="space-y-2">
                                <div v-for="item in dashboard.queues.today_schedules" :key="item.id" class="rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                            <p class="text-xs text-slate-500">{{ item.team_name }} • {{ item.start_time }}</p>
                                        </div>
                                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="goTo('/operations?tab=calendar')">Open</button>
                                    </div>
                                    <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                        <p class="rounded bg-amber-50 px-2 py-1 text-amber-700">Late: {{ item.late }}</p>
                                        <p class="rounded bg-rose-50 px-2 py-1 text-rose-700">Absent: {{ item.absent }}</p>
                                        <p class="rounded bg-slate-100 px-2 py-1 text-slate-700">No Response: {{ item.no_response }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Needs Attention Queue</h4>
                            <div v-if="dashboard.queues.needs_attention.length === 0" class="text-sm text-slate-500">No priority items right now.</div>
                            <div v-else class="space-y-2">
                                <div v-for="(item, idx) in dashboard.queues.needs_attention" :key="`${item.type}-${idx}`" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 p-2">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ item.title }}</p>
                                        <p class="text-xs text-slate-500">{{ item.subtitle }}</p>
                                    </div>
                                    <button type="button" class="rounded-md bg-[#1f2937] px-2.5 py-1 text-xs font-semibold text-white hover:bg-[#334155]" @click="goTo(item.action_url)">
                                        {{ item.action_label }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-800">Activity Log (Students & Coaches)</h4>
                                <p class="text-xs text-slate-500">
                                    {{ dashboard.activity_log.summary.total }} recent actions
                                    • Students {{ dashboard.activity_log.summary.students }}
                                    • Coaches {{ dashboard.activity_log.summary.coaches }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100" @click="activityCollapsed = !activityCollapsed">
                                    {{ activityCollapsed ? 'Show' : 'Minimize' }}
                                </button>
                                <button
                                    v-if="!activityCollapsed"
                                    type="button"
                                    class="rounded-md border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100"
                                    @click="activityMaximized = !activityMaximized"
                                >
                                    {{ activityMaximized ? 'Normal Size' : 'Maximize' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="!activityCollapsed" :class="activityMaximized ? 'max-h-[520px]' : 'max-h-[260px]'" class="overflow-y-auto rounded-lg border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-slate-50 text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Actor</th>
                                        <th class="px-3 py-2 text-left">Action</th>
                                        <th class="px-3 py-2 text-left">When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in dashboard.activity_log.items" :key="item.id" class="border-t border-slate-200 text-slate-700">
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-slate-900">{{ item.actor_name }}</div>
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="roleTone(item.actor_role)">
                                                {{ item.actor_role.replace('-', ' ') }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="capitalize text-xs text-slate-500">{{ item.action_type }}</div>
                                            <div>{{ item.description }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-600">{{ formatActivityTime(item.happened_at) }}</td>
                                    </tr>
                                    <tr v-if="dashboard.activity_log.items.length === 0">
                                        <td colspan="3" class="px-3 py-8 text-center text-slate-500">No recent student/coach activity found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div v-else class="space-y-5">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-bold text-[#1f2937]">Dashboard</h3>
                        <p class="mt-1 text-sm text-slate-600">No dashboard data available.</p>
                    </section>
                </div>

            </main>
        
            <RoleFooter
                title="Administrator Console"
                description="Manage people, teams, schedules, health, and academic compliance from one workspace."
                :links="footerLinks"
            />
        
        </div>
    </div>
</template>
