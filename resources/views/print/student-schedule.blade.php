@extends('print.layout')

@section('title', 'Personal Schedule & Attendance History')

@section('meta')
    <div>Student: {{ $student['name'] }}</div>
    <div>Student ID: {{ $student['student_id_number'] ?? '-' }}</div>
    <div>Team: {{ $team['team_name'] }}</div>
    <div>Date Range: {{ $rangeLabel }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="section-title">Schedules</div>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Venue</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($schedules as $row)
                <tr>
                    <td>{{ $row['title'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['venue'] }}</td>
                    <td class="nowrap">{{ $row['start'] }}</td>
                    <td class="nowrap">{{ $row['end'] }}</td>
                    <td>{{ $row['attendance_status'] ?? 'No Response' }}</td>
                    <td>{{ $row['attendance_notes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No schedules available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
