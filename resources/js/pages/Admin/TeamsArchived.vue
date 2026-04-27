<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type TeamRow = {
    id: number
    team_name: string
    team_avatar: string | null
    sport?: { id: number; name: string } | null
    year?: string | number | null
    coach?: { id: number; first_name?: string; last_name?: string } | null
    assistantCoach?: { id: number; first_name?: string; last_name?: string } | null
    players_count: number
    max_players: number
    roster_health: { key: string; label: string; tone: string }
    is_archived: boolean
    archived_at?: string | null
}

const props = defineProps<{
    teams: {
        data: TeamRow[]
        meta: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }
    filters: Record<string, any>
    options: {
        sports: { id: number; name: string }[]
        years: (string | number)[]
    }
    readOnly: boolean
}>()

const showFilters = ref(false)
const filters = reactive({
    search: String(props.filters?.search ?? ''),
    sport_id: props.filters?.sport_id ? String(props.filters.sport_id) : '',
    year: props.filters?.year ? String(props.filters.year) : '',
    sort: String(props.filters?.sort ?? 'updated_at'),
    direction: String(props.filters?.direction ?? 'desc'),
})

const expandedTeamIds = ref<number[]>([])
const rosterCache = ref<Record<number, any[]>>({})
const rosterLoading = ref<Record<number, boolean>>({})
const restoreDialogOpen = ref(false)
const pendingRestoreTeam = ref<TeamRow | null>(null)

function buildQuery(extra: Record<string, any> = {}) {
    return {
        search: filters.search || undefined,
        sport_id: filters.sport_id || undefined,
        year: filters.year || undefined,
        sort: filters.sort,
        direction: filters.direction,
        ...extra,
    }
}

function reload(extra: Record<string, any> = {}) {
    router.get('/teams/archived', buildQuery(extra), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearFilters() {
    filters.search = ''
    filters.sport_id = ''
    filters.year = ''
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    showFilters.value = false
    reload()
}

function goBackToActiveTeams() {
    router.get('/teams')
}

function fullName(person: any): string {
    const first = person?.first_name ?? ''
    const last = person?.last_name ?? ''
    const out = `${first} ${last}`.trim()
    return out || 'N/A'
}

function teamAvatarUrl(path: string | null) {
    if (!path) return '/images/default-avatar.svg'
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path}`
}

function formatArchivedAt(value: string | null | undefined) {
    if (!value) return 'Unknown date'
    const date = new Date(value)
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

function formatMeasure(value: string | number | null | undefined, unit: string) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    if (!text) return '-'
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function printRoster(teamId: number) {
    window.open(`/teams/${teamId}/print`, '_blank')
}

async function toggleTeamExpanded(teamId: number) {
    if (expandedTeamIds.value.includes(teamId)) {
        expandedTeamIds.value = expandedTeamIds.value.filter((id) => id !== teamId)
        return
    }

    expandedTeamIds.value.push(teamId)
    if (rosterCache.value[teamId]) return

    rosterLoading.value = { ...rosterLoading.value, [teamId]: true }
    try {
        const response = await fetch(`/teams/${teamId}/roster`, { credentials: 'same-origin' })
        const data = await response.json()
        rosterCache.value = {
            ...rosterCache.value,
            [teamId]: data.players ?? [],
        }
    } catch {
        rosterCache.value = {
            ...rosterCache.value,
            [teamId]: [],
        }
    } finally {
        rosterLoading.value = { ...rosterLoading.value, [teamId]: false }
    }
}

function promptRestore(team: TeamRow) {
    if (props.readOnly) return
    pendingRestoreTeam.value = team
    restoreDialogOpen.value = true
}

function confirmRestore() {
    if (!pendingRestoreTeam.value) return
    restoreDialogOpen.value = false
    router.post(`/teams/${pendingRestoreTeam.value.id}/reactivate`, {}, { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Archived Teams</h1>
                    <p class="text-sm text-slate-600">Historical and inactive teams are managed here separately from active operations.</p>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="goBackToActiveTeams"
                >
                    Back to Active Teams
                </button>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search archived teams, sport, year, coach"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                    @keyup.enter="reload()"
                />
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="reload()">
                    Search
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="showFilters = !showFilters">
                    Filters
                </button>
            </div>

            <div v-if="showFilters" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 lg:grid-cols-4">
                <select v-model="filters.sport_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Sports</option>
                    <option v-for="sport in options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                </select>
                <select v-model="filters.year" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Years</option>
                    <option v-for="y in options.years" :key="String(y)" :value="String(y)">{{ y }}</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="filters.sort" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="updated_at">Sort: Updated</option>
                        <option value="team_name">Sort: Team Name</option>
                        <option value="year">Sort: Year</option>
                        <option value="sport">Sort: Sport</option>
                        <option value="players">Sort: Players</option>
                    </select>
                    <select v-model="filters.direction" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="reload()">Apply</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="clearFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-[#034485]/45 bg-white p-4">
            <div v-if="teams.data.length === 0" class="p-4 text-sm text-slate-500">
                No archived teams found for the selected filters.
            </div>

            <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="team in teams.data" :key="team.id" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Archived</p>
                        <p class="text-[11px] text-slate-500">Archived: {{ formatArchivedAt(team.archived_at) }}</p>
                    </div>

                    <div class="flex items-start gap-3">
                        <img :src="teamAvatarUrl(team.team_avatar)" alt="Team Avatar" class="h-12 w-12 rounded-md object-cover" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-semibold text-slate-900">{{ team.team_name }}</p>
                            <p class="text-xs text-slate-600">{{ team.sport?.name || 'No sport' }} • {{ team.year || 'Unassigned year' }}</p>
                            <p class="text-xs text-slate-500">Head: {{ fullName(team.coach) }}</p>
                            <p class="text-xs text-slate-500">Assistant: {{ fullName(team.assistantCoach) }}</p>
                            <p class="text-xs text-slate-500">Players: {{ team.players_count }} / {{ team.max_players }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs" @click="toggleTeamExpanded(team.id)">View Roster</button>
                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs" @click="printRoster(team.id)">Print Roster</button>
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-2 py-1 text-xs text-emerald-700"
                            @click="promptRestore(team)"
                        >
                            Restore Team
                        </button>
                    </div>

                    <div v-if="expandedTeamIds.includes(team.id)" class="mt-3 rounded-md border border-slate-200 bg-white p-2">
                        <p v-if="rosterLoading[team.id]" class="text-xs text-slate-500">Loading roster...</p>
                        <ul v-else-if="(rosterCache[team.id] || []).length" class="space-y-2 text-xs text-slate-700">
                            <li v-for="player in rosterCache[team.id]" :key="player.id">
                                <p class="font-medium text-slate-800">
                                    {{ player.name }} <span class="font-normal text-slate-500">({{ player.student_id_number || 'No ID' }})</span>
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Height: <span class="font-medium text-slate-700">{{ formatMeasure(player.height, 'cm') }}</span>
                                    <span class="mx-1.5 text-slate-300">|</span>
                                    Weight: <span class="font-medium text-slate-700">{{ formatMeasure(player.weight, 'kg') }}</span>
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-slate-500">No players assigned.</p>
                    </div>
                </article>
            </div>
        </section>

        <ConfirmDialog
            :open="restoreDialogOpen"
            title="Restore Team"
            :description="pendingRestoreTeam ? `Restore ${pendingRestoreTeam.team_name} to active teams?` : ''"
            confirm-text="Restore"
            confirm-variant="default"
            @update:open="restoreDialogOpen = $event"
            @confirm="confirmRestore"
        />
    </div>
</template>
