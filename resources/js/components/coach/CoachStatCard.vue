<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  note?: string
}>()
</script>

<template>
  <article class="coach-stat-card">
    <p class="coach-stat-card__label">{{ label }}</p>
    <p class="coach-stat-card__value">{{ value }}</p>
    <p v-if="note" class="coach-stat-card__note">{{ note }}</p>
  </article>
</template>

<style scoped>
.coach-stat-card {
  border: 1px solid #d1d5db;
  border-radius: 0.85rem;
  background: #ffffff;
  padding: 0.95rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.coach-stat-card__label {
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  font-weight: 700;
}

.coach-stat-card__value {
  margin-top: 0.2rem;
  font-size: 1.45rem;
  line-height: 1.1;
  font-weight: 800;
  color: #0f172a;
}

.coach-stat-card__note {
  margin-top: 0.3rem;
  font-size: 0.78rem;
  color: #475569;
}
</style>
