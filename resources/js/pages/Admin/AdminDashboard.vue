<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';

import RoleFooter from '@/components/ui/RoleFooter.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import UserAccountMenu from '@/components/UserAccountMenu.vue';
import { useInertiaLoading } from '@/composables/useInertiaLoading';

const SIDEBAR_PREF_KEY = 'ac-vmis-admin-sidebar-collapsed';

const slots = useSlots();
const page = usePage();
const { isLoading } = useInertiaLoading();

type DashboardPayload = {
    filters: {
        period: 'today' | 'week' | 'month';
        start_date: string;
        end_date: string;
    };
    kpis: {
        attendance_rate: number;
        no_response: number;
        expired_clearances: number;
        academic_at_risk: number;
        pending_approvals: number;
    };
    trends: {
        labels: string[];
        attendance: {
            present: number[];
            late: number[];
            absent: number[];
            no_response: number[];
        };
        health_distribution: {
            fit: number;
            fit_with_restrictions: number;
            not_fit: number;
            expired: number;
        };
        academic_by_team: Array<{
            team_name: string;
            eligible: number;
            pending_review: number;
            ineligible: number;
            total: number;
        }>;
        heatmap: {
            days: string[];
            hours: number[];
            cells: Array<{ day: string; hour: number; late: number; no_response: number; value: number }>;
        };
    };
    queues: {
        today_schedules: Array<{
            id: number;
            title: string;
            team_name: string;
            start_time: string;
            roster_total: number;
            late: number;
            absent: number;
            no_response: number;
        }>;
        needs_attention: Array<{
            type: string;
            title: string;
            subtitle: string;
            action_label: string;
            action_url: string;
            priority: number;
        }>;
    };
    activity_log: {
        items: Array<{
            id: string;
            actor_id: number;
            actor_name: string;
            actor_role: string;
            action_type: string;
            description: string;
            happened_at: string;
        }>;
        summary: {
            total: number;
            students: number;
            coaches: number;
        };
    };
    action_center: {
        summary: {
            open_issues: number;
            critical: number;
            due_today: number;
            pending_review: number;
        };
        groups: Array<{
            key: string;
            title: string;
            description: string;
            count: number;
            action_label: string;
            action_url: string;
            tone: 'slate' | 'blue' | 'amber' | 'rose' | 'emerald';
            items: Array<{
                id: string;
                title: string;
                subtitle: string;
                meta?: string | null;
                urgency: 'critical' | 'high' | 'medium';
                action_label: string;
                action_url: string;
            }>;
        }>;
        recent_activity: {
            items: Array<{
                id: string;
                actor_name: string;
                actor_role: string;
                description: string;
                happened_at: string;
            }>;
            summary: {
                total: number;
                students: number;
                coaches: number;
            };
        };
        high_priority_count: number;
        source_count: number;
    };
};

const hasDefaultSlot = computed(() => Boolean(slots.default));
const currentPath = computed(() => {
    const raw = String(page.url || '');
    const base = raw.split('#')[0].split('?')[0];
    return base || '/';
});
const dashboard = computed(() => (page.props.dashboard as DashboardPayload | undefined) ?? null);
const mobileNavOpen = ref(false);
const sidebarCollapsed = ref(false);
const notificationsOpen = ref(false);
const notificationsCloseTimer = ref<number | null>(null);
const reportsHoverCloseTimer = ref<number | null>(null);
const reportsTriggerRef = ref<HTMLElement | null>(null);
const reportsHoverStyle = ref<Record<string, string>>({});

const adminNotifications = ref<
    Array<{
        id: number;
        title: string;
        message: string;
        type: string;
        is_read: boolean;
        published_at: string | null;
    }>
>([]);
const bellProcessingIds = ref<number[]>([]);

watch(
    () => (page.props.auth as any)?.admin_notifications?.recent,
    (items) => {
        adminNotifications.value = Array.isArray(items) ? items.map((item: any) => ({ ...item })) : [];
    },
    { immediate: true },
);

const bellUnreadCount = computed(() => {
    if (adminNotifications.value.length) {
        return adminNotifications.value.filter((item) => !item.is_read).length;
    }
    return notificationsCount.value;
});

const notificationsCount = computed(() => {
    const unread = (page.props.auth as any)?.announcements?.unread_count;
    if (typeof unread === 'number') return unread;
    const fallback = (page.props.auth as any)?.admin_notifications?.items;
    if (Array.isArray(fallback)) {
        return fallback.reduce((sum, item) => sum + Number(item.count || 0), 0);
    }
    return 0;
});

function logout() {
    router.post('/logout');
}

type NavEntry = {
    name: string;
    iconPaths: string[];
    route?: string;
    children?: Array<{
        name: string;
        route: string;
    }>;
};

