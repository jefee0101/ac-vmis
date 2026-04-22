<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type TeamOption = {
    id: number
    team_name: string
    sport_name: string
}

const reportTabs = [
    { label: 'Attendance', href: '/reports/attendance' },
    { label: 'Roster', href: '/reports/roster' },
    { label: 'Academics', href: '/reports/academics' },
    { label: 'Health', href: '/reports/health' },
]

const props = defineProps<{
    filters: {
        selected: {
            team_id: number | null
            clearance_status: string | null
            review_state: string | null
            start_date: string | null
            end_date: string | null
        }
        options: {
            teams: TeamOption[]
            clearance_statuses: { value: string; label: string }[]
            review_states: { value: string; label: string }[]
        }
    }
    healthReport: {
        summary: {
            total_records: number
            fit: number
            fit_with_restrictions: number
            not_fit: number
            expired: number
            reviewed: number
        }
        rows: Array<{
            id: number
            student_name: string
            student_id_number: string | null
            team_name: string
            clearance_date: string | null
            valid_until: string | null
            physician_name: string
            clearance_status: string
            review_state: string
            reviewed_by: string
        }>
    }
}>()

const form = reactive({
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    clearance_status: props.filters.selected.clearance_status ?? '',
    review_state: props.filters.selected.review_state ?? '',
    start_date: props.filters.selected.start_date ?? '',
    end_date: props.filters.selected.end_date ?? '',
})

const queryString = computed(() => {
    const params = new URLSearchParams()

    if (form.team_id) params.set('team_id', form.team_id)
    if (form.clearance_status) params.set('clearance_status', form.clearance_status)
    if (form.review_state) params.set('review_state', form.review_state)
    if (form.start_date) params.set('start_date', form.start_date)
    if (form.end_date) params.set('end_date', form.end_date)

    const query = params.toString()
    return query ? `?${query}` : ''
})

function applyFilters() {
    router.get('/reports/health', {
        team_id: form.team_id || undefined,
        clearance_status: form.clearance_status || undefined,
        review_state: form.review_state || undefined,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.team_id = ''
    form.clearance_status = ''
    form.review_state = ''
    form.start_date = ''
    form.end_date = ''
    applyFilters()
}
</script>

<template>
    <Head title="Health Report" />

    <div class="space-y-5">
        <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Health Clearance Report</h1>
                    <p class="text-sm text-slate-600">Monitor clearance validity, review completion, and current fitness status for student-athletes.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="tab in reportTabs"
                        :key="tab.href"
                        :href="tab.href"
                        class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                        :class="tab.href === '/reports/health' ? 'border-[#1f2937] bg-[#1f2937] text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        {{ tab.label }}
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="form.team_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in props.filters.options.teams" :key="team.id" :value="String(team.id)">
                            {{ team.team_name }} ({{ team.sport_name }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Clearance Status</label>
                    <select v-model="form.clearance_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Clearance Statuses</option>
                        <option v-for="status in props.filters.options.clearance_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Review State</label>
                    <select v-model="form.review_state" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Review States</option>
                        <option v-for="state in props.filters.options.review_states" :key="state.value" :value="state.value">
                            {{ state.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                    <input v-model="form.start_date" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">End Date</label>
                    <input v-model="form.end_date" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="applyFilters">
                    Apply
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                    Reset
                </button>
                <a :href="`/reports/health/export.csv${queryString}`" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Export CSV
                </a>
                <a :href="`/reports/health/print${queryString}`" target="_blank" rel="noreferrer" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Open PDF View
                </a>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Records</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.healthReport.summary.total_records }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Fit</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.healthReport.summary.fit }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Fit w/ Restrictions</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.healthReport.summary.fit_with_restrictions }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Not Fit</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.healthReport.summary.not_fit }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Expired</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.healthReport.summary.expired }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Reviewed</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.healthReport.summary.reviewed }}</p>
                </article>
            </div>

            <div v-if="props.healthReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No health clearance data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-[#034485]/45">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Clearance Date</th>
                            <th class="px-5 py-4">Valid Until</th>
                            <th class="px-5 py-4">Physician</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Review</th>
                            <th class="px-5 py-4">Reviewed By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.healthReport.rows" :key="row.id" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.team_name }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.clearance_date || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.valid_until || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.physician_name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ row.clearance_status }}</span>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.review_state }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.reviewed_by }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
