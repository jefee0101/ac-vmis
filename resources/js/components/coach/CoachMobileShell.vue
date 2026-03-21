<script setup lang="ts">
import AnnouncementBellButton from '@/components/AnnouncementBellButton.vue'
import UserAccountMenu from '@/components/UserAccountMenu.vue'
import CoachBottomNav from '@/components/coach/CoachBottomNav.vue'
import RoleFooter from '@/components/ui/RoleFooter.vue'
import { coachPrimaryNav, coachSecondaryNav, type CoachNavItem } from '@/config/coachNav'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps<{
  title?: string
}>()

const page = usePage()
const currentPath = computed(() => String(page.url || ''))
const unreadCount = computed(() => Number(page.props.auth?.announcements?.unread_count ?? 0))
const mobileMenuOpen = ref(false)
const isNavCollapsed = ref(false)

const primaryItems = coachPrimaryNav
const secondaryItems = coachSecondaryNav
const hasSecondaryItems = secondaryItems.length > 0
const navToggleLabel = computed(() => (isNavCollapsed.value ? 'Expand sidebar' : 'Collapse sidebar'))

const footerLinks = [
  { label: 'Dashboard', href: '/coach/dashboard' },
  { label: 'Schedule', href: '/coach/schedule' },
  { label: 'Operations', href: '/coach/operations' },
  { label: 'Team', href: '/coach/team' },
  { label: 'Academics', href: '/coach/academics' },
  { label: 'Announcements', href: '/announcements' },
  { label: 'Profile', href: '/account/profile' },
  { label: 'Settings', href: '/account/settings' },
]

const activeLabel = computed(() => {
  const all = [...primaryItems, ...secondaryItems]
  const found = all.find((item) => isActive(item.route))
  return found?.label ?? props.title ?? 'Coach Workspace'
})

function isActive(route: string): boolean {
  return currentPath.value === route || currentPath.value.startsWith(`${route}/`)
}

function go(route: string) {
  mobileMenuOpen.value = false
  router.get(route)
}

function logout() {
  mobileMenuOpen.value = false
  router.post('/logout')
}

function toggleNav() {
  isNavCollapsed.value = !isNavCollapsed.value
}

function onEsc(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    mobileMenuOpen.value = false
  }
}

function iconPath(icon: string) {
  if (icon === 'layout-grid') return 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'
  if (icon === 'users') return 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6'
  if (icon === 'calendar') return 'M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'
  if (icon === 'heart-pulse') return 'M22 12h-4l-3 8-4-16-3 8H2'
  if (icon === 'graduation-cap') return 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5'
  if (icon === 'clipboard-check') return 'M9 3h6M9 1h6a2 2 0 0 1 2 2v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a2 2 0 0 1 2-2zm-1 11l2 2 4-4'
  if (icon === 'bell') return 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0'
  if (icon === 'user') return 'M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'
  if (icon === 'settings') return 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'
  return 'M12 2v20M2 12h20'
}

onMounted(() => {
  const saved = window.localStorage.getItem('coach_nav_collapsed')
  if (saved !== null) {
    isNavCollapsed.value = saved === '1'
  }
  window.addEventListener('keydown', onEsc)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onEsc)
  document.body.style.overflow = ''
})

watch(mobileMenuOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})

watch(isNavCollapsed, (collapsed) => {
  window.localStorage.setItem('coach_nav_collapsed', collapsed ? '1' : '0')
})
</script>

