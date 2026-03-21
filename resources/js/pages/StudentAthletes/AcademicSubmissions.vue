<script setup lang="ts">
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Spinner from '@/components/ui/spinner/Spinner.vue'

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
    student: { id: number; name: string; student_id_number: string | null } | null
    openPeriods: Period[]
    submissions: Submission[]
}>()

const academicPeriodId = ref<number | null>(props.openPeriods?.[0]?.id ?? null)
const documentType = ref<'grade_report' | 'other'>('grade_report')
const semesterGpa = ref('')
const notes = ref('')
const file = ref<File | null>(null)
const submitError = ref('')
const isSubmitting = ref(false)
const uploadProgress = ref(0)
const showHistoryTable = ref(false)
const showPendingOnly = ref(false)

const selectedPeriod = computed(() => props.openPeriods.find((p) => p.id === academicPeriodId.value) ?? null)
const eligibilityLabel = computed(() => {
    const status = String(selectedPeriod.value?.eligibility_status ?? '').trim().toLowerCase()
    if (!status) return null
    if (status === 'eligible') return 'Eligible'
    if (status === 'probation') return 'Probation'
    if (status === 'ineligible') return 'Ineligible'
    return status.replace(/\b\w/g, (c) => c.toUpperCase())
})
const canSubmit = computed(() => {
    if (!selectedPeriod.value) return true
    if (selectedPeriod.value.is_eligible) return false
    if (selectedPeriod.value.can_submit === false) return false
    return true
})

const activeWindowCount = computed(() => props.openPeriods?.length ?? 0)
const filteredSubmissions = computed(() => {
    const items = props.submissions || []
    if (!showPendingOnly.value) return items
    return items.filter((row) => !row.evaluation)
})
const totalSubmissions = computed(() => filteredSubmissions.value.length)
const pendingCount = computed(() => filteredSubmissions.value.filter((row) => !row.evaluation).length)
const evaluatedCount = computed(() => filteredSubmissions.value.filter((row) => row.evaluation).length)

function parseTime(value: string | null | undefined) {
    if (!value) return 0
    const t = Date.parse(value)
    return Number.isNaN(t) ? 0 : t
}

const sortedSubmissions = computed(() =>
    [...filteredSubmissions.value].sort((a, b) => parseTime(b.uploaded_at) - parseTime(a.uploaded_at))
)

const latestSubmission = computed(() => sortedSubmissions.value[0] || null)
const nextDeadline = computed(() => {
    const items = props.openPeriods || []
    if (!items.length) return null
    return [...items].sort((a, b) => parseTime(a.ends_on) - parseTime(b.ends_on))[0]
})

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
    return { label: raw, class: 'bg-slate-50 text-[#1f2937] border border-slate-100' }
}

