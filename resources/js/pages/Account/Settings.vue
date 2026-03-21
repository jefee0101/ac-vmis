<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { type ThemeMode, useTheme } from '@/composables/useTheme'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

defineOptions({
  layout: (h: any, page: any) => {
    const role = String(page?.props?.auth?.user?.role ?? '')
    const layout = role === 'admin' ? AdminDashboard : role === 'coach' ? CoachDashboard : StudentAthleteDashboard
    return h(layout, [page])
  },
})

const props = defineProps<{
  settings: {
    notification_email_enabled: boolean
    notification_in_app_enabled: boolean
    notify_approvals: boolean
    notify_schedule_changes: boolean
    notify_attendance_changes: boolean
    notify_wellness_alerts: boolean
    notify_academic_alerts: boolean
    notify_attendance_exceptions: boolean
    notify_wellness_injury_threshold: boolean
    wellness_injury_threshold_level: number
    theme_preference: 'system' | 'light' | 'dark'
    timezone: string
    language: string
  }
  scope: {
    notifications: string[]
    coach_preferences: boolean
  }
  compliance: {
    admin: {
      pending_approvals: number
      expired_clearances: number
      academic_at_risk: number
    } | null
    student: {
      health_clearance_status: string | null
      health_clearance_expires_on: string | null
      academic_status: string | null
      academic_period: string | null
      missing_latest_submission: boolean
    } | null
    coach: {
      tracked_students: number
      latest_period: string | null
      missing_submissions_count: number
      eligible_count: number
      probation_count: number
      ineligible_count: number
    } | null
  }
}>()

const page = usePage()
const role = computed(() => String(page.props.auth?.user?.role ?? ''))
const hasNotificationField = (field: string) => props.scope.notifications.includes(field)
const { setTheme, getTheme } = useTheme()

const themeCards: Array<{ value: 'light' | 'dark'; label: string; description: string; icon: string }> = [
  { value: 'light', label: 'Light', description: 'Clean and bright workspace.', icon: 'sun' },
  { value: 'dark', label: 'Dark', description: 'Blue-tinted dark interface.', icon: 'moon' },
]

function normalizedTheme(value: string | null | undefined): 'light' | 'dark' {
  if (value === 'dark' || value === 'blue' || value === 'light') return value === 'blue' ? 'dark' : value
  const current = getTheme()
  return current === 'dark' ? current : 'light'
}

const form = useForm({
  notification_email_enabled: Boolean(props.settings?.notification_email_enabled ?? true),
  notification_in_app_enabled: Boolean(props.settings?.notification_in_app_enabled ?? true),
  notify_approvals: Boolean(props.settings?.notify_approvals ?? true),
  notify_schedule_changes: Boolean(props.settings?.notify_schedule_changes ?? true),
  notify_attendance_changes: Boolean(props.settings?.notify_attendance_changes ?? true),
  notify_wellness_alerts: Boolean(props.settings?.notify_wellness_alerts ?? true),
  notify_academic_alerts: Boolean(props.settings?.notify_academic_alerts ?? true),
  notify_attendance_exceptions: Boolean(props.settings?.notify_attendance_exceptions ?? true),
  notify_wellness_injury_threshold: Boolean(props.settings?.notify_wellness_injury_threshold ?? true),
  wellness_injury_threshold_level: Number(props.settings?.wellness_injury_threshold_level ?? 3),
  theme_preference: normalizedTheme(props.settings?.theme_preference) as 'light' | 'dark',
  timezone: props.settings?.timezone ?? 'Asia/Manila',
  language: props.settings?.language ?? 'en',
})

