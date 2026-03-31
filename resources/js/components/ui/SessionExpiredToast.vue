<script setup lang="ts">
import { useSessionExpired } from '@/composables/useSessionExpired'

const { visible, message, hideSessionExpired } = useSessionExpired()
</script>

<template>
  <transition name="session-pop">
    <div v-if="visible" class="session-overlay" role="alert" aria-live="assertive">
      <div class="session-card">
        <h3 class="session-title">Session expired</h3>
        <p class="session-message">{{ message }}</p>
        <div class="session-actions">
          <button type="button" class="session-btn" @click="hideSessionExpired">Dismiss</button>
          <a href="/login" class="session-link">Back to login</a>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.session-overlay {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.55);
  padding: 1.25rem;
}

.session-card {
  width: min(420px, 95vw);
  border-radius: 18px;
  border: 1px solid rgba(3, 68, 133, 0.45);
  background: #ffffff;
  padding: 1.4rem 1.6rem;
  box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
  text-align: left;
}

.session-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}

.session-message {
  margin-top: 0.5rem;
  font-size: 0.92rem;
  color: #475569;
}

.session-actions {
  margin-top: 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  align-items: center;
}

.session-btn {
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.45);
  padding: 0.45rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #034485;
  background: #ffffff;
  transition: background 0.2s ease;
}

.session-btn:hover {
  background: rgba(3, 68, 133, 0.08);
}

.session-link {
  border-radius: 999px;
  background: #034485;
  padding: 0.45rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #ffffff;
  transition: background 0.2s ease;
}

.session-link:hover {
  background: #04519f;
}

.session-pop-enter-active,
.session-pop-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.session-pop-enter-from,
.session-pop-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}
</style>
