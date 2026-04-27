<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, useSlots } from 'vue'

import CoachMobileShell from '@/components/coach/CoachMobileShell.vue'
import { useSportColors } from '@/composables/useSportColors'

type TeamInfo = {
  id: number
  team_name: string
  sport: string
}

type ScheduleInfo = {
  id: number
  title: string
  type: string | null
  venue: string | null
  start: string | null
  end: string | null
}

type Metrics = {
  attendance_needs_review: number
  attendance_in_progress: number
  wellness_pending: number
  academic_missing: number
  roster_total: number
  roster_injured: number
  roster_missing_positions: number
  roster_jersey_pending: number
  latest_period: string | null
}

type AttendanceSnapshot = {
  present: number
  late: number
  absent: number
  excused: number
}

const slots = useSlots()
const hasDefaultSlot = computed(() => Boolean(slots.default))

const props = defineProps<{
  team?: TeamInfo | null
  nextSchedule?: ScheduleInfo | null
  metrics?: Partial<Metrics>
  attendanceSnapshot?: Partial<AttendanceSnapshot>
}>()
const { sportColor, sportTextColor, sportLabel } = useSportColors()

function goTo(route: string) {
  router.get(route)
}

const safeMetrics = computed<Metrics>(() => ({
  attendance_needs_review: props.metrics?.attendance_needs_review ?? 0,
  attendance_in_progress: props.metrics?.attendance_in_progress ?? 0,
  wellness_pending: props.metrics?.wellness_pending ?? 0,
  academic_missing: props.metrics?.academic_missing ?? 0,
  roster_total: props.metrics?.roster_total ?? 0,
  roster_injured: props.metrics?.roster_injured ?? 0,
  roster_missing_positions: props.metrics?.roster_missing_positions ?? 0,
  roster_jersey_pending: props.metrics?.roster_jersey_pending ?? 0,
  latest_period: props.metrics?.latest_period ?? null,
}))

const safeSnapshot = computed<AttendanceSnapshot>(() => ({
  present: props.attendanceSnapshot?.present ?? 0,
  late: props.attendanceSnapshot?.late ?? 0,
  absent: props.attendanceSnapshot?.absent ?? 0,
  excused: props.attendanceSnapshot?.excused ?? 0,
}))

const nextScheduleTime = computed(() => {
  if (!props.nextSchedule?.start) return null
  return new Date(props.nextSchedule.start)
})

function formatPHT(dt: string | null) {
  if (!dt) return '-'
  const date = new Date(dt)
  return date.toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

const nextCountdown = computed(() => {
  if (!nextScheduleTime.value) return null
  const diff = nextScheduleTime.value.getTime() - Date.now()
  if (diff <= 0) return 'Starting soon'
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)
  if (days > 0) return `Starts in ${days}d ${hours % 24}h`
  if (hours > 0) return `Starts in ${hours}h ${minutes % 60}m`
  return `Starts in ${minutes}m`
})
</script>

