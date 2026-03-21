@extends('print.layout')

@section('title', 'Team Roster')

@section('meta')
    <div>{{ $contextLabel }}</div>
    <div>Team: {{ $team['team_name'] }}</div>
    <div>Sport: {{ $team['sport'] }}</div>
    <div>Season: {{ $team['year'] ?? 'N/A' }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="section-title">Staff Assignment</div>
    <table>
        <thead>
            <tr>
                <th>Head Coach</th>
                <th>Assistant Coach</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $team['coach'] ?? 'Unassigned' }}</td>
                <td>{{ $team['assistant'] ?? 'Unassigned' }}</td>
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
            </tr>
        </thead>
        <tbody>
            @forelse ($players as $player)
                <tr>
                    <td>{{ $player['name'] }}</td>
                    <td class="nowrap">{{ $player['student_id_number'] ?? '-' }}</td>
                    <td>{{ $player['jersey_number'] ?? '-' }}</td>
                    <td>{{ $player['athlete_position'] ?? '-' }}</td>
                    <td>{{ $player['player_status'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No players assigned.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
