<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';

defineOptions({
    layout: AdminDashboard,
});

type ReadinessFilter = 'all' | 'ready' | 'incomplete';
type QueueSort = 'newest' | 'oldest' | 'name_asc' | 'name_desc';
type QueueStatus = 'pending' | 'rejected';

type QueueUser = {
    id: number;
    name: string;
    email: string;
    role: 'student-athlete' | 'student' | 'coach';
    status: 'pending' | 'approved' | 'rejected';
    created_at: string | null;
    student?: {
        id: number;
        student_id_number: string | null;
        first_name: string | null;
        last_name: string | null;
        course_or_strand: string | null;
        current_grade_level: string | null;
        latest_health_clearance?: {
            id: number;
            clearance_status: string | null;
            valid_until: string | null;
            physician_name: string | null;
        } | null;
        latest_academic_document?: {
            id: number;
            document_type: string | null;
            uploaded_at: string | null;
        } | null;
    } | null;
    coach?: {
        id: number;
        first_name: string | null;
        last_name: string | null;
        coach_status: string | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedQueue = {
    data: QueueUser[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

type Filters = {
    search?: string;
    status?: QueueStatus;
    readiness?: ReadinessFilter;
    sort?: QueueSort;
};

type Stats = {
    pending_total: number;
    ready_total: number;
    incomplete_total: number;
    rejected_total: number;
};

const props = defineProps<{
    queue: PaginatedQueue;
    filters?: Filters;
    stats?: Stats;
    pendingCount?: number;
}>();

const search = ref(props.filters?.search ?? '');
const status = ref<QueueStatus>(props.filters?.status ?? 'pending');
const readiness = ref<ReadinessFilter>(props.filters?.readiness ?? 'all');
const sort = ref<QueueSort>(props.filters?.sort ?? 'newest');

const approvingId = ref<number | null>(null);
const rejectingId = ref<number | null>(null);
const approveTarget = ref<QueueUser | null>(null);
const rejectTarget = ref<QueueUser | null>(null);
const rejectRemarks = ref('');
const topTab = ref<'approved' | 'queue'>('queue');

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let topTabTimeout: ReturnType<typeof setTimeout> | null = null;

const stats = computed(() => ({
    pending_total: props.stats?.pending_total ?? 0,
    ready_total: props.stats?.ready_total ?? 0,
    incomplete_total: props.stats?.incomplete_total ?? 0,
    rejected_total: props.stats?.rejected_total ?? 0,
}));
const queue = computed(() => props.queue);
const isRejectedView = computed(() => status.value === 'rejected');

function buildQuery(resetPage = true) {
    return {
        search: search.value.trim() || undefined,
        status: status.value,
        readiness: readiness.value === 'all' ? undefined : readiness.value,
        sort: sort.value,
        page: resetPage ? 1 : props.queue.current_page,
    };
}

function applyFilters({ resetPage = true, debounce = false }: { resetPage?: boolean; debounce?: boolean } = {}) {
    const visit = () => {
        router.get('/people/queue', buildQuery(resetPage), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['queue', 'filters', 'stats', 'pendingCount'],
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

watch(status, () => {
    if (status.value === 'rejected') {
        readiness.value = 'all';
    }
    applyFilters({ resetPage: true });
});

watch(readiness, () => {
    applyFilters({ resetPage: true });
});

watch(sort, () => {
    applyFilters({ resetPage: true });
});

function goToUserManagement() {
    if (topTab.value === 'approved') return;
    topTab.value = 'approved';
    if (topTabTimeout) clearTimeout(topTabTimeout);
    topTabTimeout = setTimeout(() => {
        router.get('/people');
    }, 180);
}

function visitPage(url: string | null) {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['queue', 'filters', 'stats', 'pendingCount'],
    });
}

function isStudentRole(user: QueueUser) {
    return user.role === 'student-athlete' || user.role === 'student';
}

function hasRequirements(user: QueueUser) {
    if (!isStudentRole(user)) return true;

    return Boolean(user.student?.latest_health_clearance) && Boolean(user.student?.latest_academic_document);
}

function requirementIssues(user: QueueUser) {
    if (!isStudentRole(user)) return [];

    const issues: string[] = [];

    if (!user.student?.latest_health_clearance) {
        issues.push('Missing health clearance');
    }
    if (!user.student?.latest_academic_document) {
        issues.push('Missing academic document');
    }

    return issues;
}

function queuePosition(index: number) {
    return (props.queue.current_page - 1) * props.queue.per_page + index + 1;
}

function formatRole(role: QueueUser['role']) {
    return role.replace('-', ' ');
}

function formatDate(value?: string | null) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleDateString();
}

function formatClearanceStatus(value?: string | null) {
    if (!value) return 'No clearance';
    return value.replaceAll('_', ' ');
}

function formatDocumentType(value?: string | null) {
    if (!value) return 'No document';
    return value.replaceAll('_', ' ').toUpperCase();
}

function clearanceFileUrl(id?: number | null) {
    if (!id) return null;
    return `/files/clearance/${id}`;
}

function academicFileUrl(id?: number | null) {
    if (!id) return null;
    return `/files/academic/${id}`;
}

function openApproveDialog(user: QueueUser) {
    approveTarget.value = user;
}

function closeApproveDialog() {
    approveTarget.value = null;
}

function openRejectDialog(user: QueueUser) {
    rejectTarget.value = user;
    rejectRemarks.value = '';
}

function closeRejectDialog() {
    rejectTarget.value = null;
    rejectRemarks.value = '';
}

function approveUser() {
    if (!approveTarget.value) return;

    approvingId.value = approveTarget.value.id;

    router.post(
        `/admin/users/${approveTarget.value.id}/approve`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeApproveDialog();
                router.reload({
                    only: ['queue', 'filters', 'stats', 'pendingCount'],
                });
            },
            onFinish: () => {
                approvingId.value = null;
            },
        },
    );
}

function rejectUser() {
    if (!rejectTarget.value) return;

    rejectingId.value = rejectTarget.value.id;

    router.post(
        `/admin/users/${rejectTarget.value.id}/reject`,
        {
            remarks: rejectRemarks.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeRejectDialog();
                router.reload({
                    only: ['queue', 'filters', 'stats', 'pendingCount'],
                });
            },
            onFinish: () => {
                rejectingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="People Queue" />

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
                    @click="goToUserManagement"
                    class="relative z-10 w-full justify-center rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="topTab === 'approved' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Approved Users
                </button>
                <button
                    type="button"
                    class="relative z-10 inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="topTab === 'queue' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                    aria-current="page"
                >
                    Approval Queue
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="topTab === 'queue' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ props.pendingCount ?? stats.pending_total }}
                    </span>
                </button>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Pending Accounts</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.pending_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Ready To Approve</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.ready_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Needs Requirements</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.incomplete_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-xs tracking-wide text-slate-500 uppercase">Rejected Accounts</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ stats.rejected_total }}</p>
            </article>
        </section>

        <section class="rounded-xl border border-[#034485]/45 bg-white p-4">
            <div class="relative mb-3 inline-flex items-center rounded-full border border-[#034485]/45 bg-white p-1">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full transition-transform duration-200 ease-out"
                    :class="status === 'pending' ? 'translate-x-0 bg-[#1f2937]' : 'translate-x-full bg-rose-600'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    @click="status = 'pending'"
                    class="relative z-10 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="status === 'pending' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Pending Queue
                </button>
                <button
                    type="button"
                    @click="status = 'rejected'"
                    class="relative z-10 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                    :class="status === 'rejected' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Rejected Users
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
                    v-model="readiness"
                    :disabled="isRejectedView"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="all">All Readiness</option>
                    <option value="ready">Ready to Approve</option>
                    <option value="incomplete">Incomplete Requirements</option>
                </select>

                <select
                    v-model="sort"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name_asc">Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                </select>
            </div>
        </section>

        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <section :key="status" class="overflow-hidden rounded-xl border border-[#034485]/45 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">#</th>
                                <th class="px-4 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Role</th>
                                <th class="px-4 py-3 text-left font-semibold">Registered</th>
                                <th class="px-4 py-3 text-left font-semibold">Readiness</th>
                                <th class="px-4 py-3 text-left font-semibold">Requirements</th>
                                <th class="px-4 py-3 text-left font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(user, index) in queue.data" :key="user.id" class="border-t border-slate-200 align-top">
                                <td class="px-4 py-3 font-semibold text-slate-600">{{ queuePosition(index) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ user.name }}</p>
                                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                                    <p v-if="user.student?.student_id_number" class="text-xs text-slate-500">
                                        ID: {{ user.student.student_id_number }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-700 capitalize">{{ formatRole(user.role) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatDate(user.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            isRejectedView
                                                ? 'bg-rose-100 text-rose-700'
                                                : hasRequirements(user)
                                                  ? 'bg-emerald-100 text-emerald-700'
                                                  : 'bg-amber-100 text-amber-700'
                                        "
                                    >
                                        {{ isRejectedView ? 'Rejected' : hasRequirements(user) ? 'Ready' : 'Incomplete' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="isStudentRole(user)">
                                        <div class="space-y-1 text-xs">
                                            <p :class="user.student?.latest_health_clearance ? 'text-emerald-700' : 'text-amber-700'">
                                                {{
                                                    user.student?.latest_health_clearance
                                                        ? `Health: ${formatClearanceStatus(user.student.latest_health_clearance.clearance_status)}`
                                                        : 'Health: Missing'
                                                }}
                                            </p>
                                            <p :class="user.student?.latest_academic_document ? 'text-emerald-700' : 'text-amber-700'">
                                                {{
                                                    user.student?.latest_academic_document
                                                        ? `Academic: ${formatDocumentType(user.student.latest_academic_document.document_type)}`
                                                        : 'Academic: Missing'
                                                }}
                                            </p>
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <a
                                                    v-if="clearanceFileUrl(user.student?.latest_health_clearance?.id)"
                                                    :href="clearanceFileUrl(user.student?.latest_health_clearance?.id)!"
                                                    target="_blank"
                                                    class="text-[11px] font-semibold text-[#1f2937] hover:underline"
                                                >
                                                    View Clearance
                                                </a>
                                                <a
                                                    v-if="academicFileUrl(user.student?.latest_academic_document?.id)"
                                                    :href="academicFileUrl(user.student?.latest_academic_document?.id)!"
                                                    target="_blank"
                                                    class="text-[11px] font-semibold text-[#1f2937] hover:underline"
                                                >
                                                    View Academic
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                    <p v-else class="text-xs text-slate-500">Coach accounts are approval-ready.</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="!isRejectedView" class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            @click="openApproveDialog(user)"
                                            :disabled="!hasRequirements(user) || approvingId === user.id || rejectingId === user.id"
                                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            @click="openRejectDialog(user)"
                                            :disabled="approvingId === user.id || rejectingId === user.id"
                                            class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                    <p v-if="!isRejectedView && requirementIssues(user).length" class="mt-2 text-[11px] font-medium text-amber-700">
                                        {{ requirementIssues(user).join(' | ') }}
                                    </p>
                                    <p v-if="isRejectedView" class="text-[11px] font-medium text-slate-500">
                                        Rejected account for historical review.
                                    </p>
                                </td>
                            </tr>

                            <tr v-if="queue.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    {{
                                        isRejectedView
                                            ? 'No rejected accounts for the selected filters.'
                                            : 'No accounts in the queue for the selected filters.'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        Showing {{ queue.from ?? 0 }} to {{ queue.to ?? 0 }} of {{ queue.total }}
                        {{ isRejectedView ? 'rejected accounts' : 'pending accounts' }}
                    </p>
                    <nav class="flex flex-wrap items-center gap-1" aria-label="Approval queue pagination">
                        <button
                            v-for="(link, index) in queue.links"
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
            </section>
        </Transition>
    </div>

    <Transition name="modal-fade">
        <div v-if="approveTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeApproveDialog">
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Confirm Approval</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Approve <span class="font-semibold text-slate-900">{{ approveTarget.name }}</span> and grant system access?
                </p>

                <div
                    v-if="requirementIssues(approveTarget).length"
                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-700"
                >
                    {{ requirementIssues(approveTarget).join(' | ') }}
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeApproveDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="approveUser"
                        :disabled="approvingId === approveTarget.id || !hasRequirements(approveTarget)"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Approve
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeRejectDialog">
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Reject Account</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Provide optional remarks for <span class="font-semibold text-slate-900">{{ rejectTarget.name }}</span
                    >.
                </p>

                <textarea
                    v-model="rejectRemarks"
                    rows="4"
                    placeholder="Add context for the rejection (optional)"
                    class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                />

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeRejectDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="rejectUser"
                        :disabled="rejectingId === rejectTarget.id"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Reject
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
