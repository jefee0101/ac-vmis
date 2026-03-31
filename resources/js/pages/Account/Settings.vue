<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { Head, router } from '@inertiajs/vue3'

defineOptions({
  layout: (h: any, page: any) => {
    const role = String(page?.props?.auth?.user?.role ?? '')
    const layout = role === 'admin' ? AdminDashboard : role === 'coach' ? CoachDashboard : StudentAthleteDashboard
    return h(layout, [page])
  },
})

const items = [
  { label: 'Profile', href: '/account/profile' },
  { label: 'Account Settings', href: '/account/account-settings' },
  { label: 'Notification', href: '/account/notifications' },
  { label: 'Preference', href: '/account/preferences' },
  { label: 'Help & Support', href: '/account/help' },
]

function goTo(href: string) {
  router.get(href)
}
</script>

<template>
  <Head title="Settings" />

  <div class="settings-page">
    <div class="flex max-w-3xl flex-col gap-4">
      <button
        v-for="item in items"
        :key="item.href"
        type="button"
        class="settings-pill"
        @click="goTo(item.href)"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.settings-pill {
  border-radius: 999px;
  background: #034485;
  color: #ffffff;
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.7rem 1.35rem;
  text-align: left;
  transition: all 0.2s ease;
}

.settings-pill:hover {
  background: #04519f;
}
</style>
