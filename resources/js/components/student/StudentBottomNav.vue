<script setup lang="ts">
import type { StudentNavItem } from '@/config/studentNav'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  items: StudentNavItem[]
  isActive: (route: string) => boolean
}>()

function go(route: string) {
  router.get(route)
}

function iconPath(icon: string) {
  if (icon === 'layout-grid') return 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'
  if (icon === 'users') return 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6'
  if (icon === 'calendar') return 'M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'
  if (icon === 'heart-pulse') return 'M22 12h-4l-3 8-4-16-3 8H2'
  if (icon === 'graduation-cap') return 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5'
  if (icon === 'bell') return 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0'
  return 'M12 2v20M2 12h20'
}
</script>

<template>
  <nav class="student-bottom-nav" aria-label="Primary">
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="student-bottom-nav__item"
      :class="isActive(item.route) ? 'student-bottom-nav__item--active' : ''"
      @click="go(item.route)"
    >
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="iconPath(item.icon)" />
      </svg>
      <span>{{ item.mobileLabel || item.label }}</span>
    </button>
  </nav>
</template>

<style scoped>
.student-bottom-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 45;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  border-top: 1px solid #cbd5e1;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(10px);
  padding: 0.35rem 0.5rem calc(env(safe-area-inset-bottom, 0px) + 0.35rem);
}

.student-bottom-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.12rem;
  border-radius: 0.6rem;
  color: #64748b;
  padding: 0.35rem 0.2rem;
  font-size: 0.68rem;
  font-weight: 600;
}

.student-bottom-nav__item--active {
  color: #1f2937;
  background: #e6f0fb;
}

@media (min-width: 768px) {
  .student-bottom-nav {
    display: none;
  }
}
</style>
