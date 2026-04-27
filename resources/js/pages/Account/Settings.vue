<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
  layout: (h: any, page: any) => {
    const role = String(page?.props?.auth?.user?.role ?? '')
    const layout = role === 'admin' ? AdminDashboard : role === 'coach' ? CoachDashboard : StudentAthleteDashboard
    return h(layout, [page])
  },
})

const page = usePage()
const role = computed(() => String((page.props as any)?.auth?.user?.role ?? ''))

const quickLinks = computed(() => {
  if (role.value === 'coach') {
    return [
      { title: 'Update profile details', description: 'Keep your contact information and coaching identity current.', href: '/account/profile', cta: 'Open Profile' },
      { title: 'Review alert preferences', description: 'Control how schedule, attendance, and roster updates reach you.', href: '/account/notifications', cta: 'Manage Alerts' },
      { title: 'Get workflow help', description: 'Open role-specific guidance for attendance, roster, and support concerns.', href: '/account/help', cta: 'Open Help' },
    ]
  }

  if (role.value === 'admin') {
    return [
      { title: 'Check account security', description: 'Update password and email details used for administrative access.', href: '/account/account-settings', cta: 'Open Account' },
      { title: 'Tune notification rules', description: 'Choose which operational, academic, and approval alerts you want.', href: '/account/notifications', cta: 'Manage Alerts' },
      { title: 'Review support guidance', description: 'Open role-specific help for approvals, teams, and official records.', href: '/account/help', cta: 'Open Help' },
    ]
  }

  return [
    { title: 'Complete your profile', description: 'Keep your personal and emergency information updated and accurate.', href: '/account/profile', cta: 'Open Profile' },
    { title: 'Manage notifications', description: 'Choose how academic, schedule, and status updates reach you.', href: '/account/notifications', cta: 'Manage Alerts' },
    { title: 'Find support faster', description: 'Open practical help for approval, attendance, and document concerns.', href: '/account/help', cta: 'Open Help' },
  ]
})
</script>

<template>
  <Head title="Settings" />

  <AccountShell active="settings">
    <div class="space-y-6">
      <section class="rounded-3xl border border-[#034485]/18 bg-white p-6 shadow-[0_24px_50px_-38px_rgba(15,23,42,0.45)]">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Settings overview</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-900">Account center</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
          Use the left sidebar to move through profile details, account access, notifications, preferences, and help without bouncing across oversized buttons.
        </p>
      </section>

      <section class="grid gap-4 lg:grid-cols-3">
        <article
          v-for="item in quickLinks"
          :key="item.href"
          class="rounded-2xl border border-[#034485]/14 bg-white p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)] transition hover:border-slate-200 hover:bg-slate-50/60"
        >
          <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
          <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.description }}</p>
          <Link :href="item.href" class="mt-4 inline-flex rounded-full bg-[#034485]/8 px-3 py-2 text-xs font-bold text-[#034485] transition hover:bg-slate-200/70">
            {{ item.cta }}
          </Link>
        </article>
      </section>
    </div>
  </AccountShell>
</template>
