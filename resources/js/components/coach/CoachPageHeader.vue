<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="coach-page-header">
    <h1 class="coach-page-header__title">{{ title }}</h1>
    <p v-if="subtitle" class="coach-page-header__subtitle">{{ subtitle }}</p>
    <slot />
  </header>
</template>

<style scoped>
.coach-page-header {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.coach-page-header__title {
  font-size: 1.35rem;
  line-height: 1.25;
  font-weight: 800;
  color: #0f172a;
}

.coach-page-header__subtitle {
  font-size: 0.82rem;
  color: #64748b;
}

@media (min-width: 768px) {
  .coach-page-header__title {
    font-size: 1.65rem;
  }

  .coach-page-header__subtitle {
    font-size: 0.9rem;
  }
}
</style>