const passwordForm = useForm({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const saved = ref(false)
const passwordSaved = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const coachSnapshotCards = computed(() => {
  if (!props.compliance?.coach) return []
  return [
    { label: 'Tracked Students', value: props.compliance.coach.tracked_students, tone: 'text-slate-900' },
    { label: 'Missing Submissions', value: props.compliance.coach.missing_submissions_count, tone: 'text-rose-600' },
    { label: 'Eligible', value: props.compliance.coach.eligible_count, tone: 'text-emerald-600' },
    { label: 'Probation', value: props.compliance.coach.probation_count, tone: 'text-amber-600' },
    { label: 'Ineligible', value: props.compliance.coach.ineligible_count, tone: 'text-rose-600' },
  ]
})

function submitSettings() {
  saved.value = false
  form.put('/account/settings', {
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true
      setTimeout(() => (saved.value = false), 2200)
    },
  })
}

watch(
  () => form.theme_preference,
  (value) => {
    const mode: ThemeMode = value === 'dark' || value === 'light' ? value : 'light'
    setTheme(mode)
  },
)

function submitPassword() {
  passwordSaved.value = false
  passwordForm.put('/account/password', {
    preserveScroll: true,
    onSuccess: () => {
      passwordSaved.value = true
      passwordForm.reset()
      setTimeout(() => (passwordSaved.value = false), 2200)
    },
  })
}
</script>

<template>
  <Head title="Account Settings" />

  <div class="settings-page space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-white to-slate-50/60 p-6 shadow-sm">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Settings</h1>
          <p class="text-sm text-slate-600">Control notifications, preferences, and account security.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {{ role === 'admin' ? 'Admin Scope' : role }}
        </span>
      </div>
    </section>

    <section v-if="role === 'coach' && compliance.coach" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Academic Snapshot</p>
          <h2 class="text-lg font-semibold text-slate-900">Team Academic Overview</h2>
          <p class="text-xs text-slate-500">Latest period: {{ compliance.coach.latest_period ?? 'N/A' }}</p>
        </div>
      </div>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div v-for="card in coachSnapshotCards" :key="card.label" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <p class="text-xs uppercase tracking-wide text-slate-500">{{ card.label }}</p>
          <p class="mt-1 text-lg font-semibold" :class="card.tone">{{ card.value }}</p>
        </div>
      </div>
    </section>

    <form @submit.prevent="submitSettings" class="space-y-4">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          Notifications
        </h2>
        <p class="settings-muted mt-1 text-xs text-slate-500">Choose how and when you want to be alerted.</p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="settings-kicker text-xs font-semibold uppercase tracking-wide text-slate-500">Channels</p>
            <div class="mt-3 grid gap-2">
              <label v-if="hasNotificationField('notification_email_enabled')" class="toggle"><input v-model="form.notification_email_enabled" type="checkbox" /> Email Notifications</label>
              <label v-if="hasNotificationField('notification_in_app_enabled')" class="toggle"><input v-model="form.notification_in_app_enabled" type="checkbox" /> In-App Notifications</label>
            </div>
          </div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="settings-kicker text-xs font-semibold uppercase tracking-wide text-slate-500">Alerts</p>
            <div class="mt-3 grid gap-2">
              <label v-if="hasNotificationField('notify_schedule_changes')" class="toggle"><input v-model="form.notify_schedule_changes" type="checkbox" /> Schedule Changes</label>
              <label v-if="hasNotificationField('notify_attendance_changes')" class="toggle"><input v-model="form.notify_attendance_changes" type="checkbox" /> Attendance Changes</label>
              <label v-if="hasNotificationField('notify_attendance_exceptions')" class="toggle"><input v-model="form.notify_attendance_exceptions" type="checkbox" /> Attendance Exceptions</label>
              <label v-if="hasNotificationField('notify_wellness_alerts')" class="toggle"><input v-model="form.notify_wellness_alerts" type="checkbox" /> Wellness Alerts</label>
              <label v-if="hasNotificationField('notify_academic_alerts')" class="toggle"><input v-model="form.notify_academic_alerts" type="checkbox" /> Academic Alerts</label>
              <label v-if="hasNotificationField('notify_approvals')" class="toggle"><input v-model="form.notify_approvals" type="checkbox" /> Approvals</label>
            </div>
          </div>
        </div>
      </section>

      <section v-if="role === 'coach' && scope.coach_preferences" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="4" y1="21" x2="4" y2="14" />
            <line x1="4" y1="10" x2="4" y2="3" />
            <line x1="12" y1="21" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12" y2="3" />
            <line x1="20" y1="21" x2="20" y2="16" />
            <line x1="20" y1="12" x2="20" y2="3" />
            <line x1="1" y1="14" x2="7" y2="14" />
            <line x1="9" y1="8" x2="15" y2="8" />
            <line x1="17" y1="16" x2="23" y2="16" />
          </svg>
          Coach Team Preferences
        </h2>
        <div class="mt-4">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <label class="toggle"><input v-model="form.notify_wellness_injury_threshold" type="checkbox" /> Injury Threshold Alerts</label>
            <p class="settings-muted mt-1 text-xs text-slate-500">Triggers when athlete wellness hits the threshold.</p>
            <div class="mt-3 flex items-center gap-2 text-sm text-slate-700">
              <span class="settings-kicker text-xs font-semibold uppercase tracking-wide text-slate-500">Threshold</span>
              <input v-model.number="form.wellness_injury_threshold_level" type="number" min="1" max="5" class="w-20 rounded border border-slate-300 px-2 py-1" />
            </div>
          </div>
        </div>
      </section>

      <section v-if="role === 'admin' && compliance.admin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
          Admin Control Snapshot
        </h2>
        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-3">
          <p>Pending Approvals: <span class="font-semibold">{{ compliance.admin.pending_approvals }}</span></p>
          <p>Expired Clearances: <span class="font-semibold">{{ compliance.admin.expired_clearances }}</span></p>
          <p>Academic At Risk: <span class="font-semibold">{{ compliance.admin.academic_at_risk }}</span></p>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1 .33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.26.3.45.65.56 1.03.11.38.14.78.1 1.18-.04.4-.16.78-.34 1.13-.18.35-.43.67-.73.93z" />
          </svg>
          Theme Mode
        </h2>
        <p class="settings-muted mt-1 text-xs text-slate-500">Apply visual mode across admin, public, and auth pages.</p>
        <div class="mt-3 grid gap-3 md:grid-cols-3">
          <button
            v-for="theme in themeCards"
            :key="theme.value"
            type="button"
            class="theme-option rounded-lg border p-3 text-left transition"
            :class="
              form.theme_preference === theme.value
                ? 'theme-option--active shadow-sm'
                : 'border-slate-300 bg-white hover:border-slate-400'
            "
            @click="form.theme_preference = theme.value"
          >
            <div class="flex items-center gap-2">
              <svg v-if="theme.icon === 'sun'" class="theme-option__icon h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
              </svg>
              <svg v-else-if="theme.icon === 'moon'" class="theme-option__icon h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.79A9 9 0 1 1 11.21 3c0 .55.05 1.1.16 1.63A7 7 0 0 0 19.37 12c.53.11 1.08.16 1.63.16z" />
              </svg>
              <svg v-else class="theme-option__icon h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2s6 5 6 10a6 6 0 1 1-12 0c0-5 6-10 6-10z" />
              </svg>
              <p class="theme-option__title font-semibold">{{ theme.label }}</p>
            </div>
            <p class="theme-option__description mt-1 text-xs">{{ theme.description }}</p>
          </button>
        </div>
      </section>

      <section v-if="compliance.student" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M3 3h18v18H3z" />
            <path d="M7 12l3 3 7-7" />
          </svg>
          Compliance Center
        </h2>
        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
          <p>Health Clearance Status: <span class="font-semibold">{{ compliance.student.health_clearance_status ?? 'N/A' }}</span></p>
          <p>Clearance Expiry: <span class="font-semibold">{{ compliance.student.health_clearance_expires_on ?? 'N/A' }}</span></p>
          <p>Academic Status: <span class="font-semibold">{{ compliance.student.academic_status ?? 'N/A' }}</span></p>
          <p>Latest Period: <span class="font-semibold">{{ compliance.student.academic_period ?? 'N/A' }}</span></p>
          <p class="md:col-span-2">Missing Latest Submission: <span class="font-semibold">{{ compliance.student.missing_latest_submission ? 'Yes' : 'No' }}</span></p>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="rounded-lg bg-[#1f2937] px-4 py-2 text-white font-semibold hover:bg-[#334155] transition" :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Save Settings' }}
        </button>
        <p v-if="saved" class="text-sm text-green-700">Settings updated.</p>
      </div>
    </form>

    <form @submit.prevent="submitPassword" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
      <h2 class="section-title">
        <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" />
          <path d="M7 11V8a5 5 0 1 1 10 0v3" />
        </svg>
        Change Password
      </h2>

      <div>
        <label class="settings-label text-slate-500 text-sm">Current Password</label>
        <div class="relative">
          <input
            v-model="passwordForm.current_password"
            :type="showCurrentPassword ? 'text' : 'password'"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
          />
          <button
            type="button"
            class="settings-icon absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
            :aria-label="showCurrentPassword ? 'Hide password' : 'Show password'"
            @click="showCurrentPassword = !showCurrentPassword"
          >
            <svg v-if="showCurrentPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
      </div>

      <div>
        <label class="settings-label text-slate-500 text-sm">New Password</label>
        <div class="relative">
          <input
            v-model="passwordForm.new_password"
            :type="showNewPassword ? 'text' : 'password'"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
          />
          <button
            type="button"
            class="settings-icon absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
            :aria-label="showNewPassword ? 'Hide password' : 'Show password'"
            @click="showNewPassword = !showNewPassword"
          >
            <svg v-if="showNewPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
        <p v-if="passwordForm.errors.new_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.new_password }}</p>
      </div>

      <div>
        <label class="settings-label text-slate-500 text-sm">Confirm New Password</label>
        <div class="relative">
          <input
            v-model="passwordForm.new_password_confirmation"
            :type="showConfirmPassword ? 'text' : 'password'"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
          />
          <button
            type="button"
            class="settings-icon absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
            :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
            @click="showConfirmPassword = !showConfirmPassword"
          >
            <svg v-if="showConfirmPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
        <p v-if="passwordForm.errors.new_password_confirmation" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.new_password_confirmation }}</p>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-white font-semibold hover:bg-slate-900 transition" :disabled="passwordForm.processing">
          {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
        </button>
        <p v-if="passwordSaved" class="text-sm text-green-700">Password updated.</p>
      </div>
    </form>
  </div>
</template>

<style scoped>
.theme-option {
  color: #334155;
}

.theme-option__icon {
  color: #1f2937;
}

.theme-option__title {
  color: #1e293b;
}

.theme-option__description {
  color: #64748b;
}

.theme-option--active {
  border-color: #1f2937;
  background: #e2e8f0;
}

:global(html.theme-dark) .theme-option {
  color: #eff6ff;
  border-color: #1a4f8f;
  background: #0f3b73;
}

:global(html.theme-dark) .theme-option__icon,
:global(html.theme-dark) .theme-option__title {
  color: #eff6ff;
}

:global(html.theme-dark) .theme-option__description {
  color: #bfdbfe;
}

:global(html.theme-dark) .theme-option--active {
  border-color: #93c5fd;
  background: #0b5cae;
}

:global(html.theme-dark) .theme-option--active .theme-option__icon,
:global(html.theme-dark) .theme-option--active .theme-option__title {
  color: #eff6ff;
}

:global(html.theme-dark) .theme-option--active .theme-option__description {
  color: #bfdbfe;
}

:global(html.theme-dark) .settings-page .theme-option {
  color: inherit;
}
</style>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-weight: 600;
}

:global(html.theme-dark) .section-title {
  color: #f8fafc;
}

.toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #334155;
}

:global(html.theme-dark) .toggle {
  color: #e2e8f0;
}

.settings-muted,
.settings-kicker,
.settings-label,
.settings-icon {
  color: #64748b;
}

:global(html.theme-dark) .settings-page .settings-muted,
:global(html.theme-dark) .settings-page .settings-kicker,
:global(html.theme-dark) .settings-page .settings-label,
:global(html.theme-dark) .settings-page .settings-icon {
  color: #bfdbfe;
}


</style>
