<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
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

type ScheduleRow = {
    id: number
    title: string
    type: string
    venue: string
    start: string
    end: string
}

type FormState = {
    injury_observed: boolean
    injury_notes: string
    fatigue_level: string
    performance_condition: 'excellent' | 'good' | 'fair' | 'poor'
    remarks: string
}

type FilterKey = 'all' | 'needs_review' | 'saved'

type BulkFormState = {
    injury_observed: '' | 'yes' | 'no'
    injury_notes: string
    fatigue_level: '' | '1' | '2' | '3' | '4' | '5'
    performance_condition: '' | 'excellent' | 'good' | 'fair' | 'poor'
    remarks: string
}

const props = defineProps<{
    team: { id: number; team_name: string; sport: string } | null
    schedule: ScheduleRow
    athletes: AthleteRow[]
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()
const savingKey = ref<string | null>(null)
const search = ref('')
const filter = ref<FilterKey>('all')
const selectedAthleteIds = ref<number[]>([])
const bulkForm = ref<BulkFormState>({
    injury_observed: '',
    injury_notes: '',
    fatigue_level: '',
    performance_condition: '',
    remarks: '',
})

const filterOptions: Array<{ key: FilterKey; label: string }> = [
    { key: 'all', label: 'All Athletes' },
    { key: 'needs_review', label: 'Needs Review' },
    { key: 'saved', label: 'Already Logged' },
]

function buildRowForms(rows: AthleteRow[]) {
    return Object.fromEntries(
        rows.map((row) => [
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
        selectedAthleteIds.value = []
    },
    { immediate: true },
)

const filteredAthletes = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.athletes.filter((row) => {
        const form = rowForms.value[row.student_id]
        const matchesQuery = query === ''
            || row.name.toLowerCase().includes(query)
            || String(row.student_id_number ?? '').toLowerCase().includes(query)

        if (!matchesQuery || !form) return matchesQuery

        if (filter.value === 'saved') return Boolean(row.wellness?.log_id)
        if (filter.value === 'needs_review') {
            return form.injury_observed
                || Number(form.fatigue_level) >= 4
                || ['fair', 'poor'].includes(form.performance_condition)
        }

        return true
    })
})

const selectedVisibleCount = computed(() => (
    filteredAthletes.value.filter((row) => selectedAthleteIds.value.includes(row.student_id)).length
))

const summary = computed(() => {
    const forms = props.athletes.map((row) => rowForms.value[row.student_id]).filter(Boolean)
    const fatigueValues = forms.map((form) => Number(form.fatigue_level)).filter((value) => Number.isFinite(value))

    return {
        total: props.athletes.length,
        logged: props.athletes.filter((row) => row.wellness?.log_id).length,
        injury: forms.filter((form) => form.injury_observed).length,
        needsReview: forms.filter((form) => (
            form.injury_observed
            || Number(form.fatigue_level) >= 4
            || ['fair', 'poor'].includes(form.performance_condition)
        )).length,
        averageFatigue: fatigueValues.length
            ? (fatigueValues.reduce((sum, value) => sum + value, 0) / fatigueValues.length).toFixed(1)
            : '-',
    }
})

function saveRow(studentId: number) {
    const form = rowForms.value[studentId]
    if (!form) return

    savingKey.value = `${props.schedule.id}:${studentId}`

    router.post('/coach/wellness', {
        schedule_id: props.schedule.id,
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

function toggleAthleteSelection(studentId: number) {
    if (selectedAthleteIds.value.includes(studentId)) {
        selectedAthleteIds.value = selectedAthleteIds.value.filter((id) => id !== studentId)
        return
    }

    selectedAthleteIds.value = [...selectedAthleteIds.value, studentId]
}

function toggleSelectVisible() {
    const visibleIds = filteredAthletes.value.map((row) => row.student_id)
    const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedAthleteIds.value.includes(id))

    if (allVisibleSelected) {
        selectedAthleteIds.value = selectedAthleteIds.value.filter((id) => !visibleIds.includes(id))
        return
    }

    selectedAthleteIds.value = Array.from(new Set([...selectedAthleteIds.value, ...visibleIds]))
}

function clearBulkForm() {
    bulkForm.value = {
        injury_observed: '',
        injury_notes: '',
        fatigue_level: '',
        performance_condition: '',
        remarks: '',
    }
}

function applyBulkEvaluation() {
    if (selectedAthleteIds.value.length === 0) {
        showAppToast('Select at least one athlete before applying a bulk wellness update.', 'warn', {
            summary: 'Wellness Monitoring',
        })
        return
    }

    selectedAthleteIds.value.forEach((studentId) => {
        const form = rowForms.value[studentId]
        if (!form) return

        if (bulkForm.value.injury_observed === 'yes') {
            form.injury_observed = true
        } else if (bulkForm.value.injury_observed === 'no') {
            form.injury_observed = false
            form.injury_notes = ''
        }

        if (bulkForm.value.fatigue_level) {
            form.fatigue_level = bulkForm.value.fatigue_level
        }

        if (bulkForm.value.performance_condition) {
            form.performance_condition = bulkForm.value.performance_condition
        }

        if (bulkForm.value.remarks.trim()) {
            form.remarks = bulkForm.value.remarks.trim()
        }

        if (bulkForm.value.injury_notes.trim() && form.injury_observed) {
            form.injury_notes = bulkForm.value.injury_notes.trim()
        }
    })

    showAppToast(`Applied the selected wellness values to ${selectedAthleteIds.value.length} athlete${selectedAthleteIds.value.length > 1 ? 's' : ''}.`, 'success', {
        summary: 'Wellness Monitoring',
    })
}

function statusLabel(status: string) {
    if (status === 'present') return 'Present'
    if (status === 'late') return 'Late'
    return status
}

function statusTone(status: string) {
    if (status === 'late') return 'bg-amber-100 text-amber-800 border-amber-200'
    return 'bg-emerald-100 text-emerald-700 border-emerald-200'
}

function conditionLabel(condition: string) {
    return condition.charAt(0).toUpperCase() + condition.slice(1)
}

function scheduleTypeLabel(type: string) {
    if (type === 'practice') return 'Practice'
    if (type === 'game') return 'Game'
    return type
}

function formatScheduleDate(value?: string | null) {
    if (!value) return '-'

    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
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

function filterButtonClass(option: FilterKey) {
    return filter.value === option
        ? 'border-[#034485] bg-[#034485] text-white shadow-sm'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'
}
</script>

<template>
    <Head title="Wellness Review" />

    <div class="space-y-6">
        <div class="flex flex-col gap-2">
            <Link href="/coach/wellness" class="text-sm font-medium text-[#034485] hover:text-[#033a70]">
                ← Back to Wellness Monitoring
            </Link>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Player review</p>
            <h1 class="text-2xl font-bold text-slate-900">Review Players</h1>
            <p class="max-w-3xl text-sm leading-6 text-slate-500">
                Evaluate present and late athletes for this completed session, then save wellness entries per player.
            </p>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                            :style="{ backgroundColor: sportColor(team?.sport ?? ''), color: sportTextColor(team?.sport ?? '') }"
                        >
                            {{ sportLabel(team?.sport ?? '') }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ team?.team_name }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ schedule.title }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ scheduleTypeLabel(schedule.type) }} • {{ schedule.venue || '-' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ formatScheduleDate(schedule.start) }} to {{ formatScheduleDate(schedule.end) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Athletes</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ summary.total }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Saved</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ summary.logged }}</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Injuries</p>
                        <p class="mt-1 text-lg font-bold text-rose-700">{{ summary.injury }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Fatigue</p>
                        <p class="mt-1 text-lg font-bold text-amber-800">{{ summary.averageFatigue }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_20px_55px_-42px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Athlete Wellness Queue</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Review only present or late athletes for this session, then save each evaluation after checking the athlete’s condition.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input
                            v-model="search"
                            type="text"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 sm:w-72"
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

                <div class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bulk Selection</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    Select multiple athletes, then apply the same fatigue level, no-injury flag, condition, or remarks before saving individual evaluations.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400"
                                @click="toggleSelectVisible"
                            >
                                {{ selectedVisibleCount === filteredAthletes.length && filteredAthletes.length > 0 ? 'Clear Visible Selection' : 'Select Visible Athletes' }}
                            </button>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                                {{ selectedAthleteIds.length }} selected
                            </span>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ summary.needsReview }} need closer review
                            </span>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bulk Evaluation</p>
                        <div class="mt-4 grid gap-3">
                            <select
                                v-model="bulkForm.injury_observed"
                                class="rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                            >
                                <option value="">Injury observed: leave unchanged</option>
                                <option value="yes">Set injury observed</option>
                                <option value="no">Set no injury observed</option>
                            </select>

                            <input
                                v-model="bulkForm.injury_notes"
                                type="text"
                                class="rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                placeholder="Bulk injury notes"
                            />

                            <select
                                v-model="bulkForm.fatigue_level"
                                class="rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                            >
                                <option value="">Fatigue level: leave unchanged</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select>

                            <select
                                v-model="bulkForm.performance_condition"
                                class="rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                            >
                                <option value="">Performance condition: leave unchanged</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>

                            <textarea
                                v-model="bulkForm.remarks"
                                rows="2"
                                class="rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                placeholder="Bulk coach remarks"
                            />

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-md bg-[#1f2937] px-3 py-2 text-xs font-semibold text-white hover:bg-[#111827]"
                                    @click="applyBulkEvaluation"
                                >
                                    Apply to Selected
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400"
                                    @click="clearBulkForm"
                                >
                                    Clear Bulk Values
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="filteredAthletes.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500">
            No athletes match this session or the current filter.
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
                        <button
                            type="button"
                            class="mt-1 h-5 w-5 rounded border border-slate-300 bg-white text-[11px] font-bold leading-none text-[#034485]"
                            @click="toggleAthleteSelection(row.student_id)"
                        >
                            {{ selectedAthleteIds.includes(row.student_id) ? '✓' : '' }}
                        </button>
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-sm font-bold text-slate-700">
                            {{ athleteInitials(row.name) }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                    :class="statusTone(row.attendance_status)"
                                >
                                    {{ statusLabel(row.attendance_status) }}
                                </span>
                                <span
                                    v-if="row.wellness?.log_id"
                                    class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                                >
                                    Saved
                                </span>
                            </div>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ row.name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || '-' }}</p>
                        </div>
                    </div>

                    <div v-if="athleteNeedsAttention(row.student_id)" class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-800">
                        Needs follow-up
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Injury Observed</p>
                                <p class="mt-1 text-sm text-slate-600">Mark this if the athlete showed a confirmed concern during or after the session.</p>
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
                            placeholder="Add injury notes if observed"
                        />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fatigue Level</p>
                            <p class="mt-1 text-sm text-slate-600">Select the athlete’s post-session fatigue from 1 to 5.</p>
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
                            <p class="mt-1 text-sm text-slate-600">Capture the athlete’s overall condition during or after the session.</p>
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
                            placeholder="Add coaching notes, recovery reminders, or readiness comments"
                        />
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Save the athlete’s wellness evaluation once the session review is complete.
                    </p>
                    <button
                        type="button"
                        class="rounded-2xl bg-[#034485] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#033a70] disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="savingKey === `${schedule.id}:${row.student_id}`"
                        @click="saveRow(row.student_id)"
                    >
                        {{ savingKey === `${schedule.id}:${row.student_id}` ? 'Saving...' : 'Save Evaluation' }}
                    </button>
                </div>
            </article>
        </div>
    </div>
</template>