const pages: NavEntry[] = [
    { name: 'Dashboard', route: '/AdminDashboard', iconPaths: ['M3 13h8V3H3z', 'M13 21h8v-6h-8z', 'M13 11h8V3h-8z', 'M3 21h8v-6H3z'] },
    {
        name: 'People',
        route: '/people',
        iconPaths: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 8v6', 'M23 11h-6'],
    },
    {
        name: 'Teams',
        route: '/teams',
        iconPaths: [
            'M17 21v-2a4 4 0 0 0-3-3.87',
            'M7 21v-2a4 4 0 0 1 3-3.87',
            'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
            'M5 8H4a2 2 0 0 0-2 2v2',
            'M19 8h1a2 2 0 0 1 2 2v2',
        ],
    },
    { name: 'Operations', route: '/operations', iconPaths: ['M3 3v18h18', 'M7 13l3-3 3 2 5-6'] },
    { name: 'Health & Clearance', route: '/health', iconPaths: ['M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z', 'M9 12l2 2 4-4'] },
    {
        name: 'Academics',
        route: '/academics',
        iconPaths: ['M4 19.5V6a2 2 0 0 1 2-2h9l5 5v10.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z', 'M14 4v5h5', 'M8 13h8', 'M8 17h5'],
    },
    {
        name: 'Audit Trail',
        route: '/audit-trail',
        iconPaths: ['M12 8v5l3 2', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'M12 3v2', 'M12 19v2'],
    },
    {
        name: 'Reports',
        iconPaths: ['M4 19h16', 'M7 16V8', 'M12 16V5', 'M17 16v-3'],
        children: [
            { name: 'Attendance', route: '/reports/attendance' },
            { name: 'Roster', route: '/reports/roster' },
            { name: 'Academics', route: '/reports/academics' },
            { name: 'Health', route: '/reports/health' },
        ],
    },
];

