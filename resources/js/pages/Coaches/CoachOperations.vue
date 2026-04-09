<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AttendanceRecord from '@/pages/Coaches/AttendanceRecord.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
  layout: CoachDashboard,
})

defineProps<{
  activeTab: 'attendance' | 'wellness'
  teams: Array<{
    id: number
    team_name: string
    sport: string
  }>
  selectedTeamId: number | null
  team: {
    id: number
    team_name: string
    sport: string
  } | null
  attendanceSchedules: Array<{
    id: number
    title: string
    type: string
    venue: string
    start: string
    end: string
    qr_closes_at: string
    is_qr_open: boolean
  }>
  selectedAttendanceScheduleId: number | null
  attendanceRows: Array<{
    student_id: number
    student_id_number: string | null
    full_name: string
    jersey_number: string | number | null
    athlete_position: string | null
    attendance_status: string | null
    verification_method: string | null
    attendance_notes: string | null
    recorded_at: string | null
    wellness: {
      injury_observed: boolean
      injury_notes: string | null
      fatigue_level: number | null
      performance_condition: 'excellent' | 'good' | 'fair' | 'poor' | null
      remarks: string | null
      log_id: number | null
    }
  }>
  attendancePagination?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  } | null
  wellnessSchedules: Array<{
    id: number
    title: string
    type: string
    venue: string
    start: string
    end: string
  }>
  selectedWellnessScheduleId: number | null
  wellnessAthletes: Array<{
    student_id: number
    student_id_number: string | null
    name: string
    attendance_status: string
    wellness: {
      injury_observed: boolean
      injury_notes: string | null
      fatigue_level: number | null
      performance_condition: 'excellent' | 'good' | 'fair' | 'poor' | null
      remarks: string | null
      log_id: number | null
    }
  }>
}>()
</script>

<template>
  <Head title="Coach Attendance" />

  <div class="space-y-4">
    <AttendanceRecord
      mode="index"
      :teams="teams"
      :selected-team-id="selectedTeamId"
      :team="team"
      :schedules="attendanceSchedules"
      :selected-schedule-id="selectedAttendanceScheduleId"
      :attendance-rows="attendanceRows"
      :attendance-pagination="attendancePagination ?? null"
    />
  </div>
</template>
