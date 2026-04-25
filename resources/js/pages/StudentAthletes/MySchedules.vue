<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import ToastEventBus from 'primevue/toasteventbus';
import QRCode from 'qrcode';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { VueCal } from 'vue-cal';

import FormAlert from '@/components/ui/form/FormAlert.vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { useSportColors } from '@/composables/useSportColors';
import { useUserTimezone } from '@/composables/useUserTimezone';
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue';
import 'vue-cal/style';

defineOptions({
    layout: StudentAthleteDashboard,
});

const props = defineProps<{
    team: {
        id: number;
        team_name: string;
        sport: string;
    } | null;
    teams: Array<{ id: number; team_name: string; sport: string }>;
    selectedTeamId: number | null;
    schedules: any[];
    accessLocked?: boolean;
    lockStatus?: string | null;
    lockMessage?: string | null;
}>();

const { sportColor, sportTextColor, sportLabel } = useSportColors();
const { timezone } = useUserTimezone();

const selectedScheduleId = ref<number | null>(null);
const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null);
const processingScheduleId = ref<number | null>(null);
const calendarViewDate = ref<Date | string>('');
const calendarSelectedDate = ref<Date | string>('');
const showCalendar = ref(false);
const showCompleted = ref(false);
const showReasonModal = ref(false);
const reasonText = ref('');
const reasonStatus = ref<'absent' | 'excused' | null>(null);
const reasonScheduleId = ref<number | null>(null);
const showQrModal = ref(false);
const qrScheduleId = ref<number | null>(null);
const qrToken = ref('');
const qrExpiresAt = ref<string | null>(null);
const qrSecondsLeft = ref(0);
const qrError = ref<string | null>(null);
const qrDataUrl = ref<string | null>(null);
const qrLoading = ref(false);
let qrRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let qrCountdownTimer: ReturnType<typeof setInterval> | null = null;

const sortedSchedules = computed(() => [...(props.schedules || [])].sort((a, b) => +new Date(a.start) - +new Date(b.start)));

const calendarEvents = computed(() =>
    sortedSchedules.value
        .filter((item) => item.start && item.end)
        .map((item: any) => ({
            id: item.id,
            title: item.title,
            start: new Date(item.start),
            end: new Date(item.end),
            content: `${item.type} • ${item.venue || '-'}`,
            backgroundColor: sportColor(item.sport),
            color: sportTextColor(item.sport),
            class: selectedScheduleId.value === item.id ? 'student-schedule--focused' : '',
        })),
);

const selectedSchedule = computed(() => sortedSchedules.value.find((item) => item.id === selectedScheduleId.value) || null);

const upcomingSchedules = computed(() => sortedSchedules.value.filter((item) => !isPastSchedule(item)));

const completedSchedules = computed(() => sortedSchedules.value.filter((item) => isPastSchedule(item)));

const nextSchedule = computed(() => upcomingSchedules.value[0] || null);

const needsResponseCount = computed(() => upcomingSchedules.value.filter((item) => !item.attendance_status).length);

watch(
    sortedSchedules,
    (items) => {
        if (!items.length || calendarViewDate.value) return;
        const firstDate = new Date(items[0].start);
        calendarViewDate.value = firstDate;
        calendarSelectedDate.value = firstDate;
    },
    { immediate: true },
);

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null;
    },
);

onMounted(() => {
    showCalendar.value = window.matchMedia('(min-width: 1280px)').matches;
});