const footerLinks = [
    { label: 'Dashboard', href: '/AdminDashboard' },
    { label: 'People', href: '/people' },
    { label: 'Teams', href: '/teams' },
    { label: 'Operations', href: '/operations' },
    { label: 'Health', href: '/health' },
    { label: 'Academics', href: '/academics' },
    { label: 'Audit Trail', href: '/audit-trail' },
    { label: 'Reports', href: '/reports/attendance' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
];

const currentPageName = computed(() => {
    if (currentPath.value === '/people/queue' || currentPath.value.startsWith('/people/queue/')) {
        return 'People';
    }
    if (currentPath.value === '/operations/attendance' || currentPath.value.startsWith('/operations/attendance/')) {
        return 'Operations';
    }
    if (currentPath.value === '/health/wellness' || currentPath.value.startsWith('/health/wellness/')) {
        return 'Health & Clearance';
    }
    if (currentPath.value === '/account/profile' || currentPath.value.startsWith('/account/profile/')) {
        return 'Profile';
    }
    if (currentPath.value === '/account/settings' || currentPath.value.startsWith('/account/settings/')) {
        return 'Settings';
    }
    if (currentPath.value === '/account/account-settings' || currentPath.value.startsWith('/account/account-settings/')) {
        return 'Account Settings';
    }
    if (currentPath.value === '/account/notifications' || currentPath.value.startsWith('/account/notifications/')) {
        return 'Notifications';
    }
    if (currentPath.value === '/account/preferences' || currentPath.value.startsWith('/account/preferences/')) {
        return 'Preferences';
    }
    if (currentPath.value === '/account/help' || currentPath.value.startsWith('/account/help/')) {
        return 'Help & Support';
    }
    if (currentPath.value === '/announcements' || currentPath.value.startsWith('/announcements/')) {
        return 'Announcements';
    }

    const match = pages.find((item) => {
        if (item.route) {
            return currentPath.value === item.route || currentPath.value.startsWith(`${item.route}/`);
        }

        return item.children?.some((child) => currentPath.value === child.route || currentPath.value.startsWith(`${child.route}/`)) ?? false;
    });
    return match?.name ?? 'Dashboard';
});

const isSettingsRoute = computed(() => {
    return (
        currentPath.value === '/account/settings' ||
        currentPath.value.startsWith('/account/settings/') ||
        currentPath.value === '/account/account-settings' ||
        currentPath.value.startsWith('/account/account-settings/') ||
        currentPath.value === '/account/notifications' ||
        currentPath.value.startsWith('/account/notifications/') ||
        currentPath.value === '/account/preferences' ||
        currentPath.value.startsWith('/account/preferences/')
    );
});

const isHelpRoute = computed(() => {
    return (
        currentPath.value === '/account/help' ||
        currentPath.value.startsWith('/account/help/') ||
        currentPath.value === '/contact' ||
        currentPath.value.startsWith('/contact/')
    );
});

const selectedPeriod = computed(() => dashboard.value?.filters.period ?? 'week');
const actionCenter = computed(() => dashboard.value?.action_center ?? null);
const reportsExpanded = ref(false);
const reportsHoverOpen = ref(false);

const attendanceMax = computed(() => {
    const values = dashboard.value
        ? [
              ...dashboard.value.trends.attendance.present,
              ...dashboard.value.trends.attendance.late,
              ...dashboard.value.trends.attendance.absent,
              ...dashboard.value.trends.attendance.no_response,
          ]
        : [0];

    return Math.max(1, ...values);
});

const weeklySummary = computed(() => {
    if (!dashboard.value) return [];

    const labels = dashboard.value.trends.labels ?? [];
    return labels.map((label, index) => {
        const onTime = dashboard.value?.trends.attendance.present[index] ?? 0;
        const late = dashboard.value?.trends.attendance.late[index] ?? 0;
        const noResponse = dashboard.value?.trends.attendance.no_response[index] ?? 0;
        const total = onTime + late + noResponse;

        return {
            label,
            onTime,
            late,
            noResponse,
            total,
        };
    });
});

const weeklyMax = computed(() => {
    const values = weeklySummary.value.map((item) => item.total);
    return Math.max(1, ...values);
});

function goTo(route: string) {
    mobileNavOpen.value = false;
    router.get(route);
}

function goToNavTarget(route: string) {
    mobileNavOpen.value = false;
    reportsHoverOpen.value = false;
    router.get(route);
}

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`);
}

function isChildActive(route: string): boolean {
    return isActive(route);
}

function isEntryActive(entry: NavEntry): boolean {
    if (entry.route) {
        return isActive(entry.route);
    }

    if (entry.children?.some((child) => isActive(child.route))) {
        return true;
    }

    return entry.children?.some((child) => isChildActive(child.route)) ?? false;
}

function toggleReportsNav() {
    reportsExpanded.value = !reportsExpanded.value;
}

function setReportsTriggerRef(el: Element | null) {
    reportsTriggerRef.value = el instanceof HTMLElement ? el : null;
}

function updateReportsHoverPosition() {
    if (!reportsTriggerRef.value) return;

    const rect = reportsTriggerRef.value.getBoundingClientRect();
    const menuWidth = 224;
    const menuHeight = 220;
    const viewportPadding = 12;
    const idealTop = rect.top + rect.height / 2 - menuHeight / 2;
    const maxTop = window.innerHeight - menuHeight - viewportPadding;
    const clampedTop = Math.min(Math.max(viewportPadding, idealTop), Math.max(viewportPadding, maxTop));
    const maxLeft = window.innerWidth - menuWidth - viewportPadding;
    const clampedLeft = Math.min(rect.right + 8, Math.max(viewportPadding, maxLeft));

    reportsHoverStyle.value = {
        position: 'fixed',
        top: `${clampedTop}px`,
        left: `${clampedLeft}px`,
    };
}

function clearReportsHoverCloseTimer() {
    if (reportsHoverCloseTimer.value) {
        window.clearTimeout(reportsHoverCloseTimer.value);
        reportsHoverCloseTimer.value = null;
    }
}

function openReportsHoverMenu() {
    if (sidebarCollapsed.value && !mobileNavOpen.value) {
        clearReportsHoverCloseTimer();
        updateReportsHoverPosition();
        reportsHoverOpen.value = true;
    }
}

function closeReportsHoverMenu() {
    clearReportsHoverCloseTimer();
    reportsHoverOpen.value = false;
}

function scheduleReportsHoverClose() {
    if (!sidebarCollapsed.value || mobileNavOpen.value) {
        closeReportsHoverMenu();
        return;
    }

    clearReportsHoverCloseTimer();
    reportsHoverCloseTimer.value = window.setTimeout(() => {
        reportsHoverOpen.value = false;
        reportsHoverCloseTimer.value = null;
    }, 140);
}

function handleReportsEntryClick() {
    if (sidebarCollapsed.value && !mobileNavOpen.value) {
        updateReportsHoverPosition();
        reportsHoverOpen.value = !reportsHoverOpen.value;
        return;
    }

    toggleReportsNav();
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem(SIDEBAR_PREF_KEY, sidebarCollapsed.value ? '1' : '0');

    if (!sidebarCollapsed.value) {
        closeReportsHoverMenu();
        return;
    }

    updateReportsHoverPosition();
}

function closeMobileNav() {
    mobileNavOpen.value = false;
    closeReportsHoverMenu();
}

function openNotifications() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value);
        notificationsCloseTimer.value = null;
    }
    notificationsOpen.value = true;
}

function scheduleNotificationsClose() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value);
    }
    notificationsCloseTimer.value = window.setTimeout(() => {
        notificationsOpen.value = false;
        notificationsCloseTimer.value = null;
    }, 180);
}

function isBellProcessing(id: number) {
    return bellProcessingIds.value.includes(id);
}

function markBellRead(item: { id: number; is_read: boolean }) {
    if (item.is_read || isBellProcessing(item.id)) return;
    const previous = item.is_read;
    item.is_read = true;
    bellProcessingIds.value = [...bellProcessingIds.value, item.id];

    router.put(
        `/announcements/${item.id}/read`,
        {},
        {
            preserveScroll: true,
            onError: () => {
                item.is_read = previous;
            },
            onFinish: () => {
                bellProcessingIds.value = bellProcessingIds.value.filter((id) => id !== item.id);
            },
        },
    );
}

function onEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeMobileNav();
        closeReportsHoverMenu();
    }
}

function setDashboardPeriod(period: 'today' | 'week' | 'month') {
    if (hasDefaultSlot.value) return;
    router.get(
        '/AdminDashboard',
        { period },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function buildPolyline(values: number[]) {
    if (values.length === 0) return '';

    const width = 620;
    const height = 210;
    const pad = 16;
    const innerWidth = width - pad * 2;
    const innerHeight = height - pad * 2;
    const step = values.length > 1 ? innerWidth / (values.length - 1) : 0;

    return values
        .map((value, index) => {
            const x = pad + index * step;
            const y = pad + (innerHeight - (value / attendanceMax.value) * innerHeight);
            return `${x},${y}`;
        })
        .join(' ');
}

function healthPercent(value: number, total: number) {
    if (total <= 0) return 0;
    return Math.round((value / total) * 100);
}

function academicSegment(value: number, total: number) {
    if (total <= 0) return 0;
    return Math.max(4, Math.round((value / total) * 100));
}

function weeklyBarHeight(value: number) {
    if (value <= 0 || weeklyMax.value <= 0) return 0;
    return Math.round((value / weeklyMax.value) * 160);
}

function formatActivityTime(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function roleTone(role: string) {
    if (role === 'coach') return 'bg-slate-100 text-slate-700';
    return 'bg-emerald-100 text-emerald-700';
}

function actionGroupTone(tone: 'slate' | 'blue' | 'amber' | 'rose' | 'emerald') {
    if (tone === 'blue') return 'bg-[#034485]/10 text-[#034485] border border-[#034485]/15';
    if (tone === 'amber') return 'bg-amber-50 text-amber-700 border border-amber-200';
    if (tone === 'rose') return 'bg-rose-50 text-rose-700 border border-rose-200';
    if (tone === 'emerald') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    return 'bg-slate-100 text-slate-700 border border-slate-200';
}

function actionSummaryTone(key: 'open' | 'critical' | 'today' | 'review') {
    if (key === 'open') return 'text-[#034485]';
    if (key === 'critical') return 'text-rose-700';
    if (key === 'today') return 'text-amber-700';
    return 'text-slate-900';
}

function urgencyTone(level: 'critical' | 'high' | 'medium') {
    if (level === 'critical') return 'bg-rose-50 text-rose-700 border border-rose-200';
    if (level === 'high') return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-slate-100 text-slate-700 border border-slate-200';
}

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_PREF_KEY) === '1';
    reportsExpanded.value = currentPath.value.startsWith('/reports');
    window.addEventListener('keydown', onEscape);
    window.addEventListener('resize', updateReportsHoverPosition);
    window.addEventListener('scroll', updateReportsHoverPosition, true);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    window.removeEventListener('resize', updateReportsHoverPosition);
    window.removeEventListener('scroll', updateReportsHoverPosition, true);
    clearReportsHoverCloseTimer();
    document.body.style.overflow = '';
});

watch(mobileNavOpen, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

watch(
    () => currentPath.value,
    (value) => {
        if (value.startsWith('/reports')) {
            reportsExpanded.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="admin-shell min-h-screen bg-[#f5f7fb] text-slate-900">
        <div class="bg-[radial-gradient(circle_at_top_right,rgba(3,68,133,0.10),transparent_42%)] pointer-events-none fixed inset-0 -z-10" />

        <div v-if="mobileNavOpen" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="closeMobileNav" />

        <aside
            class="fixed left-0 z-30 border-r border-[#bfd4eb]/90 bg-[#eaf3ff]/95 backdrop-blur transition-[transform,width] duration-300 ease-out will-change-[transform,width]"
            :class="[
                mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
                'top-18 h-[calc(100vh-72px)]',
                sidebarCollapsed ? 'w-70 max-w-[85vw] lg:w-22' : 'w-70 max-w-[85vw] lg:w-70',
                'lg:translate-x-0',
            ]"
        >
            <div class="flex h-full flex-col">
                <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-visible px-3 py-4">
                    <div
                        v-for="entry in pages"
                        :key="entry.name"
                        class="relative space-y-1"
                        @mouseenter="entry.children ? openReportsHoverMenu() : undefined"
                        @mouseleave="entry.children ? scheduleReportsHoverClose() : undefined"
                        @focusin="entry.children ? openReportsHoverMenu() : undefined"
                        @focusout="entry.children ? scheduleReportsHoverClose() : undefined"
                    >
                        <button
                            type="button"
                            :ref="entry.children ? setReportsTriggerRef : undefined"
                            @click="entry.children ? handleReportsEntryClick() : goTo(entry.route!)"
                            class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color,transform] duration-200 ease-out"
                            :class="[
                                isEntryActive(entry)
                                    ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                    : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                                sidebarCollapsed && !mobileNavOpen ? 'justify-center px-2' : '',
                            ]"
                            :title="sidebarCollapsed ? entry.name : ''"
                            :aria-expanded="entry.children ? (sidebarCollapsed && !mobileNavOpen ? reportsHoverOpen : reportsExpanded) : undefined"
                            :aria-haspopup="entry.children ? 'menu' : undefined"
                        >
                            <svg
                                class="h-4.5 w-4.5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    v-for="(path, idx) in entry.iconPaths"
                                    :key="`${entry.name}-icon-${idx}`"
                                    :d="path"
                                />
                            </svg>
                            <span
                                class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                                :class="
                                    sidebarCollapsed
                                        ? 'ml-0 max-w-45 scale-100 opacity-100 lg:max-w-0 lg:scale-95 lg:overflow-hidden lg:opacity-0'
                                        : 'ml-2 max-w-45 scale-100 opacity-100'
                                "
                            >
                                {{ entry.name }}
                            </span>
                            <svg
                                v-if="entry.children && (!sidebarCollapsed || mobileNavOpen)"
                                class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                                :class="reportsExpanded ? 'rotate-180' : ''"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <div
                            v-if="entry.children && reportsExpanded && (!sidebarCollapsed || mobileNavOpen)"
                            class="space-y-1 pl-4"
                        >
                            <button
                                v-for="child in entry.children"
                                :key="`${entry.name}-${child.name}`"
                                type="button"
                                @click="goToNavTarget(child.route)"
                                class="flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                                :class="[
                                    isChildActive(child.route)
                                        ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                : 'border-transparent text-slate-600 hover:border-[#bfd4eb] hover:bg-white/75 hover:text-slate-900',
                                ]"
                            >
                                <span class="truncate">{{ child.name }}</span>
                            </button>
                        </div>

                        <div
                            v-if="entry.children && reportsHoverOpen && sidebarCollapsed && !mobileNavOpen"
                            :style="reportsHoverStyle"
                            class="z-[60] w-56 overflow-hidden rounded-xl border border-[#bfd4eb] bg-[#f7fbff] shadow-[0_24px_60px_-24px_rgba(15,23,42,0.45)]"
                            @mouseenter="openReportsHoverMenu"
                            @mouseleave="scheduleReportsHoverClose"
                        >
                            <div class="border-b border-[#d6e4f4] px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Reports</p>
                            </div>
                            <div class="space-y-1 p-2">
                                <button
                                    v-for="child in entry.children"
                                    :key="`${entry.name}-hover-${child.name}`"
                                    type="button"
                                    @click="goToNavTarget(child.route)"
                                    role="menuitem"
                                    class="flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                                    :class="[
                                        isChildActive(child.route)
                                            ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                            : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/80',
                                    ]"
                                >
                                    <span class="truncate">{{ child.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="border-t border-[#d6e4f4] px-3 py-3">
                    <button
                        type="button"
                        class="group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isSettingsRoute
                                ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                            sidebarCollapsed && !mobileNavOpen ? 'justify-center' : '',
                        ]"
                        @click="goTo('/account/settings')"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.05.05a2 2 0 1 1-2.83 2.83l-.05-.05A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.2a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-.4-1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.05.05a2 2 0 1 1-2.83-2.83l.05-.05A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4H2.8a2 2 0 1 1 0-4H3a1.7 1.7 0 0 0 1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.05-.05A2 2 0 1 1 7.04 3.8l.05.05A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1V2.8a2 2 0 1 1 4 0V3a1.7 1.7 0 0 0 .4 1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.05-.05A2 2 0 1 1 20.2 7.04l-.05.05A1.7 1.7 0 0 0 19.4 9c0 .4.2.77.6 1 .3.2.64.35 1 .4h.2a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1 .4 1.7 1.7 0 0 0-.6 1z"
                            />
                        </svg>
                        <span v-if="!sidebarCollapsed || mobileNavOpen" class="ml-2">Settings</span>
                    </button>
                    <button
                        type="button"
                        class="group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isHelpRoute
                                ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                            sidebarCollapsed && !mobileNavOpen ? 'justify-center' : '',
                        ]"
                        @click="goTo('/account/help')"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 17v.01" />
                            <path d="M12 13a2 2 0 1 0-2-2" />
                        </svg>
                        <span v-if="!sidebarCollapsed || mobileNavOpen" class="ml-2">Help &amp; Support</span>
                    </button>
                    <button
                        type="button"
                        class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium text-rose-600 transition-all duration-200 hover:border-rose-200 hover:bg-rose-50"
                        :class="sidebarCollapsed && !mobileNavOpen ? 'justify-center' : ''"
                        @click="logout"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span v-if="!sidebarCollapsed || mobileNavOpen" class="ml-2">Logout</span>
                    </button>
                </div>
            </div>
        </aside>

        <header class="admin-shell__topbar fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/88 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] backdrop-blur">
            <div class="flex w-full items-center justify-between gap-3 pl-0 pr-2 py-3 sm:pl-0 sm:pr-3 lg:pl-0 lg:pr-4">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="admin-shell__nav-toggle inline-flex h-9 w-9 items-center justify-center rounded-md border lg:hidden"
                        @click="mobileNavOpen = true"
                        aria-label="Open admin navigation"
                    >
                        <span class="space-y-1">
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                        </span>
                    </button>

                    <div class="min-w-0 flex items-center gap-2">
                        <div class="inline-flex max-w-full flex-col rounded-2xl bg-[#034485] px-3 py-2 shadow-[0_14px_28px_-20px_rgba(3,68,133,0.55)]">
                            <p class="admin-shell__kicker truncate text-[11px] font-semibold tracking-[0.18em] text-white uppercase">AC VMIS Admin</p>
                            <div class="flex min-w-0 items-center gap-2">
                                <h2 class="admin-shell__title truncate text-sm font-semibold text-white sm:text-base">{{ currentPageName }}</h2>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="admin-shell__nav-toggle hidden h-9 w-9 items-center justify-center rounded-md border lg:inline-flex"
                            @click="toggleSidebar"
                            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            aria-label="Toggle sidebar"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path v-if="sidebarCollapsed" d="M8 6l6 6-6 6" />
                                <path v-else d="M16 6l-6 6 6 6" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div v-if="isLoading" class="admin-shell__loading-pill inline-flex items-center" aria-label="Loading" title="Loading">
                        <Spinner class="h-3.5 w-3.5 text-[#1f2937]" />
                    </div>
                    <div
                        class="relative"
                        @mouseenter="openNotifications"
                        @mouseleave="scheduleNotificationsClose"
                        @focusin="openNotifications"
                        @focusout="scheduleNotificationsClose"
                    >
                        <button
                            type="button"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                            aria-label="Open announcements"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" />
                                <path d="M9 17a3 3 0 0 0 6 0" />
                            </svg>
                            <span
                                v-if="bellUnreadCount > 0"
                                class="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ bellUnreadCount }}
                            </span>
                        </button>

                        <div
                            v-if="notificationsOpen"
                            class="absolute right-0 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200 px-3 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                Announcements
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ bellUnreadCount }}</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <button
                                    v-for="item in adminNotifications"
                                    :key="item.id ?? item.title"
                                    type="button"
                                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition"
                                    :class="
                                        item.is_read
                                            ? 'border-b border-slate-100 text-slate-700 hover:bg-slate-50'
                                            : 'border-b border-white/10 bg-[#034485] text-white hover:bg-[#033a70]'
                                    "
                                    @click="
                                        markBellRead(item);
                                        goTo('/announcements');
                                    "
                                >
                                    <span class="mt-1 inline-flex h-2 w-2 shrink-0 rounded-full" :class="item.is_read ? 'bg-[#034485]' : 'bg-white'" />
                                    <span class="flex-1">
                                        <span class="block font-semibold" :class="item.is_read ? 'text-slate-800' : 'text-white'">{{ item.title }}</span>
                                        <span class="block text-xs" :class="item.is_read ? 'text-slate-500' : 'text-white/80'">{{ item.message }}</span>
                                    </span>
                                    <span class="ml-auto text-[10px] font-semibold" :class="item.is_read ? 'text-slate-400' : 'text-white/70'">{{ item.published_at ?? '' }}</span>
                                </button>
                                <div v-if="adminNotifications.length === 0" class="px-3 py-4 text-xs text-slate-500">No announcements right now.</div>
                            </div>
                            <div class="border-t border-slate-200 px-3 py-2">
                                <button
                                    type="button"
                                    class="w-full rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    @click="goTo('/announcements')"
                                >
                                    View all
                                </button>
                            </div>
                        </div>
                    </div>
                    <UserAccountMenu :dark="false" menu-placement="bottom" compact />
                </div>
            </div>
        </header>

        <div class="pt-18 transition-[padding] duration-300 ease-out will-change-[padding]" :class="sidebarCollapsed ? 'lg:pl-22' : 'lg:pl-70'">
            <main class="mx-auto max-w-400 px-4 py-5 sm:px-6">
                <slot v-if="hasDefaultSlot" />

                <div v-else-if="dashboard" class="space-y-5">
                    <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-[#034485]">Overview</h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="selectedPeriod === 'today' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="setDashboardPeriod('today')"
                                >
                                    Today
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="selectedPeriod === 'week' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="setDashboardPeriod('week')"
                                >
                                    This Week
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="selectedPeriod === 'month' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="setDashboardPeriod('month')"
                                >
                                    This Month
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                            <p class="text-xs text-slate-500">Attendance Rate</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ dashboard.kpis.attendance_rate }}%</p>
                        </article>
                        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs text-amber-700">No Response</p>
                            <p class="mt-1 text-2xl font-bold text-amber-900">{{ dashboard.kpis.no_response }}</p>
                        </article>
                        <article class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                            <p class="text-xs text-rose-700">Expired Clearances</p>
                            <p class="mt-1 text-2xl font-bold text-rose-900">{{ dashboard.kpis.expired_clearances }}</p>
                        </article>
                        <article class="rounded-xl border border-orange-200 bg-orange-50 p-4">
                            <p class="text-xs text-orange-700">Academic At Risk</p>
                            <p class="mt-1 text-2xl font-bold text-orange-900">{{ dashboard.kpis.academic_at_risk }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs text-slate-700">Pending Approvals</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ dashboard.kpis.pending_approvals }}</p>
                        </article>
                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4 xl:col-span-2">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-800">Attendance Trend</h4>
                                <div class="flex gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 text-emerald-700"
                                        ><span class="h-2 w-2 rounded-full bg-emerald-500" />Present</span
                                    >
                                    <span class="inline-flex items-center gap-1 text-amber-700"
                                        ><span class="h-2 w-2 rounded-full bg-amber-500" />Late</span
                                    >
                                    <span class="inline-flex items-center gap-1 text-red-700"
                                        ><span class="h-2 w-2 rounded-full bg-red-500" />Absent</span
                                    >
                                    <span class="inline-flex items-center gap-1 text-slate-700"
                                        ><span class="h-2 w-2 rounded-full bg-slate-500" />No Response</span
                                    >
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 620 210" class="h-55 w-full min-w-155 rounded-lg bg-slate-50">
                                    <polyline
                                        :points="buildPolyline(dashboard.trends.attendance.present)"
                                        fill="none"
                                        stroke="#10b981"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />
                                    <polyline
                                        :points="buildPolyline(dashboard.trends.attendance.late)"
                                        fill="none"
                                        stroke="#f59e0b"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />
                                    <polyline
                                        :points="buildPolyline(dashboard.trends.attendance.absent)"
                                        fill="none"
                                        stroke="#ef4444"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />
                                    <polyline
                                        :points="buildPolyline(dashboard.trends.attendance.no_response)"
                                        fill="none"
                                        stroke="#64748b"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-1 text-[10px] text-slate-500 sm:grid-cols-8">
                                <span v-for="label in dashboard.trends.labels" :key="label" class="truncate">{{ label }}</span>
                            </div>
                        </article>

                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Academic Status by Team</h4>
                            <div v-if="dashboard.trends.academic_by_team.length === 0" class="text-sm text-slate-500">
                                No evaluations found for current period.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="team in dashboard.trends.academic_by_team"
                                    :key="team.team_name"
                                    class="rounded-lg border border-slate-100 p-2"
                                >
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <span class="font-medium text-slate-700">{{ team.team_name }}</span>
                                        <span class="text-slate-500">{{ team.total }} athletes</span>
                                    </div>
                                    <div class="flex h-2 overflow-hidden rounded-full bg-slate-100">
                                        <span class="bg-emerald-500" :style="{ width: `${academicSegment(team.eligible, team.total)}%` }" />
                                        <span class="bg-amber-500" :style="{ width: `${academicSegment(team.pending_review, team.total)}%` }" />
                                        <span class="bg-red-500" :style="{ width: `${academicSegment(team.ineligible, team.total)}%` }" />
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-800">Weekly Response Summary</h4>
                                <div class="flex gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 text-emerald-700"
                                        ><span class="h-2 w-2 rounded-full bg-emerald-500" />On-time</span
                                    >
                                    <span class="inline-flex items-center gap-1 text-amber-700"
                                        ><span class="h-2 w-2 rounded-full bg-amber-500" />Late</span
                                    >
                                    <span class="inline-flex items-center gap-1 text-slate-700"
                                        ><span class="h-2 w-2 rounded-full bg-slate-500" />No Response</span
                                    >
                                </div>
                            </div>
                            <div v-if="weeklySummary.length === 0" class="text-sm text-slate-500">
                                No attendance response data found for this period.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <div class="min-w-140">
                                    <div class="flex h-44 items-end gap-3">
                                        <div v-for="item in weeklySummary" :key="item.label" class="flex min-w-9 flex-1 flex-col items-stretch">
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
                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Today's Schedules</h4>
                            <div v-if="dashboard.queues.today_schedules.length === 0" class="text-sm text-slate-500">No schedules for today.</div>
                            <div v-else class="space-y-2">
                                <div v-for="item in dashboard.queues.today_schedules" :key="item.id" class="rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                            <p class="text-xs text-slate-500">{{ item.team_name }} • {{ item.start_time }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100"
                                            @click="goTo('/operations?tab=calendar')"
                                        >
                                            Open
                                        </button>
                                    </div>
                                    <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                        <p class="rounded bg-amber-50 px-2 py-1 text-amber-700">Late: {{ item.late }}</p>
                                        <p class="rounded bg-rose-50 px-2 py-1 text-rose-700">Absent: {{ item.absent }}</p>
                                        <p class="rounded bg-slate-100 px-2 py-1 text-slate-700">No Response: {{ item.no_response }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                            <h4 class="mb-3 text-sm font-semibold text-slate-800">Needs Attention Queue</h4>
                            <div v-if="dashboard.queues.needs_attention.length === 0" class="text-sm text-slate-500">
                                No priority items right now.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="(item, idx) in dashboard.queues.needs_attention"
                                    :key="`${item.type}-${idx}`"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 p-2"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ item.title }}</p>
                                        <p class="text-xs text-slate-500">{{ item.subtitle }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#1f2937] px-2.5 py-1 text-xs font-semibold text-white hover:bg-[#334155]"
                                        @click="goTo(item.action_url)"
                                    >
                                        {{ item.action_label }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="rounded-xl border border-[#034485]/45 bg-white p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-800">Action Needed</h4>
                                <p class="text-xs text-slate-500">Priority issues that require admin follow-up across varsity operations.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full border border-[#034485]/20 bg-[#034485]/5 px-3 py-1 text-[11px] font-semibold text-[#034485]">
                                    {{ actionCenter?.summary.open_issues ?? 0 }} open
                                </span>
                                <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-semibold text-rose-700">
                                    {{ actionCenter?.high_priority_count ?? 0 }} high priority
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500">Open Issues</p>
                                <p class="mt-2 text-2xl font-bold" :class="actionSummaryTone('open')">{{ actionCenter?.summary.open_issues ?? 0 }}</p>
                            </article>
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500">Critical</p>
                                <p class="mt-2 text-2xl font-bold" :class="actionSummaryTone('critical')">{{ actionCenter?.summary.critical ?? 0 }}</p>
                            </article>
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500">Due Today</p>
                                <p class="mt-2 text-2xl font-bold" :class="actionSummaryTone('today')">{{ actionCenter?.summary.due_today ?? 0 }}</p>
                            </article>
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500">Pending Review</p>
                                <p class="mt-2 text-2xl font-bold" :class="actionSummaryTone('review')">{{ actionCenter?.summary.pending_review ?? 0 }}</p>
                            </article>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.5fr_0.95fr]">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <article
                                    v-for="group in actionCenter?.groups ?? []"
                                    :key="group.key"
                                    class="rounded-xl border border-slate-200 bg-white p-4"
                                >
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h5 class="text-sm font-semibold text-slate-900">{{ group.title }}</h5>
                                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="actionGroupTone(group.tone)">
                                                    {{ group.count }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ group.description }}</p>
                                        </div>
                                    </div>

                                    <div v-if="group.items.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">
                                        No items in this queue right now.
                                    </div>

                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="item in group.items"
                                            :key="item.id"
                                            class="rounded-lg border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-sm font-medium text-slate-900">{{ item.title }}</p>
                                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]" :class="urgencyTone(item.urgency)">
                                                            {{ item.urgency }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ item.subtitle }}</p>
                                                    <p v-if="item.meta" class="mt-1 text-[11px] font-medium text-slate-400">{{ item.meta }}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded-md bg-[#1f2937] px-2.5 py-1 text-xs font-semibold text-white hover:bg-[#334155]"
                                                    @click="goTo(item.action_url)"
                                                >
                                                    {{ item.action_label }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex justify-end">
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                            @click="goTo(group.action_url)"
                                        >
                                            {{ group.action_label }}
                                        </button>
                                    </div>
                                </article>
                            </div>

                            <aside class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <div class="mb-3">
                                    <h5 class="text-sm font-semibold text-slate-800">Recent Activity</h5>
                                    <p class="text-xs text-slate-500">
                                        {{ actionCenter?.recent_activity.summary.total ?? 0 }} recent actions • Students
                                        {{ actionCenter?.recent_activity.summary.students ?? 0 }} • Coaches
                                        {{ actionCenter?.recent_activity.summary.coaches ?? 0 }}
                                    </p>
                                </div>

                                <div v-if="(actionCenter?.recent_activity.items ?? []).length === 0" class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-4 text-sm text-slate-500">
                                    No recent student or coach activity found.
                                </div>

                                <div v-else class="space-y-1">
                                    <div
                                        v-for="item in actionCenter?.recent_activity.items ?? []"
                                        :key="item.id"
                                        class="border-b border-slate-200/80 py-3 last:border-b-0"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-medium text-slate-900">{{ item.actor_name }}</p>
                                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize" :class="roleTone(item.actor_role)">
                                                        {{ item.actor_role.replace('-', ' ') }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ item.description }}</p>
                                            </div>
                                            <span class="shrink-0 text-[11px] font-medium text-slate-400">{{ formatActivityTime(item.happened_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </section>
                </div>

                <div v-else class="space-y-5">
                    <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
                        <h3 class="text-xl font-bold text-[#1f2937]">Dashboard</h3>
                        <p class="mt-1 text-sm text-slate-600">No dashboard data available.</p>
                    </section>
                </div>
            </main>

            <RoleFooter
                title="AC VMIS"
                description="Manage varsity operations, health, and academic workflows from one dashboard."
                :links="footerLinks"
            />
        </div>
    </div>
</template>
