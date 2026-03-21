<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
    course: string | null;
    education_level: string | null;
    current_grade_level: string | null;
    student_status: string | null;
    phone_number: string | null;
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

const props = defineProps<{
    users: PaginatedUsers;
    filters?: Filters;
    totals?: Totals;
    pendingCount?: number;
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

if (!['name:asc', 'name:desc', 'email:asc', 'email:desc', 'created_at:asc', 'created_at:desc'].includes(sortOption.value)) {
    sortOption.value = defaultSort;
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

const totalUsers = computed(() => props.totals?.all ?? 0);
const totalStudents = computed(() => props.totals?.students ?? 0);
const totalCoaches = computed(() => props.totals?.coaches ?? 0);
const totalDeactivated = computed(() => props.totals?.deactivated ?? 0);
const filteredTotal = computed(() => props.totals?.filtered ?? props.users.total);
const hasActiveFilters = computed(() =>
    search.value.trim() !== ''
    || roleFilter.value !== 'all'
    || statusFilter.value !== 'approved'
    || sortOption.value !== defaultSort,
);
const isDeactivatedView = computed(() => statusFilter.value === 'deactivated');
const hasBlockingModal = computed(() => Boolean(selectedUser.value || deactivateTarget.value || reactivateTarget.value));

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

function closeInfo() {
    selectedUser.value = null;
}

function goToApprovalRequests() {
    router.get('/people/queue');
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
            user.student.course,
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

    router.post(`/admin/users/${deactivateTarget.value.id}/deactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            closeDeactivateDialog();
            router.reload({ only: ['users', 'filters', 'totals', 'pendingCount'] });
        },
        onFinish: () => {
            actionLoadingId.value = null;
        },
    });
}

function reactivateUser() {
    if (!reactivateTarget.value) return;

    actionLoadingId.value = reactivateTarget.value.id;

    router.post(`/admin/users/${reactivateTarget.value.id}/reactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            closeReactivateDialog();
            router.reload({ only: ['users', 'filters', 'totals', 'pendingCount'] });
        },
        onFinish: () => {
            actionLoadingId.value = null;
        },
    });
}

function onModalEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeInfo();
        closeDeactivateDialog();
        closeReactivateDialog();
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
</script>

