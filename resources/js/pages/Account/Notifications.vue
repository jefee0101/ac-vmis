<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

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

const hasNotificationField = (field: string) => props.scope.notifications.includes(field)
const saved = ref(false)
const page = usePage()
const role = computed(() => String((page.props as any)?.auth?.user?.role ?? ''))

const labelMap = computed(() => {
  if (role.value === 'coach') {
    return {
      notify_academic_alerts: 'Academic Submission Updates',
      notify_schedule_changes: 'Team Assignment (Coach)',
      notify_attendance_exceptions: 'Roster Changes (Assistants & Athletes)',
      notify_attendance_changes: 'Attendance Status Updates',
      notify_wellness_alerts: 'Wellness Monitoring Alerts',
      notify_wellness_injury_threshold: 'Injury threshold alerts',
      notify_approvals: 'Newly pending accounts',
    }
  }

  if (role.value === 'student' || role.value === 'student-athlete') {
    return {
      notify_academic_alerts: 'Academic Period Openings',
      notify_attendance_changes: 'Submission Status Updates',
      notify_wellness_alerts: 'Wellness Log Updates',
      notify_schedule_changes: 'Schedule Updates (Start, Change, Cancel)',
      notify_attendance_exceptions: 'Team Roster & Coaching Updates',
      notify_wellness_injury_threshold: 'Injury Threshold Alerts',
      notify_approvals: 'Account Status Updates',
    }
  }

  return {
    notify_academic_alerts: 'Academic Submissions',
    notify_schedule_changes: 'Schedules',
    notify_attendance_exceptions: 'Team Change Requests',
    notify_attendance_changes: 'Period Ending Soon',
    notify_wellness_alerts: 'Clearance & Wellness',
    notify_wellness_injury_threshold: 'Injury Threshold Alerts',
    notify_approvals: 'Newly Pending Accounts',
  }
})

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
  theme_preference: (props.settings?.theme_preference ?? 'light') as 'light' | 'dark',
  timezone: props.settings?.timezone ?? 'Asia/Manila',
  language: props.settings?.language ?? 'en',
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
</script>

<template>
  <Head title="Notifications" />

  <div class="settings-page space-y-6">
      <div>
        <Link href="/account/settings" class="back-pill">Back</Link>
      </div>

      <form @submit.prevent="submitSettings" class="space-y-4">
        <section class="rounded-2xl border border-[#034485]/40 bg-white p-5">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          Notifications
        </h2>
        <p class="settings-muted mt-1 text-xs text-slate-500">Choose how and when you want to be alerted.</p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
          <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="settings-kicker text-xs font-semibold uppercase tracking-wide text-slate-500">Email Notifications</p>
                <p class="text-xs text-slate-500">
                  {{
                    role === 'coach'
                      ? 'Send coach alerts to your email.'
                      : role === 'student' || role === 'student-athlete'
                        ? 'Send student alerts to your email.'
                        : 'Send admin alerts to your email.'
                  }}
                </p>
              </div>
              <label v-if="hasNotificationField('notification_email_enabled')" class="switch">
                <input v-model="form.notification_email_enabled" type="checkbox" />
                <span class="slider" />
              </label>
            </div>
            <div class="mt-4 grid gap-2">
              <div v-if="hasNotificationField('notify_approvals')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_approvals }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_approvals" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
              <div v-if="hasNotificationField('notify_attendance_exceptions')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_attendance_exceptions }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_attendance_exceptions" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
              <div v-if="hasNotificationField('notify_schedule_changes')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_schedule_changes }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_schedule_changes" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
              <div v-if="hasNotificationField('notify_wellness_alerts')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_wellness_alerts }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_wellness_alerts" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
              <div v-if="hasNotificationField('notify_academic_alerts')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_academic_alerts }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_academic_alerts" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
              <div v-if="hasNotificationField('notify_attendance_changes')" class="toggle-row" :class="{ 'toggle-row--disabled': !form.notification_email_enabled }">
                <span>{{ labelMap.notify_attendance_changes }}</span>
                <label class="switch switch--sm">
                  <input v-model="form.notify_attendance_changes" type="checkbox" :disabled="!form.notification_email_enabled" />
                  <span class="slider" />
                </label>
              </div>
            </div>
          </div>
        </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" class="rounded-lg bg-[#1f2937] px-4 py-2 text-white font-semibold hover:bg-[#334155] transition" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Notifications' }}
          </button>
          <p v-if="saved" class="text-sm text-green-700">Notification settings updated.</p>
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
.settings-kicker {
  color: #64748b;
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

.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 0.9rem;
  color: #334155;
}

.toggle-row--disabled {
  opacity: 0.5;
}

.switch {
  position: relative;
  width: 42px;
  height: 22px;
  flex-shrink: 0;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #cbd5e1;
  border-radius: 999px;
  transition: background 0.2s ease;
}

.slider::before {
  content: '';
  position: absolute;
  height: 18px;
  width: 18px;
  left: 2px;
  top: 2px;
  background: #ffffff;
  border-radius: 999px;
  transition: transform 0.2s ease;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
}

.switch input:checked + .slider {
  background: #034485;
}

.switch input:checked + .slider::before {
  transform: translateX(20px);
}

.switch--sm {
  width: 36px;
  height: 18px;
}

.switch--sm .slider::before {
  width: 14px;
  height: 14px;
  top: 2px;
  left: 2px;
}

.switch--sm input:checked + .slider::before {
  transform: translateX(16px);
}
</style>
