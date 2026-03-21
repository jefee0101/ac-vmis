<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Report')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #ffffff;
        }
        .report {
            max-width: 980px;
            margin: 0 auto;
        }
        .report-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .report-title {
            font-size: 20px;
            font-weight: 700;
        }
        .report-meta {
            font-size: 12px;
            color: #475569;
            text-align: right;
        }
        .section-title {
            margin: 16px 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .kpi {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            font-size: 12px;
        }
        .kpi strong { display: block; font-size: 16px; color: #0f172a; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            text-align: left;
        }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .nowrap { white-space: nowrap; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="report">
    <header class="report-header">
        <div class="report-title">@yield('title')</div>
        <div class="report-meta">
            @yield('meta')
        </div>
    </header>

    @yield('content')
</div>
</body>
</html>
