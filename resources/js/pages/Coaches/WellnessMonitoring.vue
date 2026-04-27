<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import { showAppToast } from '@/composables/useAppToast'
import { useSportColors } from '@/composables/useSportColors'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
    layout: CoachDashboard,
})

type AthleteRow = {
    student_id: number
    student_id_number: string | null
    name: string
    attendance_status: string
    wellness: {
        injury_observed: boolean
        injury_notes: string | null
        fatigue_level: number | null
        performance_condition: 'excellent' | 'good' | 'fair' | 'poor' | null
        remarks: string | null
        log_id: number | null
    }
}

type FormState = {
    injury_observed: boolean
    injury_notes: string
    fatigue_level: string
    performance_condition: 'excellent' | 'good' | 'fair' | 'poor'
    remarks: string
}

type FilterKey = 'all' | 'injury' | 'needs_review' | 'saved'

const props = defineProps<{
    team: { id: number; team_name: string; sport: string } | null
    schedules: Array<{
        id: number
        title: string
        type: string
        venue: string
        start: string
        end: string
    }>
    selectedScheduleId: number | null
    athletes: AthleteRow[]
}>()

const selectedSchedule = ref<number | null>(props.selectedScheduleId)
const savingKey = ref<string | null>(null)
const search = ref('')
const filter = ref<FilterKey>('all')
const { sportColor, sportTextColor, sportLabel } = useSportColors()
const filterOptions: Array<{ key: FilterKey; label: string }> = [
    { key: 'all', label: 'All Athletes' },
    { key: 'needs_review', label: 'Needs Review' },
    { key: 'injury', label: 'Injury Flags' },
    { key: 'saved', label: 'Already Logged' },
]

function buildRowForms(rows: AthleteRow[]) {
    return Object.fromEntries(
        (rows || []).map((row) => [
            row.student_id,
            {
                injury_observed: !!row.wellness?.injury_observed,
                injury_notes: row.wellness?.injury_notes ?? '',
                fatigue_level: row.wellness?.fatigue_level ? String(row.wellness.fatigue_level) : '3',
                performance_condition: row.wellness?.performance_condition ?? 'good',
                remarks: row.wellness?.remarks ?? '',
            } satisfies FormState,
        ]),
    )
}

const rowForms = ref<Record<number, FormState>>(buildRowForms(props.athletes || []))

watch(
    () => props.athletes,
    (rows) => {
        rowForms.value = buildRowForms(rows || [])
    },
    { immediate: true },
)

const selectedScheduleDetails = computed(() => {
    return props.schedules.find((item) => item.id === selectedSchedule.value) ?? null
})

const teamTone = computed(() => {
    const base = sportColor(props.team?.sport ?? '')
    return {
        backgroundColor: `${base}14`,
        borderColor: `${base}55`,
        color: sportTextColor(props.team?.sport ?? ''),
    }
})

const filteredAthletes = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.athletes.filter((row) => {
        const form = rowForms.value[row.student_id]
        const matchesQuery = query === ''
            || row.name.toLowerCase().includes(query)
            || String(row.student_id_number ?? '').toLowerCase().includes(query)

        if (!matchesQuery) return false
        if (!form) return true

        if (filter.value === 'injury') return form.injury_observed
        if (filter.value === 'saved') return Boolean(row.wellness?.log_id)
        if (filter.value === 'needs_review') {
            return form.injury_observed
                || Number(form.fatigue_level) >= 4
                || ['fair', 'poor'].includes(form.performance_condition)
        }

        return true
    })
})

const summary = computed(() => {
    const forms = props.athletes.map((row) => rowForms.value[row.student_id]).filter(Boolean)
    const fatigueValues = forms.map((form) => Number(form.fatigue_level)).filter((value) => Number.isFinite(value))
    const urgent = forms.filter((form) => form.injury_observed || Number(form.fatigue_level) >= 5 || form.performance_condition === 'poor').length
    const caution = forms.filter((form) => !form.injury_observed && (Number(form.fatigue_level) === 4 || form.performance_condition === 'fair')).length
    const logged = props.athletes.filter((row) => row.wellness?.log_id).length
    const total = props.athletes.length

    return {
        total,
        logged,
        injury: forms.filter((form) => form.injury_observed).length,
        flagged: forms.filter((form) => Number(form.fatigue_level) >= 4 || ['fair', 'poor'].includes(form.performance_condition)).length,
        averageFatigue: fatigueValues.length
            ? (fatigueValues.reduce((sum, value) => sum + value, 0) / fatigueValues.length).toFixed(1)
            : '-',
        urgent,
        caution,
        stable: Math.max(total - urgent - caution, 0),
        progress: total > 0 ? Math.round((logged / total) * 100) : 0,
    }
})

