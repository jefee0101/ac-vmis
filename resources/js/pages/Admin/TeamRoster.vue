<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import { showAppToast } from '@/composables/useAppToast'
import { useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type CoachOption = {
    id: number
    name: string
    status: string | null
    email: string | null
    is_available: boolean
    assigned_team_id: number | null
    assigned_role: string | null
    unavailable_reason: string | null
}

type PlayerOption = {
    id: number
    name: string
    student_id_number: string | null
    academic_level_label: string | null
    email: string | null
    is_available: boolean
    assigned_team_id: number | null
    unavailable_reason: string | null
}

type TeamPlayerRow = {
    id: number
    student_id: number
    name: string
    student_id_number: string | null
    academic_level_label: string | null
    course_or_strand: string | null
    email: string | null
    avatar: string | null
    height: string | null
    weight: string | null
    jersey_number: string | null
    athlete_position: string | null
    player_status: string
    manual_inactive: boolean
}

const props = defineProps<{
    team: {
        id: number
        team_name: string
        team_avatar: string | null
        sport: { id: number; name: string } | null
        year: string | number | null
        description: string | null
        coach: { id: number; user_id: number | null; name: string; email: string | null; avatar: string | null; coach_status: string | null } | null
        assistantCoach: { id: number; user_id: number | null; name: string; email: string | null; avatar: string | null; coach_status: string | null } | null
        players: TeamPlayerRow[]
    }
    coaches: CoachOption[]
    players: PlayerOption[]
    readOnly: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const headCoachId = ref<number | null>(props.team.coach?.id ?? null)
const assistantCoachId = ref<number | null>(props.team.assistantCoach?.id ?? null)
const selectedPlayerIds = ref<number[]>(props.team.players.map((player) => player.student_id))
const availableSearch = ref('')
const rosterSearch = ref('')
const rosterStatusFilter = ref<'all' | 'active' | 'injured' | 'suspended' | 'inactive'>('all')
const savingMembership = ref(false)
const savingPlayerId = ref<number | null>(null)
const actingPlayerId = ref<number | null>(null)

const existingPlayersByStudentId = computed(() => new Map(props.team.players.map((player) => [player.student_id, player])))

const playerDrafts = reactive<Record<number, { jersey_number: string; athlete_position: string }>>(
    Object.fromEntries(
        props.team.players.map((player) => [
            player.id,
            {
                jersey_number: player.jersey_number ?? '',
                athlete_position: player.athlete_position ?? '',
            },
        ]),
    ),
)

const sportPositionMap: Record<string, string[]> = {
    basketball: ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'],
    soccer: [
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
}

const sportPositions = computed(() => {
    const sportName = String(props.team.sport?.name ?? '').trim().toLowerCase()
    return sportPositionMap[sportName] ?? []
})

const originalMembershipSignature = computed(() => JSON.stringify({
    coach_id: props.team.coach?.id ?? null,
    assistant_coach_id: props.team.assistantCoach?.id ?? null,
    player_ids: [...props.team.players.map((player) => player.student_id)].sort((a, b) => a - b),
}))

const currentMembershipSignature = computed(() => JSON.stringify({
    coach_id: headCoachId.value ?? null,
    assistant_coach_id: assistantCoachId.value ?? null,
    player_ids: [...selectedPlayerIds.value].sort((a, b) => a - b),
}))

const membershipDirty = computed(() => originalMembershipSignature.value !== currentMembershipSignature.value)

const rosterRows = computed(() => {
    return selectedPlayerIds.value
        .map((studentId) => {
            const existing = existingPlayersByStudentId.value.get(studentId)
            if (existing) return existing

            const option = props.players.find((player) => player.id === studentId)
            if (!option) return null

            return {
                id: 0,
                student_id: option.id,
                name: option.name,
                student_id_number: option.student_id_number,
                academic_level_label: option.academic_level_label,
                course_or_strand: null,
                email: option.email,
                avatar: null,
                height: null,
                weight: null,
                jersey_number: null,
                athlete_position: null,
                player_status: 'active',
                manual_inactive: false,
            } satisfies TeamPlayerRow
        })
        .filter(Boolean)
})

const filteredRosterRows = computed(() => {
    const query = rosterSearch.value.trim().toLowerCase()

    return rosterRows.value.filter((player) => {
        const statusMatch = rosterStatusFilter.value === 'all' || String(player.player_status).toLowerCase() === rosterStatusFilter.value
        if (!statusMatch) return false

        if (!query) return true

        return [
            player.name,
            player.student_id_number,
            player.course_or_strand,
            player.academic_level_label,
            player.email,
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .some((value) => value.includes(query))
    })
})

const availablePlayers = computed(() => {
    const query = availableSearch.value.trim().toLowerCase()
    const selectedIds = new Set(selectedPlayerIds.value)

    return props.players.filter((player) => {
        if (selectedIds.has(player.id)) return false
        if (!(player.is_available || player.assigned_team_id === props.team.id)) return false
        if (!query) return true

        return [
            player.name,
            player.student_id_number,
            player.academic_level_label,
            player.email,
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .some((value) => value.includes(query))
    })
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

function initialsFromText(value?: string | null) {
    return String(value ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function playerStatusTone(status?: string | null) {
    const value = String(status ?? 'active').toLowerCase()
    if (value === 'inactive') return 'bg-slate-200 text-slate-700'
    if (value === 'injured') return 'bg-amber-100 text-amber-700'
    if (value === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function formatMeasure(value: string | null | undefined, unit: string) {
    const text = String(value ?? '').trim()
    if (!text) return '-'
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function canUseCoach(option: CoachOption) {
    return option.is_available || option.id === headCoachId.value || option.id === assistantCoachId.value
}

function addPlayer(studentId: number) {
    if (selectedPlayerIds.value.includes(studentId)) return
    selectedPlayerIds.value = [...selectedPlayerIds.value, studentId]
}

function removePlayer(studentId: number) {
    selectedPlayerIds.value = selectedPlayerIds.value.filter((id) => id !== studentId)
}

function saveMembership() {
    if (!headCoachId.value) {
        showAppToast('Please assign a head coach before saving.', 'error', {
            summary: 'Team Roster',
        })
        return
    }

    router.put(`/teams/${props.team.id}/view-roster`, {
        coach_id: headCoachId.value,
        assistant_coach_id: assistantCoachId.value,
        player_ids: selectedPlayerIds.value,
    }, {
        preserveScroll: true,
        onStart: () => {
            savingMembership.value = true
        },
        onSuccess: () => {
            showAppToast('Roster membership updated successfully.', 'success', {
                summary: 'Team Roster',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]
            showAppToast(String(firstError || 'Unable to update the team roster.'), 'error', {
                summary: 'Team Roster',
            })
        },
        onFinish: () => {
            savingMembership.value = false
        },
    })
}

function savePlayerDetails(player: TeamPlayerRow) {
    if (!player.id) {
        showAppToast('Save roster membership first before editing jersey or position for newly added players.', 'error', {
            summary: 'Player Details',
        })
        return
    }

    router.put(`/teams/team-players/${player.id}/details`, {
        jersey_number: playerDrafts[player.id]?.jersey_number ?? '',
        athlete_position: playerDrafts[player.id]?.athlete_position ?? '',
    }, {
        preserveScroll: true,
        onStart: () => {
            savingPlayerId.value = player.id
        },
        onSuccess: () => {
            showAppToast('Player details updated successfully.', 'success', {
                summary: 'Player Details',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]
            showAppToast(String(firstError || 'Unable to update player details.'), 'error', {
                summary: 'Player Details',
            })
        },
        onFinish: () => {
            savingPlayerId.value = null
        },
    })
}

function togglePlayerStatus(player: TeamPlayerRow) {
    if (!player.id) return

    const route = String(player.player_status).toLowerCase() === 'inactive'
        ? `/teams/team-players/${player.id}/reactivate`
        : `/teams/team-players/${player.id}/deactivate`

    router.post(route, {}, {
        preserveScroll: true,
        onStart: () => {
            actingPlayerId.value = player.id
        },
        onSuccess: () => {
            showAppToast(
                String(player.player_status).toLowerCase() === 'inactive'
                    ? 'Player reactivated successfully.'
                    : 'Player marked inactive successfully.',
                'success',
                { summary: 'Roster Status' },
            )
        },
        onError: () => {
            showAppToast('Unable to update player status right now.', 'error', {
                summary: 'Roster Status',
            })
        },
        onFinish: () => {
            actingPlayerId.value = null
        },
    })
}

function goToSchedules() {
    router.get('/operations', {
        tab: 'calendar',
        team_id: props.team.id,
    })
}

function archiveTeam() {
    router.post(`/teams/${props.team.id}/archive`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`${team.team_name} Roster`" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <Link href="/teams" class="text-sm font-medium text-[#034485] hover:text-[#033a70]">
                    ← Back to Teams
                </Link>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Admin team workspace</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border border-[#034485]/30 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5"
                    @click="goToSchedules"
                >
                    Schedules
                </button>
                <button
                    v-if="!readOnly"
                    type="button"
                    class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100"
                    @click="archiveTeam"
                >
                    Archive
                </button>
            </div>
        </div>

        <section class="rounded-3xl bg-[#034485] p-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 overflow-hidden rounded-2xl border border-white/20 bg-white/10">
                        <img :src="teamAvatarUrl(team.team_avatar)" alt="Team avatar" class="h-full w-full object-cover" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :style="{ backgroundColor: sportColor(team.sport?.name ?? ''), color: sportTextColor(team.sport?.name ?? '') }"
                            >
                                {{ sportLabel(team.sport?.name ?? '') }}
                            </span>
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                                {{ team.year || 'No Year' }}
                            </span>
                        </div>
                        <h1 class="mt-3 text-3xl font-bold">{{ team.team_name }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            Manage coach assignments, player membership, and roster details from one dedicated admin page.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Players</p>
                        <p class="mt-1 text-xl font-bold">{{ selectedPlayerIds.length }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Head Coach</p>
                        <p class="mt-1 text-sm font-semibold">{{ team.coach?.name || 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Assistant</p>
                        <p class="mt-1 text-sm font-semibold">{{ team.assistantCoach?.name || 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Search Ready</p>
                        <p class="mt-1 text-sm font-semibold">Roster + Players</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Coach Section</h2>
                            <p class="text-sm text-slate-500">Update the head coach and assistant coach assigned to this team.</p>
                        </div>
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70] disabled:opacity-60"
                            :disabled="!membershipDirty || savingMembership"
                            @click="saveMembership"
                        >
                            {{ savingMembership ? 'Saving...' : 'Save Roster Changes' }}
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Head Coach</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700">
                                    <img v-if="team.coach?.avatar" :src="userAvatarUrl(team.coach.avatar)" alt="Head coach photo" class="h-full w-full object-cover" />
                                    <span v-else>{{ initialsFromText(team.coach?.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ team.coach?.name || 'Unassigned' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ team.coach?.email || 'No email available' }}</p>
                                </div>
                            </div>
                            <select v-model="headCoachId" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                                <option :value="null" disabled>Select head coach</option>
                                <option
                                    v-for="coach in coaches"
                                    :key="coach.id"
                                    :value="coach.id"
                                    :disabled="!canUseCoach(coach) || coach.id === assistantCoachId"
                                >
                                    {{ coach.name }}{{ !coach.is_available && coach.id !== team.coach?.id ? ` - ${coach.unavailable_reason}` : '' }}
                                </option>
                            </select>
                        </article>

                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Assistant Coach</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700">
                                    <img v-if="team.assistantCoach?.avatar" :src="userAvatarUrl(team.assistantCoach.avatar)" alt="Assistant coach photo" class="h-full w-full object-cover" />
                                    <span v-else>{{ initialsFromText(team.assistantCoach?.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ team.assistantCoach?.name || 'Unassigned' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ team.assistantCoach?.email || 'No assistant coach assigned' }}</p>
                                </div>
                            </div>
                            <select v-model="assistantCoachId" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                                <option :value="null">No assistant coach</option>
                                <option
                                    v-for="coach in coaches"
                                    :key="coach.id"
                                    :value="coach.id"
                                    :disabled="!canUseCoach(coach) || coach.id === headCoachId"
                                >
                                    {{ coach.name }}{{ !coach.is_available && coach.id !== team.assistantCoach?.id ? ` - ${coach.unavailable_reason}` : '' }}
                                </option>
                            </select>
                        </article>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Player Section</h2>
                            <p class="text-sm text-slate-500">Search, filter, and manage student-athletes assigned to this team.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input
                                v-model="rosterSearch"
                                type="text"
                                placeholder="Search roster by name, ID, course"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-72"
                            />
                            <select v-model="rosterStatusFilter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option value="all">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="injured">Injured</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <article
                            v-for="player in filteredRosterRows"
                            :key="`${player.student_id}-${player.id}`"
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                        <img v-if="player.avatar" :src="userAvatarUrl(player.avatar)" alt="Player profile photo" class="h-full w-full object-cover" />
                                        <span v-else>{{ initialsFromText(player.name) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="truncate text-base font-semibold text-slate-900">{{ player.name }}</p>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="playerStatusTone(player.player_status)">
                                                {{ String(player.player_status || 'active').toUpperCase() }}
                                            </span>
                                            <span v-if="!player.id" class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-semibold text-sky-700">
                                                Pending Save
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ player.student_id_number || '-' }} • {{ player.academic_level_label || 'No level set' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ player.course_or_strand || player.email || 'No extra details' }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="!readOnly"
                                        type="button"
                                        class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400"
                                        @click="removePlayer(player.student_id)"
                                    >
                                        Remove Player
                                    </button>
                                    <button
                                        v-if="!readOnly && player.id"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-xs font-semibold"
                                        :class="String(player.player_status).toLowerCase() === 'inactive'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                            : 'border-amber-300 bg-amber-50 text-amber-700'"
                                        :disabled="actingPlayerId === player.id"
                                        @click="togglePlayerStatus(player)"
                                    >
                                        {{ actingPlayerId === player.id ? 'Updating...' : String(player.player_status).toLowerCase() === 'inactive' ? 'Reactivate' : 'Mark Inactive' }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-4">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Height</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.height, 'cm') }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Weight</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.weight, 'kg') }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Jersey</p>
                                    <input
                                        v-if="player.id"
                                        v-model="playerDrafts[player.id].jersey_number"
                                        type="text"
                                        maxlength="20"
                                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    />
                                    <p v-else class="mt-1 text-sm text-slate-500">Save roster first</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Position</p>
                                    <select
                                        v-if="player.id && sportPositions.length > 0"
                                        v-model="playerDrafts[player.id].athlete_position"
                                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    >
                                        <option value="">Select position</option>
                                        <option v-for="position in sportPositions" :key="position" :value="position">{{ position }}</option>
                                    </select>
                                    <input
                                        v-else-if="player.id"
                                        v-model="playerDrafts[player.id].athlete_position"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="Set position"
                                    />
                                    <p v-else class="mt-1 text-sm text-slate-500">Save roster first</p>
                                </div>
                            </div>

                            <div v-if="!readOnly && player.id" class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70] disabled:opacity-60"
                                    :disabled="savingPlayerId === player.id"
                                    @click="savePlayerDetails(player)"
                                >
                                    {{ savingPlayerId === player.id ? 'Saving...' : 'Save Player Details' }}
                                </button>
                            </div>
                        </article>

                        <div v-if="filteredRosterRows.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            No players matched the current roster search or filter.
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-lg font-semibold text-slate-900">Add Student-Athletes</h2>
                        <p class="text-sm text-slate-500">Browse available students and add them to the roster before saving team membership.</p>
                    </div>

                    <input
                        v-model="availableSearch"
                        type="text"
                        placeholder="Search available players"
                        class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    />

                    <div class="mt-4 max-h-[42rem] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="player in availablePlayers"
                            :key="player.id"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p class="text-sm font-semibold text-slate-900">{{ player.name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ player.student_id_number || '-' }} • {{ player.academic_level_label || 'No level set' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ player.email || 'No email available' }}</p>
                            <button
                                v-if="!readOnly"
                                type="button"
                                class="mt-3 rounded-full border border-[#034485]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#034485]/5"
                                @click="addPlayer(player.id)"
                            >
                                Add Player
                            </button>
                        </article>

                        <div v-if="availablePlayers.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            No available players matched your search.
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</template>
