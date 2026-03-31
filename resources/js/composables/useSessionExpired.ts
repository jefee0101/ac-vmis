import { ref } from 'vue'

const visible = ref(false)
const message = ref('Your session expired. Please log in again.')

export function useSessionExpired() {
  function showSessionExpired(customMessage?: string) {
    message.value = customMessage || 'Your session expired. Please log in again.'
    visible.value = true
  }

  function hideSessionExpired() {
    visible.value = false
  }

  return { visible, message, showSessionExpired, hideSessionExpired }
}
