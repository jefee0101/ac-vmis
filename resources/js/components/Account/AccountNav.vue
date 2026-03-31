<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  active: 'profile' | 'settings' | 'account' | 'notifications' | 'preferences' | 'help'
}>()

const items = computed(() => [
  { key: 'profile', label: 'Profile', href: '/account/profile' },
  { key: 'settings', label: 'Settings', href: '/account/settings' },
  { key: 'account', label: 'Account Settings', href: '/account/account-settings' },
  { key: 'notifications', label: 'Notification', href: '/account/notifications' },
  { key: 'preferences', label: 'Preference', href: '/account/preferences' },
  { key: 'help', label: 'Help & Support', href: '/account/help' },
])

function goTo(href: string) {
  router.get(href)
}
</script>

<template>
  <div class="account-nav rounded-2xl border border-[#034485]/40 bg-white p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Settings</h1>
        <p class="text-sm text-slate-600">Profile, notifications, preferences, and account security.</p>
      </div>
    </div>
    <div class="mt-4 flex flex-col gap-2">
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        class="pill-link"
        :class="props.active === item.key ? 'pill-link--active' : ''"
        @click="goTo(item.href)"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.pill-link {
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.45);
  padding: 0.5rem 0.95rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #034485;
  background: #ffffff;
  transition: all 0.2s ease;
  text-align: left;
  width: 100%;
}

.pill-link:hover {
  background: rgba(3, 68, 133, 0.08);
}

.pill-link--active {
  background: #034485;
  color: #ffffff;
  border-color: #034485;
}
</style>
