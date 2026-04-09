<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';

defineOptions({
    layout: AdminDashboard,
});

type RoleFilter = 'all' | 'student-athlete' | 'coach';
type UserStatusFilter = 'approved' | 'deactivated';
type SortField = 'name' | 'email' | 'created_at';
type SortDirection = 'asc' | 'desc';
type SortOption = `${SortField}:${SortDirection}`;

type StudentInfo = {
    student_id_number: string | null;
    course_or_strand: string | null;
    education_level: string | null;
    current_grade_level: string | null;
    student_status: string | null;
    phone_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    height: string | null;
    weight: string | null;
};

type CoachInfo = {
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    coach_status: string | null;
    phone_number: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: 'student-athlete' | 'coach';
    status: UserStatusFilter;
    avatar: string | null;
    created_at: string | null;
    student?: StudentInfo | null;
    coach?: CoachInfo | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

type Filters = {
    search?: string;
    role?: RoleFilter;
    status?: UserStatusFilter;
    sort?: SortField;
    direction?: SortDirection;
};

type Totals = {
    all: number;
    students: number;
    coaches: number;
    deactivated: number;
    filtered: number;
};

type SportOption = {
    id: number;
    name: string;
};

type AssignableTeam = {
    id: number;
    team_name: string;
    year: string | null;
    sport_id: number;
    sport_name: string | null;
    coach_id: number | null;
    assistant_coach_id: number | null;
    coach_name: string | null;
    assistant_coach_name: string | null;
};

type CoachOnboardingFlash = {
    email: string;
    temporary_password: string;
    email_sent: boolean;
    activation_url: string;
};

const props = defineProps<{
    users: PaginatedUsers;
    filters?: Filters;
    totals?: Totals;
    pendingCount?: number;
    sports?: SportOption[];
    assignableTeams?: AssignableTeam[];
}>();

const defaultSort: SortOption = 'created_at:desc';
const selectedUser = ref<UserRow | null>(null);
const copiedUserId = ref<number | null>(null);
const deactivateTarget = ref<UserRow | null>(null);
const reactivateTarget = ref<UserRow | null>(null);
const actionLoadingId = ref<number | null>(null);
const search = ref(props.filters?.search ?? '');
const roleFilter = ref<RoleFilter>(props.filters?.role ?? 'all');
const statusFilter = ref<UserStatusFilter>(props.filters?.status ?? 'approved');
const sortOption = ref<SortOption>(`${props.filters?.sort ?? 'created_at'}:${props.filters?.direction ?? 'desc'}` as SortOption);
const topTab = ref<'approved' | 'queue'>('approved');
const createCoachOpen = ref(false);
const createCoachFeedback = ref<string | null>(null);
const selectedSportIds = ref<number[]>([]);
const copiedOnboardingPassword = ref(false);
const copiedActivationLink = ref(false);
const onboardingFlash = ref<CoachOnboardingFlash | null>(null);
const page = usePage();
const createCoachForm = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    date_of_birth: '',
    gender: '',
    home_address: '',
    notes: '',
    assignment_role: 'assistant',
    team_ids: [] as number[],
});

if (!['name:asc', 'name:desc', 'email:asc', 'email:desc', 'created_at:asc', 'created_at:desc'].includes(sortOption.value)) {
    sortOption.value = defaultSort;
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let topTabTimeout: ReturnType<typeof setTimeout> | null = null;

const totalUsers = computed(() => props.totals?.all ?? 0);
const totalStudents = computed(() => props.totals?.students ?? 0);
const totalCoaches = computed(() => props.totals?.coaches ?? 0);
const totalDeactivated = computed(() => props.totals?.deactivated ?? 0);
const filteredTotal = computed(() => props.totals?.filtered ?? props.users.total);
const hasActiveFilters = computed(
    () => search.value.trim() !== '' || roleFilter.value !== 'all' || statusFilter.value !== 'approved' || sortOption.value !== defaultSort,
);
const isDeactivatedView = computed(() => statusFilter.value === 'deactivated');
const hasBlockingModal = computed(() => Boolean(selectedUser.value || deactivateTarget.value || reactivateTarget.value || createCoachOpen.value));
const sportOptions = computed(() => props.sports ?? []);
const assignableTeams = computed(() => props.assignableTeams ?? []);
const filteredAssignableTeams = computed(() => {
    if (selectedSportIds.value.length === 0) {
        return assignableTeams.value;
    }
    const selected = new Set(selectedSportIds.value);
    return assignableTeams.value.filter((team) => selected.has(team.sport_id));
});

function buildQuery(resetPage = true) {
    const [sort, direction] = sortOption.value.split(':') as [SortField, SortDirection];

    return {
        search: search.value.trim() || undefined,
        role: roleFilter.value === 'all' ? undefined : roleFilter.value,
        status: statusFilter.value,
        sort,
        direction,
        page: resetPage ? 1 : props.users.current_page,
    };
}

function applyFilters({ resetPage = true, debounce = false }: { resetPage?: boolean; debounce?: boolean } = {}) {
    const visit = () => {
        router.get('/people', buildQuery(resetPage), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters', 'totals', 'pendingCount'],
        });
    };

    if (!debounce) {
        visit();
        return;
    }

    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(visit, 250);
}