<template>
  <CoachMobileShell>
    <section v-if="hasDefaultSlot">
      <slot />
    </section>

    <section v-else class="space-y-5">
      <Head title="Coach Dashboard" />

      <section class="rounded-2xl border border-[#034485]/30 bg-white p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <h1 class="mt-2 text-2xl font-bold text-slate-900">
              {{ props.team?.team_name ? `${props.team.team_name} Workspace` : 'Coach Workspace' }}
            </h1>
            <div v-if="props.team?.sport" class="mt-3 flex flex-wrap items-center gap-2">
              <span class="text-sm text-slate-600">Sport:</span>
              <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                :style="{ backgroundColor: sportColor(props.team.sport), color: sportTextColor(props.team.sport) }"
              >
                {{ sportLabel(props.team.sport) }}
              </span>
            </div>
            <p v-else class="text-sm text-slate-600">
              Use this page to manage attendance, post-training condition records, and team schedules.
            </p>
          </div>
          <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button
              type="button"
              class="w-full rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70] sm:w-auto"
              @click="goTo('/coach/schedule')"
            >
              Record Attendance
            </button>
            <button
              type="button"
              class="w-full rounded-full border border-[#034485]/40 px-4 py-2 text-sm font-semibold text-[#034485] hover:border-[#034485]/70 hover:bg-[#034485]/5 sm:w-auto"
              @click="goTo('/coach/schedule')"
            >
              Create Schedule
            </button>
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr]">
          <div class="rounded-2xl border border-[#034485]/25 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Next Schedule</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ props.nextSchedule?.title ?? 'No upcoming schedule has been recorded' }}</p>
            <p class="text-xs text-slate-500">
              {{ props.nextSchedule ? `${formatPHT(props.nextSchedule.start)} • ${props.nextSchedule.venue || '-'}`
                : 'Create a schedule to keep team activities properly organized.' }}
            </p>
            <p v-if="nextCountdown" class="mt-2 text-xs font-semibold text-[#1f2937]">{{ nextCountdown }}</p>
          </div>
          <div class="rounded-2xl border border-[#034485]/25 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Attendance Checks</p>
            <p class="mt-2 text-lg font-semibold text-[#1f2937]">{{ safeMetrics.attendance_needs_review }}</p>
            <p class="text-xs text-slate-500">Completed schedules that still require attendance records.</p>
          </div>
          <div class="rounded-2xl border border-[#034485]/25 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Condition Records Pending</p>
            <p class="mt-2 text-lg font-semibold text-rose-600">{{ safeMetrics.wellness_pending }}</p>
            <p class="text-xs text-slate-500">Sessions that still require post-training condition records.</p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-[#034485]/30 bg-white p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600">Primary Actions</h2>
          <span class="text-xs text-slate-500">Primary workflows</span>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <button
            type="button"
            @click="goTo('/coach/schedule')"
            class="group flex h-full min-h-[11rem] flex-col justify-between rounded-2xl border border-[#034485]/25 bg-white p-4 text-left transition hover:border-[#034485]/50 hover:bg-[#f8fbff]"
          >
            <div class="flex items-start gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#034485]/10 text-[#034485]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" />
                </svg>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Schedule Management</p>
                <p class="text-xs text-slate-500">Create, revise, and organize official team activities.</p>
              </div>
            </div>
          </button>

          <button
            type="button"
            @click="goTo('/coach/schedule')"
            class="group flex h-full min-h-[11rem] flex-col justify-between rounded-2xl border border-[#034485]/25 bg-white p-4 text-left transition hover:border-[#034485]/50 hover:bg-[#f8fbff]"
          >
            <div class="flex items-start gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#034485]/10 text-[#034485]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M9 3h6M9 1h6a2 2 0 0 1 2 2v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a2 2 0 0 1 2-2zm-1 11l2 2 4-4" />
                </svg>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Attendance Workflow</p>
                <p class="text-xs text-slate-500">Open a schedule and record attendance directly from the schedule modal.</p>
                <p v-if="safeMetrics.attendance_needs_review > 0 || safeMetrics.wellness_pending > 0" class="mt-1 text-[11px] font-semibold text-rose-600">
                  {{ safeMetrics.attendance_needs_review }} attendance records pending • {{ safeMetrics.wellness_pending }} condition records pending
                </p>
              </div>
            </div>
          </button>

          <button
            type="button"
            @click="goTo('/coach/academics')"
            class="group flex h-full min-h-[11rem] flex-col justify-between rounded-2xl border border-[#034485]/25 bg-white p-4 text-left transition hover:border-[#034485]/50 hover:bg-[#f8fbff]"
          >
            <div class="flex items-start gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#034485]/10 text-[#034485]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5" />
                </svg>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Academic Visibility</p>
                <p class="text-xs text-slate-500">Review academic submissions and eligibility-related records.</p>
                <p v-if="safeMetrics.academic_missing > 0" class="mt-1 text-[11px] font-semibold text-emerald-700">
                  {{ safeMetrics.academic_missing }} submissions pending
                </p>
              </div>
            </div>
          </button>
        </div>
      </section>

      <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-[#034485]/30 bg-white p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-600">Roster Signals</h3>
            <span class="text-xs text-slate-500">Total: {{ safeMetrics.roster_total }}</span>
          </div>
          <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-[#034485]/20 bg-white p-3">
              <p class="text-xs text-slate-500">Injured</p>
              <p class="mt-1 text-lg font-semibold text-rose-600">{{ safeMetrics.roster_injured }}</p>
            </div>
            <div class="rounded-xl border border-[#034485]/20 bg-white p-3">
              <p class="text-xs text-slate-500">Missing Positions</p>
              <p class="mt-1 text-lg font-semibold text-amber-600">{{ safeMetrics.roster_missing_positions }}</p>
            </div>
            <div class="rounded-xl border border-[#034485]/20 bg-white p-3">
              <p class="text-xs text-slate-500">Jersey Pending</p>
              <p class="mt-1 text-lg font-semibold text-slate-900">{{ safeMetrics.roster_jersey_pending }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-[#034485]/30 bg-white p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-600">Attendance Snapshot (7 days)</h3>
            <span class="text-xs text-slate-500">Team trend</span>
          </div>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
              <p class="text-xs text-emerald-700">Present</p>
              <p class="mt-1 text-lg font-semibold text-emerald-800">{{ safeSnapshot.present }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
              <p class="text-xs text-amber-700">Late</p>
              <p class="mt-1 text-lg font-semibold text-amber-800">{{ safeSnapshot.late }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
              <p class="text-xs text-rose-700">Absent</p>
              <p class="mt-1 text-lg font-semibold text-rose-800">{{ safeSnapshot.absent }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <p class="text-xs text-slate-600">Excused</p>
              <p class="mt-1 text-lg font-semibold text-slate-800">{{ safeSnapshot.excused }}</p>
            </div>
          </div>
        </div>
      </section>

      <div class="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom,0px)+4.2rem)] z-30 px-4 md:hidden">
        <div class="mx-auto flex max-w-md gap-2 rounded-xl border border-[#034485]/30 bg-white/95 p-2 backdrop-blur">
          <button type="button" class="flex-1 rounded-md bg-[#034485] px-3 py-2 text-xs font-semibold text-white" @click="goTo('/coach/schedule')">Record Attendance</button>
          <button type="button" class="flex-1 rounded-md border border-[#034485]/40 bg-white px-3 py-2 text-xs font-semibold text-[#034485]" @click="goTo('/coach/schedule')">Create Schedule</button>
        </div>
      </div>
    </section>  
  </CoachMobileShell>
</template>