function openSchedule(scheduleId: number) {
    selectedSchedule.value = scheduleId
    router.get('/coach/wellness', { schedule_id: scheduleId }, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    })
}

function saveRow(studentId: number) {
    if (!selectedSchedule.value) return

    const form = rowForms.value[studentId]
    if (!form) return

    savingKey.value = `${selectedSchedule.value}:${studentId}`

    router.post('/coach/wellness', {
        schedule_id: selectedSchedule.value,
        student_id: studentId,
        injury_observed: form.injury_observed,
        injury_notes: form.injury_notes,
        fatigue_level: Number(form.fatigue_level),
        performance_condition: form.performance_condition,
        remarks: form.remarks,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('Wellness evaluation saved.', 'success', {
                summary: 'Wellness Monitoring',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]
            showAppToast(String(firstError || 'Unable to save wellness evaluation.'), 'error', {
                summary: 'Wellness Monitoring',
            })
        },
        onFinish: () => {
            savingKey.value = null
        },
    })
}

function statusLabel(status: string) {
    if (status === 'present') return 'Present'
    if (status === 'late') return 'Late'
    if (status === 'excused') return 'Excused'
    return status
}

function statusTone(status: string) {
    if (status === 'late') return 'bg-amber-100 text-amber-800 border-amber-200'
    if (status === 'excused') return 'bg-sky-100 text-sky-700 border-sky-200'
    return 'bg-emerald-100 text-emerald-700 border-emerald-200'
}

function fatigueTone(level: string | number) {
    const normalized = Number(level)
    if (normalized >= 5) return 'bg-rose-600 text-white border-rose-600'
    if (normalized >= 4) return 'bg-amber-500 text-slate-950 border-amber-500'
    if (normalized >= 3) return 'bg-slate-900 text-white border-slate-900'
    return 'bg-white text-slate-600 border-slate-200'
}

function conditionTone(condition: string) {
    if (condition === 'excellent') return 'bg-emerald-600 text-white border-emerald-600'
    if (condition === 'good') return 'bg-sky-600 text-white border-sky-600'
    if (condition === 'fair') return 'bg-amber-500 text-slate-950 border-amber-500'
    return 'bg-rose-600 text-white border-rose-600'
}

function conditionLabel(condition: string) {
    return condition.charAt(0).toUpperCase() + condition.slice(1)
}

function scheduleTypeLabel(type: string) {
    if (type === 'practice') return 'Practice'
    if (type === 'game') return 'Game'
    return conditionLabel(type)
}

