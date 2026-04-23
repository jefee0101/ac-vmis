<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { VueCal } from 'vue-cal'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { useSportColors } from '@/composables/useSportColors'
import { useUserTimezone } from '@/composables/useUserTimezone'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: CoachDashboard,
})

const props = defineProps<{
    schedules: any[]
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
}>()

// Layout mode
const layout = ref<'list' | 'calendar'>('list')
const calendarContainer = ref<HTMLElement | null>(null)

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    }
)

const selectedTeam = computed(() => props.teams.find(team => team.id === selectedTeamId.value) ?? null)

// Modal state
const showModal = ref(false)
const editingId = ref<number | null>(null)
const modalMode = ref<'view' | 'form'>('form')
const selectedSchedule = ref<any | null>(null)

// VueCal drag creation resolver
const pendingResolve = ref<any>(null)

// Form state
const form = ref({
    title: '',
    type: 'Practice',
    venue: '',
    start_time: '',
    end_time: '',
    notes: '',
})
const canManage = computed(() => selectedTeamId.value !== null)
const ownerSchedules = computed(() => props.schedules.filter((item: any) => item.is_owner))
const { sportColor, sportTextColor } = useSportColors()
const { timezone } = useUserTimezone()
const deleteDialogOpen = ref(false)
const pendingDeleteId = ref<number | null>(null)

function tintHex(hex: string, amount: number) {
    const clean = hex.replace('#', '')
    if (clean.length !== 6) return hex
    const r = parseInt(clean.slice(0, 2), 16)
    const g = parseInt(clean.slice(2, 4), 16)
    const b = parseInt(clean.slice(4, 6), 16)
    const mix = (channel: number) => Math.round(channel + (255 - channel) * amount)
    return `#${mix(r).toString(16).padStart(2, '0')}${mix(g).toString(16).padStart(2, '0')}${mix(b).toString(16).padStart(2, '0')}`
}

function stripeColors(sport: any) {
    const base = sportColor(sport)
    const lighter = tintHex(base, 0.55)
    return { base, lighter }
}

let dragPlaceholderObserver: MutationObserver | null = null

function toLocalInput(dt: string | null) {
    if (!dt) return ''
    return dt.replace(' ', 'T').slice(0, 16)
}

/**
 * Calendar events
 */
const visibleSchedules = computed(() =>
    ownerSchedules.value.filter((item: any) => scheduleStatus(item) !== 'completed')
)

const calendarEvents = computed(() =>
    visibleSchedules.value
        .filter(i => i.start && i.end)
        .map((item: any) => ({
            id: item.id,
            title: item.title,
            start: new Date(item.start),
            end: new Date(item.end),
            backgroundColor: sportColor(item.sport),
            color: sportTextColor(item.sport),
            is_locked: item.is_locked,
            draggable: item.is_owner && !item.is_locked,
            resizable: item.is_owner && !item.is_locked,
        }))
)

