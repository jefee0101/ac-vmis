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

const passwordForm = useForm({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const passwordSaved = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const page = usePage()
const currentEmail = computed(() => String(page.props.auth?.user?.email ?? ''))

const emailForm = useForm({
  email: currentEmail.value,
})

const emailSaved = ref(false)
const deleteForm = useForm({})

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

function submitEmail() {
  emailSaved.value = false
  emailForm.put('/account/account-settings', {
    preserveScroll: true,
    onSuccess: () => {
      emailSaved.value = true
      setTimeout(() => (emailSaved.value = false), 2200)
    },
  })
}

function confirmDelete() {
  if (!window.confirm('Delete your account? This will deactivate access immediately.')) return
  deleteForm.delete('/account/delete')
}
</script>

<template>
  <Head title="Account Settings" />

  <div class="settings-page space-y-6">
      <div>
        <Link href="/account/settings" class="back-pill">Back</Link>
      </div>

      <form id="settings-account" @submit.prevent="submitPassword" class="rounded-2xl border border-[#034485]/40 bg-white p-5 space-y-3">
        <h2 class="section-title">
          <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V8a5 5 0 1 1 10 0v3" />
          </svg>
          Account Settings
        </h2>
        <p class="settings-muted text-xs text-slate-500">Change password &amp; review two-factor authentication.</p>

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

        <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-slate-900">Two-Factor Authentication</p>
              <p class="text-xs text-slate-500">Static placeholder for upcoming 2FA configuration.</p>
            </div>
            <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Coming Soon</span>
          </div>
        </div>
      </form>

      <form id="settings-email" @submit.prevent="submitEmail" class="rounded-2xl border border-[#034485]/40 bg-white p-5 space-y-3">
        <h2 class="section-title">Account Email</h2>
        <p class="settings-muted text-xs text-slate-500">Update the email address tied to your account.</p>
        <div>
          <label class="settings-label text-slate-500 text-sm">Email Address</label>
          <input v-model="emailForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
          <p v-if="emailForm.errors.email" class="mt-1 text-xs text-red-600">{{ emailForm.errors.email }}</p>
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-white font-semibold hover:bg-slate-900 transition" :disabled="emailForm.processing">
            {{ emailForm.processing ? 'Saving...' : 'Update Email' }}
          </button>
          <p v-if="emailSaved" class="text-sm text-green-700">Email updated.</p>
        </div>
      </form>

      <section class="rounded-2xl border border-red-200 bg-red-50 p-5 space-y-3">
        <h2 class="section-title text-red-700">Delete Account</h2>
        <p class="text-xs text-red-700">This action will deactivate your access immediately.</p>
        <button
          type="button"
          class="rounded-lg bg-red-600 px-4 py-2 text-white font-semibold hover:bg-red-700 transition"
          @click="confirmDelete"
          :disabled="deleteForm.processing"
        >
          {{ deleteForm.processing ? 'Processing...' : 'Delete Account' }}
        </button>
      </section>
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
.settings-label,
.settings-icon {
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
</style>