function formatPHT(dt: string | Date | null) {
    if (!dt) return '-';

    const d = typeof dt === 'string' ? new Date(dt) : dt;

    return d.toLocaleString('en-PH', {
        timeZone: timezone,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function statusLabel(status: string | null) {
    if (status === 'present') return 'Attending';
    if (status === 'absent') return 'Not Attending';
    if (status === 'excused') return 'Excused';
    return 'No Response';
}

function statusClass(status: string | null) {
    if (status === 'present') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if (status === 'absent') return 'bg-rose-50 text-rose-700 border border-rose-200';
    if (status === 'excused') return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-[#034485]/5 text-slate-600 border border-[#034485]/20';
}

function isPastSchedule(item: any) {
    if (item?.end) return new Date(item.end).getTime() < Date.now();
    if (item?.start) return new Date(item.start).getTime() < Date.now();
    return false;
}

function isToday(item: any) {
    if (!item?.start) return false;
    const d = new Date(item.start);
    const now = new Date();
    return d.toDateString() === now.toDateString();
}

function hexToRgb(value: string) {
    const hex = value.replace('#', '');
    const normalized =
        hex.length === 3
            ? hex
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : hex;
    const num = parseInt(normalized, 16);
    return {
        r: (num >> 16) & 255,
        g: (num >> 8) & 255,
        b: num & 255,
    };
}

function mixWithWhite(color: string, amount = 0.4) {
    const { r, g, b } = hexToRgb(color);
    const mix = (channel: number) => Math.round(channel + (255 - channel) * amount);
    return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
}

function stripeColors(sport: any) {
    const base = sportColor(sport);
    return {
        base,
        lighter: mixWithWhite(base, 0.5),
    };
}

function timingLabel(item: any) {
    if (isPastSchedule(item)) return 'Completed';
    return 'Upcoming';
}

function timingClass(item: any) {
    return isPastSchedule(item)
        ? 'bg-[#034485]/5 text-slate-600 border border-[#034485]/25'
        : 'bg-[#034485]/5 text-[#1f2937] border border-[#034485]/20';
}

function needsResponse(item: any) {
    return !isPastSchedule(item) && !item.attendance_status;
}

function canRespondToAttendance(item: any) {
    return !isPastSchedule(item) && Boolean(item?.checkin_window_open);
}

function attendanceUnavailableMessage(item: any) {
    return String(item?.checkin_window_reason || 'Attendance confirmation is not available right now.');
}

function setAttendance(scheduleId: number, status: 'present' | 'absent' | 'excused', notes: string | null = null) {
    const schedule = sortedSchedules.value.find((item) => item.id === scheduleId);
    if (schedule && !canRespondToAttendance(schedule)) {
        ToastEventBus.emit('add', {
            severity: 'warn',
            summary: 'Attendance Unavailable',
            detail: attendanceUnavailableMessage(schedule),
            life: 3200,
        });
        return;
    }

    processingScheduleId.value = scheduleId;

    router.put(
        `/Student/Schedules/${scheduleId}/attendance`,
        { status, notes },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const label = statusLabel(status);
                ToastEventBus.emit('add', {
                    severity: 'success',
                    summary: 'Attendance Updated',
                    detail: `Your attendance has been marked as ${label.toLowerCase()}.`,
                    life: 2400,
                });
            },
            onError: (errors: Record<string, string | string[]>) => {
                const attendanceError = errors?.attendance;
                const notesError = errors?.notes;
                const rawMessage = attendanceError ?? notesError ?? 'We could not update your attendance right now.';
                const detail = Array.isArray(rawMessage) ? String(rawMessage[0] ?? '') : String(rawMessage);

                ToastEventBus.emit('add', {
                    severity: 'error',
                    summary: 'Attendance Update Failed',
                    detail,
                    life: 3400,
                });
            },
            onFinish: () => {
                processingScheduleId.value = null;
            },
        },
    );
}

function openReasonModal(item: any, status: 'absent' | 'excused') {
    reasonScheduleId.value = item.id;
    reasonStatus.value = status;
    reasonText.value = item.attendance_notes ?? '';
    showReasonModal.value = true;
}

function closeReasonModal() {
    showReasonModal.value = false;
    reasonText.value = '';
    reasonStatus.value = null;
    reasonScheduleId.value = null;
}

function submitReason() {
    if (!reasonScheduleId.value || !reasonStatus.value) return;
    if (!reasonText.value.trim()) return;

    setAttendance(reasonScheduleId.value, reasonStatus.value, reasonText.value.trim());
    closeReasonModal();
}

function onCalendarEventClick({ event }: any) {
    selectedScheduleId.value = event.id;
    calendarViewDate.value = new Date(event.start);
    calendarSelectedDate.value = new Date(event.start);
}

function focusSchedule(item: any) {
    selectedScheduleId.value = item.id;
    calendarViewDate.value = new Date(item.start);
    calendarSelectedDate.value = new Date(item.start);
}

const qrPayload = computed(() => {
    if (!qrToken.value || !qrScheduleId.value) return '';
    return JSON.stringify({
        token: qrToken.value,
        schedule_id: qrScheduleId.value,
    });
});

const qrImageUrl = computed(() => {
    return qrDataUrl.value;
});

watch(qrPayload, async (payload) => {
    if (!payload) {
        qrDataUrl.value = null;
        return;
    }

    try {
        qrDataUrl.value = await QRCode.toDataURL(payload, {
            width: 260,
            margin: 1,
            errorCorrectionLevel: 'M',
        });
    } catch {
        qrDataUrl.value = null;
        qrError.value = 'Unable to render local QR image.';
    }
});

function clearQrTimers() {
    if (qrRefreshTimer) {
        clearTimeout(qrRefreshTimer);
        qrRefreshTimer = null;
    }
    if (qrCountdownTimer) {
        clearInterval(qrCountdownTimer);
        qrCountdownTimer = null;
    }
}

async function fetchQrToken() {
    if (!qrScheduleId.value) return;

    qrError.value = null;
    qrLoading.value = true;
    try {
        const response = await fetch(`/Student/Schedules/${qrScheduleId.value}/qr-token`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok) {
            qrError.value = data?.message ?? 'Unable to generate QR token.';
            return;
        }

        qrToken.value = data.token;
        qrExpiresAt.value = data.expires_at;
        const rotationSeconds = Number(data.rotation_seconds ?? 25);
        qrRefreshTimer = setTimeout(fetchQrToken, Math.max(20, rotationSeconds) * 1000);
        startQrCountdown();
    } catch {
        qrError.value = 'Network error while fetching QR token.';
    } finally {
        qrLoading.value = false;
    }
}

function startQrCountdown() {
    if (!qrExpiresAt.value) return;
    if (qrCountdownTimer) clearInterval(qrCountdownTimer);

    const update = () => {
        const ms = new Date(qrExpiresAt.value as string).getTime() - Date.now();
        qrSecondsLeft.value = Math.max(0, Math.ceil(ms / 1000));
    };
    update();
    qrCountdownTimer = setInterval(update, 1000);
}

function openQrModal(scheduleId: number) {
    const schedule = sortedSchedules.value.find((item) => item.id === scheduleId);
    if (schedule && !canRespondToAttendance(schedule)) {
        ToastEventBus.emit('add', {
            severity: 'warn',
            summary: 'QR Unavailable',
            detail: attendanceUnavailableMessage(schedule),
            life: 3200,
        });
        return;
    }

    showQrModal.value = true;
    qrScheduleId.value = scheduleId;
    qrToken.value = '';
    qrExpiresAt.value = null;
    qrSecondsLeft.value = 0;
    clearQrTimers();
    fetchQrToken();
}

function changeTeam() {
    if (!selectedTeamId.value) return;
    router.get(
        '/MySchedule',
        { team_id: selectedTeamId.value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function closeQrModal() {
    showQrModal.value = false;
    clearQrTimers();
    qrScheduleId.value = null;
    qrToken.value = '';
    qrExpiresAt.value = null;
    qrSecondsLeft.value = 0;
    qrDataUrl.value = null;
    qrError.value = null;
    qrLoading.value = false;
}

onUnmounted(() => {
    clearQrTimers();
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">My Schedule</h1>
                <p class="text-sm text-slate-500">Confirm your attendance for practices, trainings, and meetings.</p>
            </div>
            <div v-if="!accessLocked" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button
                    @click="showCalendar = !showCalendar"
                    class="w-full rounded-lg border border-[#034485]/40 bg-[#034485] px-3 py-2 text-xs font-semibold text-white hover:bg-[#033a70] sm:w-auto"
                >
                    {{ showCalendar ? 'Hide Calendar' : 'Show Calendar' }}
                </button>
                <button
                    @click="showCompleted = !showCompleted"
                    class="w-full rounded-lg border border-[#034485]/40 bg-[#034485] px-3 py-2 text-xs font-semibold text-white hover:bg-[#033a70] sm:w-auto"
                >
                    {{ showCompleted ? 'Hide Completed' : 'Show Completed' }}
                </button>
            </div>
        </div>

        <div v-if="accessLocked" class="rounded-xl border border-[#034485]/30 bg-[#034485]/5 p-6 text-slate-700">
            <h2 class="text-sm font-semibold text-slate-800">Schedule Access Paused</h2>
            <p class="mt-1 text-sm text-slate-600">{{ lockMessage || 'Schedule access is paused during the academic submission window.' }}</p>
            <div class="mt-3 text-xs text-slate-600">
                Status:
                <span class="ml-2 inline-flex rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white">
                    {{ lockStatus || 'Suspended' }}
                </span>
            </div>
            <Link
                href="/AcademicSubmissions"
                class="mt-4 inline-flex rounded-full border border-[#034485]/40 px-3 py-1 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
            >
                Open Academic Submissions
            </Link>
        </div>

        <template v-else>
            <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Team</span>
                    <select
                        v-model.number="selectedTeamId"
                        @change="changeTeam"
                        class="rounded-md border border-[#034485]/40 px-2 py-1 text-xs text-slate-700"
                    >
                        <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                            {{ teamOption.team_name }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="team" class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                <span class="rounded px-2 py-0.5" :style="{ backgroundColor: sportColor(team.sport), color: sportTextColor(team.sport) }">
                    {{ sportLabel(team.sport) }}
                </span>
                <span class="text-slate-500">{{ team.team_name }}</span>
            </div>

            <div v-if="!team" class="rounded-xl border border-[#034485]/35 bg-white p-6 text-slate-600">You are not assigned to a team yet.</div>

            <div v-else class="space-y-4">
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Next session</p>
                        <p v-if="nextSchedule" class="mt-1 text-sm font-semibold text-slate-900">{{ nextSchedule.title }}</p>
                        <p v-if="nextSchedule" class="text-xs text-slate-500">{{ formatPHT(nextSchedule.start) }}</p>
                        <p v-else class="mt-1 text-sm text-slate-500">No upcoming sessions have been scheduled.</p>
                    </div>
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Upcoming sessions</p>
                        <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ upcomingSchedules.length }}</p>
                        <p class="text-xs text-slate-500">Scheduled this season</p>
                    </div>
                    <div class="rounded-xl border border-[#034485]/35 bg-white p-4">
                        <p class="text-xs text-slate-500">Pending confirmation</p>
                        <p class="mt-1 text-2xl font-semibold text-rose-600">{{ needsResponseCount }}</p>
                        <p class="text-xs text-slate-500">Attendance to confirm</p>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
                    <section v-if="showCalendar" class="rounded-xl border border-[#034485]/35 bg-white p-4 xl:col-span-3">
                        <p class="mb-3 text-xs text-slate-500">Tip: Click a schedule on the calendar to focus it on the right panel.</p>

                        <VueCal
                            sm
                            style="height: 650px"
                            :events="calendarEvents"
                            v-model:view-date="calendarViewDate"
                            v-model:selected-date="calendarSelectedDate"
                            default-view="week"
                            :time="true"
                            :twelve-hour="true"
                            time-format="h:mm {am}"
                            events-on-month-view
                            @event-click="onCalendarEventClick"
                        />
                    </section>

                    <aside
                        class="max-h-162.5 overflow-y-auto rounded-xl border border-[#034485]/35 bg-white p-4"
                        :class="showCalendar ? 'xl:col-span-2' : 'xl:col-span-5'"
                    >
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="font-semibold text-slate-900">Upcoming Sessions</h2>
                                <p class="text-xs text-slate-500">{{ upcomingSchedules.length }} scheduled</p>
                            </div>
                            <span v-if="selectedSchedule" class="text-xs break-words text-[#1f2937] sm:text-right">Focused: {{ selectedSchedule.title }}</span>
                        </div>

                        <div v-if="upcomingSchedules.length === 0" class="text-sm text-slate-500">No upcoming sessions are available at this time.</div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="item in upcomingSchedules"
                                :key="item.id"
                                class="student-schedule-card relative overflow-hidden rounded-3xl border border-[#034485]/40 bg-white p-4 transition"
                                :class="item.id === selectedScheduleId ? 'border-[#034485] bg-[#034485]/5' : ''"
                            >
                                <div
                                    class="pointer-events-none absolute top-1/2 left-1/2 flex h-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-6 gap-1 opacity-60"
                                >
                                    <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                    <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="leading-tight font-medium text-slate-900">{{ item.title }}</div>
                                        <div class="text-xs text-slate-500">{{ item.type }} • {{ item.venue || '-' }}</div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded px-2 py-0.5 text-[10px]" :class="statusClass(item.attendance_status)">
                                            {{ statusLabel(item.attendance_status) }}
                                        </span>
                                        <button
                                            @click="focusSchedule(item)"
                                            class="rounded border border-[#034485]/35 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-[#034485]/5"
                                        >
                                            Focus
                                        </button>
                                        <button
                                            @click="openQrModal(item.id)"
                                            :disabled="(qrLoading && qrScheduleId === item.id) || !canRespondToAttendance(item)"
                                            class="rounded bg-[#1f2937] px-2.5 py-1 text-xs text-white hover:bg-[#334155] disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span class="inline-flex items-center gap-1.5">
                                                <Spinner v-if="qrLoading && qrScheduleId === item.id" class="h-3 w-3 text-white" />
                                                {{ qrLoading && qrScheduleId === item.id ? 'Generating...' : 'Show QR' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                    <span class="rounded-full border px-2 py-0.5" :class="timingClass(item)">{{ timingLabel(item) }}</span>
                                    <span v-if="isToday(item)" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700"
                                        >Today</span
                                    >
                                    <span v-if="needsResponse(item)" class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700"
                                        >Pending confirmation</span
                                    >
                                </div>

                                <div class="mt-2 text-xs text-slate-600">{{ formatPHT(item.start) }}</div>
                                <div class="text-xs text-slate-600">{{ formatPHT(item.end) }}</div>

                                <div v-if="item.notes" class="mt-2 text-xs text-slate-500">{{ item.notes }}</div>
                                <div
                                    v-if="item.attendance_notes && (item.attendance_status === 'absent' || item.attendance_status === 'excused')"
                                    class="mt-2 text-xs text-amber-600"
                                >
                                    Reason: {{ item.attendance_notes }}
                                </div>

                                <div class="mt-3 border-t border-[#034485]/20 pt-3">
                                    <p class="mb-2 text-[11px] text-slate-500">Attendance Confirmation</p>
                                    <p v-if="!canRespondToAttendance(item)" class="mb-2 text-[11px] text-amber-600">
                                        {{ attendanceUnavailableMessage(item) }}
                                    </p>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <button
                                            @click="setAttendance(item.id, 'present')"
                                            :disabled="processingScheduleId === item.id || !canRespondToAttendance(item)"
                                            class="w-full rounded bg-emerald-600 px-3 py-1.5 text-xs text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                        >
                                            Attending
                                        </button>
                                        <button
                                            @click="openReasonModal(item, 'absent')"
                                            :disabled="processingScheduleId === item.id || !canRespondToAttendance(item)"
                                            class="w-full rounded bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                        >
                                            Not Attending
                                        </button>
                                        <button
                                            @click="openReasonModal(item, 'excused')"
                                            :disabled="processingScheduleId === item.id || !canRespondToAttendance(item)"
                                            class="w-full rounded bg-amber-500 px-3 py-1.5 text-xs text-slate-900 hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                        >
                                            Excused
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="showCompleted" class="mt-6">
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-800">Completed</h3>
                                <span class="text-xs text-slate-500">{{ completedSchedules.length }}</span>
                            </div>
                            <div v-if="completedSchedules.length === 0" class="text-sm text-slate-500">No completed sessions are available at this time.</div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in completedSchedules"
                                    :key="item.id"
                                    class="student-schedule-card relative overflow-hidden rounded-3xl border border-[#034485]/40 bg-white p-4 transition"
                                    :class="item.id === selectedScheduleId ? 'border-[#034485] bg-[#034485]/5' : ''"
                                >
                                    <div
                                        class="pointer-events-none absolute top-1/2 left-1/2 flex h-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-6 gap-1 opacity-60"
                                    >
                                        <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                        <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                                    </div>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="leading-tight font-medium text-slate-900">{{ item.title }}</div>
                                            <div class="text-xs text-slate-500">{{ item.type }} • {{ item.venue || '-' }}</div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded px-2 py-0.5 text-[10px]" :class="statusClass(item.attendance_status)">
                                                {{ statusLabel(item.attendance_status) }}
                                            </span>
                                            <button
                                                @click="focusSchedule(item)"
                                                class="rounded border border-[#034485]/35 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-[#034485]/5"
                                            >
                                                Focus
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                        <span class="rounded-full border px-2 py-0.5" :class="timingClass(item)">{{ timingLabel(item) }}</span>
                                        <span v-if="isToday(item)" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700"
                                            >Today</span
                                        >
                                    </div>

                                    <div class="mt-2 text-xs text-slate-600">{{ formatPHT(item.start) }}</div>
                                    <div class="text-xs text-slate-600">{{ formatPHT(item.end) }}</div>

                                    <div v-if="item.notes" class="mt-2 text-xs text-slate-500">{{ item.notes }}</div>
                                    <div
                                        v-if="item.attendance_notes && (item.attendance_status === 'absent' || item.attendance_status === 'excused')"
                                        class="mt-2 text-xs text-amber-600"
                                    >
                                        Reason: {{ item.attendance_notes }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <div v-if="showReasonModal" @click.self="closeReasonModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40">
                <div class="w-full max-w-md rounded-xl border border-[#034485]/35 bg-white">
                    <div class="flex items-center justify-between border-b border-[#034485]/20 px-5 py-4">
                        <h3 class="font-semibold text-slate-900">
                            {{ reasonStatus === 'absent' ? 'Reason for Not Attending' : 'Reason for Excused' }}
                        </h3>
                        <button @click="closeReasonModal" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>

                    <div class="p-5">
                        <textarea
                            v-model="reasonText"
                            rows="4"
                            class="w-full rounded-lg border border-[#034485]/35 bg-white px-3 py-2 text-slate-700"
                            placeholder="Write your reason here..."
                        />
                    </div>

                    <div class="flex justify-end gap-2 border-t border-[#034485]/20 px-5 py-4">
                        <button
                            @click="closeReasonModal"
                            class="rounded border border-[#034485]/35 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-[#034485]/5"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitReason"
                            :disabled="!reasonText.trim()"
                            class="rounded bg-[#034485] px-3 py-1.5 text-sm text-white hover:bg-[#033a70] disabled:opacity-50"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showQrModal" @click.self="closeQrModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                <div class="w-full max-w-sm rounded-xl border border-[#034485]/35 bg-white">
                    <div class="flex items-center justify-between border-b border-[#034485]/20 px-4 py-3">
                        <h3 class="font-semibold text-slate-900">Schedule Check-in QR</h3>
                        <button @click="closeQrModal" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>
                    <div class="space-y-3 p-4">
                        <p class="text-xs text-slate-500">Show this to your coach. QR rotates automatically for security.</p>
                        <p v-if="qrSecondsLeft > 0" class="text-xs text-amber-600">Token refresh in about {{ qrSecondsLeft }}s</p>
                        <FormAlert tone="error" compact :message="qrError" />
                        <div v-if="qrLoading" class="space-y-2 rounded-lg bg-slate-100 p-3">
                            <Skeleton class="h-5 w-1/2 bg-slate-200" />
                            <Skeleton class="h-60 w-full bg-slate-200" />
                        </div>
                        <div v-if="qrImageUrl" class="flex justify-center rounded-lg bg-white p-3">
                            <img :src="qrImageUrl" alt="Attendance QR" class="h-60 w-60 object-contain" />
                        </div>
                        <div v-if="qrToken" class="text-[11px] break-all text-slate-400">Token: {{ qrToken }}</div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
:deep(.vuecal__event.student-schedule--focused) {
    outline: 2px solid #034485;
    outline-offset: 1px;
}
</style>
