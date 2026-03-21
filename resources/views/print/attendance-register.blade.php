@extends('print.layout')

@section('title', 'Attendance Register')

@section('meta')
    <div>Date Range: {{ $rangeLabel }}</div>
    <div>Team: {{ $filtersSummary['team'] }}</div>
    <div>Schedule: {{ $filtersSummary['schedule'] }}</div>
    <div>Status: {{ $filtersSummary['status'] }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="kpis">
        <div class="kpi"><span class="muted">Present</span><strong>{{ $counts['present'] }}</strong></div>
        <div class="kpi"><span class="muted">Late</span><strong>{{ $counts['late'] }}</strong></div>
        <div class="kpi"><span class="muted">Absent</span><strong>{{ $counts['absent'] }}</strong></div>
        <div class="kpi"><span class="muted">Excused</span><strong>{{ $counts['excused'] }}</strong></div>
        <div class="kpi"><span class="muted">No Response</span><strong>{{ $counts['no_response'] }}</strong></div>
        <div class="kpi"><span class="muted">Total</span><strong>{{ $counts['total_records'] }}</strong></div>
    </div>

    <div class="section-title">Records</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Schedule</th>
                <th>Type</th>
                <th>Team</th>
                <th>Sport</th>
                <th>Student</th>
                <th>Status</th>
                <th>Recorded At</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $row)
                <tr>
                    <td class="nowrap">{{ $row['schedule_date'] }}</td>
                    <td>{{ $row['schedule_title'] }}</td>
                    <td>{{ $row['schedule_type'] }}</td>
                    <td>{{ $row['team_name'] }}</td>
                    <td>{{ $row['sport_name'] }}</td>
                    <td>{{ $row['student_name'] }} ({{ $row['student_id_number'] ?? '-' }})</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="nowrap">{{ $row['recorded_at'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">No attendance records found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