<template>
  <div class="coach-shell min-h-screen bg-slate-50 text-slate-900" :class="isNavCollapsed ? 'coach-shell--collapsed' : ''">
    <div class="coach-shell__glow pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(15, 23, 42,0.10),transparent_40%)]" />

    <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-slate-900/45 md:hidden" @click="mobileMenuOpen = false" />

    <div class="transition-all duration-300 ease-out" :class="isNavCollapsed ? 'md:pl-20' : 'md:pl-64'">
      <header class="coach-shell__topbar sticky top-0 z-50 border-b border-slate-200 backdrop-blur">
        <div class="mx-auto flex w-full max-w-[1600px] items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
          <button
            type="button"
            class="coach-shell__nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border md:hidden"
            @click="mobileMenuOpen = true"
            aria-label="Open menu"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
          </button>

          <div class="min-w-0 flex-1">
            <div class="coach-shell__brand">
              <p class="coach-shell__brand-title truncate text-sm font-extrabold uppercase tracking-[0.08em] text-white">AC VMIS Coach</p>
              <p class="coach-shell__brand-subtitle truncate text-xs font-semibold text-blue-100">{{ activeLabel }}</p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <AnnouncementBellButton :unread-count="unreadCount" :dark="false" @click="go('/announcements')" />
            <UserAccountMenu :dark="false" menu-placement="bottom" compact />
          </div>
        </div>
      </header>

    <aside
      class="hidden border-r border-slate-200 bg-white md:fixed md:inset-y-0 md:left-0 md:z-40 md:flex md:flex-col"
      :class="isNavCollapsed ? 'md:w-20' : 'md:w-64'"
    >
      <div class="coach-shell__sidebar-header flex items-start justify-between gap-2 border-b border-slate-200 px-4 py-4">
        <div class="coach-shell__brand" :class="isNavCollapsed ? 'coach-shell__brand--collapsed' : ''">
          <p
            class="coach-shell__brand-title text-xs font-extrabold uppercase tracking-[0.08em] text-white"
            :class="isNavCollapsed ? 'text-[10px]' : ''"
          >
            AC VMIS Coach
          </p>
          <p v-if="!isNavCollapsed" class="coach-shell__brand-subtitle mt-1 text-sm font-semibold text-blue-100">Coach Workspace</p>
        </div>
        <button
          type="button"
          class="coach-shell__nav-toggle hidden h-9 w-9 items-center justify-center rounded-lg border md:inline-flex"
          :aria-label="navToggleLabel"
          :title="navToggleLabel"
          @click="toggleNav"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path v-if="isNavCollapsed" d="M9 6l6 6-6 6" />
            <path v-else d="M15 6l-6 6 6 6" />
          </svg>
        </button>
      </div>

      <nav class="flex-1 space-y-1 px-3 py-3" aria-label="Primary">
        <button
          v-for="item in primaryItems"
          :key="item.key"
          type="button"
          class="coach-side-link"
          :class="[isActive(item.route) ? 'coach-side-link--active' : '', isNavCollapsed ? 'coach-side-link--collapsed' : '']"
          :title="item.label"
          :aria-label="item.label"
          @click="go(item.route)"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="iconPath(item.icon)" />
          </svg>
          <span v-if="!isNavCollapsed">{{ item.label }}</span>
        </button>
      </nav>

      <div class="border-t border-slate-200 px-3 py-3">
        <div v-if="hasSecondaryItems">
          <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">More</p>
          <button
          v-for="item in secondaryItems"
          :key="item.key"
          type="button"
          class="coach-side-link"
          :class="[isActive(item.route) ? 'coach-side-link--active' : '', isNavCollapsed ? 'coach-side-link--collapsed' : '']"
          :title="item.label"
          :aria-label="item.label"
          @click="go(item.route)"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="iconPath(item.icon)" />
          </svg>
          <span v-if="!isNavCollapsed">{{ item.label }}</span>
        </button>
      </div>

        <button
          type="button"
          class="coach-side-link text-red-600"
          :class="isNavCollapsed ? 'coach-side-link--collapsed' : ''"
          @click="logout"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5-5-5" />
            <path d="M21 12H9" />
          </svg>
          <span v-if="!isNavCollapsed">Logout</span>
        </button>
      </div>
    </aside>

    <aside
      class="fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r border-slate-200 bg-white p-4 transition md:hidden"
      :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="mb-4 flex items-center justify-between">
        <p class="text-sm font-bold text-[#1f2937]">Coach Menu</p>
        <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="mobileMenuOpen = false">Close</button>
      </div>

      <div class="space-y-2">
        <button
          v-for="item in [...primaryItems, ...secondaryItems]"
          :key="item.key"
          type="button"
          class="coach-side-link w-full"
          :class="isActive(item.route) ? 'coach-side-link--active' : ''"
          @click="go(item.route)"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="iconPath(item.icon)" />
          </svg>
          <span>{{ item.label }}</span>
        </button>

        <button type="button" class="coach-side-link w-full text-red-600" @click="logout">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5-5-5" />
            <path d="M21 12H9" />
          </svg>
          <span>Logout</span>
        </button>
      </div>
    </aside>

      <main
        class="mx-auto w-full max-w-[1600px] px-4 py-4 pb-[calc(env(safe-area-inset-bottom,0px)+5.5rem)] sm:px-6 md:px-6 md:pb-6 lg:px-8"
      >
        <slot />
      </main>

      <RoleFooter
        title="Coach Workspace"
        description="Manage schedules, attendance checks, wellness tracking, and academic visibility in one place."
        :links="footerLinks"
        :bottom-nav="true"
      />
    </div>

    <CoachBottomNav :items="primaryItems" :is-active="isActive" />
  </div>
</template>

<style scoped>
.coach-side-link {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.55rem;
  border-radius: 0.65rem;
  padding: 0.55rem 0.65rem;
  font-size: 0.83rem;
  font-weight: 600;
  color: #334155;
  transition: background-color 120ms ease, color 120ms ease;
}

.coach-side-link:hover {
  background: #f1f5f9;
}

.coach-side-link--active {
  background: #e2e8f0;
  color: #1f2937;
}

.coach-side-link--collapsed {
  justify-content: center;
  padding: 0.6rem;
}
</style>
