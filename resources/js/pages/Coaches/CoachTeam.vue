<script setup lang="ts">
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useSportColors } from '@/composables/useSportColors'

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
        course_or_strand?: string | null
        current_grade_level?: string | null
        phone_number?: string | null
        gender?: string | null
        height?: string | null
        weight?: string | null
        emergency_contact_name?: string | null
        emergency_contact_relationship?: string | null
        emergency_contact_phone?: string | null
        user?: {
            email?: string | null
            avatar?: string | null
        } | null
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
const detailsOpen = ref(false)
const selectedPlayer = ref<PlayerRow | null>(null)
const copiedField = ref<string | null>(null)

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const { sportColor, sportTextColor } = useSportColors()

const players = computed<PlayerRow[]>(() => props.team?.players ?? [])
const selectedStudent = computed(() => selectedPlayer.value?.student ?? null)

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

function userAvatarUrl(path?: string | null) {
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

function openDetails(player: PlayerRow) {
    selectedPlayer.value = player
    detailsOpen.value = true
}

function closeDetails() {
    detailsOpen.value = false
}

function formatSimple(value?: string | number | null) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    return text === '' ? '-' : text
}

function formatMeasure(value?: string | number | null, unit?: string) {
    const text = formatSimple(value)
    if (text === '-') return text
    if (!unit) return text
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

async function copyToClipboard(value?: string | number | null, label?: string) {
    const text = formatSimple(value)
    if (text === '-') return
    try {
        await navigator.clipboard.writeText(text)
        copiedField.value = label ?? text
        window.setTimeout(() => {
            if (copiedField.value === (label ?? text)) copiedField.value = null
        }, 1200)
    } catch (error) {
        // silent fail
    }
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
        </div>

        <div v-if="!props.team" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-slate-500">You are not assigned to any team yet.</p>
        </div>

        <div v-else class="space-y-5">
            <section class="rounded-2xl border border-[#034485] bg-[#034485] p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <img
                        :src="teamAvatarUrl(props.team.team_avatar)"
                        class="h-24 w-24 rounded-2xl object-cover ring-4 ring-white sm:h-28 sm:w-28"
                    />

                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-white/90">
                            <span
                                class="rounded-full px-2 py-0.5 text-white"
                                :style="{
                                    backgroundColor: sportColor(props.team.sport?.name ?? props.team.sport?.id ?? props.team.sport),
                                    color: sportTextColor(props.team.sport?.name ?? props.team.sport?.id ?? props.team.sport),
                                }"
                            >
                                {{ props.team.sport?.name }}
                            </span>
                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-white">{{ props.team.year ?? 'N/A' }}</span>
                        </div>
                        <h2 class="mt-2 text-2xl font-bold text-white">{{ props.team.team_name }}</h2>
                        <div class="mt-2 text-sm text-white/90">
                            <p><span class="font-semibold text-white">Head Coach:</span> {{ props.team.coach?.first_name }} {{ props.team.coach?.last_name }}</p>
                            <p><span class="font-semibold text-white">Assistant Coach:</span> {{ props.team.assistantCoach?.first_name }} {{ props.team.assistantCoach?.last_name }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            class="rounded-full border border-white bg-white px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-white/90"
                            @click="openRequest()"
                        >
                            Request Team Change
                        </button>
                        <button
                            class="rounded-full border border-white bg-white px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-white/90"
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

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="player in filteredPlayers" :key="player.id" class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold text-slate-900">{{ player.student?.first_name }} {{ player.student?.last_name }}</p>
                            <p class="text-xs text-slate-500">{{ player.student?.student_id_number || '-' }}</p>
                            <button
                                type="button"
                                class="mt-1 inline-flex rounded-full border border-[#034485] px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                @click="openDetails(player)"
                            >
                                View Details
                            </button>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone((player.player_status ?? 'active') as PlayerStatus)">
                            {{ (player.player_status ?? 'active').toString().toUpperCase() }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600">
                        <div>
                            <span class="text-slate-500">Jersey</span>
                            <p class="font-semibold text-slate-900">
                                <span v-if="player.jersey_number">{{ player.jersey_number }}</span>
                                <span v-else class="text-amber-600">Pending</span>
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-500">Position</span>
                            <p class="font-semibold text-slate-900">
                                <span v-if="player.athlete_position">{{ player.athlete_position }}</span>
                                <span v-else class="text-red-600">Unassigned</span>
                            </p>
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
        </div>

        <transition name="athlete-modal">
            <div v-if="detailsOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-6" @click.self="closeDetails">
                <div class="w-full max-w-2xl rounded-2xl border border-[#034485]/35 bg-white p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="min-w-[220px] flex-1 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</p>
                            <h3 class="text-2xl font-bold text-slate-900">
                                {{ formatSimple(selectedStudent?.first_name) }} {{ formatSimple(selectedStudent?.last_name) }}
                            </h3>
                        </div>
                        <div class="grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                            <p>
                                <span class="font-semibold text-slate-900">Student ID:</span>
                                <span class="ml-1 inline-flex items-center gap-2">
                                    {{ formatSimple(selectedStudent?.student_id_number) }}
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                        @click="copyToClipboard(selectedStudent?.student_id_number, 'student-id')"
                                        title="Copy student ID"
                                        aria-label="Copy student ID"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                            <path
                                                fill="currentColor"
                                                d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                            />
                                        </svg>
                                        <span v-if="copiedField === 'student-id'" class="text-[10px]">Copied</span>
                                    </button>
                                </span>
                            </p>
                            <p><span class="font-semibold text-slate-900">Position:</span> {{ formatSimple(selectedPlayer?.athlete_position) }}</p>
                            <p><span class="font-semibold text-slate-900">Jersey:</span> {{ formatSimple(selectedPlayer?.jersey_number) }}</p>
                        </div>

                        <div class="grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                            <p><span class="font-semibold text-slate-900">Course/Strand:</span> {{ formatSimple(selectedStudent?.course_or_strand) }}</p>
                            <p><span class="font-semibold text-slate-900">Current Grade Level:</span> {{ formatSimple(selectedStudent?.current_grade_level) }}</p>
                            <p><span class="font-semibold text-slate-900">Gender:</span> {{ formatSimple(selectedStudent?.gender) }}</p>
                            <p><span class="font-semibold text-slate-900">Height:</span> {{ formatMeasure(selectedStudent?.height, 'cm') }}</p>
                            <p><span class="font-semibold text-slate-900">Weight:</span> {{ formatMeasure(selectedStudent?.weight, 'kg') }}</p>
                        </div>
                    </div>
                    <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#034485]/25 bg-[#034485]/5">
                        <img :src="userAvatarUrl(selectedStudent?.user?.avatar ?? null)" alt="Student avatar" class="h-full w-full object-cover" />
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact</p>
                    <div class="mt-2 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                        <p>
                            <span class="font-semibold text-slate-900">Email:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.user?.email) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.user?.email, 'email')"
                                    title="Copy email"
                                    aria-label="Copy email"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'email'" class="text-[10px]">Copied</span>
                                </button>
                            </span>
                        </p>
                        <p>
                            <span class="font-semibold text-slate-900">Phone:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.phone_number) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.phone_number, 'phone')"
                                    title="Copy phone number"
                                    aria-label="Copy phone number"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'phone'" class="text-[10px]">Copied</span>
                                </button>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Contact</p>
                    <div class="mt-2 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                        <p><span class="font-semibold text-slate-900">Name:</span> {{ formatSimple(selectedStudent?.emergency_contact_name) }}</p>
                        <p><span class="font-semibold text-slate-900">Relationship:</span> {{ formatSimple(selectedStudent?.emergency_contact_relationship) }}</p>
                        <p class="sm:col-span-2">
                            <span class="font-semibold text-slate-900">Phone:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.emergency_contact_phone) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.emergency_contact_phone, 'emergency-phone')"
                                    title="Copy emergency phone"
                                    aria-label="Copy emergency phone"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'emergency-phone'" class="text-[10px]">Copied</span>
                                </button>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full border border-[#034485] px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/10"
                        @click="closeDetails"
                    >
                        Close
                    </button>
                </div>
                </div>
            </div>
        </transition>

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

<style scoped>
.athlete-modal-enter-active,
.athlete-modal-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}
.athlete-modal-enter-from,
.athlete-modal-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.athlete-modal-enter-to,
.athlete-modal-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}
</style>
