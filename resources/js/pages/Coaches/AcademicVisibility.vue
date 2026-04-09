<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
    layout: CoachDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    status: 'draft' | 'open' | 'closed' | 'locked'
}

type Row = {
    student_id: number
    student_name: string
    student_id_number: string | null
    submitted: boolean
    uploaded_at: string | null
    document_url: string | null
    evaluation_status: string | null
    evaluation_gpa: number | null
}

const props = defineProps<{
    team: { id: number; team_name: string; sport: string } | null
    periods: Period[]
    selectedPeriodId: number | null
    rows: Row[]
}>()

const selectedPeriodId = ref<number | null>(props.selectedPeriodId)

const selectedPeriod = computed(() => props.periods.find((p) => p.id === selectedPeriodId.value) ?? null)
const totalRows = computed(() => props.rows.length)
const submittedCount = computed(() => props.rows.filter((row) => row.submitted).length)
const pendingCount = computed(() => totalRows.value - submittedCount.value)
const evaluatedCount = computed(() => {
    return props.rows.filter((row) => {
        const status = String(row.evaluation_status ?? '').toLowerCase()
        return status !== '' && status !== 'pending'
    }).length
})
const averageGpa = computed(() => {
    const values = props.rows
        .map((row) => row.evaluation_gpa)
        .filter((value) => typeof value === 'number' && Number.isFinite(value)) as number[]
    if (values.length === 0) return null
    const total = values.reduce((sum, value) => sum + value, 0)
    return (total / values.length).toFixed(2)
})
const submissionRate = computed(() => (totalRows.value === 0 ? 0 : Math.round((submittedCount.value / totalRows.value) * 100)))

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function submissionTone(submitted: boolean) {
    return submitted ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
}

function periodStatusLabel(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'open') return 'Open'
    if (normalized === 'locked') return 'Locked'
    if (normalized === 'draft') return 'Draft'
    return 'Closed'
}

function periodStatusTone(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'open') return 'bg-emerald-100 text-emerald-700'
    if (normalized === 'locked') return 'bg-slate-800 text-white'
    if (normalized === 'draft') return 'bg-slate-100 text-slate-600'
    return 'bg-rose-100 text-rose-700'
}

function evaluationLabel(status: string | null) {
    const normalized = String(status ?? '').replace(/_/g, ' ').toLowerCase()
    if (!normalized || normalized === 'pending') return 'Pending'
    if (normalized === 'eligible') return 'Eligible'
    if (normalized === 'probation') return 'Probation'
    if (normalized === 'ineligible') return 'Ineligible'
    return normalized.replace(/\b\w/g, (char) => char.toUpperCase())
}

function evaluationTone(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (!normalized || normalized === 'pending') return 'bg-slate-100 text-slate-600'
    if (normalized === 'eligible') return 'bg-emerald-100 text-emerald-700'
    if (normalized === 'probation') return 'bg-amber-100 text-amber-700'
    if (normalized === 'ineligible') return 'bg-rose-100 text-rose-700'
    if (normalized === 'approved' || normalized === 'passed') return 'bg-emerald-100 text-emerald-700'
    if (normalized === 'rejected' || normalized === 'failed') return 'bg-rose-100 text-rose-700'
    return 'bg-amber-100 text-amber-700'
}