function formatPHT(dt: string | Date | null, withDate = true) {
    if (!dt) return ''

    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleString('en-PH', {
        timeZone: timezone,
        month: withDate ? 'short' : undefined,
        day: withDate ? 'numeric' : undefined,
        year: withDate ? 'numeric' : undefined,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}
function formatPHTime(dt: string | Date | null) {
    if (!dt) return ''
    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleTimeString('en-PH', {
        timeZone: timezone,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function to12HourTime(hhmm: string) {
    const [hoursRaw, minutes] = hhmm.split(':')
    const hours = Number(hoursRaw)
    if (!Number.isFinite(hours) || !minutes) return hhmm

    const meridiem = hours >= 12 ? 'PM' : 'AM'
    const hour12 = hours % 12 || 12

    return `${hour12}:${minutes} ${meridiem}`
}

function toPHDragPlaceholder(text: string) {
    const match = text.trim().match(/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/)
    if (!match) return text

    return `${to12HourTime(match[1])} - ${to12HourTime(match[2])}`
}

function normalizeDragPlaceholders() {
    const root = calendarContainer.value
    if (!root) return

    root.querySelectorAll('.vuecal__event-placeholder').forEach((node) => {
        const el = node as HTMLElement
        const raw = el.textContent?.trim()
        if (!raw) return

        const formatted = toPHDragPlaceholder(raw)
        if (formatted !== raw) el.textContent = formatted
    })
}

function startDragPlaceholderObserver() {
    stopDragPlaceholderObserver()

    const root = calendarContainer.value
    if (!root) return

    normalizeDragPlaceholders()
    dragPlaceholderObserver = new MutationObserver(() => normalizeDragPlaceholders())
    dragPlaceholderObserver.observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
    })
}
function stopDragPlaceholderObserver() {
    if (dragPlaceholderObserver) {
        dragPlaceholderObserver.disconnect()
        dragPlaceholderObserver = null
    }
}

/**
 * ===== DRAG CREATE FROM CALENDAR =====
 */
function onCalendarCreate({ event, resolve }: any) {
    if (!canManage.value) {
        resolve(false)
        return
    }

    // ❗ Cancel VueCal temporary event immediately
    resolve(false)

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'

    form.value.start_time = toLocalInput(toMySQLLocal(event.start))
    form.value.end_time = toLocalInput(toMySQLLocal(event.end))

    showModal.value = true
}
function toMySQLLocal(dt: Date) {
    const pad = (n: number) => String(n).padStart(2, '0')

    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())} `
        + `${pad(dt.getHours())}:${pad(dt.getMinutes())}:00`
}

function deleteSchedule(id: number) {
    const s = props.schedules.find(i => i.id === id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    pendingDeleteId.value = id
    deleteDialogOpen.value = true
}

function confirmDeleteSchedule() {
    if (!pendingDeleteId.value) return
    const id = pendingDeleteId.value
    deleteDialogOpen.value = false

    router.delete(`/coach/schedules/${id}`, {
        data: selectedTeamId.value ? { team_id: selectedTeamId.value } : {},
        onSuccess: () => {
            if (selectedSchedule.value?.id === id) closeModal()
        },
    })
}

function isOwnerSchedule(id: number) {
    return !!props.schedules.find(i => i.id === id)?.is_owner
}

function scheduleStatus(item: any): 'upcoming' | 'in_progress' | 'completed' {
    if (item.status) return item.status
    const now = new Date()
    const start = new Date(item.start)
    const end = new Date(item.end)
    if (end < now) return 'completed'
    if (start <= now && end >= now) return 'in_progress'
    return 'upcoming'
}

function statusLabel(status: string) {
    return status === 'in_progress' ? 'In Progress' : status === 'completed' ? 'Completed' : 'Upcoming'
}

function statusTone(status: string) {
    if (status === 'in_progress') return 'bg-amber-100 text-amber-800'
    if (status === 'completed') return 'bg-slate-100 text-slate-700'
    return 'bg-slate-100 text-slate-700'
}

function attendanceState(item: any) {
    if (item.attendance_state) return item.attendance_state
    const attendanceCount = Number(item.attendance_count ?? 0)
    const rosterCount = Number(item.roster_count ?? 0)
    if (attendanceCount === 0) return 'not_started'
    if (rosterCount > 0 && attendanceCount >= rosterCount) return 'completed'
    return 'in_progress'
}

function attendanceLabel(item: any) {
    const attendanceCount = Number(item.attendance_count ?? 0)
    const rosterCount = Number(item.roster_count ?? 0)
    if (rosterCount === 0) return attendanceCount > 0 ? `Attendance ${attendanceCount}` : 'No roster'
    if (attendanceCount === 0) return 'No attendance'
    if (attendanceCount >= rosterCount) return `Attendance Complete ${attendanceCount}/${rosterCount}`
    return `Attendance ${attendanceCount}/${rosterCount}`
}

function attendanceTone(item: any) {
    const state = attendanceState(item)
    if (state === 'completed') return 'bg-emerald-100 text-emerald-700'
    if (state === 'in_progress') return 'bg-amber-100 text-amber-800'
    return 'bg-rose-100 text-rose-700'
}

function isLocked(item: any) {
    if (typeof item.is_locked === 'boolean') return item.is_locked
    return scheduleStatus(item) === 'completed' && Number(item.attendance_count ?? 0) > 0
}

function attendanceActionLabel(item: any) {
    const status = scheduleStatus(item)
    const hasAttendance = Number(item.attendance_count ?? 0) > 0
    if (status === 'completed') return hasAttendance ? 'View Attendance Record' : 'Record Attendance'
    return 'Record Attendance'
}

const groupedSchedules = computed(() => {
    const groups = {
        upcoming: [] as any[],
        in_progress: [] as any[],
        completed: [] as any[],
    }
    for (const item of ownerSchedules.value) {
        const status = scheduleStatus(item)
        groups[status].push(item)
    }
    const asc = (a: any, b: any) => new Date(a.start).getTime() - new Date(b.start).getTime()
    const desc = (a: any, b: any) => new Date(b.start).getTime() - new Date(a.start).getTime()
    groups.upcoming.sort(asc)
    groups.in_progress.sort(asc)
    groups.completed.sort(desc)
    return groups
})


/**
 * Drag move
 */
function onEventDrag(event: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    router.put(`/coach/schedules/${event.id}`, {
        start_time: toMySQLLocal(event.start),
        end_time: toMySQLLocal(event.end),
        team_id: selectedTeamId.value ?? undefined,
    })
}


/**
 * Resize
 */
function onEventResize(event: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    router.put(`/coach/schedules/${event.id}`, {
        start_time: toMySQLLocal(event.start),
        end_time: toMySQLLocal(event.end),
        team_id: selectedTeamId.value ?? undefined,
    })
}


/**
 * Click existing → edit
 */
function onEventClick({ event }: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s) return

    openViewSchedule(s)
}


/**
 * Click empty cell → create
 */
function onCellClick(date: any) {
    if (!canManage.value) return

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'

    const iso = toLocalInput(toMySQLLocal(date.date))
    form.value.start_time = iso
    form.value.end_time = iso

    showModal.value = true
}

/**
 * Save schedule
 */
function toMySQLFromInput(local: string) {
    if (!local) return null
    return local.replace('T', ' ') + ':00'
}

function saveSchedule() {
    if (!canManage.value) return

    const payload = {
        ...form.value,
        start_time: toMySQLFromInput(form.value.start_time),
        end_time: toMySQLFromInput(form.value.end_time),
        team_id: selectedTeamId.value ?? undefined,
    }

    if (editingId.value) {
        router.put(`/coach/schedules/${editingId.value}`, payload, {
            onSuccess: finalizeCreation,
        })
    } else {
        router.post('/coach/schedules', payload, {
            onSuccess: finalizeCreation,
        })
    }
}


/**
 * Called after successful save
 */
function finalizeCreation() {
    if (pendingResolve.value) {
        pendingResolve.value(true)
        pendingResolve.value = null
    }

    closeModal()
}

function openCreateModal() {
    if (!canManage.value) return

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'
    resetForm()
    showModal.value = true
}

function startEditFromSelected() {
    const s = selectedSchedule.value
    if (!s || !s.is_owner) return
    if (isLocked(s)) return

    editingId.value = s.id
    modalMode.value = 'form'

    form.value = {
        title: s.title,
        type: s.type,
        venue: s.venue,
        start_time: toLocalInput(toMySQLLocal(new Date(s.start))),
        end_time: toLocalInput(toMySQLLocal(new Date(s.end))),
        notes: s.notes ?? '',
    }
}

function openEditSchedule(item: any) {
    if (!item?.is_owner) return
    if (isLocked(item)) return

    selectedSchedule.value = item
    startEditFromSelected()
    showModal.value = true
}

function openViewSchedule(item: any) {
    selectedSchedule.value = item
    modalMode.value = 'view'
    showModal.value = true
}

function openAttendance(item: any) {
    const returnTo = typeof window === 'undefined'
        ? '/coach/schedule'
        : `${window.location.pathname}${window.location.search}`
    router.get(`/coach/attendance/${item.id}`, { return_to: returnTo })
}

function duplicateSchedule(item: any) {
    if (!canManage.value) return

    editingId.value = null
    selectedSchedule.value = item
    modalMode.value = 'form'
    form.value = {
        title: item.title ?? '',
        type: item.type ?? 'Practice',
        venue: item.venue ?? '',
        start_time: toLocalInput(toMySQLLocal(new Date(item.start))),
        end_time: toLocalInput(toMySQLLocal(new Date(item.end))),
        notes: item.notes ?? '',
    }
    showModal.value = true
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/coach/schedule', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function printScheduleList() {
    if (!selectedTeamId.value) return
    const params = new URLSearchParams()
    params.set('team_id', String(selectedTeamId.value))
    window.open(`/coach/schedule/print?${params.toString()}`, '_blank')
}

/**
 * Cancel modal
 */
function closeModal() {
    if (pendingResolve.value) {
        pendingResolve.value(false)
        pendingResolve.value = null
    }

    showModal.value = false
    editingId.value = null
    modalMode.value = 'form'
    selectedSchedule.value = null
    resetForm()
}

function resetForm() {
    form.value = {
        title: '',
        type: 'Practice',
        venue: '',
        start_time: '',
        end_time: '',
        notes: '',
    }
}

watch(layout, async (mode) => {
    if (mode === 'calendar') {
        await nextTick()
        startDragPlaceholderObserver()
        return
    }

    stopDragPlaceholderObserver()
})

onMounted(async () => {
    if (layout.value === 'calendar') {
        await nextTick()
        startDragPlaceholderObserver()
    }
})

onBeforeUnmount(() => {
    stopDragPlaceholderObserver()
})
</script>


<template>
    <div class="space-y-4">

        <!-- Header -->
        <div class="mb-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div v-if="props.teams.length" class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                        <select
                            v-model.number="selectedTeamId"
                            @change="changeTeam"
                            class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                        >
                            <option v-for="team in props.teams" :key="team.id" :value="team.id">
                                {{ team.team_name }}
                            </option>
                        </select>
                    </div>
                    <span v-else-if="selectedTeam" class="text-slate-500">Team: {{ selectedTeam.team_name }}</span>
                </div>
            </div>

            <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2">
                <div class="view-toggle" :class="layout === 'calendar' ? 'is-calendar' : 'is-list'">
                    <span class="view-toggle__indicator" :style="layout === 'calendar' ? { transform: 'translateX(100%)' } : { transform: 'translateX(0%)' }" />
                    <button
                        type="button"
                        class="view-toggle__btn"
                        :class="layout === 'list' ? 'is-active' : ''"
                        :aria-pressed="layout === 'list'"
                        @click="layout = 'list'"
                    >
                        List View
                    </button>
                    <button
                        type="button"
                        class="view-toggle__btn"
                        :class="layout === 'calendar' ? 'is-active' : ''"
                        :aria-pressed="layout === 'calendar'"
                        @click="layout = 'calendar'"
                    >
                        Calendar View
                    </button>
                </div>

                <button
                    type="button"
                    @click="printScheduleList"
                    :disabled="!selectedTeamId"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 sm:w-auto"
                >
                    Print
                </button>

                <button @click="openCreateModal" :disabled="!canManage"
                    class="w-full rounded-lg bg-[#1f2937] px-4 py-2 text-sm font-medium text-white hover:bg-[#111827] sm:w-auto">
                    + Create Schedule
                </button>
            </div>
        </div>

        <div v-if="!props.teams.length" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
            You are not assigned to a team yet.
        </div>

        <transition name="view-slide" mode="out-in">
            <div v-if="layout === 'list' && props.teams.length" key="list" class="space-y-6">
                <div v-if="ownerSchedules.length === 0" class="rounded-xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-500">
                    No schedules have been created yet.
                </div>

                <div v-else class="space-y-6">
                    <div v-for="(items, key) in groupedSchedules" :key="key" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">{{ statusLabel(key) }}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ items.length }}</span>
                            </div>
                            <span v-if="key === 'completed'" class="text-xs text-slate-500">Past schedules</span>
                        </div>

                        <div
                            v-if="items.length === 0"
                            class="rounded-xl border border-dashed px-4 py-6 text-sm"
                            :class="(key === 'upcoming' || key === 'in_progress')
                                ? 'border-[#034485]/40 text-[#034485]'
                                : 'border-slate-200 bg-white text-slate-500'"
                            :style="(key === 'upcoming' || key === 'in_progress')
                                ? {
                                    backgroundColor: '#f6f9ff',
                                    backgroundImage: 'repeating-linear-gradient(135deg, rgba(3, 68, 133, 0.12) 0 10px, rgba(255, 255, 255, 0) 10px 20px)',
                                  }
                                : {}"
                        >
                            No {{ statusLabel(key).toLowerCase() }} schedules.
                        </div>

                        <article v-for="item in items" :key="item.id" class="relative overflow-hidden rounded-3xl border border-[#034485]/40 bg-white p-4">
                            <div class="pointer-events-none absolute left-1/2 top-1/2 flex h-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-6 gap-1 opacity-60">
                                <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-base font-semibold text-slate-900">{{ item.title }}</div>
                                    <div class="text-xs text-slate-500">{{ item.type || '-' }} • {{ item.venue || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ formatPHT(item.start) }} → {{ formatPHT(item.end) }}</div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusTone(scheduleStatus(item))">
                                        {{ statusLabel(scheduleStatus(item)) }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="attendanceTone(item)">
                                        {{ attendanceLabel(item) }}
                                    </span>
                                    <span v-if="scheduleStatus(item) === 'completed' && Number(item.attendance_count ?? 0) === 0"
                                        class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                        Attendance required
                                    </span>
                                    <span v-if="isLocked(item)" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                        Locked
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    @click="openAttendance(item)"
                                    class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#111827]"
                                >
                                    {{ attendanceActionLabel(item) }}
                                </button>
                                <button
                                    @click="openViewSchedule(item)"
                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400"
                                >
                                    View Details
                                </button>
                                <button
                                    v-if="scheduleStatus(item) === 'completed'"
                                    @click="duplicateSchedule(item)"
                                    class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                >
                                    Duplicate
                                </button>
                                <button
                                    v-if="item.is_owner && !isLocked(item)"
                                    @click="openEditSchedule(item)"
                                    class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:border-indigo-300"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="item.is_owner && !isLocked(item)"
                                    @click="deleteSchedule(item.id)"
                                    class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:border-rose-300"
                                >
                                    Delete
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
            <div v-else-if="layout === 'calendar' && props.teams.length" key="calendar">
                <div class="mb-3 flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded bg-orange-500"></span> Basketball
                    </div>
                    <div class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded bg-blue-500"></span> Volleyball
                    </div>
                    <div class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded bg-green-500"></span> Football
                    </div>
                    <div class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded bg-yellow-400"></span> Badminton
                    </div>
                    <div class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded bg-red-500"></span> Table Tennis
                    </div>
                </div>
                <p class="mb-3 text-xs text-slate-500">
                    Select an open time slot on the calendar to create a schedule.
                </p>
                <div ref="calendarContainer" class="flex justify-center rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                    <VueCal sm style="height: 500px; width: 100%; max-width: 1150px;" :events="calendarEvents"
                        default-view="month" :time="true" :twelve-hour="true" time-format="h:mm {am}" events-on-month-view
                        :editable-events="canManage" :event-create-min-drag="15" @event-create="onCalendarCreate"
                        @event-click="onEventClick" @cell-click="onCellClick" @event-drop="onEventDrag"
                        @event-duration-change="onEventResize">
                        <!-- CUSTOM EVENT DISPLAY -->
                        <template #event="{ event }">
                            <div class="relative text-sm group pr-5">
                                <button v-if="isOwnerSchedule(event.id) && !event.is_locked"
                                    class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-600 text-white text-xs font-bold leading-none opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700"
                                    title="Delete schedule" @mousedown.stop @click.stop="deleteSchedule(event.id)">
                                    ×
                                </button>

                                <div class="text-xs font-medium leading-tight">
                                    {{ event.title }}
                                </div>

                                <div class="text-[10px] opacity-70 leading-tight">
                                    {{ formatPHTime(event.start) }} – {{ formatPHTime(event.end) }}
                                </div>
                            </div>
                        </template>
                    </VueCal>
                </div>
            </div>
        </transition>

        <!-- ========== MODAL ========== -->
        <div v-if="showModal" @click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-6">

            <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-xl">

                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule</p>
                        <h2 class="text-lg font-semibold" :class="modalMode === 'view' ? 'text-[#034485]' : 'text-slate-900'">
                            {{ modalMode === 'view' ? 'Schedule Details' : (editingId ? 'Edit Schedule' : 'Create Schedule') }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="modalMode === 'view' && selectedSchedule?.is_owner && !isLocked(selectedSchedule)"
                            @click="startEditFromSelected"
                            class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:border-indigo-300"
                        >
                            Edit Schedule
                        </button>

                        <button @click="closeModal" class="rounded-full border border-transparent px-2 py-1 text-slate-400 hover:text-slate-700">
                            ✕
                        </button>
                    </div>
                </div>

                <div v-if="modalMode === 'view'" class="space-y-4 p-6 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="selectedSchedule"
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="statusTone(scheduleStatus(selectedSchedule))"
                        >
                            {{ statusLabel(scheduleStatus(selectedSchedule)) }}
                        </span>
                        <span
                            v-if="selectedSchedule"
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="attendanceTone(selectedSchedule)"
                        >
                            {{ attendanceLabel(selectedSchedule) }}
                        </span>
                        <span v-if="selectedSchedule && isLocked(selectedSchedule)" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                            Locked
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.title || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.type || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Venue</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.venue || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Time</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">
                                {{ formatPHT(selectedSchedule?.start || null) || '-' }} → {{ formatPHT(selectedSchedule?.end || null) || '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ selectedSchedule?.notes || 'No additional notes provided.' }}</p>
                    </div>
                </div>

                <div v-else class="space-y-5 p-6">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            placeholder="e.g. Morning Practice"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</label>
                            <select
                                v-model="form.type"
                                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            >
                                <option>Practice</option>
                                <option>Game</option>
                                <option>Meeting</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Venue</label>
                            <input
                                v-model="form.venue"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                                placeholder="e.g. Main Gym"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Start</label>
                            <input
                                v-model="form.start_time"
                                type="datetime-local"
                                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">End</label>
                            <input
                                v-model="form.end_time"
                                type="datetime-local"
                                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            placeholder="Optional notes for the team"
                        ></textarea>
                    </div>
                </div>

                <div v-if="modalMode === 'form'" class="flex flex-col-reverse gap-2 border-t border-slate-200 px-6 py-4 sm:flex-row sm:justify-end">
                    <button @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400">
                        Cancel
                    </button>

                    <button @click="saveSchedule"
                        class="rounded-md bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white hover:bg-[#111827]">
                        Save Schedule
                    </button>
                </div>
            </div>
        </div>

    </div>

    <ConfirmDialog
        :open="deleteDialogOpen"
        title="Delete Schedule"
        description="Delete this schedule? This action cannot be undone."
        confirm-text="Delete"
        confirm-variant="destructive"
        @update:open="deleteDialogOpen = $event"
        @confirm="confirmDeleteSchedule"
    />
</template>

<style scoped>
.view-slide-enter-active,
.view-slide-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.view-slide-enter-from,
.view-slide-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.view-toggle {
  position: relative;
  display: inline-grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.35);
  background: #ffffff;
  padding: 4px;
  min-width: 220px;
}

.view-toggle__indicator {
  position: absolute;
  top: 4px;
  left: 4px;
  width: calc(50% - 4px);
  height: calc(100% - 8px);
  border-radius: 999px;
  background: #034485;
  transition: transform 0.25s ease;
  z-index: 0;
}

.view-toggle__btn {
  position: relative;
  z-index: 1;
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #334155;
  background: transparent;
  border: none;
  border-radius: 999px;
  transition: color 0.2s ease;
}

.view-toggle__btn.is-active {
  color: #ffffff;
}
</style>
