<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
    layout: StudentAthleteDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    announcement: string | null
    eligibility_status?: string | null
    is_eligible?: boolean
    can_submit?: boolean
}

type Submission = {
    id: number
    period_id: number | null
    period_label: string | null
    document_type: string
    file_url: string | null
    uploaded_at: string | null
    notes: string | null
    evaluation: {
        gpa: number | null
        status: string
        remarks: string | null
        evaluated_at: string | null
    } | null
}

const props = defineProps<{
    student: {
        id: number
        name: string
        student_id_number: string | null
        course_or_strand?: string | null
        current_grade_level?: string | null
        academic_level_label?: string | null
    } | null
    openPeriods: Period[]
    submissions: Submission[]
    submissionHoldStatus?: string | null
    hasActiveWindow?: boolean
    hasTeam?: boolean
    hasSubmittedAll?: boolean
}>()

const page = usePage()
const academicAccess = computed(() => (page.props.auth as any)?.academic_access ?? null)
const isAcademicallyRestricted = computed(() => Boolean(academicAccess.value?.is_restricted))
const restrictionEvaluation = computed(() => academicAccess.value?.evaluation ?? null)

function parseTime(value: string | null | undefined) {
    if (!value) return 0
    const t = Date.parse(value)
    return Number.isNaN(t) ? 0 : t
}

const sortedSubmissions = computed(() =>
    [...(props.submissions || [])].sort((a, b) => parseTime(b.uploaded_at) - parseTime(a.uploaded_at))
)

