<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import MultiSelectSearch from '@/components/MultiSelectSearch.vue'
import SingleSelectSearch from '@/components/SingleSelectSearch.vue'
import Spinner from '@/components/ui/spinner/Spinner.vue'
import { router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useSportColors } from '@/composables/useSportColors'

defineOptions({
    layout: AdminDashboard,
})

type TeamPayload = {
    id: number
    team_name: string
    team_avatar: string | null
    sport_id: number | null
    year: string | null
    coach_id: number | null
    assistant_coach_id: number | null
    description: string | null
    player_ids: number[]
}

type PlayerOption = {
    id: number
    name: string
    student_id_number: string | null
    email?: string | null
    education_level?: string | null
    current_grade_level?: string | null
}

const props = defineProps<{
    coaches: { id: number; name: string; status?: string | null; email?: string | null }[]
    players: PlayerOption[]
    sports: { id: number; name: string; max_players: number }[]
    selectedTeam?: TeamPayload | null
    coachWorkloads?: Record<number, { team_id: number; role: string; team_name: string; sport_id: number; year: string | number }[]>
}>()

const teamName = ref('')
const teamAvatar = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)
const avatarPreviewFromUpload = ref(false)
const sport = ref('')
const year = ref('')
const description = ref('')
const coach = ref<number | null>(null)
const assistantCoach = ref<number | null>(null)
const players = ref<number[]>([])
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)
const isOptionLoading = ref(false)
const draftMessage = ref('')
const playerRoleFilter = ref('all')
const playerGradeFilter = ref('all')
let optionLoadingTimer: ReturnType<typeof setTimeout> | null = null
let autosaveTimer: ReturnType<typeof setTimeout> | null = null

const allCoaches = ref(props.coaches || [])
const allPlayers = ref(props.players || [])
const allSports = ref(props.sports || [])

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 10 }, (_, i) => String(currentYear + 3 - i))
const selectedPlayerCount = computed(() => players.value.length)
const isEditMode = computed(() => !!props.selectedTeam?.id)
const selectedSport = computed(() => allSports.value.find((s) => String(s.id) === sport.value) || null)
const maxPlayersForSelectedSport = computed(() => selectedSport.value?.max_players ?? 0)
const sportSelected = computed(() => !!sport.value)
const selectableCoaches = computed(() =>
    allCoaches.value.map((coachOption) => ({
        ...coachOption,
        meta: [coachOption.email ? `Email: ${coachOption.email}` : null, coachOption.status ? `Status: ${coachOption.status}` : null, `ID: ${coachOption.id}`]
            .filter(Boolean)
            .join(' • '),
    }))
)
const playerRoleOptions = computed(() => {
    const set = new Set<string>()
    allPlayers.value.forEach((player) => {
        if (player.education_level) set.add(String(player.education_level))
    })
    return Array.from(set).sort()
})
const playerGradeOptions = computed(() => {
    const set = new Set<string>()
    allPlayers.value.forEach((player) => {
        if (player.current_grade_level) set.add(String(player.current_grade_level))
    })
    return Array.from(set).sort()
})
const selectablePlayers = computed(() => {
    const filtered = allPlayers.value.filter((player) => {
        if (playerRoleFilter.value !== 'all' && player.education_level !== playerRoleFilter.value) return false
        if (playerGradeFilter.value !== 'all' && String(player.current_grade_level ?? '') !== playerGradeFilter.value) return false
        return true
    })
    const selectedSet = new Set(players.value)
    const selectedOptions = allPlayers.value.filter((player) => selectedSet.has(player.id))
    return [
        ...selectedOptions,
        ...filtered.filter((player) => !selectedSet.has(player.id)),
    ].map((playerOption) => ({
        ...playerOption,
        meta: [
            playerOption.student_id_number ? `Student ID: ${playerOption.student_id_number}` : null,
            playerOption.email ? `Email: ${playerOption.email}` : null,
            playerOption.education_level ? playerOption.education_level : null,
            playerOption.current_grade_level ? `Level: ${playerOption.current_grade_level}` : null,
        ]
            .filter(Boolean)
            .join(' • '),
    }))
})
const draftKey = computed(() => `ac-vmis:teams:draft:${isEditMode.value ? `edit:${props.selectedTeam?.id}` : 'create'}`)
const isOverLimit = computed(() => maxPlayersForSelectedSport.value > 0 && selectedPlayerCount.value > maxPlayersForSelectedSport.value)
const isAtLimit = computed(() => maxPlayersForSelectedSport.value > 0 && selectedPlayerCount.value === maxPlayersForSelectedSport.value)
const remainingSlots = computed(() => {
    if (!maxPlayersForSelectedSport.value) return 0
    return Math.max(0, maxPlayersForSelectedSport.value - selectedPlayerCount.value)
})
const previewCoachName = computed(() => allCoaches.value.find((c) => c.id === coach.value)?.name || 'Unassigned')
const previewAssistantName = computed(() => allCoaches.value.find((c) => c.id === assistantCoach.value)?.name || 'Unassigned')
const { sportColor, sportTextColor } = useSportColors()
const playerTagStyle = () => {
    if (!sportSelected.value) {
        return {
            backgroundColor: '#e2e8f0',
            color: '#1f2937',
            borderColor: '#cbd5e1',
        }
    }
    const sportValue = selectedSport.value?.name ?? selectedSport.value?.id ?? ''
    const bg = sportColor(sportValue)
    const text = sportTextColor(sportValue)
    return {
        backgroundColor: bg,
        color: text,
        borderColor: bg,
    }
}

