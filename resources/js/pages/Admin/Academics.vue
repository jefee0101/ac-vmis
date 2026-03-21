<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'

defineOptions({
    layout: AdminDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    is_submission_open: boolean
    announcement: string | null
    is_locked: boolean
    locked_at: string | null
    locked_by_name: string | null
}

type LegacyRow = {
    document_id: number
    student_id: number
    student_name: string
    student_id_number: string | null
    uploaded_at: string | null
    document_type: string
    notes: string | null
    file_url: string | null
    evaluation: {
        id: number
        gpa: number | null
        status: 'eligible' | 'probation' | 'ineligible'
        remarks: string | null
        evaluated_at: string | null
    } | null
}

type SubmissionsRow = {
    document_id: number
    student_id: number
    student_name: string
    student_id_number: string | null
    document_type: string
    uploaded_at: string | null
    notes: string | null
    period: { id: number; school_year: string; term: string } | null
    evaluation: {
        id: number
        gpa: number | null
        status: 'eligible' | 'probation' | 'ineligible'
        remarks: string | null
        evaluated_at: string | null
        evaluator_name: string | null
    } | null
}

type EvaluationsRow = {
    evaluation_id: number
    student_id: number
    student_name: string
    student_id_number: string | null
    period: { id: number; school_year: string; term: string } | null
    document_id: number | null
    document_type: string | null
    gpa: number | null
    status: 'eligible' | 'probation' | 'ineligible'
    remarks: string | null
    evaluated_at: string | null
    evaluator_name: string | null
}

type ExceptionsRow = {
    student_id: number
    student_name: string
    student_id_number: string | null
    document_id: number
    uploaded_at: string | null
    evaluation_id: number | null
    evaluation_status: 'eligible' | 'probation' | 'ineligible' | null
    gpa: number | null
    evaluated_at: string | null
    exception_type: 'pending_evaluation' | 'at_risk'
}

type PaginatedPayload<T> = {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number | null
        to: number | null
    }
}

const props = defineProps<{
    periods: Period[]
    selectedPeriodId: number | null
    rows: LegacyRow[]
}>()

const selectedPeriodId = ref<number | null>(props.selectedPeriodId)
const activeTab = ref<'periods' | 'submissions' | 'evaluations' | 'exceptions' | 'policies'>('periods')
const isLoading = ref(false)
const showFilters = ref(false)
const lockPeriodDialogOpen = ref(false)
const pendingLockValue = ref<boolean | null>(null)
const auditDialogOpen = ref(false)
const auditNote = ref('')
const pendingEvaluation = ref<EvaluationsRow | null>(null)
const noticeDialog = ref<{ open: boolean; title: string; description: string }>({
    open: false,
    title: '',
    description: '',
})
const schoolYear = ref('')
const term = ref<'1st_sem' | '2nd_sem' | 'summer'>('1st_sem')
const startsOn = ref('')
const endsOn = ref('')
const announcement = ref('')

const filterForm = reactive({
    search: '',
    status: '',
    start_date: '',
    end_date: '',
    per_page: '15',
})

const evalForms = ref<Record<number, { gpa: string; status: 'eligible' | 'probation' | 'ineligible'; remarks: string }>>(
    Object.fromEntries((props.rows || []).map((row) => [
        row.document_id,
        {
            gpa: row.evaluation?.gpa != null ? String(row.evaluation.gpa) : '',
            status: row.evaluation?.status ?? 'eligible',
            remarks: row.evaluation?.remarks ?? '',
        },
    ])),
)

const initialPeriod = props.selectedPeriodId
    ? (props.periods || []).find((p) => p.id === props.selectedPeriodId) || null
    : null