const latestSubmission = computed(() => sortedSubmissions.value[0] || null)
const latestEvaluated = computed(() => sortedSubmissions.value.find((row) => row.evaluation) || null)
const completedSubmissions = computed(() => sortedSubmissions.value.filter((row) => row.evaluation))
function statusPill(row: Submission) {
    if (!row.evaluation) {
        return { label: 'Pending review', class: 'bg-amber-50 text-amber-700 border border-amber-200' }
    }
    const raw = row.evaluation.status || 'Reviewed'
    const normalized = raw.toLowerCase()
    if (normalized.includes('approved') || normalized.includes('cleared') || normalized.includes('passed')) {
        return { label: raw, class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' }
    }
    if (normalized.includes('rejected') || normalized.includes('failed')) {
        return { label: raw, class: 'bg-rose-50 text-rose-700 border border-rose-200' }
    }
    if (normalized.includes('incomplete') || normalized.includes('needs')) {
        return { label: raw, class: 'bg-amber-50 text-amber-700 border border-amber-200' }
    }
    return { label: raw, class: 'bg-[#034485]/5 text-[#1f2937] border border-[#034485]/20' }
}

function docLabel(type: string) {
    if (type === 'grade_report') return 'Grade Report'
    return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function evaluationPill(status: string | null | undefined) {
    const value = String(status ?? '').toLowerCase()
    if (!value) return 'bg-[#034485]/5 text-slate-600 border border-[#034485]/20'
    if (value.includes('eligible') || value.includes('approved') || value.includes('passed')) {
        return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    }
    if (value.includes('ineligible') || value.includes('rejected') || value.includes('failed')) {
        return 'bg-rose-50 text-rose-700 border border-rose-200'
    }
    if (value.includes('probation') || value.includes('incomplete') || value.includes('needs')) {
        return 'bg-amber-50 text-amber-700 border border-amber-200'
    }
    return 'bg-[#034485]/5 text-slate-600 border border-[#034485]/20'
}

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function formatDate(value: string | null | undefined) {
    if (!value) return '-'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString()
}

function printAcademicSummary() {
    window.open('/AcademicSubmissions/print', '_blank')
}
</script>

<template>
    <Head title="Academic Submissions" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Academic Submissions</h1>
                <p class="text-sm text-slate-500">
                    {{ isAcademicallyRestricted ? 'Review your academic standing and submit the required records to work toward restored varsity access.' : 'Submit your semester grade proof directly to admin.' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    @click="printAcademicSummary"
                    class="rounded-lg border border-[#034485]/40 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                >
                    Print
                </button>
            </div>
        </div>

        <div v-if="!student" class="bg-white border border-[#034485]/35 rounded-lg p-4 text-slate-600">
            Student profile not found.
        </div>

        <template v-else>
            <section
                v-if="isAcademicallyRestricted"
                class="overflow-hidden rounded-[28px] border border-amber-200 bg-[linear-gradient(135deg,rgba(255,251,235,0.98),rgba(255,255,255,0.96))] shadow-sm"
            >
                <div class="grid gap-4 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,0.9fr)] lg:items-center">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                        </span>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-semibold text-slate-900">Varsity access is currently limited</p>
                                <span class="rounded-full border border-amber-200 bg-white/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700">
                                    Academic restriction
                                </span>
                            </div>
                            <p class="max-w-3xl text-sm leading-6 text-slate-600">
                                {{ academicAccess?.message }}
                            </p>
                            <p class="text-sm text-slate-600">
                                You can still use this module to review your evaluation, submit the required documents, and check what needs attention for the current academic period.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-amber-200/80 bg-white/90 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Current evaluation</p>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <div class="flex items-center justify-between gap-3">
                                <span>Status</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="evaluationPill(restrictionEvaluation?.status)">
                                    {{ restrictionEvaluation?.status ?? 'Ineligible' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Period</span>
                                <span class="text-right font-medium text-slate-800">{{ restrictionEvaluation?.period_label ?? latestEvaluated?.period_label ?? '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>GPA</span>
                                <span class="font-medium text-slate-800">{{ restrictionEvaluation?.gpa ?? latestEvaluated?.evaluation?.gpa ?? '-' }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span>Remarks</span>
                                <span class="max-w-[14rem] text-right text-slate-700">{{ restrictionEvaluation?.remarks ?? latestEvaluated?.evaluation?.remarks ?? 'Check the evaluated record below for details.' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Student</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ student.name }}</p>
                    <div class="mt-2 grid gap-1 text-xs text-slate-500">
                        <div><span class="font-semibold text-slate-700">ID:</span> {{ student.student_id_number || '-' }}</div>
                        <div><span class="font-semibold text-slate-700">Course/Strand:</span> {{ student.course_or_strand || '-' }}</div>
                        <div><span class="font-semibold text-slate-700">Academic Level:</span> {{ student.academic_level_label || student.current_grade_level || '-' }}</div>
                    </div>
                </div>

                <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Latest Evaluation</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <p class="text-2xl font-semibold text-[#1f2937]">{{ latestEvaluated?.evaluation?.gpa ?? '-' }}</p>
                        <span class="text-xs text-slate-500">GPA</span>
                    </div>
                    <div class="mt-2 text-xs text-slate-500 flex items-center gap-2">
                        <span>Status:</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="evaluationPill(latestEvaluated?.evaluation?.status)">
                            {{ latestEvaluated?.evaluation?.status ?? 'Not yet evaluated' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-500">Evaluated: {{ formatDate(latestEvaluated?.evaluation?.evaluated_at) }}</div>
                    <div class="text-xs text-slate-500">Period: {{ latestEvaluated?.period_label || '-' }}</div>
                </div>

                <div class="rounded-3xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Latest Submission</p>
                    <div class="mt-2 text-sm font-semibold text-slate-900">{{ latestSubmission?.period_label || 'No submissions on record' }}</div>
                    <div class="text-xs text-slate-500">{{ latestSubmission?.uploaded_at || '-' }}</div>
                    <div class="mt-2 text-xs text-slate-600">
                        {{ latestSubmission ? docLabel(latestSubmission.document_type) : '—' }}
                    </div>
                    <div v-if="latestSubmission?.file_url" class="mt-2">
                        <a :href="latestSubmission.file_url" target="_blank" class="text-xs font-semibold text-[#1f2937] hover:underline">
                            View submitted file
                        </a>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-[#034485]/35 rounded-xl p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Submission Window</h2>
                        <p class="text-xs text-slate-500">Active academic submission period.</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ openPeriods.length }} open</span>
                </div>
                <div v-if="openPeriods.length === 0" class="text-sm text-slate-500">
                    No active submission period is available at this time. Please wait for an administrator's announcement.
                </div>
                <div v-else class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <Link
                        v-for="p in openPeriods"
                        :key="p.id"
                        :href="`/AcademicSubmissions/new?period_id=${p.id}`"
                        class="border border-[#034485]/40 rounded-lg bg-[#034485] p-3 text-sm text-white transition hover:border-[#033a70] hover:bg-[#033a70]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium">{{ p.school_year }} - {{ termLabel(p.term) }}</div>
                            <span
                                class="text-[10px] rounded-full border px-2 py-0.5"
                                :class="p.is_eligible ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-[#034485]/20 bg-[#034485]/5 text-[#1f2937]'"
                            >
                                {{ p.is_eligible ? 'Eligible' : 'Open' }}
                            </span>
                        </div>
                        <div class="text-xs text-white/70 mt-1">Window: {{ p.starts_on }} to {{ p.ends_on }}</div>
                        <div v-if="p.announcement" class="text-xs text-amber-200 mt-1">{{ p.announcement }}</div>
                        <div class="mt-2 text-[11px] font-semibold text-white">Click to submit</div>
                    </Link>
                </div>
                <div
                    v-if="hasActiveWindow"
                    class="rounded-lg border border-[#034485]/25 bg-[#034485]/5 px-3 py-2 text-xs text-slate-600"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-700">Current status:</span>
                        <Link
                            v-if="submissionHoldStatus"
                            href="/AcademicSubmissions/new"
                            class="rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white hover:bg-[#033a70]"
                        >
                            {{ submissionHoldStatus }}
                        </Link>
                        <span
                            v-else
                            class="rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-semibold text-white"
                        >
                            Submitted
                        </span>
                        <span class="text-slate-500">
                            {{
                                submissionHoldStatus
                                    ? (hasTeam ? 'Team access is paused until submissions are completed.' : 'Access resumes after you submit.')
                                    : 'Your submission has been received. Schedule and wellness access remains available.'
                            }}
                        </span>
                    </div>
                </div>
            </section>


            <section v-if="completedSubmissions.length > 0" class="grid grid-cols-1 gap-3 md:hidden">
                <div v-for="row in completedSubmissions" :key="row.id" class="rounded-xl border border-[#034485]/35 bg-white p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ row.period_label || 'Unknown period' }}</p>
                            <p class="text-xs text-slate-500">{{ row.uploaded_at || '-' }}</p>
                        </div>
                        <span class="text-[10px] rounded-full px-2 py-0.5" :class="statusPill(row).class">
                            {{ statusPill(row).label }}
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-slate-600">
                        {{ docLabel(row.document_type) }}
                        <span v-if="row.file_url" class="ml-2 text-[#1f2937]">• <a :href="row.file_url" target="_blank" class="hover:underline">View file</a></span>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">
                        Notes: {{ row.notes || '—' }}
                    </div>
                    <div v-if="row.evaluation" class="mt-2 text-xs text-slate-500">
                        GPA: {{ row.evaluation.gpa ?? '-' }} • {{ row.evaluation.remarks || 'No remarks' }}
                    </div>
                </div>
            </section>

            <div v-else-if="completedSubmissions.length === 0" class="bg-white border border-[#034485]/35 rounded-xl p-6 text-slate-500 text-center">
                No completed submissions are available at this time.
            </div>

            <section class="bg-white border border-[#034485]/35 rounded-xl overflow-x-auto">
                <div class="flex items-center justify-between px-4 pt-4">
                    <h2 class="text-sm font-semibold text-[#034485]">Completed / Past Submissions</h2>
                    <span class="text-xs text-slate-500">{{ completedSubmissions.length }} total</span>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-[#034485] text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">Period</th>
                            <th class="px-3 py-2 text-left">Uploaded</th>
                            <th class="px-3 py-2 text-left">Document</th>
                            <th class="px-3 py-2 text-left">Evaluation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in completedSubmissions" :key="row.id" class="border-t border-[#034485]/20 text-slate-700">                            <td class="px-3 py-2">{{ row.period_label || '-' }}</td>
                            <td class="px-3 py-2">
                                <div>{{ row.uploaded_at || '-' }}</div>
                                <div class="text-xs text-slate-500">{{ row.notes || '-' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="uppercase text-xs">{{ docLabel(row.document_type) }}</div>
                                <a v-if="row.file_url" :href="row.file_url" target="_blank" class="text-[#1f2937] hover:underline">View</a>
                            </td>
                            <td class="px-3 py-2">
                                <span class="text-[11px] rounded-full px-2 py-0.5" :class="statusPill(row).class">
                                    {{ statusPill(row).label }}
                                </span>
                                <div v-if="row.evaluation" class="text-xs text-slate-500 mt-1">GPA: {{ row.evaluation.gpa ?? '-' }}</div>
                                <div v-if="row.evaluation" class="text-xs text-slate-500">{{ row.evaluation.remarks || '-' }}</div>
                            </td>
                        </tr>
                        <tr v-if="completedSubmissions.length === 0">
                            <td colspan="4" class="px-3 py-8 text-center text-slate-500">No submissions are on record at this time.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>
