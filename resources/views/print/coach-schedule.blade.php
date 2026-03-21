@extends('print.layout')

@section('title', 'Coach Schedule List')

@section('meta')
    <div>Team: {{ $team['team_name'] }}</div>
    <div>Sport: {{ $team['sport'] }}</div>
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
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($schedules as $schedule)
                <tr>
                    <td>{{ $schedule['title'] }}</td>
                    <td>{{ $schedule['type'] }}</td>
                    <td>{{ $schedule['venue'] }}</td>
                    <td class="nowrap">{{ $schedule['start'] }}</td>
                    <td class="nowrap">{{ $schedule['end'] }}</td>
                    <td>{{ $schedule['notes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No schedules for the selected range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
