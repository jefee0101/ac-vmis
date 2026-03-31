<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { type ThemeMode, useTheme } from '@/composables/useTheme'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
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
  compliance: Record<string, unknown> | null
}>()

const { setTheme, getTheme } = useTheme()
const saved = ref(false)
const page = usePage()
const role = computed(() => String((page.props as any)?.auth?.user?.role ?? ''))

const themeCards: Array<{ value: 'light' | 'dark'; label: string; description: string; icon: string }> = [
  { value: 'light', label: 'Light', description: 'Clean and bright workspace.', icon: 'sun' },
  { value: 'dark', label: 'Dark', description: 'Blue-tinted dark interface.', icon: 'moon' },
]

const navOrder = ref(
  role.value === 'coach'
    ? ['Dashboard', 'My Team', 'Schedule', 'Attendance', 'Academics']
    : ['People', 'Teams', 'Operations', 'Health & Clearance', 'Academics'],
)

function moveNavItem(index: number, direction: 'up' | 'down') {
  const target = direction === 'up' ? index - 1 : index + 1
  if (target < 0 || target >= navOrder.value.length) return
  const next = [...navOrder.value]
  ;[next[index], next[target]] = [next[target], next[index]]
  navOrder.value = next
}

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

watch(
  () => form.theme_preference,
  (value) => {
    const mode: ThemeMode = value === 'dark' || value === 'light' ? value : 'light'
    setTheme(mode)
  },
)

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
</script>

<template>
  <Head title="Preferences" />

  <div class="settings-page space-y-6">
      <div>
        <Link href="/account/settings" class="back-pill">Back</Link>
      </div>

      <form @submit.prevent="submitSettings" class="space-y-4">
        <section class="rounded-2xl border border-[#034485]/40 bg-white p-5">
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
                ? 'theme-option--active'
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
              <p class="theme-option__title font-semibold">{{ theme.label }}</p>
            </div>
            <p class="theme-option__description mt-1 text-xs">{{ theme.description }}</p>
          </button>
        </div>
        </section>

      <section class="rounded-2xl border border-[#034485]/40 bg-white p-5">
        <h2 class="section-title">Workspace Navigation</h2>
        <div class="mt-3 grid gap-2">
          <div
            v-for="(item, index) in navOrder"
            :key="item"
            class="nav-item flex items-center justify-between rounded-xl border border-[#034485] bg-[#034485] px-4 py-3"
          >
            <span class="text-sm font-semibold text-white">{{ item }}</span>
            <div class="flex items-center gap-2">
              <button type="button" class="nav-move" :disabled="index === 0" @click="moveNavItem(index, 'up')">↑</button>
              <button type="button" class="nav-move" :disabled="index === navOrder.length - 1" @click="moveNavItem(index, 'down')">↓</button>
            </div>
          </div>
        </div>
      </section>

        <section class="rounded-2xl border border-[#034485]/40 bg-white p-5">
        <h2 class="section-title">Locale</h2>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
          <div>
            <label class="settings-label text-slate-500 text-sm">Timezone</label>
            <input v-model="form.timezone" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="settings-label text-slate-500 text-sm">Language</label>
            <input v-model="form.language" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
        </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" class="rounded-lg bg-[#1f2937] px-4 py-2 text-white font-semibold hover:bg-[#334155] transition" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Preferences' }}
          </button>
          <p v-if="saved" class="text-sm text-green-700">Preferences updated.</p>
        </div>
      </form>
  </div>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-weight: 600;
}

.settings-muted,
.settings-label {
  color: #64748b;
}

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

.nav-item {
  transition: background 0.2s ease;
}

.nav-move {
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.4);
  padding: 0.15rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #034485;
  background: #ffffff;
  transition: background 0.2s ease;
}

.nav-move:hover:not(:disabled) {
  background: rgba(3, 68, 133, 0.1);
}

.nav-move:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.back-pill {
  border-radius: 999px;
  background: #034485;
  padding: 0.4rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #ffffff;
  transition: background 0.2s ease;
  display: inline-flex;
  align-items: center;
}

.back-pill:hover {
  background: #04519f;
}
</style>
