<script setup lang="ts">
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import FormAlert from '@/components/ui/form/FormAlert.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSportColors } from '@/composables/useSportColors'
import { useUserTimezone } from '@/composables/useUserTimezone'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Spinner from '@/components/ui/spinner/Spinner.vue'

defineOptions({
    layout: CoachDashboard,
})

const props = defineProps<{
    mode?: 'index' | 'detail'
    teams?: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId?: number | null
    team: {
        id: number
        team_name: string
        sport: string
    } | null
    schedules: Array<{
        id: number
        title: string
        type: string
        venue: string
        start: string
        end: string
        qr_closes_at: string
        is_qr_open: boolean
    }>
    selectedScheduleId: number | null
    attendancePagination?: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    } | null
    attendanceRows: Array<{
        student_id: number
        student_id_number: string | null
        full_name: string
        jersey_number: string | number | null
        athlete_position: string | null
        attendance_status: string | null
        verification_method: string | null
        attendance_notes: string | null
        recorded_at: string | null
        wellness: {
            injury_observed: boolean
            injury_notes: string | null
            fatigue_level: number | null
            performance_condition: 'excellent' | 'good' | 'fair' | 'poor' | null
            remarks: string | null
            log_id: number | null
        }
    }>
}>()

const selectedSchedule = ref<number | null>(props.selectedScheduleId)
const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const scanToken = ref('')
const scanSubmitting = ref(false)
const scheduleLoading = ref(false)
const manualSubmittingKey = ref<string | null>(null)
const showScannerModal = ref(false)
const scannerError = ref<string | null>(null)
const scannerScheduleId = ref<number | null>(null)
const scannerBusy = ref(false)
const scannerStarting = ref(false)
const manualReasonDialogOpen = ref(false)
const manualReason = ref('')
const pendingManualOverride = ref<{ scheduleId: number; studentId: number } | null>(null)
const wellnessDrawerOpen = ref(false)
const wellnessSaving = ref(false)
const wellnessMessage = ref<string | null>(null)
const wellnessTarget = ref<{ studentId: number; name: string; status: string | null } | null>(null)
const wellnessForm = ref({
    injury_observed: false,
    injury_notes: '',
    fatigue_level: '3',
    performance_condition: 'good',
    remarks: '',
})
const videoEl = ref<HTMLVideoElement | null>(null)
let mediaStream: MediaStream | null = null
let scannerLoopId: number | null = null
const { sportColor, sportTextColor, sportLabel } = useSportColors()
const { timezone } = useUserTimezone()
const scheduleSearch = ref('')
const scheduleType = ref('all')
const scheduleStart = ref('')
const scheduleEnd = ref('')

function tintHex(hex: string, amount: number) {
    const clean = hex.replace('#', '')
    if (clean.length !== 6) return hex
    const r = parseInt(clean.slice(0, 2), 16)
    const g = parseInt(clean.slice(2, 4), 16)
    const b = parseInt(clean.slice(4, 6), 16)
    const mix = (channel: number) => Math.round(channel + (255 - channel) * amount)
    return `#${mix(r).toString(16).padStart(2, '0')}${mix(g).toString(16).padStart(2, '0')}${mix(b).toString(16).padStart(2, '0')}`
}

function stripeColors(sport: any) {
    const base = sportColor(sport)
    const lighter = tintHex(base, 0.55)
    return { base, lighter }
}

const viewMode = computed(() => props.mode ?? 'index')
const isDetail = computed(() => viewMode.value === 'detail')
const selectedScheduleItem = computed(() => props.schedules.find((schedule) => schedule.id === selectedSchedule.value) ?? null)
const attendancePagination = computed(() => props.attendancePagination ?? null)
const scheduleTypes = computed(() => {
    const types = new Set<string>()
    props.schedules.forEach((item) => {
        if (item.type) types.add(String(item.type))
    })
    return Array.from(types).sort()
})