const submissionsState = ref<PaginatedPayload<SubmissionsRow>>({
    data: (props.rows || []).map((row) => ({
        document_id: row.document_id,
        student_id: row.student_id,
        student_name: row.student_name,
        student_id_number: row.student_id_number,
        document_type: row.document_type,
        uploaded_at: row.uploaded_at,
        notes: row.notes,
        period: selectedPeriodId.value
            ? {
                id: selectedPeriodId.value,
                school_year: initialPeriod?.school_year || '',
                term: initialPeriod?.term || '',
            }
            : null,
        evaluation: row.evaluation
            ? {
                id: row.evaluation.id,
                gpa: row.evaluation.gpa,
                status: row.evaluation.status,
                remarks: row.evaluation.remarks,
                evaluated_at: row.evaluation.evaluated_at,
                evaluator_name: null,
            }
            : null,
    })),
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: props.rows?.length ?? 0,
        from: props.rows?.length ? 1 : null,
        to: props.rows?.length ?? null,
    },
})

const evaluationsState = ref<PaginatedPayload<EvaluationsRow>>({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
})

const exceptionsState = ref<{
    kpis: { missing_submissions: number; pending_evaluation: number; probation: number; ineligible: number }
    rows: ExceptionsRow[]
}>({
    kpis: { missing_submissions: 0, pending_evaluation: 0, probation: 0, ineligible: 0 },
    rows: [],
})

const selectedPeriod = computed(() =>
    (props.periods || []).find((p) => p.id === selectedPeriodId.value) || null,
)

