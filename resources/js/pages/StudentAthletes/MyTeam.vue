<script setup lang="ts">
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

defineOptions({
    layout: StudentAthleteDashboard,
});

const props = defineProps<{
    team: any | null
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
    currentStudentId: number | null
}>();

const showTeam = computed(() => !!props.team);
const jerseyDraft = ref('');
const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)

const myMembership = computed(() => {
    if (!props.team?.players?.length || !props.currentStudentId) return null;
    return props.team.players.find((player: any) => player.student?.id === props.currentStudentId) ?? null;
});

const totalPlayers = computed(() => props.team?.players?.length ?? 0)
const positionsFilled = computed(() => {
    if (!props.team?.players?.length) return 0
    return props.team.players.filter((player: any) => (player.athlete_position ?? '').toString().trim() !== '').length
})
const jerseysAssigned = computed(() => {
    if (!props.team?.players?.length) return 0
    return props.team.players.filter((player: any) => (player.jersey_number ?? '').toString().trim() !== '').length
})

watch(
    myMembership,
    (membership) => {
        jerseyDraft.value = membership?.jersey_number ?? '';
    },
    { immediate: true },
);

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    },
)

function saveDesiredJersey() {
    if (!myMembership.value) return;
    router.put(
        `/Student/TeamPlayers/${myMembership.value.id}/jersey`,
        { jersey_number: jerseyDraft.value },
        { preserveScroll: true, preserveState: true },
    );
}

function teamAvatarUrl(path?: string | null) {
    if (!path) return '/images/default-avatar.svg';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path;
    }
    return `/storage/${path}`;
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/MyTeam', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <div class="space-y-6">
        <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
            <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                <select
                    v-model.number="selectedTeamId"
                    @change="changeTeam"
                    class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                >
                    <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                        {{ teamOption.team_name }}
                    </option>
                </select>
            </div>
            <span v-else-if="team" class="text-slate-500">Team: {{ team.team_name }}</span>
        </div>

        <!-- No team assigned -->
        <div v-if="!showTeam" class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
            <p class="text-slate-600 font-medium">You are not assigned to any team yet.</p>
            <p class="text-sm text-slate-500 mt-1">Once assigned, your team, schedule, and wellness logs will appear here.</p>
        </div>

        <!-- Team card -->
        <div v-else class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="h-20 w-20 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center">
                            <img
                                :src="teamAvatarUrl(props.team.team_avatar)"
                                class="h-full w-full object-cover"
                                alt="Team avatar"
                            />
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">My Team</p>
                            <h2 class="text-2xl font-bold text-slate-900">{{ props.team.team_name }}</h2>
                            <p class="text-sm text-slate-500">{{ props.team.sport?.name }} • {{ props.team.year }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Athlete</span>
                        <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-[#1f2937]">
                            {{ totalPlayers }} Players
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ positionsFilled }} Positions Set
                        </span>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                            {{ jerseysAssigned }} Jerseys Set
                        </span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Head Coach</p>
                        <p class="text-sm font-semibold text-slate-800 mt-1">
                            {{ props.team.coach?.first_name }} {{ props.team.coach?.last_name }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <a
                                v-if="props.team.coach?.email"
                                :href="`mailto:${props.team.coach.email}`"
                                class="rounded-full border border-slate-200 px-2 py-0.5 text-slate-600 hover:bg-slate-50"
                            >
                                Email
                            </a>
                            <a
                                v-if="props.team.coach?.phone_number"
                                :href="`tel:${props.team.coach.phone_number}`"
                                class="rounded-full border border-slate-200 px-2 py-0.5 text-slate-600 hover:bg-slate-50"
                            >
                                Call
                            </a>
                            <span
                                v-if="!props.team.coach?.email && !props.team.coach?.phone_number"
                                class="text-slate-400"
                            >
                                Contact via admin
                            </span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Assistant Coach</p>
                        <p class="text-sm font-semibold text-slate-800 mt-1">
                            <span v-if="props.team.assistantCoach">
                                {{ props.team.assistantCoach?.first_name }} {{ props.team.assistantCoach?.last_name }}
                            </span>
                            <span v-else class="text-slate-400 font-medium">Not assigned</span>
                        </p>
                        <div v-if="props.team.assistantCoach" class="mt-2 flex flex-wrap gap-2 text-xs">
                            <a
                                v-if="props.team.assistantCoach?.email"
                                :href="`mailto:${props.team.assistantCoach.email}`"
                                class="rounded-full border border-slate-200 px-2 py-0.5 text-slate-600 hover:bg-slate-50"
                            >
                                Email
                            </a>
                            <a
                                v-if="props.team.assistantCoach?.phone_number"
                                :href="`tel:${props.team.assistantCoach.phone_number}`"
                                class="rounded-full border border-slate-200 px-2 py-0.5 text-slate-600 hover:bg-slate-50"
                            >
                                Call
                            </a>
                            <span
                                v-if="!props.team.assistantCoach?.email && !props.team.assistantCoach?.phone_number"
                                class="text-slate-400"
                            >
                                Contact via admin
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="myMembership" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">My Assignment</h3>
                        <p class="text-sm text-slate-500 mt-1">Your current role and jersey request.</p>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-slate-600">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold">
                            Position: {{ myMembership.athlete_position || 'Not assigned' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold">
                            Jersey: {{ myMembership.jersey_number || '-' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="text-sm font-medium text-slate-700" for="desired-jersey">Desired Jersey #</label>
                    <input
                        id="desired-jersey"
                        v-model="jerseyDraft"
                        type="text"
                        maxlength="20"
                        class="w-full sm:w-52 px-3 py-2 border border-slate-200 rounded-md text-slate-700"
                        placeholder="e.g. 7"
                    />
                    <button
                        @click="saveDesiredJersey"
                        class="px-4 py-2 bg-[#1f2937] text-white rounded-md hover:bg-[#334155] transition"
                    >
                        Save Jersey
                    </button>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Team Members</h3>
                    <span class="text-sm text-slate-500">{{ totalPlayers }} total</span>
                </div>

                <div v-if="props.team.players?.length" class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 gap-3 md:hidden">
                        <div v-for="player in props.team.players" :key="player.id" class="rounded-xl border border-slate-200 p-4">
                            <p class="font-semibold text-slate-800">{{ player.student?.first_name }} {{ player.student?.last_name }}</p>
                            <p class="text-xs text-slate-500">ID: {{ player.student?.student_id_number || '-' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5">Jersey: {{ player.jersey_number || '-' }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5">Position: {{ player.athlete_position || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">Player</th>
                                    <th class="px-4 py-3 text-left">Student ID</th>
                                    <th class="px-4 py-3 text-left">Jersey</th>
                                    <th class="px-4 py-3 text-left">Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="player in props.team.players" :key="player.id" class="border-t border-slate-200 text-slate-700">
                                    <td class="px-4 py-3">
                                        {{ player.student?.first_name }} {{ player.student?.last_name }}
                                    </td>
                                    <td class="px-4 py-3">{{ player.student?.student_id_number || '-' }}</td>
                                    <td class="px-4 py-3">{{ player.jersey_number || '-' }}</td>
                                    <td class="px-4 py-3">{{ player.athlete_position || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-else class="text-slate-500 mt-4">No players assigned.</p>
            </section>
        </div>
    </div>
</template>
