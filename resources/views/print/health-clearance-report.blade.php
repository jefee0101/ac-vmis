<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Clearance Status Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 28px; }
        h1, p { margin: 0; }
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Health Clearance Status Report</h1>
        <p class="subtle">Generated {{ $generatedAt }}</p>
    </div>

    <div class="filters">
        <div class="filters-row">
            <div><strong>Team:</strong> {{ $filtersSummary['team'] }}</div>
            <div><strong>Clearance Status:</strong> {{ $filtersSummary['clearance_status'] }}</div>
            <div><strong>Review State:</strong> {{ $filtersSummary['review_state'] }}</div>
            <div><strong>Date Range:</strong> {{ $filtersSummary['date_range'] }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="card">
            <div class="label">Total Records</div>
            <div class="value">{{ $summary['total_records'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Fit</div>
            <div class="value">{{ $summary['fit'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Fit w/ Restrictions</div>
            <div class="value">{{ $summary['fit_with_restrictions'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Not Fit</div>
            <div class="value">{{ $summary['not_fit'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Expired</div>
            <div class="value">{{ $summary['expired'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Reviewed</div>
            <div class="value">{{ $summary['reviewed'] ?? 0 }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Team</th>
                <th>Clearance Date</th>
                <th>Valid Until</th>
                <th>Physician</th>
                <th>Status</th>
                <th>Review State</th>
                <th>Reviewed By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['student_id_number'] ?? 'No student ID' }}</td>
                    <td>{{ $row['team_name'] }}</td>
                    <td>{{ $row['clearance_date'] ?? 'N/A' }}</td>
                    <td>{{ $row['valid_until'] ?? 'N/A' }}</td>
                    <td>{{ $row['physician_name'] }}</td>
                    <td>{{ $row['clearance_status'] }}</td>
                    <td>{{ $row['review_state'] }}</td>
                    <td>{{ $row['reviewed_by'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No health clearance data found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
