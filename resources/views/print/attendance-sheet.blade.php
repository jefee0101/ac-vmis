@extends('print.layout')

@section('title', 'Attendance Sheet')

@section('meta')
    <div>{{ $modeLabel }}</div>
    <div>Team: {{ $team['team_name'] }}</div>
    <div>Schedule: {{ $schedule['title'] }}</div>
    <div>Date: {{ $schedule['start'] }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="section-title">Schedule Details</div>
    <table>
        <tbody>
            <tr>
                <th>Type</th>
                <td>{{ $schedule['type'] }}</td>
                <th>Venue</th>
                <td>{{ $schedule['venue'] }}</td>
            </tr>
            <tr>
                <th>Start</th>
                <td>{{ $schedule['start'] }}</td>
                <th>End</th>
                <td>{{ $schedule['end'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Roster</div>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Jersey</th>
                <th>Position</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="nowrap">{{ $row['student_id_number'] ?? '-' }}</td>
                    <td>{{ $row['jersey_number'] ?? '-' }}</td>
                    <td>{{ $row['athlete_position'] ?? '-' }}</td>
                    <td>{{ $row['status'] ?? '-' }}</td>
                    <td>{{ $row['notes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No roster entries available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
