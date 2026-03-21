@extends('print.layout')

@section('title', 'Academic Submissions & Eligibility')

@section('meta')
    <div>Student: {{ $student['name'] }}</div>
    <div>Student ID: {{ $student['student_id_number'] ?? '-' }}</div>
    <div>Generated: {{ $generatedAt }}</div>
@endsection

@section('content')
    <div class="section-title">Eligibility Summary</div>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Status</th>
                <th>Submission Window</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periods as $period)
                <tr>
                    <td>{{ $period['label'] }}</td>
                    <td>{{ $period['eligibility_status'] ?? 'Pending' }}</td>
                    <td>{{ $period['window'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">No academic periods available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Submission History</div>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Document</th>
                <th>Uploaded</th>
                <th>Status</th>
                <th>GPA</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($submissions as $row)
                <tr>
                    <td>{{ $row['period_label'] ?? '-' }}</td>
                    <td>{{ $row['document_type'] }}</td>
                    <td class="nowrap">{{ $row['uploaded_at'] ?? '-' }}</td>
                    <td>{{ $row['status'] ?? 'Pending' }}</td>
                    <td>{{ $row['gpa'] ?? '-' }}</td>
                    <td>{{ $row['remarks'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No submissions found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
