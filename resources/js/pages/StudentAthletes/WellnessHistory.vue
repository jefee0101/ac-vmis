<script setup lang="ts">
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({
    layout: StudentAthleteDashboard,
});

type WellnessLog = {
    id: number;
    log_date: string | null;
    team_name: string | null;
    schedule_title: string | null;
    schedule_type: string | null;
    injury_observed: boolean;
    injury_notes: string | null;
    fatigue_level: number | null;
    performance_condition: string | null;
    remarks: string | null;
    logged_by: string | null;
    created_at: string | null;
};

const props = defineProps<{
    student: {
        id: number;
        student_id_number: string | null;
        name: string;
    } | null;
    logs: WellnessLog[];
    accessLocked?: boolean;
    lockStatus?: string | null;
    lockMessage?: string | null;
}>();

const search = ref('');
const selectedLogId = ref<number | null>(null);
const showInjuryOnly = ref(false);

const filteredLogs = computed(() => {
    const q = search.value.trim().toLowerCase();
    const items = props.logs || [];
    return items.filter((row) => {
        if (showInjuryOnly.value && !row.injury_observed) return false;
        if (!q) return true;
        return (
            (row.team_name || '').toLowerCase().includes(q) ||
            (row.schedule_title || '').toLowerCase().includes(q) ||
            (row.logged_by || '').toLowerCase().includes(q) ||
            (row.performance_condition || '').toLowerCase().includes(q)
        );
    });
});

const latestLog = computed(() => filteredLogs.value[0] || null);
const totalLogs = computed(() => filteredLogs.value.length);
const injuryCount = computed(() => filteredLogs.value.filter((row) => row.injury_observed).length);
const avgFatigue = computed(() => {
    const values = filteredLogs.value.map((row) => row.fatigue_level).filter((v) => typeof v === 'number') as number[];
    if (!values.length) return null;
    return Math.round(values.reduce((sum, v) => sum + v, 0) / values.length);
});

function toggleDetails(id: number) {
    selectedLogId.value = selectedLogId.value === id ? null : id;
}
</script>

<template>
    <Head title="Wellness History" />
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Wellness History</h1>
                <p class="mt-1 text-sm text-slate-500">Your coach-recorded post-session wellness logs.</p>
            </div>
            <div v-if="!accessLocked" class="flex flex-wrap gap-2">
                <button
                    @click="showInjuryOnly = !showInjuryOnly"
                    class="rounded-full border border-[#034485]/40 bg-white px-4 py-2 text-xs font-semibold"
                    :class="showInjuryOnly ? 'border-[#034485] bg-[#034485] text-white' : 'text-[#034485] hover:bg-[#034485]/10'"
                >
                    {{ showInjuryOnly ? 'Showing Injury Only' : 'Filter Injury Only' }}
                </button>
            </div>
        </div>

        <div v-if="accessLocked" class="rounded-3xl border border-[#034485]/35 bg-[#034485]/5 p-6 text-slate-700">
            <h2 class="text-sm font-semibold text-slate-800">Wellness Access Paused</h2>
            <p class="mt-1 text-sm text-slate-600">{{ lockMessage || 'Wellness access is paused during the academic submission window.' }}</p>
            <div class="mt-3 text-xs text-slate-600">
                Status:
                <span class="ml-2 inline-flex rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white">
                    {{ lockStatus || 'Suspended' }}
                </span>
            </div>
            <Link
                href="/AcademicSubmissions"
                class="mt-4 inline-flex rounded-full border border-[#034485]/40 px-3 py-1 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
            >
                Go to Academic Submissions
            </Link>
        </div>

        <template v-else>
            <div v-if="!student" class="rounded-3xl border border-[#034485]/35 bg-white p-4 text-slate-600">Student profile not found.</div>

            <template v-else>
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Total logs</p>
                        <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ totalLogs }}</p>
                    </div>
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Injury flags</p>
                        <p class="mt-1 text-2xl font-semibold text-rose-600">{{ injuryCount }}</p>
                    </div>
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Avg fatigue</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ avgFatigue ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Latest log</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ latestLog?.log_date || '—' }}</p>
                        <p class="text-xs text-slate-500">{{ latestLog?.schedule_title || 'No entries yet' }}</p>
                    </div>
                </section>

                <div class="rounded-full border border-[#034485]/35 bg-white p-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search team, schedule, coach..."
                        class="w-full rounded-full border border-[#034485]/30 bg-white px-4 py-2 text-slate-700"
                    />
                </div>

                <section v-if="filteredLogs.length > 0" class="grid grid-cols-1 gap-3 md:hidden">
                    <button
                        v-for="row in filteredLogs"
                        :key="row.id"
                        class="rounded-3xl border border-[#034485]/35 bg-white p-4 text-left"
                        @click="toggleDetails(row.id)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ row.log_date || '-' }}</p>
                                <p class="text-xs text-slate-500">{{ row.schedule_title || '-' }} • {{ row.schedule_type || '-' }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="
                                    row.injury_observed
                                        ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                        : 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                "
                            >
                                {{ row.injury_observed ? 'Injury' : 'Clear' }}
                            </span>
                        </div>

                        <div class="mt-2 text-xs text-slate-600">
                            Fatigue: {{ row.fatigue_level ?? '-' }} • Performance: {{ row.performance_condition || '-' }}
                        </div>

                        <div v-if="selectedLogId === row.id" class="mt-3 space-y-1 text-xs text-slate-500">
                            <div>Team: {{ row.team_name || '-' }}</div>
                            <div>Coach: {{ row.logged_by || '-' }}</div>
                            <div v-if="row.injury_notes">Injury notes: {{ row.injury_notes }}</div>
                            <div v-if="row.remarks">Remarks: {{ row.remarks }}</div>
                        </div>
                    </button>
                </section>

                <div v-else-if="filteredLogs.length === 0" class="rounded-3xl border border-[#034485]/35 bg-white p-6 text-center text-slate-500">
                    No wellness logs available yet.
                </div>

                <div class="overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-[#034485] text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Schedule</th>
                                    <th class="px-4 py-3 text-left">Condition</th>
                                    <th class="px-4 py-3 text-left">Coach</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in filteredLogs" :key="row.id" class="border-t border-[#034485]/20 text-slate-700">
                                    <td class="px-4 py-3">{{ row.log_date || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div>{{ row.team_name || '-' }}</div>
                                        <div class="text-xs text-slate-500">{{ row.schedule_title || '-' }} ({{ row.schedule_type || '-' }})</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>Fatigue: {{ row.fatigue_level ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 capitalize">Performance: {{ row.performance_condition || '-' }}</div>
                                        <div :class="row.injury_observed ? 'text-xs text-rose-600' : 'text-xs text-emerald-600'">
                                            {{ row.injury_observed ? 'Injury observed' : 'No injury observed' }}
                                        </div>
                                        <div v-if="row.injury_notes" class="text-xs text-slate-500">Injury notes: {{ row.injury_notes }}</div>
                                        <div v-if="row.remarks" class="text-xs text-slate-500">Remarks: {{ row.remarks }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ row.logged_by || '-' }}</td>
                                </tr>
                                <tr v-if="filteredLogs.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">No wellness logs available yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