watch(search, () => {
    applyFilters({ resetPage: true, debounce: true });
});

watch(roleFilter, () => {
    applyFilters({ resetPage: true });
});

watch(statusFilter, () => {
    applyFilters({ resetPage: true });
});

watch(sortOption, () => {
    applyFilters({ resetPage: true });
});

function openInfo(user: UserRow) {
    selectedUser.value = user;
}

function openCreateCoach() {
    createCoachOpen.value = true;
}

function closeCreateCoach() {
    createCoachOpen.value = false;
    createCoachForm.reset();
    createCoachForm.clearErrors();
    createCoachForm.assignment_role = 'assistant';
    createCoachForm.team_ids = [];
    selectedSportIds.value = [];
    onboardingFlash.value = null;
    copiedOnboardingPassword.value = false;
    copiedActivationLink.value = false;
}

function teamSlotTaken(team: AssignableTeam) {
    return createCoachForm.assignment_role === 'head' ? Boolean(team.coach_id) : Boolean(team.assistant_coach_id);
}

function teamSlotLabel(team: AssignableTeam) {
    if (createCoachForm.assignment_role === 'head') {
        return team.coach_name ? `Head coach: ${team.coach_name}` : 'Head coach slot open';
    }
    return team.assistant_coach_name ? `Assistant coach: ${team.assistant_coach_name}` : 'Assistant slot open';
}

function submitCreateCoach() {
    createCoachFeedback.value = null;
    createCoachForm.post('/admin/coaches', {
        preserveScroll: true,
        onSuccess: (visit) => {
            const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined;
            onboardingFlash.value = flash ?? null;
            createCoachFeedback.value = flash
                ? 'Coach account created. Share the temporary password now. It is shown only once.'
                : 'Coach account created successfully.';
            createCoachForm.reset();
            createCoachForm.assignment_role = 'assistant';
            createCoachForm.team_ids = [];
            selectedSportIds.value = [];
            copiedOnboardingPassword.value = false;
            copiedActivationLink.value = false;
            createCoachOpen.value = true;
        },
    });
}

async function copyOnboardingPassword() {
    if (!onboardingFlash.value?.temporary_password) return;
    try {
        await navigator.clipboard.writeText(onboardingFlash.value.temporary_password);
        copiedOnboardingPassword.value = true;
        setTimeout(() => {
            copiedOnboardingPassword.value = false;
        }, 1400);
    } catch {
        copiedOnboardingPassword.value = false;
    }
}

async function copyActivationLink() {
    if (!onboardingFlash.value?.activation_url) return;
    try {
        await navigator.clipboard.writeText(onboardingFlash.value.activation_url);
        copiedActivationLink.value = true;
        setTimeout(() => {
            copiedActivationLink.value = false;
        }, 1400);
    } catch {
        copiedActivationLink.value = false;
    }
}

function regenerateCoachCredentials(user: UserRow) {
    if (user.role !== 'coach') return;
    const confirmed = window.confirm(`Regenerate onboarding credentials for ${user.name}? This invalidates the previous temporary password.`);
    if (!confirmed) return;

    router.post(
        `/admin/coaches/${user.id}/regenerate-onboarding`,
        {},
        {
            preserveScroll: true,
            onSuccess: (visit) => {
                const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined;
                onboardingFlash.value = flash ?? null;
                copiedOnboardingPassword.value = false;
                copiedActivationLink.value = false;
                createCoachFeedback.value = flash
                    ? 'Coach credentials regenerated. Share the new temporary password now.'
                    : 'Coach onboarding credentials regenerated.';
                createCoachOpen.value = true;
            },
        },
    );
}