const coachAssignments = computed(() => {
    if (!coach.value || !props.coachWorkloads) return []
    return props.coachWorkloads[coach.value] || []
})

const assistantAssignments = computed(() => {
    if (!assistantCoach.value || !props.coachWorkloads) return []
    return props.coachWorkloads[assistantCoach.value] || []
})

const headCoachConflictHint = computed(() => {
    if (!sport.value || !year.value) return []
    return coachAssignments.value.filter((assignment) => {
        if (isEditMode.value && assignment.team_id === props.selectedTeam?.id) return false
        return String(assignment.sport_id) === sport.value && String(assignment.year) === year.value
    })
})

const assistantConflictHint = computed(() => {
    if (!sport.value || !year.value) return []
    return assistantAssignments.value.filter((assignment) => {
        if (isEditMode.value && assignment.team_id === props.selectedTeam?.id) return false
        return String(assignment.sport_id) === sport.value && String(assignment.year) === year.value
    })
})

function captureCurrentState() {
    return {
        team_name: teamName.value,
        sport_id: sport.value,
        year: year.value,
        coach_id: coach.value,
        assistant_coach_id: assistantCoach.value,
        description: description.value,
        players: players.value,
    }
}

function saveDraftSoon() {
    if (isSaving.value) return
    if (autosaveTimer) clearTimeout(autosaveTimer)

    autosaveTimer = setTimeout(() => {
        try {
            localStorage.setItem(draftKey.value, JSON.stringify(captureCurrentState()))
            draftMessage.value = `Draft autosaved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
        } catch {
            draftMessage.value = 'Autosave failed (storage unavailable).'
        }
    }, 400)
}

function restoreDraftIfNeeded() {
    if (isEditMode.value) return

    try {
        const raw = localStorage.getItem(draftKey.value)
        if (!raw) return
        const parsed = JSON.parse(raw)

        if (typeof parsed.team_name === 'string') teamName.value = parsed.team_name
        if (typeof parsed.sport_id === 'string') sport.value = parsed.sport_id
        if (typeof parsed.year === 'string') year.value = parsed.year
        if (typeof parsed.description === 'string') description.value = parsed.description
        if (typeof parsed.coach_id === 'number') coach.value = parsed.coach_id
        if (typeof parsed.assistant_coach_id === 'number') assistantCoach.value = parsed.assistant_coach_id
        if (Array.isArray(parsed.players)) players.value = parsed.players.filter((id) => typeof id === 'number')
        draftMessage.value = 'Restored autosaved draft.'
    } catch {
        draftMessage.value = ''
    }
}

function clearDraft() {
    localStorage.removeItem(draftKey.value)
    if (!isEditMode.value) {
        draftMessage.value = 'Draft cleared.'
    }
}

if (props.selectedTeam) {
    teamName.value = props.selectedTeam.team_name ?? ''
    sport.value = props.selectedTeam.sport_id ? String(props.selectedTeam.sport_id) : ''
    year.value = props.selectedTeam.year ?? ''
    coach.value = props.selectedTeam.coach_id ?? null
    assistantCoach.value = props.selectedTeam.assistant_coach_id ?? null
    description.value = props.selectedTeam.description ?? ''
    players.value = props.selectedTeam.player_ids ?? []
    if (props.selectedTeam.team_avatar) {
        avatarPreview.value = props.selectedTeam.team_avatar.startsWith('/storage/')
            ? props.selectedTeam.team_avatar
            : `/storage/${props.selectedTeam.team_avatar}`
    }
} else {
    restoreDraftIfNeeded()
}

watch([teamName, sport, year, coach, assistantCoach, description, players], saveDraftSoon, { deep: true })

watch([coach, assistantCoach], () => {
    if (coach.value && assistantCoach.value && coach.value === assistantCoach.value) {
        assistantCoach.value = null
    }
})

watch(sport, (newSport, oldSport) => {
    if (!newSport) {
        coach.value = null
        assistantCoach.value = null
        players.value = []
        return
    }

    if (oldSport && newSport !== oldSport) {
        isOptionLoading.value = true
        if (optionLoadingTimer) clearTimeout(optionLoadingTimer)
        optionLoadingTimer = setTimeout(() => {
            isOptionLoading.value = false
        }, 300)
        coach.value = null
        assistantCoach.value = null
        players.value = []
    }
})

watch(players, () => {
    errors.value = { ...errors.value, players: '' }
})

function goBack() {
    router.get('/teams')
}

function handleAvatarUpload(event: Event) {
    const files = (event.target as HTMLInputElement).files
    if (!files || !files.length) return

    if (avatarPreview.value && avatarPreviewFromUpload.value) {
        URL.revokeObjectURL(avatarPreview.value)
    }

    teamAvatar.value = files[0]
    avatarPreview.value = URL.createObjectURL(files[0])
    avatarPreviewFromUpload.value = true
}

function validate() {
    const nextErrors: Record<string, string> = {}
    if (!teamName.value.trim()) nextErrors.team_name = 'Team name is required.'
    if (!sport.value) nextErrors.sport_id = 'Select a sport.'
    if (!year.value) nextErrors.year = 'Select a year.'
    if (!coach.value) nextErrors.coach_id = 'Select a head coach.'

    if (players.value.length > maxPlayersForSelectedSport.value) {
        nextErrors.players = `Maximum players for ${selectedSport.value?.name ?? 'this sport'} is ${maxPlayersForSelectedSport.value}.`
    }

    if (headCoachConflictHint.value.length > 0) {
        nextErrors.coach_id = 'Selected head coach already has an assignment in the same sport and year.'
    }

    if (assistantConflictHint.value.length > 0) {
        nextErrors.assistant_coach_id = 'Selected assistant coach already has an assignment in the same sport and year.'
    }

    errors.value = nextErrors
    return Object.keys(nextErrors).length === 0
}

function submitTeam() {
    if (isSaving.value) return
    if (!validate()) return

    const formData = new FormData()
    formData.append('team_name', teamName.value.trim())
    if (teamAvatar.value) formData.append('team_avatar', teamAvatar.value)
    formData.append('sport_id', sport.value)
    formData.append('year', year.value)
    formData.append('coach_id', String(coach.value))
    formData.append('assistant_coach_id', String(assistantCoach.value ?? ''))
    formData.append('players', JSON.stringify(players.value.map((id) => ({ student_id: id }))))
    formData.append('description', description.value)

    const endpoint = isEditMode.value ? `/teams/${props.selectedTeam?.id}` : '/teams/create'
    const requestOptions = {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
        },
        onFinish: () => {
            isSaving.value = false
        },
        onSuccess: () => {
            clearDraft()
        },
        onError: (backendErrors: Record<string, any>) => {
            const normalized: Record<string, string> = {}
            Object.keys(backendErrors).forEach((key) => {
                const val = backendErrors[key]
                normalized[key] = Array.isArray(val) ? String(val[0]) : String(val)
            })
            errors.value = normalized
        },
    }

    if (isEditMode.value) {
        router.put(endpoint, formData, requestOptions)
        return
    }

    router.post(endpoint, formData, requestOptions)
}

onBeforeUnmount(() => {
    if (avatarPreview.value && avatarPreviewFromUpload.value) URL.revokeObjectURL(avatarPreview.value)
    if (optionLoadingTimer) clearTimeout(optionLoadingTimer)
    if (autosaveTimer) clearTimeout(autosaveTimer)
})
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <button @click="goBack" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100">
                    Back to Teams
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ isEditMode ? 'Edit Team' : 'Create Team' }}</h1>
                    <p class="text-sm text-slate-600">Set sport first, then assign coaches and players with roster rules.</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500">{{ draftMessage }}</p>
                <button v-if="!isEditMode" type="button" class="text-xs text-slate-600 underline" @click="clearDraft">Clear draft</button>
            </div>
        </div>

        <section class="space-y-5 rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <div class="space-y-4 lg:col-span-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Team Name</label>
                                <input
                                    v-model="teamName"
                                    type="text"
                                    placeholder="e.g., Falcons A-Team"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <p v-if="errors.team_name" class="mt-1 text-xs text-red-600">{{ errors.team_name }}</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Sport</label>
                                    <select v-model="sport" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="" disabled>Select sport</option>
                                        <option v-for="s in allSports" :key="s.id" :value="String(s.id)">
                                            {{ s.name }} (Max {{ s.max_players }})
                                        </option>
                                    </select>
                                    <p v-if="errors.sport_id" class="mt-1 text-xs text-red-600">{{ errors.sport_id }}</p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                                    <select v-model="year" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="" disabled>Select year</option>
                                        <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                    </select>
                                    <p v-if="errors.year" class="mt-1 text-xs text-red-600">{{ errors.year }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-700">Team Avatar</label>
                            <input type="file" accept="image/*" @change="handleAvatarUpload" class="w-full text-sm text-slate-600" />
                            <div class="flex h-40 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <img
                                    v-if="avatarPreview"
                                    :src="avatarPreview"
                                    alt="Avatar Preview"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else class="text-xs text-slate-400">No image selected</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Head Coach</label>
                        <SingleSelectSearch
                            v-model="coach"
                            :options="selectableCoaches"
                            :loading="isOptionLoading"
                            :placeholder="sportSelected ? 'Search head coach...' : 'Select sport first'"
                            badge-class="bg-[#034485] text-white"
                            remove-class="text-white/80 hover:text-white"
                        />
                        <button
                            v-if="coach"
                            type="button"
                            class="mt-1 text-xs font-medium text-[#034485] underline hover:text-[#023666]"
                            @click="coach = null"
                        >
                            Remove head coach
                        </button>
                        <p v-if="!sportSelected" class="mt-1 text-xs text-slate-500">Select sport first for conflict-aware guidance.</p>
                        <p v-if="sportSelected && selectableCoaches.length === 0" class="mt-1 text-xs text-amber-700">No coaches loaded. Check coach records in People.</p>
                        <p v-else-if="headCoachConflictHint.length" class="mt-1 text-xs text-amber-700">
                            Coach conflict hint: already assigned to {{ headCoachConflictHint.map((x) => x.team_name).join(', ') }} in this sport/year.
                        </p>
                        <p v-if="errors.coach_id" class="mt-1 text-xs text-red-600">{{ errors.coach_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Assistant Coach (Optional)</label>
                        <SingleSelectSearch
                            v-model="assistantCoach"
                            :options="selectableCoaches"
                            :loading="isOptionLoading"
                            :placeholder="sportSelected ? 'Search assistant coach...' : 'Select sport first'"
                            badge-class="bg-[#034485] text-white"
                            remove-class="text-white/80 hover:text-white"
                        />
                        <button
                            v-if="assistantCoach"
                            type="button"
                            class="mt-1 text-xs font-medium text-[#034485] underline hover:text-[#023666]"
                            @click="assistantCoach = null"
                        >
                            Remove assistant coach
                        </button>
                        <p v-if="coach && assistantCoach && coach === assistantCoach" class="mt-1 text-xs text-amber-700">
                            Assistant coach cannot be the same as head coach.
                        </p>
                        <p v-else-if="assistantConflictHint.length" class="mt-1 text-xs text-amber-700">
                            Assistant conflict hint: already assigned to {{ assistantConflictHint.map((x) => x.team_name).join(', ') }} in this sport/year.
                        </p>
                        <p v-if="errors.assistant_coach_id" class="mt-1 text-xs text-red-600">{{ errors.assistant_coach_id }}</p>
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label class="block text-sm font-medium text-slate-700">Players</label>
                            <span class="text-xs text-slate-500">
                                {{ selectedPlayerCount }} / {{ maxPlayersForSelectedSport || 0 }} selected
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Education Level</label>
                                <select v-model="playerRoleFilter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                    <option value="all">All Levels</option>
                                    <option v-for="level in playerRoleOptions" :key="level" :value="level">
                                        {{ level }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Grade / Year Level</label>
                                <select v-model="playerGradeFilter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                    <option value="all">All Grades</option>
                                    <option v-for="grade in playerGradeOptions" :key="grade" :value="grade">
                                        {{ grade }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <MultiSelectSearch
                            v-model="players"
                            :options="selectablePlayers"
                            :loading="isOptionLoading"
                            :placeholder="sportSelected ? `Search players (max ${maxPlayersForSelectedSport})...` : 'Select sport first'"
                            :tag-style="playerTagStyle"
                        />
                        <button
                            v-if="players.length"
                            type="button"
                            class="mt-1 text-xs font-medium text-[#034485] underline hover:text-[#023666]"
                            @click="players = []"
                        >
                            Clear selected players
                        </button>
                        <p v-if="!sportSelected" class="mt-1 text-xs text-slate-500">Select sport first to apply roster limits correctly.</p>
                        <p v-if="sportSelected && selectablePlayers.length === 0" class="mt-1 text-xs text-amber-700">No players loaded. Check student-athlete records in People.</p>
                        <p v-else class="mt-1 text-xs text-slate-500">
                            Max players for {{ selectedSport?.name }}: {{ maxPlayersForSelectedSport }}.
                        </p>
                        <p v-if="isOverLimit" class="mt-1 text-xs font-semibold text-rose-600">
                            Over limit by {{ selectedPlayerCount - maxPlayersForSelectedSport }} players. Please remove extras.
                        </p>
                        <p v-else-if="isAtLimit && maxPlayersForSelectedSport" class="mt-1 text-xs text-amber-600">
                            Max capacity reached. Remove a player to add a new one.
                        </p>
                        <p v-if="errors.players" class="mt-1 text-xs text-red-600">{{ errors.players }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description (Optional)</label>
                        <textarea
                            v-model="description"
                            rows="3"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            placeholder="Team description..."
                        />
                    </div>
                </div>

                <aside class="rounded-xl border border-[#034485]/45 bg-[#034485] p-4 text-white">
                    <h2 class="text-sm font-semibold text-white/90">Live Preview</h2>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="h-12 w-12 overflow-hidden rounded-lg border border-white/20 bg-white/10">
                            <img v-if="avatarPreview" :src="avatarPreview" alt="Team Avatar" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center text-xs text-white/70">Logo</div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">{{ teamName || 'Team Name' }}</p>
                            <span class="mt-1 inline-flex rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-semibold text-white">
                                {{ selectedSport?.name || 'Select sport' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-xs text-white/80">
                        <p><span class="font-semibold text-white/90">Year:</span> {{ year || 'Unassigned' }}</p>
                        <p><span class="font-semibold text-white/90">Head Coach:</span> {{ previewCoachName }}</p>
                        <p><span class="font-semibold text-white/90">Assistant:</span> {{ previewAssistantName }}</p>
                    </div>

                    <div class="mt-4 rounded-lg border border-white/20 bg-white/10 p-3 text-xs text-white/80">
                        <p class="font-semibold text-white/90">Roster Capacity</p>
                        <p class="mt-1">Selected: {{ selectedPlayerCount }}</p>
                        <p>Max Players: {{ maxPlayersForSelectedSport || 0 }}</p>
                        <p>Open Slots: {{ remainingSlots }}</p>
                        <p v-if="isOverLimit" class="mt-2 font-semibold text-rose-200">Over limit — adjust roster.</p>
                        <p v-else-if="isAtLimit && maxPlayersForSelectedSport" class="mt-2 font-semibold text-amber-200">At max capacity.</p>
                        <p v-else class="mt-2 text-white/70">Capacity available.</p>
                    </div>
                </aside>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-2">
                <button
                    @click="submitTeam"
                    :disabled="isSaving || isOverLimit"
                    class="rounded-lg bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#334155] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span class="inline-flex items-center gap-2">
                        <Spinner v-if="isSaving" class="h-4 w-4 text-white" />
                        {{ isSaving ? (isEditMode ? 'Updating...' : 'Creating...') : (isEditMode ? 'Update Team' : 'Create Team') }}
                    </span>
                </button>
            </div>
        </section>
    </div>
</template>
