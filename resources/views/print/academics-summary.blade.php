@extends('print.layout')

@section('title', 'Academic Eligibility Summary')

@section('meta')
    <div>Term: {{ $periodLabel }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="kpis">
        <div class="kpi"><span class="muted">Eligible</span><strong>{{ $counts['eligible'] }}</strong></div>
        <div class="kpi"><span class="muted">Probation</span><strong>{{ $counts['probation'] }}</strong></div>
        <div class="kpi"><span class="muted">Ineligible</span><strong>{{ $counts['ineligible'] }}</strong></div>
        <div class="kpi"><span class="muted">Pending</span><strong>{{ $counts['pending'] }}</strong></div>
        <div class="kpi"><span class="muted">Total</span><strong>{{ $counts['total'] }}</strong></div>
    </div>

    <div class="section-title">Student Eligibility</div>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Document</th>
                <th>Uploaded</th>
                <th>Status</th>
                <th>GPA</th>
                <th>Evaluated</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['student_name'] }}</td>
                    <td class="nowrap">{{ $row['student_id_number'] ?? '-' }}</td>
                    <td>{{ $row['document_type'] }}</td>
                    <td class="nowrap">{{ $row['uploaded_at'] ?? '-' }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="nowrap">{{ $row['gpa'] ?? '-' }}</td>
                    <td class="nowrap">{{ $row['evaluated_at'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No academic submissions for this term.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
