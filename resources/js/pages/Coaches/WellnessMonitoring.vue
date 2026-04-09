<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

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

type FormState = {
    injury_observed: boolean
    injury_notes: string
    fatigue_level: string
    performance_condition: string
    remarks: string
}

const rowForms = ref<Record<number, FormState>>(
    Object.fromEntries(
        (props.athletes || []).map((row) => [
            row.student_id,
            {
                injury_observed: !!row.wellness?.injury_observed,
                injury_notes: row.wellness?.injury_notes ?? '',
                fatigue_level: row.wellness?.fatigue_level ? String(row.wellness.fatigue_level) : '3',
                performance_condition: row.wellness?.performance_condition ?? 'good',
                remarks: row.wellness?.remarks ?? '',
            },
        ]),
    ),
)

function openSchedule(scheduleId: number) {
    selectedSchedule.value = scheduleId
    router.get('/coach/operations', { tab: 'wellness', wellness_schedule_id: scheduleId }, {
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
</script>

<template>
    <Head title="Wellness Monitoring" />
    <div class="space-y-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-slate-900">Wellness Monitoring</h1>
            <p class="text-sm text-slate-500">Post-practice or game logs for athletes who attended.</p>
        </div>

        <div v-if="!team" class="rounded-xl border border-slate-200 bg-white p-6 text-slate-500">
            You are not assigned to a team yet.
        </div>

        <div v-else class="space-y-4">
            <div class="text-sm text-slate-600">
                Team: <span class="font-medium text-slate-900">{{ team.team_name }}</span>
                • Sport: <span class="font-medium capitalize text-slate-900">{{ team.sport }}</span>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <label class="mb-1 block text-sm text-slate-600">Select Completed Practice/Game</label>
                <select
                    v-model="selectedSchedule"
                    @change="openSchedule(Number(selectedSchedule))"
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                >
                    <option :value="null" disabled>Select schedule</option>
                    <option v-for="s in schedules" :key="s.id" :value="s.id">
                        {{ s.title }} ({{ s.type }}) - {{ new Date(s.end).toLocaleDateString() }}
                    </option>
                </select>
                <p v-if="schedules.length === 0" class="mt-2 text-xs text-slate-500">
                    No completed practice/game schedules available yet.
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3 text-sm text-slate-600">
                    Attended Athletes
                </div>

                <div v-if="athletes.length === 0" class="p-6 text-sm text-slate-500">
                    No attended students found for this schedule.
                </div>

                <div v-else class="divide-y divide-slate-200">
                    <div v-for="row in athletes" :key="row.student_id" class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ row.name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ row.student_id_number || '-' }} • {{ statusLabel(row.attendance_status) }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="rowForms[row.student_id].injury_observed" type="checkbox" />
                                Injury Observed
                            </label>
                            <div>
                                <label class="text-xs text-slate-500">Fatigue Level</label>
                                <select
                                    v-model="rowForms[row.student_id].fatigue_level"
                                    class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
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
                                    v-model="rowForms[row.student_id].performance_condition"
                                    class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
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
                                    v-model="rowForms[row.student_id].injury_notes"
                                    type="text"
                                    class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                                    placeholder="Optional"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-slate-500">Remarks</label>
                            <textarea
                                v-model="rowForms[row.student_id].remarks"
                                rows="2"
                                class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                                placeholder="Optional"
                            />
                        </div>

                        <div class="flex justify-end">
                            <button
                                @click="saveRow(row.student_id)"
                                class="px-3 py-1.5 rounded bg-[#F53003] text-white hover:bg-[#d42a02] transition"
                                :disabled="savingKey === `${selectedSchedule}:${row.student_id}`"
                            >
                                Save Wellness Log
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
