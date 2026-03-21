<script setup lang="ts">
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import CoachPageHeader from '@/components/coach/CoachPageHeader.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

defineOptions({
    layout: CoachDashboard,
})

type PlayerStatus = 'active' | 'injured' | 'suspended'

type PlayerRow = {
    id: number
    jersey_number: string | number | null
    athlete_position: string | null
    player_status?: PlayerStatus | null
    student?: {
        first_name?: string
        last_name?: string
        student_id_number?: string
        year_level?: string | null
    }
}

const props = defineProps<{
    team: any | null
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
}>()

const positionDrafts = ref<Record<number, string>>({})
const statusDrafts = ref<Record<number, PlayerStatus>>({})
const positionSaveState = ref<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>({})
const statusSaveState = ref<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>({})

const requestDialogOpen = ref(false)
const requestNotes = ref('')
const requestSubmitting = ref(false)
const requestMessage = ref<string | null>(null)

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)

const players = computed<PlayerRow[]>(() => props.team?.players ?? [])

watch(
    () => props.team?.players,
    (list: PlayerRow[] | undefined) => {
        if (!list?.length) return
        const nextPositions: Record<number, string> = {}
        const nextStatuses: Record<number, PlayerStatus> = {}
        for (const player of list) {
            nextPositions[player.id] = player.athlete_position ?? ''
            nextStatuses[player.id] = (player.player_status ?? 'active') as PlayerStatus
        }
        positionDrafts.value = nextPositions
        statusDrafts.value = nextStatuses
    },
    { immediate: true }
)

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    }
)

const sportPositionMap: Record<string, string[]> = {
    basketball: ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'],
    football: [
        'Goalkeeper',
        'Right Back',
        'Left Back',
        'Center Back',
        'Wing Back',
        'Defensive Midfielder',
        'Central Midfielder',
        'Attacking Midfielder',
        'Winger',
        'Striker',
    ],
    volleyball: ['Setter', 'Outside Hitter', 'Opposite Hitter', 'Middle Blocker', 'Libero', 'Defensive Specialist'],
    badminton: ['Singles Player', 'Doubles Specialist', 'Mixed Doubles Specialist'],
    'table tennis': ['Singles Player', 'Doubles Player', 'Mixed Doubles Player'],
}

function positionsForSport(): string[] {
    const sportName = String(props.team?.sport?.name ?? '')
        .trim()
        .toLowerCase()

    return sportPositionMap[sportName] ?? []
}

const filteredPlayers = computed(() => {
    return players.value
})