function formatScheduleDate(value?: string | null) {
    if (!value) return '-'
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function athleteNeedsAttention(studentId: number) {
    const form = rowForms.value[studentId]
    if (!form) return false

    return form.injury_observed
        || Number(form.fatigue_level) >= 4
        || ['fair', 'poor'].includes(form.performance_condition)
}

function athleteInitials(name: string) {
    return name
        .split(',')
        .flatMap((part) => part.trim().split(/\s+/))
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

function athleteRiskLevel(studentId: number) {
    const form = rowForms.value[studentId]
    if (!form) return 'stable'
    if (form.injury_observed || Number(form.fatigue_level) >= 5 || form.performance_condition === 'poor') return 'urgent'
    if (Number(form.fatigue_level) >= 4 || form.performance_condition === 'fair') return 'caution'
    return 'stable'
}

function riskTone(level: string) {
    if (level === 'urgent') return 'border-rose-200 bg-rose-50 text-rose-700'
    if (level === 'caution') return 'border-amber-200 bg-amber-50 text-amber-800'
    return 'border-emerald-200 bg-emerald-50 text-emerald-700'
}

function riskLabel(level: string) {
    if (level === 'urgent') return 'Urgent Follow-up'
    if (level === 'caution') return 'Monitor Closely'
    return 'Stable'
}

function filterButtonClass(option: FilterKey) {
    return filter.value === option
        ? 'border-[#034485] bg-[#034485] text-white shadow-sm'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'
}
</script>

<template>
    <Head title="Wellness Monitoring" />

    <div class="space-y-6">
        <div class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Post-session evaluation</p>
            <h1 class="text-2xl font-bold text-slate-900">Wellness Monitoring</h1>
            <p class="max-w-3xl text-sm leading-6 text-slate-500">
                Review attended athletes after practice or game sessions, flag injuries quickly, and move through post-session checks with a clearer, faster coaching workflow.
            </p>
        </div>

        <div v-if="!team" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
            You are not assigned to a team yet.
        </div>

        <div v-else class="space-y-6">
            <section
                class="overflow-hidden rounded-[2rem] border bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] shadow-[0_24px_60px_-40px_rgba(15,23,42,0.45)]"
                :style="{ borderColor: teamTone.borderColor }"
            >
                <div class="grid gap-5 p-5 lg:grid-cols-[1.25fr_0.95fr] lg:p-6">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                                :style="{ backgroundColor: sportColor(team.sport), color: sportTextColor(team.sport) }"
                            >
                                {{ sportLabel(team.sport) }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ summary.total }} attended athletes
                            </span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-2xl font-bold text-slate-900">{{ team.team_name }}</h2>
                            <p class="text-sm text-slate-500">
                                Choose a completed practice or game, then move down the queue with quicker injury, fatigue, and performance decisions that support post-session evaluation.
                            </p>
                            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session Progress</p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ summary.logged }} of {{ summary.total }} saved</p>
                                    </div>
                                    <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                                        {{ summary.progress }}% complete
                                    </span>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-[#034485] transition-all"
                                        :style="{ width: `${summary.progress}%` }"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Logged</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.logged }}</p>
                            </div>
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Injury Flags</p>
                                <p class="mt-2 text-2xl font-bold text-rose-700">{{ summary.injury }}</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Needs Review</p>
                                <p class="mt-2 text-2xl font-bold text-amber-800">{{ summary.flagged }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Fatigue</p>
                                <p class="mt-2 text-2xl font-bold text-[#034485]">{{ summary.averageFatigue }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 rounded-3xl border border-[#034485]/18 bg-[linear-gradient(135deg,rgba(248,251,255,0.98),rgba(255,255,255,0.96))] p-5">
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Completed session</label>
                        <select
                            v-model="selectedSchedule"
                            @change="openSchedule(Number(selectedSchedule))"
                            class="mt-3 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm"
                        >
                            <option :value="null" disabled>Select schedule</option>
                            <option v-for="s in schedules" :key="s.id" :value="s.id">
                                {{ s.title }} • {{ scheduleTypeLabel(s.type) }} • {{ new Date(s.end).toLocaleDateString() }}
                            </option>
                        </select>

                        <div v-if="selectedScheduleDetails" class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                            <p class="font-semibold text-slate-900">{{ selectedScheduleDetails.title }}</p>
                            <p>{{ scheduleTypeLabel(selectedScheduleDetails.type) }} • {{ selectedScheduleDetails.venue || '-' }}</p>
                            <p>{{ formatScheduleDate(selectedScheduleDetails.start) }} to {{ formatScheduleDate(selectedScheduleDetails.end) }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-rose-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Urgent</p>
                                <p class="mt-2 text-xl font-bold text-rose-700">{{ summary.urgent }}</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monitor</p>
                                <p class="mt-2 text-xl font-bold text-amber-800">{{ summary.caution }}</p>
                            </div>
                            <div class="rounded-2xl border border-emerald-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stable</p>
                                <p class="mt-2 text-xl font-bold text-emerald-700">{{ summary.stable }}</p>
                            </div>
                        </div>

                        <p v-if="schedules.length === 0" class="mt-4 text-sm text-slate-500">
                            No completed practice/game schedules are available yet.
                        </p>
                        <p v-else class="mt-4 text-sm leading-6 text-slate-500">
                            Only athletes marked <span class="font-semibold text-slate-700">present</span>, <span class="font-semibold text-slate-700">late</span>, or <span class="font-semibold text-slate-700">excused</span> in attendance appear here.
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_20px_55px_-42px_rgba(15,23,42,0.45)]">
                <div class="flex flex-col gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Evaluation Queue</p>
                        <p class="mt-1 text-sm text-slate-500">Triage the roster by urgency, then save each athlete review directly from the card without leaving the page.</p>
                    </div>

                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <input
                            v-model="search"
                            type="text"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 xl:w-72"
                            placeholder="Search athlete or student ID"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in filterOptions"
                                :key="option.key"
                                type="button"
                                class="rounded-full border px-4 py-2 text-xs font-semibold transition"
                                :class="filterButtonClass(option.key)"
                                @click="filter = option.key"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="filteredAthletes.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500">
                No athletes match the selected schedule or filter.
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="row in filteredAthletes"
                    :key="row.student_id"
                    class="rounded-3xl border bg-white p-5 shadow-[0_18px_48px_-40px_rgba(15,23,42,0.45)] transition"
                    :class="athleteNeedsAttention(row.student_id) ? 'border-amber-200 shadow-[0_24px_60px_-42px_rgba(245,158,11,0.35)]' : 'border-slate-200'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-sm font-bold text-slate-700">
                                {{ athleteInitials(row.name) }}
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="riskTone(athleteRiskLevel(row.student_id))"
                                    >
                                        {{ riskLabel(athleteRiskLevel(row.student_id)) }}
                                    </span>
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="statusTone(row.attendance_status)"
                                    >
                                        {{ statusLabel(row.attendance_status) }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ row.name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || '-' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600"
                            >
                                Fatigue {{ rowForms[row.student_id].fatigue_level }}/5
                            </span>
                            <span
                                class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                :class="rowForms[row.student_id].performance_condition === 'good' || rowForms[row.student_id].performance_condition === 'excellent'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-amber-200 bg-amber-50 text-amber-800'"
                            >
                                {{ conditionLabel(rowForms[row.student_id].performance_condition) }}
                            </span>
                            <span
                                v-if="row.wellness?.log_id"
                                class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                            >
                                Logged
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Review Priority</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ riskLabel(athleteRiskLevel(row.student_id)) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Attendance</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ statusLabel(row.attendance_status) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Record State</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ row.wellness?.log_id ? 'Previously saved' : 'New review' }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Injury Observation</p>
                                    <p class="mt-1 text-sm text-slate-600">Flag this when the athlete shows a confirmed post-session concern.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="rowForms[row.student_id].injury_observed
                                        ? 'border-rose-600 bg-rose-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                                    @click="rowForms[row.student_id].injury_observed = !rowForms[row.student_id].injury_observed"
                                >
                                    {{ rowForms[row.student_id].injury_observed ? 'Injury Observed' : 'No Injury Observed' }}
                                </button>
                            </div>

                            <textarea
                                v-model="rowForms[row.student_id].injury_notes"
                                rows="2"
                                class="mt-3 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                :disabled="!rowForms[row.student_id].injury_observed"
                                placeholder="Describe the injury concern, affected area, or immediate action taken"
                            />
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fatigue Level</p>
                                <p class="mt-1 text-sm text-slate-600">Pick the athlete’s post-session fatigue from low to very high.</p>
                                <div class="mt-3 grid grid-cols-5 gap-2">
                                    <button
                                        v-for="level in [1, 2, 3, 4, 5]"
                                        :key="level"
                                        type="button"
                                        class="rounded-2xl border px-0 py-3 text-sm font-bold transition"
                                        :class="fatigueTone(rowForms[row.student_id].fatigue_level === String(level) ? level : 0)"
                                        @click="rowForms[row.student_id].fatigue_level = String(level)"
                                    >
                                        {{ level }}
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Performance Condition</p>
                                <p class="mt-1 text-sm text-slate-600">Capture how the athlete looked overall at the end of the session.</p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button
                                        v-for="condition in ['excellent', 'good', 'fair', 'poor']"
                                        :key="condition"
                                        type="button"
                                        class="rounded-2xl border px-3 py-2 text-xs font-semibold transition"
                                        :class="rowForms[row.student_id].performance_condition === condition
                                            ? conditionTone(condition)
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                        @click="rowForms[row.student_id].performance_condition = condition as FormState['performance_condition']"
                                    >
                                        {{ conditionLabel(condition) }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Coach Remarks</label>
                            <textarea
                                v-model="rowForms[row.student_id].remarks"
                                rows="3"
                                class="mt-3 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                placeholder="Add recovery reminders, readiness notes, or any detail that helps the next review"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">
                            Save once the athlete’s post-session condition has been reviewed and the notes are ready for follow-up.
                        </p>
                        <button
                            type="button"
                            class="rounded-2xl bg-[#034485] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#033a70] disabled:cursor-not-allowed disabled:bg-slate-300"
                            :disabled="savingKey === `${selectedSchedule}:${row.student_id}`"
                            @click="saveRow(row.student_id)"
                        >
                            {{ savingKey === `${selectedSchedule}:${row.student_id}` ? 'Saving...' : 'Save Evaluation' }}
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
