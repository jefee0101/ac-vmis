import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, ref } from 'vue'

export function useInertiaLoading() {
    const isLoading = ref(false)
    let activeVisits = 0

    const removeStart = router.on('start', () => {
        activeVisits += 1
        isLoading.value = true
    })

    const removeFinish = router.on('finish', () => {
        activeVisits = Math.max(0, activeVisits - 1)
        isLoading.value = activeVisits > 0
    })

    onBeforeUnmount(() => {
        removeStart()
        removeFinish()
    })

    return { isLoading }
}
