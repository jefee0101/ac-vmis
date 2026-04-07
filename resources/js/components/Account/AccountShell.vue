<script setup lang="ts">
import AccountNav from '@/components/Account/AccountNav.vue'

defineProps<{
  active: 'profile' | 'settings' | 'account' | 'notifications' | 'preferences' | 'help'
}>()
</script>

<template>
  <div class="account-shell">
    <aside class="account-shell__sidebar">
      <AccountNav :active="active" />
    </aside>
    <main class="account-shell__content">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.account-shell {
  display: grid;
  gap: 1.5rem;
  align-items: start;
}

.account-shell__sidebar,
.account-shell__content {
  min-width: 0;
}

@media (min-width: 1024px) {
  .account-shell {
    grid-template-columns: 16rem minmax(0, 1fr);
  }

  .account-shell__sidebar {
    position: sticky;
    top: 1.5rem;
  }
}
</style>