function docLabel(type: string) {
    if (type === 'grade_report') return 'Grade Report'
    return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function submit() {
    if (isSubmitting.value) return
    submitError.value = ''

    if (!academicPeriodId.value) {
        submitError.value = 'Please select an academic period.'
        return
    }
    if (!canSubmit.value) {
        submitError.value = 'You are already eligible for this period. Further submissions are locked.'
        return
    }
    if (!file.value) {
        submitError.value = 'Please attach your semestral grade document.'
        return
    }

    const fd = new FormData()
    fd.append('academic_period_id', String(academicPeriodId.value))
    fd.append('document_type', documentType.value)
    fd.append('semester_gpa', semesterGpa.value)
    fd.append('notes', notes.value)
    fd.append('document_file', file.value)

    router.post('/AcademicSubmissions', fd, {
        forceFormData: true,
        onStart: () => {
            isSubmitting.value = true
            uploadProgress.value = 0
        },
        onProgress: (event) => {
            uploadProgress.value = Math.round(event?.percentage ?? 0)
        },
        onFinish: () => {
            isSubmitting.value = false
            uploadProgress.value = 0
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {})[0]
            submitError.value = Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Submission failed.')
        },
    })
}

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
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
                <p class="text-sm text-slate-500">Submit your semester grade proof directly to admin.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    @click="printAcademicSummary"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                >
                    Print
                </button>
                <button
                    @click="showHistoryTable = !showHistoryTable"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                >
                    {{ showHistoryTable ? 'Hide Table' : 'Show Table' }}
                </button>
                <button
                    @click="showPendingOnly = !showPendingOnly"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold"
                    :class="showPendingOnly ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-slate-600 hover:bg-slate-50'"
                >
                    {{ showPendingOnly ? 'Showing Pending Only' : 'Filter Pending' }}
                </button>
            </div>
        </div>

        <div v-if="!student" class="bg-white border border-slate-200 rounded-lg p-4 text-slate-600">
            Student profile not found.
        </div>

        <template v-else>
            <div class="bg-white border border-slate-200 rounded-lg p-4 text-sm text-slate-700">
                <span class="font-medium">{{ student.name }}</span>
                <span class="text-slate-500"> • {{ student.student_id_number || '-' }}</span>
            </div>

            <section class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Active windows</p>
                    <p class="text-2xl font-semibold text-[#1f2937] mt-1">{{ activeWindowCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Submissions</p>
                    <p class="text-2xl font-semibold text-slate-900 mt-1">{{ totalSubmissions }}</p>
                    <p class="text-xs text-slate-500">{{ evaluatedCount }} reviewed</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Pending review</p>
                    <p class="text-2xl font-semibold text-amber-700 mt-1">{{ pendingCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Next deadline</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1">{{ nextDeadline?.ends_on || 'No active window' }}</p>
                    <p class="text-xs text-slate-500">{{ nextDeadline?.school_year ? `${nextDeadline.school_year} • ${termLabel(nextDeadline.term)}` : '' }}</p>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl p-4 space-y-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">Active Submission Windows</h2>
                    <span class="text-xs text-slate-500">{{ openPeriods.length }} open</span>
                </div>
                <div v-if="openPeriods.length === 0" class="text-sm text-slate-500">
                    No active submission window yet. Wait for admin announcement.
                </div>
                <div v-else class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div v-for="p in openPeriods" :key="p.id" class="border border-slate-200 rounded-lg p-3 text-sm text-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium">{{ p.school_year }} - {{ termLabel(p.term) }}</div>
                            <span
                                class="text-[10px] rounded-full border px-2 py-0.5"
                                :class="p.is_eligible ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-slate-50 text-[#1f2937]'"
                            >
                                {{ p.is_eligible ? 'Eligible' : 'Open' }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">Window: {{ p.starts_on }} to {{ p.ends_on }}</div>
                        <div v-if="p.announcement" class="text-xs text-amber-600 mt-1">{{ p.announcement }}</div>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl p-4 space-y-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">Submit Semester Grades</h2>
                    <span class="text-xs text-slate-500">Step 1 of 3</span>
                </div>
                <p v-if="submitError" class="text-sm text-rose-600">{{ submitError }}</p>
                <div v-if="eligibilityLabel" class="rounded-lg border px-3 py-2 text-xs" :class="canSubmit ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
                    Status: <span class="font-semibold">{{ eligibilityLabel }}</span>
                    <span v-if="!canSubmit"> — submissions locked for this period.</span>
                </div>
                <p class="text-xs text-slate-500">Choose the academic period and upload your latest grade document.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <select v-model="academicPeriodId" class="bg-white border border-slate-200 rounded px-2 py-2 text-slate-700">
                        <option :value="null" disabled>Select period</option>
                        <option v-for="p in openPeriods" :key="p.id" :value="p.id">
                            {{ p.school_year }} - {{ termLabel(p.term) }}
                        </option>
                    </select>
                    <select v-model="documentType" :disabled="!canSubmit" class="bg-white border border-slate-200 rounded px-2 py-2 text-slate-700 disabled:bg-slate-100 disabled:text-slate-400">
                        <option value="grade_report">Grade Report</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <input v-model="semesterGpa" type="number" step="0.01" min="0" max="5" placeholder="Semester GPA (optional)"
                    :disabled="!canSubmit"
                    class="w-full bg-white border border-slate-200 rounded px-2 py-2 text-slate-700 disabled:bg-slate-100 disabled:text-slate-400" />
                <textarea v-model="notes" rows="2" placeholder="Notes (optional)"
                    :disabled="!canSubmit"
                    class="w-full bg-white border border-slate-200 rounded px-2 py-2 text-slate-700 disabled:bg-slate-100 disabled:text-slate-400" />
                <input type="file" accept=".pdf,image/*"
                    @change="(e: Event) => file = (e.target as HTMLInputElement).files?.[0] ?? null"
                    :disabled="!canSubmit"
                    class="w-full text-sm text-slate-500 disabled:text-slate-300" />
                <p class="text-xs text-slate-400">Accepted: PDF, PNG, JPG.</p>
                <button @click="submit" :disabled="isSubmitting || !canSubmit" class="px-4 py-2 rounded bg-[#1f2937] text-white hover:bg-[#334155] disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="inline-flex items-center gap-2">
                        <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                        {{ isSubmitting ? 'Submitting...' : 'Submit Grade Document' }}
                    </span>
                </button>
                <div v-if="isSubmitting" class="space-y-1">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>Uploading file...</span>
                        <span>{{ uploadProgress }}%</span>
                    </div>
                    <div class="h-2 rounded bg-slate-200 overflow-hidden">
                        <div class="h-full bg-[#1f2937] transition-all duration-150" :style="{ width: `${uploadProgress}%` }" />
                    </div>
                </div>
            </section>

            <section v-if="sortedSubmissions.length > 0" class="grid grid-cols-1 gap-3 md:hidden">
                <div v-for="row in sortedSubmissions" :key="row.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
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

            <div v-else-if="sortedSubmissions.length === 0" class="bg-white border border-slate-200 rounded-xl p-6 text-slate-500 text-center">
                No submissions yet.
            </div>

            <section v-if="showHistoryTable" class="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Period</th>
                            <th class="px-3 py-2 text-left">Uploaded</th>
                            <th class="px-3 py-2 text-left">Document</th>
                            <th class="px-3 py-2 text-left">Evaluation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sortedSubmissions" :key="row.id" class="border-t border-slate-200 text-slate-700">
                            <td class="px-3 py-2">{{ row.period_label || '-' }}</td>
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
                        <tr v-if="sortedSubmissions.length === 0">
                            <td colspan="4" class="px-3 py-8 text-center text-slate-500">No submissions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>