function changePeriod() {
    router.get('/coach/academics', { period_id: selectedPeriodId.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <Head title="Academic Visibility" />

    <div class="space-y-5">

        <div v-if="!team" class="rounded-xl border border-[#034485]/45 bg-white p-6 text-slate-500">
            You are not assigned to a team yet.
        </div>

        <div v-else class="space-y-4">
            <section class="rounded-2xl border border-[#034485]/45 bg-[#034485] p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-white/80">
                            Team Overview
                            <span
                                v-if="selectedPeriod"
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="periodStatusTone(selectedPeriod.status)"
                            >
                                {{ periodStatusLabel(selectedPeriod.status) }}
                            </span>
                        </div>
                        <h2 class="mt-2 text-2xl font-bold text-white">{{ team.team_name }}</h2>
                        <p class="text-sm text-white/80">Sport: <span class="font-semibold capitalize text-white">{{ team.sport }}</span></p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-white/80">Period</label>
                            <select
                                v-model="selectedPeriodId"
                                @change="changePeriod"
                                class="mt-1 w-full min-w-[220px] rounded-xl border border-white/50 bg-white/90 px-3 py-2 text-sm text-slate-900"
                            >
                                <option :value="null" disabled>Select period</option>
                                <option v-for="p in periods" :key="p.id" :value="p.id">
                                    {{ p.school_year }} - {{ termLabel(p.term) }} ({{ periodStatusLabel(p.status).toUpperCase() }})
                                </option>
                            </select>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Submission progress</span>
                                <span class="font-semibold text-slate-700">{{ submittedCount }}/{{ totalRows }} ({{ submissionRate }}%)</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-[#1f2937]" :style="{ width: `${submissionRate}%` }"></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500">Evaluated: {{ evaluatedCount }} • Pending: {{ pendingCount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <div class="stats-pill">
                    <p class="stats-label">Total Athletes</p>
                    <p class="stats-value text-[#1f2937]">{{ totalRows }}</p>
                </div>
                <div class="stats-pill">
                    <p class="stats-label">Submitted</p>
                    <p class="stats-value text-emerald-600">{{ submittedCount }}</p>
                </div>
                <div class="stats-pill">
                    <p class="stats-label">Pending</p>
                    <p class="stats-value text-rose-600">{{ pendingCount }}</p>
                </div>
                <div class="stats-pill">
                    <p class="stats-label">Average GPA</p>
                    <p class="stats-value text-slate-900">{{ averageGpa ?? '-' }}</p>
                </div>
            </div>

            <div class="space-y-3 lg:hidden">
                <article v-for="row in rows" :key="row.student_id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold text-slate-900">{{ row.student_name }}</p>
                            <p class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="submissionTone(row.submitted)">
                            {{ row.submitted ? 'Submitted' : 'Not Submitted' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600">
                        <div>
                            <span class="text-slate-500">Uploaded</span>
                            <p class="font-semibold text-slate-900">{{ row.uploaded_at || '-' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">GPA</span>
                            <p class="font-semibold text-slate-900">{{ row.evaluation_gpa ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="evaluationTone(row.evaluation_status)">
                            {{ evaluationLabel(row.evaluation_status) }}
                        </span>
                        <a
                            v-if="row.document_url"
                            :href="row.document_url"
                            target="_blank"
                            class="rounded-full border border-slate-200 px-3 py-1 text-[10px] font-semibold text-[#1f2937] hover:border-slate-300"
                        >
                            View Document
                        </a>
                        <span v-else class="text-xs text-slate-400">No document</span>
                    </div>
                </article>
                <div v-if="rows.length === 0" class="rounded-xl border border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-500">
                    No athletes found for this period.
                </div>
            </div>

            <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white lg:block">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-[#034485] text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Student</th>
                            <th class="px-4 py-3 text-left">Submission</th>
                            <th class="px-4 py-3 text-left">Document</th>
                            <th class="px-4 py-3 text-left">Evaluation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.student_id" class="border-t border-slate-100 text-slate-700">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ row.student_name }}</div>
                                <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="submissionTone(row.submitted)">
                                    {{ row.submitted ? 'Submitted' : 'Not Submitted' }}
                                </span>
                                <div class="mt-1 text-xs text-slate-500">{{ row.uploaded_at || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <a v-if="row.document_url" :href="row.document_url" target="_blank" class="text-[#1f2937] hover:underline">View</a>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="evaluationTone(row.evaluation_status)">
                                    {{ evaluationLabel(row.evaluation_status) }}
                                </span>
                                <div class="mt-1 text-xs text-slate-500">GPA: {{ row.evaluation_gpa ?? '-' }}</div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No athletes found for this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.stats-pill {
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.35);
  background: #ffffff;
  padding: 0.65rem 1.1rem;
  min-width: 170px;
}

.stats-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
}

.stats-value {
  margin-top: 0.35rem;
  font-size: 1.4rem;
  font-weight: 700;
}
</style>