const activeFilterCount = computed(() => {
    let count = 0
    if (filterForm.search.trim()) count++
    if (filterForm.status) count++
    if (filterForm.start_date || filterForm.end_date) count++
    if (filterForm.per_page !== '15') count++
    return count
})

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function formatDateTime(dt: string | null) {
    if (!dt) return '-'
    return new Date(dt).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function statusTone(status: string | null) {
    if (status === 'eligible') return 'bg-emerald-100 text-emerald-700'
    if (status === 'probation') return 'bg-amber-100 text-amber-700'
    if (status === 'ineligible') return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-700'
}

function buildQuery(page = 1) {
    return {
        period_id: selectedPeriodId.value || undefined,
        search: filterForm.search.trim() || undefined,
        status: filterForm.status || undefined,
        start_date: filterForm.start_date || undefined,
        end_date: filterForm.end_date || undefined,
        per_page: filterForm.per_page,
        page,
    }
}

function applyQuickPeriod(period: 'today' | 'week' | 'month') {
    const now = new Date()
    const start = new Date(now)
    const end = new Date(now)

    if (period === 'week') {
        const day = start.getDay()
        const deltaToMonday = day === 0 ? 6 : day - 1
        start.setDate(start.getDate() - deltaToMonday)
        end.setDate(start.getDate() + 6)
    }

    if (period === 'month') {
        start.setDate(1)
        end.setMonth(end.getMonth() + 1, 0)
    }

    const toIsoDate = (value: Date) => {
        const yyyy = value.getFullYear()
        const mm = String(value.getMonth() + 1).padStart(2, '0')
        const dd = String(value.getDate()).padStart(2, '0')
        return `${yyyy}-${mm}-${dd}`
    }

    filterForm.start_date = toIsoDate(start)
    filterForm.end_date = toIsoDate(end)
    fetchActiveTab(1)
}

function clearQuickDates() {
    filterForm.start_date = ''
    filterForm.end_date = ''
    fetchActiveTab(1)
}

function setTab(tab: 'periods' | 'submissions' | 'evaluations' | 'exceptions' | 'policies') {
    activeTab.value = tab
    if (tab === 'exceptions') {
        filterForm.status = ''
    }
    if (tab === 'submissions' || tab === 'evaluations' || tab === 'exceptions') {
        fetchActiveTab(1)
    }
}

function createPeriod() {
    router.post('/academics/periods', {
        school_year: schoolYear.value,
        term: term.value,
        starts_on: startsOn.value,
        ends_on: endsOn.value,
        announcement: announcement.value,
    })
}

function toggleWindow(open: boolean) {
    if (!selectedPeriod.value) return
    router.put(`/academics/periods/${selectedPeriod.value.id}/window`, {
        is_submission_open: open,
        announcement: selectedPeriod.value.announcement ?? '',
    })
}

function toggleLock(lock: boolean) {
    if (!selectedPeriod.value) return
    if (lock) {
        pendingLockValue.value = true
        lockPeriodDialogOpen.value = true
        return
    }
    applyLock(false)
}

function applyLock(lock: boolean) {
    if (!selectedPeriod.value) return
    router.put(`/academics/periods/${selectedPeriod.value.id}/lock`, {
        is_locked: lock,
    })
}

function saveEvaluation(row: SubmissionsRow) {
    const form = evalForms.value[row.document_id]
    if (!form || !selectedPeriodId.value) return

    router.post('/academics/evaluate', {
        period_id: selectedPeriodId.value,
        student_id: row.student_id,
        document_id: row.document_id,
        gpa: form.gpa ? Number(form.gpa) : null,
        status: form.status,
        remarks: form.remarks,
    }, {
        preserveScroll: true,
        onSuccess: () => fetchSubmissions(submissionsState.value.meta.current_page),
    })
}

async function updateEvaluation(row: EvaluationsRow) {
    if (!row.period?.id || !row.document_id) {
        noticeDialog.value = {
            open: true,
            title: 'Update Blocked',
            description: 'Cannot update evaluation without linked period and document.',
        }
        return
    }
    pendingEvaluation.value = row
    auditNote.value = ''
    auditDialogOpen.value = true
}

async function confirmEvaluationUpdate() {
    const row = pendingEvaluation.value
    if (!row?.period?.id || !row.document_id) return

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch(`/academics/evaluations/${row.student_id}/${row.period.id}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({
            document_id: row.document_id,
            gpa: row.gpa,
            status: row.status,
            remarks: row.remarks,
            audit_note: auditNote.value.trim() || null,
        }),
    })

    if (!response.ok) {
        noticeDialog.value = {
            open: true,
            title: 'Save Failed',
            description: 'Unable to update evaluation.',
        }
        return
    }

    auditDialogOpen.value = false
    pendingEvaluation.value = null
    fetchEvaluations(evaluationsState.value.meta.current_page)
}

function confirmLockPeriod() {
    if (pendingLockValue.value !== true) return
    lockPeriodDialogOpen.value = false
    applyLock(true)
    pendingLockValue.value = null
}

function printSummary() {
    const params = new URLSearchParams()
    const query = buildQuery(1)
    if (query.period_id) {
        params.set('period_id', String(query.period_id))
    }
    const suffix = params.toString() ? `?${params.toString()}` : ''
    window.open(`/academics/print${suffix}`, '_blank')
}

async function fetchSubmissions(page = 1) {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(page)).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/submissions/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as PaginatedPayload<SubmissionsRow>

    const nextForms: Record<number, { gpa: string; status: 'eligible' | 'probation' | 'ineligible'; remarks: string }> = {}
    payload.data.forEach((row) => {
        nextForms[row.document_id] = {
            gpa: row.evaluation?.gpa != null ? String(row.evaluation.gpa) : '',
            status: row.evaluation?.status ?? 'eligible',
            remarks: row.evaluation?.remarks ?? '',
        }
    })
    evalForms.value = nextForms
    submissionsState.value = payload
}

async function fetchEvaluations(page = 1) {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(page)).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/evaluations/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as PaginatedPayload<EvaluationsRow>
    evaluationsState.value = payload
}

async function fetchExceptions() {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(1)).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/exceptions?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as {
        kpis: { missing_submissions: number; pending_evaluation: number; probation: number; ineligible: number }
        rows: ExceptionsRow[]
    }

    exceptionsState.value = payload
}

function fetchActiveTab(page = 1) {
    if (activeTab.value === 'submissions') {
        fetchSubmissions(page)
        return
    }

    if (activeTab.value === 'evaluations') {
        fetchEvaluations(page)
        return
    }

    fetchExceptions()
}

function deadlineCountdown(endsOn: string | null) {
    if (!endsOn) return 'No deadline'
    const now = new Date()
    const end = new Date(endsOn + 'T23:59:59')
    const diffMs = end.getTime() - now.getTime()
    if (diffMs < 0) return 'Deadline passed'
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24))
    if (days === 0) return 'Deadline today'
    if (days === 1) return '1 day left'
    return `${days} days left`
}

function resetFilters() {
    filterForm.search = ''
    filterForm.status = ''
    filterForm.start_date = ''
    filterForm.end_date = ''
    filterForm.per_page = '15'
    fetchActiveTab(1)
}

function changePeriod() {
    router.get('/academics', { period_id: selectedPeriodId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => fetchActiveTab(1),
    })
}

onMounted(() => {
    fetchSubmissions(1)
})
</script>

<template>
    <Head title="Academics Workspace" />

    <div class="space-y-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Academics Workspace</h1>
                    <p class="text-sm text-slate-600">Unified submissions, evaluations, and exceptions queue for academic compliance.</p>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    @click="printSummary"
                >
                    Print Summary
                </button>
            </div>

            <div v-if="activeTab !== 'periods' && activeTab !== 'policies'" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Search student, ID, document"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                    @keyup.enter="fetchActiveTab(1)"
                />
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="fetchActiveTab(1)">
                    Search
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="showFilters = !showFilters">
                    Filters <span v-if="activeFilterCount" class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs">{{ activeFilterCount }}</span>
                </button>
            </div>

            <div v-if="activeTab !== 'periods' && activeTab !== 'policies'" class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200" @click="clearQuickDates">All Time</button>
                <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200" @click="applyQuickPeriod('today')">Today</button>
                <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200" @click="applyQuickPeriod('week')">This Week</button>
                <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200" @click="applyQuickPeriod('month')">This Month</button>
            </div>

            <div v-if="showFilters && activeTab !== 'periods' && activeTab !== 'policies'" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 lg:grid-cols-4">
                <select v-model="selectedPeriodId" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="changePeriod">
                    <option :value="null" disabled>Select period</option>
                    <option v-for="p in periods" :key="p.id" :value="p.id">
                        {{ p.school_year }} - {{ termLabel(p.term) }}
                    </option>
                </select>

                <select v-model="filterForm.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm" :disabled="activeTab === 'exceptions'">
                    <option value="">All Statuses</option>
                    <option value="eligible">Eligible</option>
                    <option value="probation">Probation</option>
                    <option value="ineligible">Ineligible</option>
                    <option value="pending">Pending Evaluation</option>
                </select>

                <input v-model="filterForm.start_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="filterForm.end_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />

                <div class="grid grid-cols-3 gap-2">
                    <select v-model="filterForm.per_page" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="fetchActiveTab(1)">Apply</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="resetFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'periods' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('periods')"
                >
                    Periods
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'submissions' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('submissions')"
                >
                    Submissions
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'evaluations' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('evaluations')"
                >
                    Evaluations
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'exceptions' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('exceptions')"
                >
                    Risk & Exceptions
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'policies' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('policies')"
                >
                    Policies/Rules
                </button>
            </div>

            <div v-if="activeTab === 'periods' && selectedPeriod" class="mb-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-[#1f2937] px-2 py-0.5 text-xs font-semibold text-white">Active Period</span>
                    <span class="font-semibold text-slate-900">{{ selectedPeriod.school_year }} - {{ termLabel(selectedPeriod.term) }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="selectedPeriod.is_submission_open ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                        {{ selectedPeriod.is_submission_open ? 'OPEN' : 'CLOSED' }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="selectedPeriod.is_locked ? 'bg-slate-800 text-white' : 'bg-slate-200 text-slate-700'">
                        {{ selectedPeriod.is_locked ? 'LOCKED' : 'UNLOCKED' }}
                    </span>
                    <span class="ml-auto text-xs text-slate-500">Deadline: {{ deadlineCountdown(selectedPeriod.ends_on) }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md bg-emerald-700 px-3 py-1.5 text-white disabled:opacity-40" :disabled="selectedPeriod.is_locked" @click="toggleWindow(true)">Open Window</button>
                    <button type="button" class="rounded-md bg-red-700 px-3 py-1.5 text-white disabled:opacity-40" :disabled="selectedPeriod.is_locked" @click="toggleWindow(false)">Close Window</button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-100"
                        @click="toggleLock(!selectedPeriod.is_locked)"
                    >
                        {{ selectedPeriod.is_locked ? 'Unlock Period' : 'Lock Period' }}
                    </button>
                </div>
            </div>

            <section v-if="activeTab === 'periods'" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-800">Create Academic Period</h2>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    <input v-model="schoolYear" placeholder="2026-2027" class="rounded-md border border-slate-300 px-2 py-2 text-sm" />
                    <select v-model="term" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
                        <option value="1st_sem">1st Sem</option>
                        <option value="2nd_sem">2nd Sem</option>
                        <option value="summer">Summer</option>
                    </select>
                    <input v-model="startsOn" type="date" class="rounded-md border border-slate-300 px-2 py-2 text-sm" />
                    <input v-model="endsOn" type="date" class="rounded-md border border-slate-300 px-2 py-2 text-sm" />
                    <button type="button" class="rounded-md bg-[#1f2937] px-3 py-2 text-sm font-semibold text-white hover:bg-[#334155]" @click="createPeriod">Create</button>
                </div>
                <textarea v-model="announcement" rows="2" placeholder="Announcement for students (optional)" class="mt-2 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
            </section>

            <div v-if="activeTab === 'exceptions'" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <article class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs text-slate-500">Missing Submissions</p>
                    <p class="text-xl font-semibold text-slate-900">{{ exceptionsState.kpis.missing_submissions }}</p>
                </article>
                <article class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs text-amber-700">Pending Evaluation</p>
                    <p class="text-xl font-semibold text-amber-900">{{ exceptionsState.kpis.pending_evaluation }}</p>
                </article>
                <article class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                    <p class="text-xs text-orange-700">Probation</p>
                    <p class="text-xl font-semibold text-orange-900">{{ exceptionsState.kpis.probation }}</p>
                </article>
                <article class="rounded-lg border border-red-200 bg-red-50 p-3">
                    <p class="text-xs text-red-700">Ineligible</p>
                    <p class="text-xl font-semibold text-red-900">{{ exceptionsState.kpis.ineligible }}</p>
                </article>
            </div>

            <section v-if="activeTab === 'submissions'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Athlete</th>
                                <th class="px-4 py-3 text-left">Submission</th>
                                <th class="px-4 py-3 text-left">Evaluation</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in submissionsState.data" :key="row.document_id" class="border-t border-slate-200 text-slate-700 align-top">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium uppercase text-xs">{{ row.document_type }}</div>
                                    <div class="text-xs text-slate-500">{{ formatDateTime(row.uploaded_at) }}</div>
                                    <div class="text-xs text-slate-500">{{ row.notes || '-' }}</div>
                                </td>
                                <td class="px-4 py-3 space-y-2">
                                    <input v-model="evalForms[row.document_id].gpa" type="number" step="0.01" min="0" max="5" placeholder="GPA" class="w-full rounded-md border border-slate-300 px-2 py-1.5" />
                                    <select v-model="evalForms[row.document_id].status" class="w-full rounded-md border border-slate-300 px-2 py-1.5">
                                        <option value="eligible">Eligible</option>
                                        <option value="probation">Probation</option>
                                        <option value="ineligible">Ineligible</option>
                                    </select>
                                    <input v-model="evalForms[row.document_id].remarks" type="text" placeholder="Remarks" class="w-full rounded-md border border-slate-300 px-2 py-1.5" />
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#334155]" @click="saveEvaluation(row)">Save Evaluation</button>
                                </td>
                            </tr>
                            <tr v-if="submissionsState.data.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">No submission records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeTab === 'evaluations'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Athlete</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">GPA</th>
                                <th class="px-4 py-3 text-left">Remarks</th>
                                <th class="px-4 py-3 text-left">Updated</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in evaluationsState.data" :key="row.evaluation_id" class="border-t border-slate-200 text-slate-700 align-top">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <select v-model="row.status" class="rounded-md border border-slate-300 px-2 py-1.5">
                                        <option value="eligible">Eligible</option>
                                        <option value="probation">Probation</option>
                                        <option value="ineligible">Ineligible</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model.number="row.gpa" type="number" step="0.01" min="0" max="5" class="w-24 rounded-md border border-slate-300 px-2 py-1.5" />
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model="row.remarks" type="text" class="w-full rounded-md border border-slate-300 px-2 py-1.5" />
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ formatDateTime(row.evaluated_at) }}</div>
                                    <div class="text-xs text-slate-500">{{ row.evaluator_name || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100" @click="updateEvaluation(row)">Update</button>
                                </td>
                            </tr>
                            <tr v-if="evaluationsState.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No evaluation records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeTab === 'exceptions'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Athlete</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Submission</th>
                                <th class="px-4 py-3 text-left">Evaluated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in exceptionsState.rows" :key="`${row.student_id}-${row.document_id}`" class="border-t border-slate-200 text-slate-700">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="row.exception_type === 'pending_evaluation' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'">
                                        {{ row.exception_type === 'pending_evaluation' ? 'Pending Evaluation' : 'At Risk' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusTone(row.evaluation_status)">
                                        {{ row.evaluation_status || 'pending' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ formatDateTime(row.uploaded_at) }}</td>
                                <td class="px-4 py-3">{{ formatDateTime(row.evaluated_at) }}</td>
                            </tr>
                            <tr v-if="exceptionsState.rows.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No exception records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeTab === 'policies'" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800">Policies & Rules</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
                    <li>Only one active period should be open for submissions at a time.</li>
                    <li>Evaluations must include remarks for probation or ineligible outcomes.</li>
                    <li>Locking a period finalizes records and prevents submission/evaluation edits.</li>
                    <li>Use override actions only with clear audit notes.</li>
                </ul>
            </section>

            <div v-if="activeTab !== 'exceptions'" class="mt-4 flex items-center justify-between text-sm text-slate-600">
                <p>
                    Showing
                    {{ (activeTab === 'submissions' ? submissionsState.meta.from : evaluationsState.meta.from) || 0 }}-
                    {{ (activeTab === 'submissions' ? submissionsState.meta.to : evaluationsState.meta.to) || 0 }}
                    of
                    {{ activeTab === 'submissions' ? submissionsState.meta.total : evaluationsState.meta.total }}
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="(activeTab === 'submissions' ? submissionsState.meta.current_page : evaluationsState.meta.current_page) <= 1 || isLoading"
                        @click="fetchActiveTab((activeTab === 'submissions' ? submissionsState.meta.current_page : evaluationsState.meta.current_page) - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="(activeTab === 'submissions' ? submissionsState.meta.current_page : evaluationsState.meta.current_page) >= (activeTab === 'submissions' ? submissionsState.meta.last_page : evaluationsState.meta.last_page) || isLoading"
                        @click="fetchActiveTab((activeTab === 'submissions' ? submissionsState.meta.current_page : evaluationsState.meta.current_page) + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>

        <ConfirmDialog
            :open="lockPeriodDialogOpen"
            title="Lock Academic Period"
            description="Lock this period? This prevents further submission and evaluation edits."
            confirm-text="Lock Period"
            confirm-variant="destructive"
            @update:open="lockPeriodDialogOpen = $event"
            @confirm="confirmLockPeriod"
        />

        <ConfirmDialog
            :open="auditDialogOpen"
            title="Update Evaluation"
            description="Add an optional audit note for this admin update."
            confirm-text="Save Update"
            @update:open="auditDialogOpen = $event"
            @confirm="confirmEvaluationUpdate"
        >
            <label class="mb-1 block text-xs font-medium text-slate-600">Audit Note (Optional)</label>
            <textarea
                v-model="auditNote"
                rows="3"
                class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                placeholder="Reason or context for this change"
            />
        </ConfirmDialog>

        <ConfirmDialog
            :open="noticeDialog.open"
            :title="noticeDialog.title"
            :description="noticeDialog.description"
            confirm-text="OK"
            :show-cancel="false"
            @update:open="noticeDialog.open = $event"
            @confirm="noticeDialog.open = false"
        />

    </div>
</template>
