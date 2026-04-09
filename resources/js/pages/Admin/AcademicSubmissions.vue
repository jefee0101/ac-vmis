<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    status: 'draft' | 'open' | 'closed'
    announcement: string | null
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
        status: string | null
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
    file_url: string | null
    period: { id: number; school_year: string; term: string } | null
    evaluation: {
        id: number
        gpa: number | null
        status: string | null
        remarks: string | null
        evaluated_at: string | null
        evaluator_name: string | null
    } | null
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
const isLoading = ref(false)
const perPage = ref(15)

const evalForms = ref<Record<number, { gpa: string; remarks: string }>>(
    Object.fromEntries((props.rows || []).map((row) => [
        row.document_id,
        {
            gpa: row.evaluation?.gpa != null ? String(row.evaluation.gpa) : '',
            remarks: row.evaluation?.remarks ?? '',
        },
    ])),
)

const brokenThumbs = reactive<Record<number, boolean>>({})

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
        file_url: row.file_url,
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
        per_page: perPage.value,
        total: props.rows?.length ?? 0,
        from: props.rows?.length ? 1 : null,
        to: props.rows?.length ?? null,
    },
})

const selectedPeriod = computed(() =>
    (props.periods || []).find((p) => p.id === selectedPeriodId.value) || null,
)

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

function isImageUrl(url: string | null) {
    if (!url) return false
    if (url.includes('/files/academic/')) return true
    const clean = url.split('?')[0]
    return /\.(png|jpe?g|webp|gif)$/i.test(clean)
}

function statusTone(status: string | null | undefined) {
    if (status === 'eligible') return 'bg-emerald-100 text-emerald-700'
    if (status === 'probation') return 'bg-amber-100 text-amber-700'
    if (status === 'ineligible') return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-700'
}

function buildQuery(page = 1) {
    return {
        period_id: selectedPeriodId.value || undefined,
        per_page: perPage.value,
        page,
    }
}

async function fetchSubmissions(page = 1) {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(page)).forEach(([key, value]) => {
        if (value === undefined || value === null) return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/submissions/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as PaginatedPayload<SubmissionsRow>

    const nextForms: Record<number, { gpa: string; remarks: string }> = {}
    payload.data.forEach((row) => {
        nextForms[row.document_id] = {
            gpa: row.evaluation?.gpa != null ? String(row.evaluation.gpa) : '',
            remarks: row.evaluation?.remarks ?? '',
        }
    })
    evalForms.value = nextForms
    submissionsState.value = payload
}

function changePeriod() {
    router.get('/academics/submissions', { period_id: selectedPeriodId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => fetchSubmissions(1),
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
        remarks: form.remarks,
    }, {
        preserveScroll: true,
        onSuccess: () => fetchSubmissions(submissionsState.value.meta.current_page),
    })
}

onMounted(() => {
    fetchSubmissions(1)
})
</script>

<template>
    <Head title="Academic Submissions" />

    <div class="space-y-5">
        <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="router.get('/academics')"
                    >
                        Back to Academics
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">Academic Submissions</h1>
                        <p class="text-sm text-slate-600">Confirm documents, update GPA notes, and save evaluations.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <span>Period</span>
                    <select
                        v-model="selectedPeriodId"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        @change="changePeriod"
                    >
                        <option :value="null" disabled>Select period</option>
                        <option v-for="p in periods" :key="p.id" :value="p.id">
                            {{ p.school_year }} - {{ termLabel(p.term) }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="selectedPeriod" class="mt-3 text-xs text-slate-500">
                Viewing: {{ selectedPeriod.school_year }} - {{ termLabel(selectedPeriod.term) }}
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-[#034485]/45 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-[#034485] text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Student</th>
                            <th class="px-4 py-3 text-left">Document</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Remarks</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <transition-group name="table-fade" tag="tbody">
                        <tr v-for="row in submissionsState.data" :key="row.document_id" class="border-t border-slate-200 text-slate-700 align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a
                                        v-if="row.file_url"
                                        :href="row.file_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="group"
                                        title="Open document"
                                    >
                                        <div
                                            v-if="isImageUrl(row.file_url) && !brokenThumbs[row.document_id]"
                                            class="h-12 w-12 overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                                        >
                                            <img
                                                :src="row.file_url"
                                                alt="Document preview"
                                                class="h-full w-full object-cover"
                                                @error="brokenThumbs[row.document_id] = true"
                                            />
                                        </div>
                                        <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-semibold text-slate-500">
                                            VIEW
                                        </div>
                                    </a>
                                    <div>
                                        <div class="text-xs font-semibold uppercase text-slate-700">{{ row.document_type }}</div>
                                        <div class="text-xs text-slate-500">{{ formatDateTime(row.uploaded_at) }}</div>
                                        <div class="text-xs text-slate-500">{{ row.notes || 'No notes' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="evalForms[row.document_id]" class="space-y-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusTone(row.evaluation?.status)">
                                        Current: {{ row.evaluation?.status || 'pending' }}
                                    </span>
                                </div>
                                <div v-else class="text-xs text-slate-400">Loading status...</div>
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    v-if="evalForms[row.document_id]"
                                    v-model="evalForms[row.document_id].remarks"
                                    type="text"
                                    placeholder="Remarks (optional)"
                                    class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                                />
                                <div v-else class="text-xs text-slate-400">Loading remarks...</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#334155]"
                                    @click="saveEvaluation(row)"
                                >
                                    Save Status
                                </button>
                            </td>
                        </tr>
                        <tr v-if="submissionsState.data.length === 0" key="empty">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No submissions yet for this period.</td>
                        </tr>
                    </transition-group>
                </table>
            </div>
        </section>

        <div class="flex items-center justify-between text-sm text-slate-600">
            <p>
                Showing
                {{ submissionsState.meta.from || 0 }}-
                {{ submissionsState.meta.to || 0 }}
                of
                {{ submissionsState.meta.total }}
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                    :disabled="submissionsState.meta.current_page <= 1 || isLoading"
                    @click="fetchSubmissions(submissionsState.meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                    :disabled="submissionsState.meta.current_page >= submissionsState.meta.last_page || isLoading"
                    @click="fetchSubmissions(submissionsState.meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.table-fade-enter-active,
.table-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.table-fade-enter-from,
.table-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.table-fade-move {
    transition: transform 0.2s ease;
}
</style>
