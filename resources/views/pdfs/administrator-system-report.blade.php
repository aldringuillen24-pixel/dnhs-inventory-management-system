<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Administrator System Audit Report - Dian-ay National High School</title>
    <style>
        @page {
            margin: 28px 32px 32px 32px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            color: #1f2937;
            line-height: 1.4;
        }

        /* Institutional Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #0f766e;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-table td {
            text-align: center;
            border: none;
            padding: 0;
        }
        .republic {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #4b5563;
        }
        .deped {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #111827;
            margin-top: 1px;
        }
        .region {
            font-size: 8px;
            color: #4b5563;
            margin-top: 1px;
        }
        .school-name {
            font-size: 13.5px;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: 0.6px;
            margin-top: 3px;
        }
        .system-tag {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Document Metadata Banner */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 7px 10px;
            border: none;
            vertical-align: middle;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .report-date {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        .doc-tag {
            font-size: 8px;
            color: #334155;
            text-align: right;
        }

        /* Metrics Summary Strip */
        .metrics-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 16px;
        }
        .metric-cell {
            padding: 8px 10px;
            border-radius: 4px;
            vertical-align: top;
        }
        .metric-active {
            background-color: #eef2ff;
            border-left: 3.5px solid #6366f1;
        }
        .metric-pending {
            background-color: #fffbeb;
            border-left: 3.5px solid #f59e0b;
        }
        .metric-requests {
            background-color: #f0f9ff;
            border-left: 3.5px solid #0284c7;
        }
        .metric-maintenance {
            background-color: #fff1f2;
            border-left: 3.5px solid #f43f5e;
        }
        .metric-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .metric-value {
            font-size: 15px;
            font-weight: bold;
            display: block;
            margin-top: 3px;
        }
        .metric-sub {
            font-size: 7px;
            display: block;
            margin-top: 2px;
        }

        /* Headings & Section Titles */
        h2 {
            margin: 16px 0 6px;
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #cbd5e1;
        }

        /* Data Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.data-table th, 
        table.data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 8.5px;
        }
        table.data-table th {
            background: #f1f5f9;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #475569;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1.5px solid #94a3b8;
        }
        table.data-table tr.even {
            background-color: #f8fafc;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            font-size: 7.5px;
            border-radius: 3px;
            font-weight: bold;
        }
        .badge-emerald { background: #dcfce7; color: #15803d; }
        .badge-purple  { background: #f3e8ff; color: #7e22ce; }
        .badge-amber   { background: #fef3c7; color: #b45309; }
        .badge-gray    { background: #f1f5f9; color: #475569; }

        /* Signatory Block */
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .signatory-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .signatory-name {
            margin-top: 36px;
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
            text-decoration: underline;
        }
        .signatory-title {
            font-size: 8px;
            color: #64748b;
            margin-top: 1px;
        }
        .signatory-inst {
            font-size: 7.5px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    {{-- Institutional Letterhead --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="republic">Republic of the Philippines</div>
                <div class="deped">Department of Education</div>
                <div class="region">Negros Island Region (NIR)</div>
                <div class="school-name">DIAN-AY NATIONAL HIGH SCHOOL</div>
                <div class="system-tag">Property & Inventory Management System (IMS)</div>
            </td>
        </tr>
    </table>

    {{-- Metadata Banner --}}
    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="report-title">Administrator System Audit Report</div>
                <div class="report-date">Generated on: {{ now()->format('F d, Y - h:i A') }}</div>
            </td>
            <td style="width: 40%;" class="doc-tag">
                <div><strong>Document Code:</strong> DNHS-IMS-SYS-{{ now()->format('Ymd') }}</div>
                <div><strong>Report Scope:</strong> System Access, Workflows & Audit Ledger</div>
            </td>
        </tr>
    </table>

    {{-- System KPI Summary Boxes --}}
    <table class="metrics-table">
        <tr>
            <td class="metric-cell metric-active" style="width: 25%;">
                <span class="metric-label" style="color: #4338ca;">Active Accounts</span>
                <span class="metric-value" style="color: #1e1b4b;">{{ number_format($metrics['activeUsers']) }}</span>
                <span class="metric-sub" style="color: #6366f1;">Verified & Enabled</span>
            </td>
            <td class="metric-cell metric-pending" style="width: 25%;">
                <span class="metric-label" style="color: #b45309;">Pending Setup</span>
                <span class="metric-value" style="color: #78350f;">{{ number_format($metrics['pendingOnboarding']) }}</span>
                <span class="metric-sub" style="color: #d97706;">Awaiting Onboarding</span>
            </td>
            <td class="metric-cell metric-requests" style="width: 25%;">
                <span class="metric-label" style="color: #0369a1;">Pending Requests</span>
                <span class="metric-value" style="color: #082f49;">{{ number_format($metrics['pendingRequests']) }}</span>
                <span class="metric-sub" style="color: #0284c7;">Awaiting Custodian</span>
            </td>
            <td class="metric-cell metric-maintenance" style="width: 25%;">
                <span class="metric-label" style="color: #be123c;">Active Maintenance</span>
                <span class="metric-value" style="color: #881337;">{{ number_format($metrics['openMaintenance']) }}</span>
                <span class="metric-sub" style="color: #e11d48;">Units Under Repair</span>
            </td>
        </tr>
    </table>

    {{-- Users by Role --}}
    <h2>1. User Accounts by System Role</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70%;">Assigned System Role</th>
                <th class="right" style="width: 30%;">Registered Accounts</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roleData as $index => $role)
                <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                    <td><strong>{{ $role['label'] }}</strong></td>
                    <td class="right font-bold">{{ number_format($role['value']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="center" style="color: #94a3b8; padding: 12px;">No user role records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Workflow Statuses --}}
    <h2>2. Workflow & Maintenance Status Overview</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35%;">Functional Area</th>
                <th style="width: 45%;">Workflow Status</th>
                <th class="right" style="width: 20%;">Record Count</th>
            </tr>
        </thead>
        <tbody>
            @php $rowCount = 0; @endphp
            @foreach($requestStatusData as $status)
                <tr class="{{ $rowCount % 2 === 1 ? 'even' : '' }}">
                    <td>Assignment Request</td>
                    <td>{{ $status['label'] }}</td>
                    <td class="right font-bold">{{ number_format($status['value']) }}</td>
                </tr>
                @php $rowCount++; @endphp
            @endforeach
            @foreach($maintenanceStatusData as $status)
                <tr class="{{ $rowCount % 2 === 1 ? 'even' : '' }}">
                    <td>Equipment Maintenance</td>
                    <td>{{ $status['label'] }}</td>
                    <td class="right font-bold">{{ number_format($status['value']) }}</td>
                </tr>
                @php $rowCount++; @endphp
            @endforeach
        </tbody>
    </table>

    {{-- Recent System Audit Activity --}}
    <h2>3. Recent System Audit Activity Trail</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30%;">Action Performed</th>
                <th style="width: 25%;">Actor / Initiator</th>
                <th style="width: 25%;">Target Account / Entity</th>
                <th class="right" style="width: 20%;">Date & Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentActivity as $index => $activity)
                @php
                    $actLower = strtolower($activity->action);
                    $badgeClass = 'badge-gray';
                    if (str_contains($actLower, 'create') || str_contains($actLower, 'generate')) {
                        $badgeClass = 'badge-emerald';
                    } elseif (str_contains($actLower, 'role')) {
                        $badgeClass = 'badge-purple';
                    } elseif (str_contains($actLower, 'status') || str_contains($actLower, 'deactivate')) {
                        $badgeClass = 'badge-amber';
                    }
                @endphp
                <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</span>
                    </td>
                    <td>{{ $activity->actor_name ?: 'System' }}</td>
                    <td>{{ $activity->target_name ?: '—' }}</td>
                    <td class="right">{{ $activity->created_at?->format('M d, Y H:i') ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="center" style="color: #94a3b8; padding: 12px;">No system audit logs recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Institutional Signatory Blocks --}}
    <table class="signatory-table">
        <tr>
            <td style="width: 50%;">
                <div style="font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Prepared by:</div>
                <div class="signatory-name">
                    {{ auth()->user() ? strtoupper(auth()->user()->first_name . ' ' . auth()->user()->last_name) : 'SYSTEM ADMINISTRATOR' }}
                </div>
                <div class="signatory-title">System Administrator / ICT Coordinator</div>
                <div class="signatory-inst">Dian-ay National High School (NIR)</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div style="font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Noted & Approved by:</div>
                <div class="signatory-name">
                    SCHOOL HEAD / PRINCIPAL
                </div>
                <div class="signatory-title">School Principal</div>
                <div class="signatory-inst">Dian-ay National High School (NIR)</div>
            </td>
        </tr>
    </table>
</body>
</html>