function closeInfo() {
    selectedUser.value = null;
}

function goToApprovalRequests() {
    if (topTab.value === 'queue') return;
    topTab.value = 'queue';
    if (topTabTimeout) clearTimeout(topTabTimeout);
    topTabTimeout = setTimeout(() => {
        router.get('/people/queue');
    }, 180);
}

function visitPage(url: string | null) {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters', 'totals', 'pendingCount'],
    });
}

function resetAllFilters() {
    search.value = '';
    roleFilter.value = 'all';
    statusFilter.value = 'approved';
    sortOption.value = defaultSort;
    applyFilters({ resetPage: true });
}

function formatRole(role: UserRow['role']) {
    return role.replace('-', ' ');
}

function formatDate(value?: string | null) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleDateString();
}

function formatSimple(value?: string | null) {
    return value?.trim() ? value : '-';
}

function getPrimaryPhone(user: UserRow) {
    if (user.student?.phone_number) return user.student.phone_number;
    if (user.coach?.phone_number) return user.coach.phone_number;
    return null;
}

function getAge(value?: string | null) {
    if (!value) return null;
    const birthDate = new Date(value);
    if (Number.isNaN(birthDate.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }
    return age >= 0 ? age : null;
}

function coachDisplayName(coach: CoachInfo | null | undefined) {
    if (!coach) return '-';
    const parts = [coach.first_name, coach.middle_name, coach.last_name].filter((part) => part && part.trim());
    return parts.length ? parts.join(' ') : '-';
}

function profileCompleteness(user: UserRow) {
    if (user.student) {
        const fields = [
            user.student.student_id_number,
            user.student.course_or_strand,
            user.student.current_grade_level,
            user.student.phone_number,
            user.student.date_of_birth,
            user.student.gender,
        ];
        const filled = fields.filter((field) => field && field.toString().trim()).length;
        return Math.round((filled / fields.length) * 100);
    }

    if (user.coach) {
        const fields = [
            user.coach.first_name,
            user.coach.last_name,
            user.coach.phone_number,
            user.coach.date_of_birth,
            user.coach.gender,
            user.coach.coach_status,
        ];
        const filled = fields.filter((field) => field && field.toString().trim()).length;
        return Math.round((filled / fields.length) * 100);
    }

    return 0;
}

async function copyEmail(user: UserRow) {
    try {
        await navigator.clipboard.writeText(user.email);
        copiedUserId.value = user.id;
        setTimeout(() => {
            if (copiedUserId.value === user.id) copiedUserId.value = null;
        }, 1300);
    } catch {
        copiedUserId.value = null;
    }
}

function openDeactivateDialog(user: UserRow) {
    deactivateTarget.value = user;
}

function closeDeactivateDialog() {
    deactivateTarget.value = null;
}

function openReactivateDialog(user: UserRow) {
    reactivateTarget.value = user;
}

function closeReactivateDialog() {
    reactivateTarget.value = null;
}

function deactivateUser() {
    if (!deactivateTarget.value) return;

    actionLoadingId.value = deactivateTarget.value.id;

    router.post(
        `/admin/users/${deactivateTarget.value.id}/deactivate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeactivateDialog();
                router.reload({ only: ['users', 'filters', 'totals', 'pendingCount'] });
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        },
    );
}

function reactivateUser() {
    if (!reactivateTarget.value) return;

    actionLoadingId.value = reactivateTarget.value.id;

    router.post(
        `/admin/users/${reactivateTarget.value.id}/reactivate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeReactivateDialog();
                router.reload({ only: ['users', 'filters', 'totals', 'pendingCount'] });
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        },
    );
}

function onModalEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeInfo();
        closeDeactivateDialog();
        closeReactivateDialog();
        closeCreateCoach();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onModalEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onModalEscape);
    document.body.style.overflow = '';
    if (searchDebounce) clearTimeout(searchDebounce);
});