const filteredSchedules = computed(() => {
    const search = scheduleSearch.value.trim().toLowerCase()
    const typeFilter = scheduleType.value
    const startDate = scheduleStart.value ? new Date(`${scheduleStart.value}T00:00:00`) : null
    const endDate = scheduleEnd.value ? new Date(`${scheduleEnd.value}T23:59:59`) : null

    const matches = props.schedules.filter((item) => {
        const title = String(item.title ?? '').toLowerCase()
        const venue = String(item.venue ?? '').toLowerCase()
        const type = String(item.type ?? '').toLowerCase()
        const scheduleStartDate = item.start ? new Date(item.start) : null

        if (search && !title.includes(search) && !venue.includes(search) && !type.includes(search)) {
            return false
        }

        if (typeFilter !== 'all' && String(item.type ?? '').toLowerCase() !== typeFilter) {
            return false
        }

        if (startDate && scheduleStartDate && scheduleStartDate < startDate) {
            return false
        }

        if (endDate && scheduleStartDate && scheduleStartDate > endDate) {
            return false
        }

        return true
    })

    return matches.sort((a, b) => {
        const aTime = a.start ? new Date(a.start).getTime() : 0
        const bTime = b.start ? new Date(b.start).getTime() : 0
        return bTime - aTime
    })
})

watch(
    () => props.selectedScheduleId,
    (val) => {
        selectedSchedule.value = val
    }
)

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    }
)

watch(selectedSchedule, () => {
    wellnessDrawerOpen.value = false
    wellnessTarget.value = null
    wellnessMessage.value = null
})

function openSchedule(scheduleId: number) {
    if (viewMode.value === 'index') {
        const returnTo = typeof window === 'undefined'
            ? '/coach/operations?tab=attendance'
            : `${window.location.pathname}${window.location.search}`
        router.get(`/coach/attendance/${scheduleId}`, { return_to: returnTo })
        return
    }

    if (scheduleLoading.value) return

    if (selectedSchedule.value === scheduleId) {
        selectedSchedule.value = null
        return
    }

    selectedSchedule.value = scheduleId
    scheduleLoading.value = true
    const query: Record<string, any> = {
        tab: 'attendance',
        attendance_schedule_id: scheduleId,
    }
    if (selectedTeamId.value) {
        query.team_id = selectedTeamId.value
    }
    router.get('/coach/operations', query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            scheduleLoading.value = false
        },
    })
}

