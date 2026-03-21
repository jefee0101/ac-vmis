<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = withDefaults(defineProps<{
  dark?: boolean
  menuPlacement?: 'bottom' | 'top' | 'right'
  compact?: boolean
}>(), {
  dark: false,
  menuPlacement: 'bottom',
  compact: false,
})

const page = usePage()
const menuOpen = ref(false)

const user = computed(() => page.props.auth?.user ?? null)

const avatarUrl = computed(() => {
  const path = String(user.value?.avatar ?? '')
  if (!path) return '/images/default-avatar.svg'
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
})

const fullName = computed(() => String(user.value?.name ?? 'User'))
const menuPanelClass = computed(() => {
  if (props.menuPlacement === 'top') {
    return 'absolute right-0 bottom-full mb-2 w-48 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
  }
  if (props.menuPlacement === 'right') {
    return 'absolute left-full -top-2 ml-2 w-48 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
  }
  return 'absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
})

function goProfile() {
  menuOpen.value = false
  router.get('/account/profile')
}

function goSettings() {
  menuOpen.value = false
  router.get('/account/settings')
}

function logout() {
  menuOpen.value = false
  router.post('/logout')
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="account-card"
      :class="[
        dark ? 'account-card-dark' : 'account-card-light',
        compact ? 'account-card-compact' : '',
      ]"
      @click="menuOpen = !menuOpen"
      :title="compact ? fullName : ''"
    >
      <img :src="avatarUrl" alt="Profile" class="account-avatar h-9 w-9 rounded-full object-cover border border-white/40" />
      <div v-if="!compact" class="min-w-0 text-left">
        <p class="text-xs opacity-70 leading-none">Account</p>
        <p class="text-sm font-semibold truncate leading-tight">{{ fullName }}</p>
      </div>
    </button>

    <div
      v-if="menuOpen"
      :class="menuPanelClass"
    >
      <button @click="goProfile" class="menu-item">Profile</button>
      <button @click="goSettings" class="menu-item">Settings</button>
      <button @click="logout" class="menu-item text-red-600">Logout</button>
    </div>
  </div>
</template>

<style scoped>
.account-card {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 6px 10px;
  min-width: 180px;
  max-width: 240px;
}

.account-card-light {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.account-card-compact {
  min-width: 0;
  max-width: 44px;
  justify-content: center;
  padding: 4px;
}

.account-card-dark {
  border: 1px solid #475569;
  background: #1e293b;
  color: #e2e8f0;
}

.menu-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  font-size: 14px;
  color: #1e293b;
}

.menu-item:hover {
  background: #f8fafc;
}
</style>