watch(hasBlockingModal, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

watch(
    () => createCoachForm.assignment_role,
    () => {
        createCoachForm.team_ids = createCoachForm.team_ids.filter((teamId) => {
            const team = assignableTeams.value.find((entry) => entry.id === teamId);
            return team ? !teamSlotTaken(team) : false;
        });
    },
);

watch(
    () => (page.props as any)?.flash?.coach_onboarding,
    (value) => {
        onboardingFlash.value = (value as CoachOnboardingFlash | null) ?? null;
        copiedOnboardingPassword.value = false;
        copiedActivationLink.value = false;
    },
    { immediate: true },
);
</script>

<template>
    <Head title="People" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative inline-grid grid-cols-2 items-center rounded-full border border-[#034485]/45 bg-white p-1">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full bg-[#1f2937] transition-transform duration-200 ease-out"
                    :class="topTab === 'approved' ? 'translate-x-0' : 'translate-x-full'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    class="relative z-10 w-full justify-center rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="topTab === 'approved' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                    aria-current="page"
                >
                    Approved Users
                </button>
                <button
                    type="button"
                    @click="goToApprovalRequests"
                    class="relative z-10 inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="topTab === 'queue' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Approval Queue
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="topTab === 'queue' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ props.pendingCount ?? 0 }}
                    </span>
                </button>
            </div>
            <button
                type="button"
                @click="openCreateCoach"
                class="inline-flex items-center justify-center rounded-full bg-[#1f2937] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#334155]"
            >
                Create Coach Account
            </button>
        </div>
        <p v-if="createCoachFeedback" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            {{ createCoachFeedback }}
        </p>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Users</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalUsers }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Student-Athletes</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalStudents }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Coaches</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalCoaches }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Deactivated</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ totalDeactivated }}</p>
            </article>
        </div>

        <div class="rounded-xl border border-[#034485]/45 bg-white p-4">
            <div class="relative mb-3 inline-flex items-center rounded-full border border-[#034485]/45 bg-white p-1">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full transition-transform duration-200 ease-out"
                    :class="statusFilter === 'approved' ? 'translate-x-0 bg-[#1f2937]' : 'translate-x-full bg-amber-600'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    @click="statusFilter = 'approved'"
                    class="relative z-10 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="statusFilter === 'approved' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Approved
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'deactivated'"
                    class="relative z-10 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="statusFilter === 'deactivated' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Deactivated
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-6"
                />

                <select
                    v-model="roleFilter"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="all">All Roles</option>
                    <option value="student-athlete">Student Athlete</option>
                    <option value="coach">Coach</option>
                </select>

                <select
                    v-model="sortOption"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="created_at:desc">Newest First</option>
                    <option value="created_at:asc">Oldest First</option>
                    <option value="name:asc">Name A-Z</option>
                    <option value="name:desc">Name Z-A</option>
                    <option value="email:asc">Email A-Z</option>
                    <option value="email:desc">Email Z-A</option>
                </select>
            </div>

            <div class="mt-3 flex justify-end" v-if="hasActiveFilters">
                <button
                    type="button"
                    @click="resetAllFilters"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Reset Filters
                </button>
            </div>
            <p class="mt-2 text-xs font-medium text-slate-500">
                {{ isDeactivatedView ? 'Viewing deactivated accounts. Use Reactivate to restore access.' : `Matching Results: ${filteredTotal}` }}
            </p>
        </div>

        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div :key="statusFilter" class="overflow-hidden rounded-xl border border-[#034485]/45 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-225 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Avatar</th>
                                <th class="px-4 py-3 text-left font-semibold">Name</th>
                                <th class="px-4 py-3 text-left font-semibold">Email</th>
                                <th class="px-4 py-3 text-left font-semibold">Role</th>
                                <th class="px-4 py-3 text-left font-semibold">Account</th>
                                <th class="px-4 py-3 text-left font-semibold">Registered</th>
                                <th class="px-4 py-3 text-left font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id" class="border-t border-slate-200">
                                <td class="px-4 py-3">
                                    <img
                                        :src="user.avatar ? `/storage/${user.avatar}` : '/images/default-avatar.svg'"
                                        :alt="`${user.name} avatar`"
                                        class="h-10 w-10 rounded-full object-cover"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ user.name }}</p>
                                    <p v-if="user.student?.student_id_number" class="text-xs text-slate-500">
                                        ID: {{ user.student.student_id_number }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ user.email }}</td>
                                <td class="px-4 py-3 text-slate-700 capitalize">{{ formatRole(user.role) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="user.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ user.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ user.created_at ? new Date(user.created_at).toLocaleDateString() : '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            @click="openInfo(user)"
                                            class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#334155]"
                                        >
                                            View Details
                                        </button>
                                        <button
                                            type="button"
                                            @click="copyEmail(user)"
                                            class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                        >
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                                <path
                                                    d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                                />
                                            </svg>
                                            {{ copiedUserId === user.id ? 'Copied' : 'Copy Email' }}
                                        </button>
                                        <button
                                            v-if="user.role === 'coach'"
                                            type="button"
                                            @click="regenerateCoachCredentials(user)"
                                            class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                        >
                                            Regenerate Credentials
                                        </button>
                                        <button
                                            v-if="user.status === 'approved'"
                                            type="button"
                                            @click="openDeactivateDialog(user)"
                                            :disabled="actionLoadingId === user.id"
                                            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Deactivate
                                        </button>
                                        <button
                                            v-if="user.status === 'deactivated'"
                                            type="button"
                                            @click="openReactivateDialog(user)"
                                            :disabled="actionLoadingId === user.id"
                                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Reactivate
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No users match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of {{ users.total }} users</p>
                    <nav class="flex flex-wrap items-center gap-1" aria-label="User pagination">
                        <button
                            v-for="(link, index) in users.links"
                            :key="`${index}-${link.label}`"
                            type="button"
                            :disabled="!link.url"
                            @click="visitPage(link.url)"
                            class="min-w-9 rounded-md border px-2 py-1 text-xs transition"
                            :class="
                                link.active
                                    ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40'
                            "
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </Transition>
    </div>

    <Transition name="modal-fade">
        <div
            v-if="createCoachOpen"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:items-center"
            @click.self="closeCreateCoach"
        >
            <div
                class="modal-panel my-6 max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-2xl border border-[#034485]/45 bg-white p-6 sm:my-0 sm:p-7"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Create Coach Account</h2>
                        <p class="mt-1 text-sm text-slate-600">Admin-provisioned account with optional immediate team assignment.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeCreateCoach"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                        aria-label="Close create coach form"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="onboardingFlash" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm font-semibold text-emerald-800">New coach onboarding details (show once)</p>
                    <p class="mt-1 text-xs text-emerald-700">
                        Email: <span class="font-semibold">{{ onboardingFlash.email }}</span>
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="rounded-md border border-emerald-300 bg-white px-3 py-1.5 font-mono text-sm text-emerald-900">
                            {{ onboardingFlash.temporary_password }}
                        </span>
                        <button
                            type="button"
                            @click="copyOnboardingPassword"
                            class="rounded-md border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                        >
                            {{ copiedOnboardingPassword ? 'Copied' : 'Copy Password' }}
                        </button>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-emerald-700">Activation link:</span>
                        <button
                            type="button"
                            @click="copyActivationLink"
                            class="rounded-md border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                        >
                            {{ copiedActivationLink ? 'Copied Link' : 'Copy Activation Link' }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs" :class="onboardingFlash.email_sent ? 'text-emerald-700' : 'text-amber-700'">
                        {{
                            onboardingFlash.email_sent
                                ? 'Onboarding email sent successfully.'
                                : 'Email could not be sent. Share this password manually.'
                        }}
                    </p>
                </div>

                <form class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2" @submit.prevent="submitCreateCoach">
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">First Name</label>
                        <input
                            v-model="createCoachForm.first_name"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.first_name" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Last Name</label>
                        <input
                            v-model="createCoachForm.last_name"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.last_name" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.last_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Middle Name (Optional)</label>
                        <input
                            v-model="createCoachForm.middle_name"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Email</label>
                        <input
                            v-model="createCoachForm.email"
                            type="email"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.email" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Phone Number</label>
                        <input
                            v-model="createCoachForm.phone_number"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Gender</label>
                        <select
                            v-model="createCoachForm.gender"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        >
                            <option value="">Not set</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <p v-if="createCoachForm.errors.gender" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.gender }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Date Of Birth</label>
                        <input
                            v-model="createCoachForm.date_of_birth"
                            type="date"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.date_of_birth" class="mt-1 text-xs text-rose-600">
                            {{ createCoachForm.errors.date_of_birth }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Team Role Assignment</label>
                        <select
                            v-model="createCoachForm.assignment_role"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        >
                            <option value="assistant">Assistant Coach</option>
                            <option value="head">Head Coach</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Home Address</label>
                        <textarea
                            v-model="createCoachForm.home_address"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.home_address" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.home_address }}</p>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Sports Filter (Optional)</label>
                        <div class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2">
                            <label
                                v-for="sport in sportOptions"
                                :key="sport.id"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                            >
                                <input v-model="selectedSportIds" type="checkbox" :value="sport.id" class="h-3.5 w-3.5" />
                                {{ sport.name }}
                            </label>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Assign Team(s)</label>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-2">
                            <label
                                v-for="team in filteredAssignableTeams"
                                :key="team.id"
                                class="flex cursor-pointer items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                :class="teamSlotTaken(team) ? 'opacity-60' : ''"
                            >
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">
                                        {{ team.team_name }} <span class="text-slate-500">({{ team.year || 'N/A' }})</span>
                                    </p>
                                    <p class="text-xs text-slate-500">{{ team.sport_name || 'No sport assigned' }}</p>
                                    <p class="mt-1 text-xs" :class="teamSlotTaken(team) ? 'text-amber-700' : 'text-emerald-700'">
                                        {{ teamSlotLabel(team) }}
                                    </p>
                                </div>
                                <input
                                    v-model="createCoachForm.team_ids"
                                    type="checkbox"
                                    :value="team.id"
                                    :disabled="teamSlotTaken(team)"
                                    class="mt-1 h-4 w-4"
                                />
                            </label>
                            <p v-if="filteredAssignableTeams.length === 0" class="px-1 py-2 text-xs text-slate-500">
                                No teams found for the selected sport filter.
                            </p>
                        </div>
                        <p v-if="createCoachForm.errors.team_ids" class="mt-1 text-xs whitespace-pre-line text-rose-600">
                            {{ createCoachForm.errors.team_ids }}
                        </p>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Notes (Optional)</label>
                        <textarea
                            v-model="createCoachForm.notes"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.notes }}</p>
                    </div>

                    <div class="flex justify-end gap-2 lg:col-span-2">
                        <button
                            type="button"
                            @click="closeCreateCoach"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createCoachForm.processing"
                            class="rounded-md bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white hover:bg-[#334155] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ createCoachForm.processing ? 'Creating...' : 'Create Coach Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div v-if="selectedUser" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeInfo">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="user-info-title"
                class="modal-panel w-full max-w-3xl rounded-2xl border border-[#034485]/45 bg-white p-6 sm:p-7"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="user-info-title" class="text-lg font-bold text-slate-900">{{ selectedUser.name }}</h2>
                            <span class="rounded-full bg-[#1f2937]/10 px-2.5 py-1 text-xs font-semibold text-[#1f2937]">
                                {{ formatRole(selectedUser.role) }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="selectedUser.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ selectedUser.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ selectedUser.email }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a
                                :href="`mailto:${selectedUser.email}`"
                                class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#334155]"
                            >
                                Email User
                            </a>
                            <button
                                type="button"
                                @click="copyEmail(selectedUser)"
                                class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                    <path
                                        d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                    />
                                </svg>
                                {{ copiedUserId === selectedUser.id ? 'Copied' : 'Copy Email' }}
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeInfo"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                        aria-label="Close user information"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-5 text-sm text-slate-700 lg:grid-cols-2">
                    <div class="space-y-4">
                        <section class="rounded-lg border border-[#034485]/45 bg-slate-50 p-3">
                            <h3 class="font-semibold text-slate-900">Account Overview</h3>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <p><span class="font-medium">User ID:</span> {{ selectedUser.id }}</p>
                                <p><span class="font-medium">Joined:</span> {{ formatDate(selectedUser.created_at) }}</p>
                                <p><span class="font-medium">Profile Completeness:</span> {{ profileCompleteness(selectedUser) }}%</p>
                                <p><span class="font-medium">Status:</span> {{ selectedUser.status }}</p>
                            </div>
                        </section>

                        <section class="rounded-lg border border-[#034485]/45 bg-slate-50 p-3">
                            <h3 class="font-semibold text-slate-900">Contact</h3>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <p class="sm:col-span-2"><span class="font-medium">Email:</span> {{ selectedUser.email }}</p>
                                <p class="sm:col-span-2">
                                    <span class="font-medium">Primary Phone:</span> {{ formatSimple(getPrimaryPhone(selectedUser)) }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <div class="space-y-4">
                        <section v-if="selectedUser.student" class="rounded-lg border border-[#034485]/45 bg-slate-50 p-3">
                            <h3 class="font-semibold text-slate-900">Student Information</h3>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <p><span class="font-medium">Student ID:</span> {{ selectedUser.student.student_id_number || '-' }}</p>
                                <p><span class="font-medium">Course/Strand:</span> {{ formatSimple(selectedUser.student.course_or_strand) }}</p>
                                <p><span class="font-medium">Education Level:</span> {{ formatSimple(selectedUser.student.education_level) }}</p>
                                <p>
                                    <span class="font-medium">Current Grade Level:</span> {{ formatSimple(selectedUser.student.current_grade_level) }}
                                </p>
                                <p><span class="font-medium">Status:</span> {{ formatSimple(selectedUser.student.student_status) }}</p>
                                <p><span class="font-medium">Phone:</span> {{ formatSimple(selectedUser.student.phone_number) }}</p>
                                <p><span class="font-medium">Gender:</span> {{ formatSimple(selectedUser.student.gender) }}</p>
                                <p><span class="font-medium">Birth Date:</span> {{ formatDate(selectedUser.student.date_of_birth) }}</p>
                                <p><span class="font-medium">Age:</span> {{ getAge(selectedUser.student.date_of_birth) ?? '-' }}</p>
                                <p><span class="font-medium">Height:</span> {{ formatSimple(selectedUser.student.height) }}</p>
                                <p><span class="font-medium">Weight:</span> {{ formatSimple(selectedUser.student.weight) }}</p>
                            </div>
                            <div class="mt-3 rounded-lg border border-[#034485]/30 bg-white/70 p-2.5">
                                <h4 class="text-xs font-semibold tracking-wide text-slate-600 uppercase">Emergency Contact</h4>
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <p><span class="font-medium">Name:</span> {{ formatSimple(selectedUser.student.emergency_contact_name) }}</p>
                                    <p>
                                        <span class="font-medium">Relationship:</span>
                                        {{ formatSimple(selectedUser.student.emergency_contact_relationship) }}
                                    </p>
                                    <p class="sm:col-span-2">
                                        <span class="font-medium">Phone:</span> {{ formatSimple(selectedUser.student.emergency_contact_phone) }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section v-if="selectedUser.coach" class="rounded-lg border border-[#034485]/45 bg-slate-50 p-3">
                            <h3 class="font-semibold text-slate-900">Coach Information</h3>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <p><span class="font-medium">Name:</span> {{ coachDisplayName(selectedUser.coach) }}</p>
                                <p><span class="font-medium">Status:</span> {{ formatSimple(selectedUser.coach.coach_status) }}</p>
                                <p><span class="font-medium">Phone:</span> {{ formatSimple(selectedUser.coach.phone_number) }}</p>
                                <p><span class="font-medium">Gender:</span> {{ formatSimple(selectedUser.coach.gender) }}</p>
                                <p><span class="font-medium">Birth Date:</span> {{ formatDate(selectedUser.coach.date_of_birth) }}</p>
                                <p><span class="font-medium">Age:</span> {{ getAge(selectedUser.coach.date_of_birth) ?? '-' }}</p>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        @click="closeInfo"
                        class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="deactivateTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeDeactivateDialog"
        >
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Deactivate Account</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Deactivate <span class="font-semibold text-slate-900">{{ deactivateTarget.name }}</span
                    >? This blocks login but keeps records for audit and recovery.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeDeactivateDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="deactivateUser"
                        :disabled="actionLoadingId === deactivateTarget.id"
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Deactivate
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="reactivateTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeReactivateDialog"
        >
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Reactivate Account</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Reactivate <span class="font-semibold text-slate-900">{{ reactivateTarget.name }}</span
                    >? This restores full sign-in access immediately.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeReactivateDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="reactivateUser"
                        :disabled="actionLoadingId === reactivateTarget.id"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Reactivate
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active .modal-panel,
.modal-fade-leave-active .modal-panel {
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
}

.modal-fade-enter-from .modal-panel,
.modal-fade-leave-to .modal-panel {
    transform: translateY(8px) scale(0.98);
    opacity: 0;
}
</style>