function formatPHT(dt: string | Date | null) {
    if (!dt) return '-'

    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleString('en-PH', {
        timeZone: timezone,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/coach/operations', { tab: 'attendance', team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function changeAttendancePage(page: number) {
    if (!page || page < 1) return
    if (isDetail.value) {
        const scheduleId = selectedScheduleItem.value?.id ?? props.selectedScheduleId
        if (!scheduleId) return
        const query: Record<string, any> = { attendance_page: page }
        const returnTo = currentReturnToParam()
        if (returnTo) {
            query.return_to = returnTo
        }
        router.get(`/coach/attendance/${scheduleId}`, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
        return
    }

    const query: Record<string, any> = {
        tab: 'attendance',
        attendance_page: page,
    }
    if (selectedTeamId.value) {
        query.team_id = selectedTeamId.value
    }
    if (selectedSchedule.value) {
        query.attendance_schedule_id = selectedSchedule.value
    }
    router.get('/coach/operations', query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function printFilledSheet() {
    if (!selectedScheduleItem.value) return
    window.open(`/coach/attendance/${selectedScheduleItem.value.id}/print`, '_blank')
}

function printBlankSheet() {
    if (!selectedScheduleItem.value) return
    window.open(`/coach/attendance/${selectedScheduleItem.value.id}/print-blank`, '_blank')
}

function statusLabel(status: string | null) {
    if (status === 'present') return 'Attending'
    if (status === 'absent') return 'Not Attending'
    if (status === 'excused') return 'Excused'
    if (status === 'late') return 'Late'
    return 'No Response'
}

function statusClass(status: string | null) {
    if (status === 'present') return 'border border-emerald-300 bg-emerald-50 text-emerald-700'
    if (status === 'absent') return 'border border-red-300 bg-red-50 text-red-700'
    if (status === 'excused') return 'border border-amber-300 bg-amber-50 text-amber-700'
    if (status === 'late') return 'border border-slate-300 bg-slate-50 text-slate-700'
    return 'border border-slate-300 bg-slate-100 text-slate-600'
}

function verificationLabel(method: string | null) {
    if (method === 'qr_verified') return 'QR Verified'
    if (method === 'manual_override') return 'Manual Override'
    if (method === 'self_response') return 'Self Response'
    return '-'
}

async function verifyQr(scheduleId: number, tokenOverride?: string) {
    const payload = (tokenOverride ?? scanToken.value).trim()
    if (!payload) return
    scanSubmitting.value = true

    return new Promise<void>((resolve) => {
        router.post(`/coach/schedules/${scheduleId}/attendance/verify-qr`, {
            token: payload,
        }, {
            preserveScroll: true,
            onFinish: () => {
                scanSubmitting.value = false
                scanToken.value = ''
                resolve()
            },
        })
    })
}

function manualPresent(scheduleId: number, studentId: number) {
    pendingManualOverride.value = { scheduleId, studentId }
    manualReason.value = ''
    manualReasonDialogOpen.value = true
}

function confirmManualPresent() {
    if (!pendingManualOverride.value || !manualReason.value.trim()) return
    const { scheduleId, studentId } = pendingManualOverride.value

    manualSubmittingKey.value = `${scheduleId}:${studentId}`
    manualReasonDialogOpen.value = false
    router.post(`/coach/schedules/${scheduleId}/attendance/manual/${studentId}`, {
        status: 'present',
        reason: manualReason.value.trim(),
    }, {
        preserveScroll: true,
        onFinish: () => {
            manualSubmittingKey.value = null
            pendingManualOverride.value = null
        },
    })
}

function canLogWellness(status: string | null) {
    if (!status) return false
    if (!['present', 'late', 'excused'].includes(status)) return false
    if (!selectedScheduleItem.value) return false
    return ['practice', 'game'].includes(String(selectedScheduleItem.value.type))
}

function wellnessDisabledReason(status: string | null) {
    if (!selectedScheduleItem.value) return 'Select a schedule first.'
    if (!['practice', 'game'].includes(String(selectedScheduleItem.value.type))) {
        return 'Wellness is only for practice or game schedules.'
    }
    if (!status) return 'No attendance status yet.'
    if (!['present', 'late', 'excused'].includes(status)) {
        return 'Only attended athletes can be logged for wellness.'
    }
    return ''
}

function wellnessActionLabel(logId: number | null) {
    return logId ? 'Edit Wellness' : 'Wellness Check'
}

function openWellness(row: (typeof props.attendanceRows)[number]) {
    if (!selectedSchedule.value || !canLogWellness(row.attendance_status)) return

    wellnessTarget.value = {
        studentId: row.student_id,
        name: row.full_name,
        status: row.attendance_status,
    }

    wellnessForm.value = {
        injury_observed: !!row.wellness?.injury_observed,
        injury_notes: row.wellness?.injury_notes ?? '',
        fatigue_level: row.wellness?.fatigue_level ? String(row.wellness.fatigue_level) : '3',
        performance_condition: row.wellness?.performance_condition ?? 'good',
        remarks: row.wellness?.remarks ?? '',
    }

    wellnessMessage.value = null
    wellnessDrawerOpen.value = true
}

function closeWellness() {
    wellnessDrawerOpen.value = false
    wellnessTarget.value = null
    wellnessMessage.value = null
}

function backToSchedules() {
    if (typeof window === 'undefined') {
        router.get('/coach/operations', { tab: 'attendance' })
        return
    }

    const params = new URLSearchParams(window.location.search)
    const returnTo = params.get('return_to')
    if (returnTo) {
        router.get(returnTo)
        return
    }

    router.get('/coach/operations', { tab: 'attendance' })
}

function currentReturnToParam() {
    if (typeof window === 'undefined') return null
    const params = new URLSearchParams(window.location.search)
    return params.get('return_to')
}

function saveWellness() {
    if (!selectedSchedule.value || !wellnessTarget.value) return
    wellnessSaving.value = true

    router.post('/coach/wellness', {
        schedule_id: selectedSchedule.value,
        student_id: wellnessTarget.value.studentId,
        injury_observed: wellnessForm.value.injury_observed,
        injury_notes: wellnessForm.value.injury_notes,
        fatigue_level: Number(wellnessForm.value.fatigue_level),
        performance_condition: wellnessForm.value.performance_condition,
        remarks: wellnessForm.value.remarks,
    }, {
        preserveScroll: true,
        onFinish: () => {
            wellnessSaving.value = false
            wellnessMessage.value = 'Wellness saved.'
        },
    })
}

function stopScanner() {
    if (scannerLoopId !== null) {
        window.cancelAnimationFrame(scannerLoopId)
        scannerLoopId = null
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop())
        mediaStream = null
    }

    scannerBusy.value = false
}

function closeScannerModal() {
    stopScanner()
    showScannerModal.value = false
    scannerScheduleId.value = null
    scannerError.value = null
}

async function startScanner(scheduleId: number) {
    scannerScheduleId.value = scheduleId
    showScannerModal.value = true
    scannerError.value = null
    scannerStarting.value = true

    try {
        const Detector = (window as any).BarcodeDetector
        if (!Detector) {
            scannerError.value = 'BarcodeDetector is not supported in this browser. Use manual token input.'
            return
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        })

        if (!videoEl.value) {
            scannerError.value = 'Camera view is unavailable.'
            return
        }

        videoEl.value.srcObject = mediaStream
        await videoEl.value.play()
        const detector = new Detector({ formats: ['qr_code'] })

        const scanFrame = async () => {
            if (!videoEl.value || scannerBusy.value) {
                scannerLoopId = window.requestAnimationFrame(scanFrame)
                return
            }

            try {
                const results = await detector.detect(videoEl.value)
                if (results?.length) {
                    const rawValue = String(results[0]?.rawValue ?? '').trim()
                    if (rawValue && scannerScheduleId.value) {
                        scannerBusy.value = true
                        await verifyQr(scannerScheduleId.value, rawValue)
                        closeScannerModal()
                        return
                    }
                }
            } catch {
                // Keep scanning on transient detection errors.
            }

            scannerLoopId = window.requestAnimationFrame(scanFrame)
        }

        scannerLoopId = window.requestAnimationFrame(scanFrame)
    } catch {
        scannerError.value = 'Unable to access camera. Check browser permissions and HTTPS/localhost.'
    } finally {
        scannerStarting.value = false
    }
}

onBeforeUnmount(() => {
    stopScanner()
})
</script>

<template>
    <div class="space-y-4">
        <div v-if="props.teams?.length && !isDetail" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
            <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                <select
                    v-model.number="selectedTeamId"
                    @change="changeTeam"
                    class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                >
                    <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                        {{ teamOption.team_name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="!team" class="rounded-xl border border-slate-200 bg-white p-6 text-slate-500">
            You are not assigned to a team yet.
        </div>

        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                <span class="px-2 py-0.5 rounded" :style="{ backgroundColor: sportColor(team.sport), color: sportTextColor(team.sport) }">
                    {{ sportLabel(team.sport) }}
                </span>
                <span>{{ team.team_name }}</span>
            </div>

            <div class="space-y-4">
                <div v-if="schedules.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                    No schedules available.
                </div>

                <div v-else-if="!isDetail" class="space-y-3">
                    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-[#034485]/40 bg-[#034485] p-3">
                        <label class="flex w-full flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-white/80 sm:w-[220px]">
                            Search
                            <input
                                v-model="scheduleSearch"
                                type="text"
                                placeholder="Search title, type, venue..."
                                class="rounded-lg border border-white/40 bg-white px-3 py-2 text-sm font-medium text-slate-700 placeholder:text-slate-400"
                            />
                        </label>
                        <label class="flex w-full flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-white/80 sm:w-[180px]">
                            Type
                            <select
                                v-model="scheduleType"
                                class="rounded-lg border border-white/40 bg-white px-3 py-2 text-sm font-medium text-slate-700"
                            >
                                <option value="all">All types</option>
                                <option v-for="type in scheduleTypes" :key="type" :value="type.toLowerCase()">
                                    {{ type }}
                                </option>
                            </select>
                        </label>
                        <label class="flex w-full flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-white/80 sm:w-[160px]">
                            From
                            <input
                                v-model="scheduleStart"
                                type="date"
                                class="rounded-lg border border-white/40 bg-white px-3 py-2 text-sm font-medium text-slate-700"
                            />
                        </label>
                        <label class="flex w-full flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-white/80 sm:w-[160px]">
                            To
                            <input
                                v-model="scheduleEnd"
                                type="date"
                                class="rounded-lg border border-white/40 bg-white px-3 py-2 text-sm font-medium text-slate-700"
                            />
                        </label>
                    </div>

                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schedules</p>
                        <span class="text-xs text-slate-400">{{ schedules.length }} total</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in filteredSchedules"
                        :key="item.id"
                        type="button"
                        @click="openSchedule(item.id)"
                        class="group relative overflow-hidden rounded-3xl border border-[#034485]/40 bg-white p-4 text-left transition hover:border-[#034485]/70"
                    >
                        <div class="pointer-events-none absolute left-1/2 top-1/2 flex h-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-6 gap-1 opacity-60">
                            <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(team?.sport).base }"></span>
                            <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(team?.sport).lighter }"></span>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ item.type || '-' }} • {{ item.venue || '-' }}</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                  :class="item.is_qr_open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                {{ item.is_qr_open ? 'QR Open' : 'QR Closed' }}
                            </span>
                        </div>
                        <div class="mt-3 space-y-1 text-xs text-slate-600">
                            <div><span class="text-slate-500">Start:</span> {{ formatPHT(item.start) }}</div>
                            <div><span class="text-slate-500">End:</span> {{ formatPHT(item.end) }}</div>
                        </div>
                        <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-[#034485] px-3 py-1 text-[11px] font-semibold text-white">
                            View Attendance
                            <span class="text-sm leading-none">→</span>
                        </div>
                    </button>
                    </div>
                </div>

                <div v-if="!isDetail && !selectedScheduleItem && schedules.length > 0" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                    Select a schedule card to view attendance and log wellness.
                </div>

                <div v-if="isDetail" class="flex items-center">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 text-xs font-semibold text-[#1f2937] hover:text-[#111827]"
                        @click="backToSchedules"
                    >
                        <span class="text-base leading-none">←</span>
                        Back to schedules
                    </button>
                </div>

                <section v-if="isDetail && selectedScheduleItem" class="space-y-4">
                    <div class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="text-lg font-semibold text-slate-900">{{ selectedScheduleItem.title }}</div>
                                <div class="mt-1 text-xs text-slate-600">
                                    {{ selectedScheduleItem.type || '-' }} • {{ selectedScheduleItem.venue || '-' }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ formatPHT(selectedScheduleItem.start) }} → {{ formatPHT(selectedScheduleItem.end) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="selectedScheduleItem.is_qr_open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ selectedScheduleItem.is_qr_open ? 'QR Open' : 'QR Closed' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-600">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Type</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedScheduleItem.type || '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-600">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Venue</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedScheduleItem.venue || '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-600">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Start</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatPHT(selectedScheduleItem.start) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-600">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">End</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatPHT(selectedScheduleItem.end) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-600 sm:col-span-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">QR Window Closes</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatPHT(selectedScheduleItem.qr_closes_at) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                @click="printFilledSheet"
                            >
                                Print Filled Sheet
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                @click="printBlankSheet"
                            >
                                Print Blank Sheet
                            </button>
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <label class="mb-2 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Scan Token Input</label>
                            <div class="flex flex-wrap gap-2">
                                <input
                                    v-model="scanToken"
                                    type="text"
                                    class="flex-1 rounded border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900"
                                    placeholder="Paste or scan token payload here"
                                />
                                <button
                                    @click="verifyQr(selectedScheduleItem.id)"
                                    :disabled="scanSubmitting || !selectedScheduleItem.is_qr_open"
                                    class="px-3 py-2 rounded bg-indigo-600 text-white text-xs hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <Spinner v-if="scanSubmitting" class="h-3 w-3 text-white" />
                                        {{ scanSubmitting ? 'Verifying...' : 'Verify QR' }}
                                    </span>
                                </button>
                                <button
                                    @click="startScanner(selectedScheduleItem.id)"
                                    :disabled="!selectedScheduleItem.is_qr_open || scannerStarting"
                                    class="px-3 py-2 rounded bg-[#1f2937] text-white text-xs hover:bg-[#111827] disabled:opacity-50"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <Spinner v-if="scannerStarting" class="h-3 w-3 text-white" />
                                        {{ scannerStarting ? 'Starting Camera...' : 'Open Camera' }}
                                    </span>
                                </button>
                            </div>
                            <p class="mt-2 text-[11px]" :class="selectedScheduleItem.is_qr_open ? 'text-emerald-700' : 'text-red-700'">
                                {{ selectedScheduleItem.is_qr_open ? 'QR check-in is open.' : 'QR check-in is closed.' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="scheduleLoading" class="space-y-2 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <Skeleton class="h-4 w-1/3 bg-slate-200/80" />
                        <Skeleton class="h-10 bg-slate-200/80" />
                        <Skeleton class="h-10 bg-slate-200/80" />
                    </div>

                    <div v-else-if="attendanceRows.length === 0" class="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                        No student-athletes found for this team.
                    </div>

                    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:hidden">
                        <article v-for="row in attendanceRows" :key="row.student_id" class="border-b border-slate-200 px-4 py-3 last:border-b-0">
                            <p class="font-semibold text-slate-900">{{ row.full_name }}</p>
                            <p class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                <p>Jersey: {{ row.jersey_number || '-' }}</p>
                                <p>Position: {{ row.athlete_position || '-' }}</p>
                                <p>Verification: {{ verificationLabel(row.verification_method) }}</p>
                                <p>Recorded: {{ formatPHT(row.recorded_at) }}</p>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-[11px] px-2 py-0.5 rounded" :class="statusClass(row.attendance_status)">{{ statusLabel(row.attendance_status) }}</span>
                                <button
                                    @click="manualPresent(selectedScheduleItem.id, row.student_id)"
                                    :disabled="manualSubmittingKey === `${selectedScheduleItem.id}:${row.student_id}`"
                                    class="rounded bg-amber-600 px-2 py-1 text-xs text-white hover:bg-amber-700 disabled:opacity-50"
                                >
                                    Manual Present
                                </button>
                                <button
                                    @click="openWellness(row)"
                                    :disabled="!canLogWellness(row.attendance_status)"
                                    :title="!canLogWellness(row.attendance_status) ? wellnessDisabledReason(row.attendance_status) : ''"
                                    class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    {{ wellnessActionLabel(row.wellness?.log_id ?? null) }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white lg:block">
                        <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-[#034485] text-white">
                            <tr>
                                <th class="px-4 py-3">Student</th>
                                <th class="px-4 py-3">ID Number</th>
                                <th class="px-4 py-3">Jersey</th>
                                <th class="px-4 py-3">Position</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Verification</th>
                                <th class="px-4 py-3">Notes</th>
                                <th class="px-4 py-3">Recorded</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in attendanceRows" :key="row.student_id" class="border-t border-slate-200">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.full_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ row.student_id_number || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ row.jersey_number || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ row.athlete_position || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[11px] px-2 py-0.5 rounded" :class="statusClass(row.attendance_status)">
                                        {{ statusLabel(row.attendance_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-700">{{ verificationLabel(row.verification_method) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ row.attendance_notes || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatPHT(row.recorded_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        <button
                                            @click="manualPresent(selectedScheduleItem.id, row.student_id)"
                                            :disabled="manualSubmittingKey === `${selectedScheduleItem.id}:${row.student_id}`"
                                            class="px-2 py-1 rounded bg-amber-600 text-white text-xs hover:bg-amber-700 disabled:opacity-50"
                                        >
                                            Manual Present
                                        </button>
                                        <button
                                            @click="openWellness(row)"
                                            :disabled="!canLogWellness(row.attendance_status)"
                                            :title="!canLogWellness(row.attendance_status) ? wellnessDisabledReason(row.attendance_status) : ''"
                                            class="px-2 py-1 rounded border border-slate-300 bg-white text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            {{ wellnessActionLabel(row.wellness?.log_id ?? null) }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        </table>
                        </div>
                    </div>

                    <div
                        v-if="attendancePagination && attendancePagination.last_page > 1"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600"
                    >
                        <span>
                            Page {{ attendancePagination.current_page }} of {{ attendancePagination.last_page }}
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400 disabled:opacity-50"
                                :disabled="attendancePagination.current_page <= 1"
                                @click="changeAttendancePage(attendancePagination.current_page - 1)"
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                class="rounded border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400 disabled:opacity-50"
                                :disabled="attendancePagination.current_page >= attendancePagination.last_page"
                                @click="changeAttendancePage(attendancePagination.current_page + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </section>

                <div v-else-if="isDetail" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                    Schedule not found or unavailable.
                </div>
            </div>
        </div>

        <div
            v-if="showScannerModal"
            @click.self="closeScannerModal"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold text-slate-900">QR Scanner</h3>
                    <button @click="closeScannerModal" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <div class="p-4 space-y-3">
                    <p class="text-xs text-slate-500">
                        Point your camera at the student's rotating QR.
                    </p>
                    <p v-if="scannerStarting" class="text-xs text-emerald-300">
                        Starting camera...
                    </p>
                    <video
                        ref="videoEl"
                        autoplay
                        playsinline
                        muted
                        class="w-full rounded-lg border border-gray-700 bg-black min-h-64"
                    />
                    <FormAlert tone="error" compact :message="scannerError" />
                </div>
            </div>
        </div>

        <div v-if="wellnessDrawerOpen" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-slate-900/50" @click="closeWellness" />
            <div class="absolute right-0 top-0 h-full w-full max-w-md overflow-y-auto bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-slate-200 bg-slate-50/60 px-4 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#1f2937]">Wellness Check</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ wellnessTarget?.name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ selectedScheduleItem?.title || 'Selected Schedule' }}
                            • {{ statusLabel(wellnessTarget?.status ?? null) }}
                        </p>
                    </div>
                    <button @click="closeWellness" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <div class="space-y-4 px-4 py-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="wellnessForm.injury_observed" type="checkbox" />
                        Injury Observed
                    </label>

                    <div>
                        <label class="text-xs text-slate-500">Fatigue Level</label>
                        <select
                            v-model="wellnessForm.fatigue_level"
                            class="w-full rounded border border-slate-300 px-2 py-2 text-sm text-slate-900"
                        >
                            <option value="1">1 - Very Low</option>
                            <option value="2">2 - Low</option>
                            <option value="3">3 - Moderate</option>
                            <option value="4">4 - High</option>
                            <option value="5">5 - Very High</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-500">Performance Condition</label>
                        <select
                            v-model="wellnessForm.performance_condition"
                            class="w-full rounded border border-slate-300 px-2 py-2 text-sm text-slate-900"
                        >
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-500">Injury Notes</label>
                        <input
                            v-model="wellnessForm.injury_notes"
                            type="text"
                            class="w-full rounded border border-slate-300 px-2 py-2 text-sm text-slate-900"
                            placeholder="Optional"
                        />
                    </div>

                    <div>
                        <label class="text-xs text-slate-500">Remarks</label>
                        <textarea
                            v-model="wellnessForm.remarks"
                            rows="3"
                            class="w-full rounded border border-slate-300 px-2 py-2 text-sm text-slate-900"
                            placeholder="Optional"
                        />
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p v-if="wellnessMessage" class="text-xs text-emerald-600">{{ wellnessMessage }}</p>
                        <button
                            @click="saveWellness"
                            :disabled="wellnessSaving"
                            class="ml-auto rounded bg-[#1f2937] px-3 py-2 text-sm font-semibold text-white hover:bg-[#111827] disabled:opacity-50"
                        >
                            {{ wellnessSaving ? 'Saving...' : 'Save Wellness' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="manualReasonDialogOpen"
            title="Manual Attendance Override"
            description="Provide a reason for this manual present status."
            confirm-text="Apply Override"
            @update:open="manualReasonDialogOpen = $event"
            @confirm="confirmManualPresent"
        >
            <label class="mb-1 block text-xs font-medium text-slate-600">Reason</label>
            <textarea
                v-model="manualReason"
                rows="3"
                class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                placeholder="Required reason"
            />
            <p v-if="!manualReason.trim()" class="mt-1 text-xs text-red-600">Reason is required.</p>
        </ConfirmDialog>
    </div>
</template>
