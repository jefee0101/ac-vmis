<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 28px; }
        h1, h2, p { margin: 0; }
        .header { margin-bottom: 20px; }
        .subtle { color: #475569; font-size: 12px; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 18px 0; }
        .card { border: 1px solid rgba(3, 68, 133, 0.45); padding: 12px; border-radius: 8px; }
        .card .label { font-size: 11px; text-transform: uppercase; color: #64748b; }
        .card .value { font-size: 22px; font-weight: bold; color: #034485; margin-top: 6px; }
        .filters { margin: 18px 0; border: 1px solid rgba(3, 68, 133, 0.45); border-radius: 8px; padding: 12px; }
        .filters-row { display: flex; gap: 18px; flex-wrap: wrap; font-size: 12px; color: #334155; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; color: #475569; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Summary Report</h1>
        <p class="subtle">Generated {{ $generatedAt }}</p>
    </div>

    <div class="filters">
        <div class="filters-row">
            <div><strong>Team:</strong> {{ $filtersSummary['team'] }}</div>
            <div><strong>Status:</strong> {{ $filtersSummary['status'] }}</div>
            <div><strong>Date Range:</strong> {{ $filtersSummary['date_range'] }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="card">
            <div class="label">Attendance Rate</div>
            <div class="value">{{ number_format((float) ($summary['attendance_rate'] ?? 0), 2) }}%</div>
        </div>
        <div class="card">
            <div class="label">Present</div>
            <div class="value">{{ $summary['present'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Absent</div>
            <div class="value">{{ $summary['absent'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Late</div>
            <div class="value">{{ $summary['late'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Excused</div>
            <div class="value">{{ $summary['excused'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">No Response</div>
            <div class="value">{{ $summary['no_response'] ?? 0 }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Team</th>
                <th>Sport</th>
                <th class="right">Sessions</th>
                <th class="right">Present</th>
                <th class="right">Absent</th>
                <th class="right">Late</th>
                <th class="right">Excused</th>
                <th class="right">No Response</th>
                <th class="right">Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['student_id_number'] ?? 'No student ID' }}</td>
                    <td>{{ $row['team_name'] }}</td>
                    <td>{{ $row['sport_name'] }}</td>
                    <td class="right">{{ $row['total_sessions'] }}</td>
                    <td class="right">{{ $row['present'] }}</td>
                    <td class="right">{{ $row['absent'] }}</td>
                    <td class="right">{{ $row['late'] }}</td>
                    <td class="right">{{ $row['excused'] }}</td>
                    <td class="right">{{ $row['no_response'] }}</td>
                    <td class="right">{{ number_format((float) $row['attendance_rate'], 2) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">No attendance data found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