function teamAvatarUrl(path?: string | null) {
    if (!path) return '/images/default-avatar.svg'
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path}`
}

function statusTone(status: PlayerStatus) {
    if (status === 'injured') return 'bg-amber-100 text-amber-700'
    if (status === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function setSaveState(
    stateRef: typeof positionSaveState,
    playerId: number,
    state: 'idle' | 'saving' | 'saved' | 'error'
) {
    stateRef.value = { ...stateRef.value, [playerId]: state }
    if (state === 'saved' || state === 'error') {
        window.setTimeout(() => {
            stateRef.value = { ...stateRef.value, [playerId]: 'idle' }
        }, 2000)
    }
}

function savePosition(teamPlayerId: number) {
    setSaveState(positionSaveState, teamPlayerId, 'saving')
    router.put(
        `/coach/team-players/${teamPlayerId}/position`,
        { athlete_position: positionDrafts.value[teamPlayerId] ?? '' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveState(positionSaveState, teamPlayerId, 'saved'),
            onError: () => setSaveState(positionSaveState, teamPlayerId, 'error'),
        },
    )
}

function saveStatus(teamPlayerId: number) {
    setSaveState(statusSaveState, teamPlayerId, 'saving')
    router.put(
        `/coach/team-players/${teamPlayerId}/status`,
        { player_status: statusDrafts.value[teamPlayerId] },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveState(statusSaveState, teamPlayerId, 'saved'),
            onError: () => setSaveState(statusSaveState, teamPlayerId, 'error'),
        },
    )
}

function openRequest() {
    requestNotes.value = ''
    requestMessage.value = null
    requestDialogOpen.value = true
}

function submitRequest() {
    requestSubmitting.value = true
    requestMessage.value = null

    router.post(
        '/coach/team/requests',
        {
            type: 'team_change',
            notes: requestNotes.value,
            team_id: selectedTeamId.value ?? undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                requestMessage.value = 'Request sent to admin.'
                requestSubmitting.value = false
            },
            onError: () => {
                requestMessage.value = 'Unable to send request.'
                requestSubmitting.value = false
            },
        },
    )
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/coach/team', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function printTeamRoster() {
    if (!selectedTeamId.value) return
    const params = new URLSearchParams()
    params.set('team_id', String(selectedTeamId.value))
    window.open(`/coach/team/print?${params.toString()}`, '_blank')
}
</script>

<template>
    <div class="space-y-5">
        <CoachPageHeader title="My Team" subtitle="Lineup, jersey requests, athlete positions, and availability." />
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

        <div v-if="!props.team" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-slate-500">You are not assigned to any team yet.</p>
        </div>

        <div v-else class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-white to-slate-50/60 p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <img
                        :src="teamAvatarUrl(props.team.team_avatar)"
                        class="h-24 w-24 rounded-2xl object-cover ring-4 ring-white sm:h-28 sm:w-28"
                    />

                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[#1f2937]">{{ props.team.sport?.name }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5">Season {{ props.team.year ?? 'N/A' }}</span>
                        </div>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ props.team.team_name }}</h2>
                        <div class="mt-2 text-sm text-slate-600">
                            <p><span class="font-semibold text-slate-700">Head Coach:</span> {{ props.team.coach?.first_name }} {{ props.team.coach?.last_name }}</p>
                            <p><span class="font-semibold text-slate-700">Assistant Coach:</span> {{ props.team.assistantCoach?.first_name }} {{ props.team.assistantCoach?.last_name }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-[#1f2937] hover:border-slate-300"
                            @click="openRequest()"
                        >
                            Request Team Change
                        </button>
                        <button
                            class="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400"
                            @click="printTeamRoster"
                        >
                            Print Roster
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="filteredPlayers.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                No players found for this team.
            </div>

            <div class="space-y-3 lg:hidden">
                <article v-for="player in filteredPlayers" :key="player.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold text-slate-900">{{ player.student?.first_name }} {{ player.student?.last_name }}</p>
                            <p class="text-xs text-slate-500">{{ player.student?.student_id_number || '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Year: {{ player.student?.year_level || '-' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone((player.player_status ?? 'active') as PlayerStatus)">
                            {{ (player.player_status ?? 'active').toString().toUpperCase() }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600">
                        <div>
                            <span class="text-slate-500">Jersey</span>
                            <p class="font-semibold text-slate-900">{{ player.jersey_number || 'Pending' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Position</span>
                            <p class="font-semibold text-slate-900">{{ player.athlete_position || 'Unassigned' }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="text-xs text-slate-500">Update Position</label>
                            <input
                                v-model="positionDrafts[player.id]"
                                :list="`sport-position-options-${props.team.id}`"
                                type="text"
                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                placeholder="Assign position"
                            />
                            <div class="mt-2 flex items-center justify-between">
                                <button @click="savePosition(player.id)" class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs text-white hover:bg-[#111827]">Save Position</button>
                                <span v-if="positionSaveState[player.id] && positionSaveState[player.id] !== 'idle'" class="text-[11px] text-slate-500">
                                    {{ positionSaveState[player.id] === 'saving' ? 'Saving...' : positionSaveState[player.id] === 'saved' ? 'Saved' : 'Error' }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Status</label>
                            <select
                                v-model="statusDrafts[player.id]"
                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                @change="saveStatus(player.id)"
                            >
                                <option value="active">Active</option>
                                <option value="injured">Injured</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <span v-if="statusSaveState[player.id] && statusSaveState[player.id] !== 'idle'" class="mt-1 block text-[11px] text-slate-500">
                                {{ statusSaveState[player.id] === 'saving' ? 'Saving...' : statusSaveState[player.id] === 'saved' ? 'Saved' : 'Error' }}
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white lg:block">
                <div class="max-h-[520px] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-3 text-left">Student</th>
                                <th class="px-3 py-3 text-left">ID Number</th>
                                <th class="px-3 py-3 text-left">Year</th>
                                <th class="px-3 py-3 text-left">Jersey</th>
                                <th class="px-3 py-3 text-left">Status</th>
                                <th class="px-3 py-3 text-left">Position</th>
                                <th class="px-3 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="player in filteredPlayers" :key="player.id" class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">{{ player.student?.first_name }} {{ player.student?.last_name }}</div>
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ player.student?.student_id_number || '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ player.student?.year_level || '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ player.jersey_number || 'Pending' }}</td>
                                <td class="px-3 py-2">
                                    <select
                                        v-model="statusDrafts[player.id]"
                                        class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                                        @change="saveStatus(player.id)"
                                    >
                                        <option value="active">Active</option>
                                        <option value="injured">Injured</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                    <span v-if="statusSaveState[player.id] && statusSaveState[player.id] !== 'idle'" class="mt-1 block text-[11px] text-slate-500">
                                        {{ statusSaveState[player.id] === 'saving' ? 'Saving...' : statusSaveState[player.id] === 'saved' ? 'Saved' : 'Error' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model="positionDrafts[player.id]"
                                        :list="`sport-position-options-${props.team.id}`"
                                        type="text"
                                        class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                                        placeholder="Assign position"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <button
                                        @click="savePosition(player.id)"
                                        class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs text-white hover:bg-[#111827]"
                                    >
                                        Save Position
                                    </button>
                                    <span v-if="positionSaveState[player.id] && positionSaveState[player.id] !== 'idle'" class="mt-1 block text-[11px] text-slate-500">
                                        {{ positionSaveState[player.id] === 'saving' ? 'Saving...' : positionSaveState[player.id] === 'saved' ? 'Saved' : 'Error' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="requestDialogOpen"
            title="Request Team Change"
            description="Add the details you want the admin team to review."
            confirm-text="Send Request"
            @update:open="requestDialogOpen = $event"
            @confirm="submitRequest"
        >
            <div class="space-y-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Details (optional)</label>
                <textarea
                    v-model="requestNotes"
                    rows="3"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                    placeholder="Provide context (names, schedule, reason)"
                />

                <p v-if="requestMessage" class="text-xs text-emerald-600">{{ requestMessage }}</p>
                <p v-if="requestSubmitting" class="text-xs text-slate-500">Sending request...</p>
            </div>
        </ConfirmDialog>

        <datalist :id="`sport-position-options-${props.team?.id}`">
            <option v-for="position in positionsForSport()" :key="position" :value="position" />
        </datalist>
    </div>
</template>