<template>
    <Head title="People" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">People</h1>
                <p class="text-sm text-slate-600">Manage approved users with searchable, paginated records.</p>
            </div>
            <div class="inline-flex items-center rounded-lg border border-slate-300 bg-white p-1 shadow-sm">
                <button
                    type="button"
                    class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white"
                    aria-current="page"
                >
                    Approved Users
                </button>
                <button
                    type="button"
                    @click="goToApprovalRequests"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Approval Queue
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                        {{ props.pendingCount ?? 0 }}
                    </span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Approved</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalUsers }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Student-Athletes</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalStudents }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Coaches</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalCoaches }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Deactivated</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ totalDeactivated }}</p>
            </article>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 inline-flex items-center rounded-lg border border-slate-300 bg-white p-1 shadow-sm">
                <button
                    type="button"
                    @click="statusFilter = 'approved'"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="statusFilter === 'approved' ? 'bg-[#1f2937] text-white' : 'text-slate-700 hover:bg-slate-100'"
                >
                    Approved
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'deactivated'"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="statusFilter === 'deactivated' ? 'bg-amber-600 text-white' : 'text-slate-700 hover:bg-slate-100'"
                >
                    Deactivated
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-6"
                />

                <select
                    v-model="roleFilter"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="all">All Roles</option>
                    <option value="student-athlete">Student Athlete</option>
                    <option value="coach">Coach</option>
                </select>

                <select
                    v-model="sortOption"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
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

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[900px] w-full text-sm">
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
                            <td class="px-4 py-3 capitalize text-slate-700">{{ formatRole(user.role) }}</td>
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
                                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        {{ copiedUserId === user.id ? 'Copied' : 'Copy Email' }}
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
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                No users match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of {{ users.total }} users
                </p>
                <nav class="flex flex-wrap items-center gap-1" aria-label="User pagination">
                    <button
                        v-for="(link, index) in users.links"
                        :key="`${index}-${link.label}`"
                        type="button"
                        :disabled="!link.url"
                        @click="visitPage(link.url)"
                        class="min-w-9 rounded-md border px-2 py-1 text-xs transition"
                        :class="link.active
                            ? 'border-[#1f2937] bg-[#1f2937] text-white'
                            : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </div>

    <div
        v-if="selectedUser"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="closeInfo"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="user-info-title"
            class="w-full max-w-xl rounded-xl border border-slate-200 bg-white p-5 shadow-xl"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="user-info-title" class="text-lg font-bold text-slate-900">User Information</h2>
                    <p class="text-sm text-slate-600">{{ selectedUser.name }} ({{ selectedUser.email }})</p>
                </div>
                <button
                    type="button"
                    @click="closeInfo"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                    aria-label="Close user information"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-4 text-sm text-slate-700">
                <section class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <h3 class="font-semibold text-slate-900">Account Overview</h3>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <p><span class="font-medium">User ID:</span> {{ selectedUser.id }}</p>
                        <p><span class="font-medium">Role:</span> {{ formatRole(selectedUser.role) }}</p>
                        <p><span class="font-medium">Joined:</span> {{ formatDate(selectedUser.created_at) }}</p>
                        <p><span class="font-medium">Profile Completeness:</span> {{ profileCompleteness(selectedUser) }}%</p>
                        <p class="sm:col-span-2"><span class="font-medium">Primary Contact:</span> {{ formatSimple(getPrimaryPhone(selectedUser)) }}</p>
                    </div>
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
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            {{ copiedUserId === selectedUser.id ? 'Copied' : 'Copy Email' }}
                        </button>
                    </div>
                </section>

                <div v-if="selectedUser.student" class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <h3 class="font-semibold text-slate-900">Student Information</h3>
                    <p class="mt-2"><span class="font-medium">Student ID:</span> {{ selectedUser.student.student_id_number || '-' }}</p>
                    <p><span class="font-medium">Course:</span> {{ formatSimple(selectedUser.student.course) }}</p>
                    <p><span class="font-medium">Education Level:</span> {{ formatSimple(selectedUser.student.education_level) }}</p>
                    <p><span class="font-medium">Year/Grade:</span> {{ formatSimple(selectedUser.student.current_grade_level) }}</p>
                    <p><span class="font-medium">Status:</span> {{ formatSimple(selectedUser.student.student_status) }}</p>
                    <p><span class="font-medium">Phone:</span> {{ formatSimple(selectedUser.student.phone_number) }}</p>
                    <p><span class="font-medium">Gender:</span> {{ formatSimple(selectedUser.student.gender) }}</p>
                    <p><span class="font-medium">Birth Date:</span> {{ formatDate(selectedUser.student.date_of_birth) }}</p>
                    <p><span class="font-medium">Age:</span> {{ getAge(selectedUser.student.date_of_birth) ?? '-' }}</p>
                    <p><span class="font-medium">Height:</span> {{ formatSimple(selectedUser.student.height) }}</p>
                    <p><span class="font-medium">Weight:</span> {{ formatSimple(selectedUser.student.weight) }}</p>
                </div>

                <div v-if="selectedUser.coach" class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <h3 class="font-semibold text-slate-900">Coach Information</h3>
                    <p class="mt-2"><span class="font-medium">Name:</span> {{ coachDisplayName(selectedUser.coach) }}</p>
                    <p><span class="font-medium">Status:</span> {{ formatSimple(selectedUser.coach.coach_status) }}</p>
                    <p><span class="font-medium">Phone:</span> {{ formatSimple(selectedUser.coach.phone_number) }}</p>
                    <p><span class="font-medium">Gender:</span> {{ formatSimple(selectedUser.coach.gender) }}</p>
                    <p><span class="font-medium">Birth Date:</span> {{ formatDate(selectedUser.coach.date_of_birth) }}</p>
                    <p><span class="font-medium">Age:</span> {{ getAge(selectedUser.coach.date_of_birth) ?? '-' }}</p>
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

    <div
        v-if="deactivateTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="closeDeactivateDialog"
    >
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900">Deactivate Account</h2>
            <p class="mt-2 text-sm text-slate-600">
                Deactivate <span class="font-semibold text-slate-900">{{ deactivateTarget.name }}</span>?
                This blocks login but keeps records for audit and recovery.
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

    <div
        v-if="reactivateTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="closeReactivateDialog"
    >
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900">Reactivate Account</h2>
            <p class="mt-2 text-sm text-slate-600">
                Reactivate <span class="font-semibold text-slate-900">{{ reactivateTarget.name }}</span>?
                This restores full sign-in access immediately.
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
</template>
