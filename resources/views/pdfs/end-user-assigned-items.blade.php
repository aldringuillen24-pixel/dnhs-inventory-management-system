<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Assigned Items - Dian-ay National High School</title>
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
            letter-spacing: 0.6px;
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
            letter-spacing: 0.4px;
            margin-top: 2px;
        }

        /* Metadata Banner */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .meta-table td {
            border: none;
            vertical-align: top;
        }
        .report-title {
            font-size: 11.5px;
            font-weight: bold;
            color: #115e59;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-date {
            font-size: 8.5px;
            color: #4b5563;
            margin-top: 2px;
        }
        .doc-tag {
            font-size: 8.5px;
            color: #0f766e;
            text-align: right;
        }

        /* Items Card */
        .card {
            border: 1px solid #d1d5db;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .card-title {
            background: #f9fafb;
            border-bottom: 1px solid #d1d5db;
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #111827;
        }
        .card-title .sub {
            font-weight: normal;
            text-transform: none;
            letter-spacing: 0;
            color: #6b7280;
        }
        .card table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        .card table.grid th {
            background: #0f766e;
            color: #ffffff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            text-align: left;
            border: 1px solid #0b5f58;
        }
        .card table.grid td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            font-size: 8.5px;
            vertical-align: top;
        }
        .card table.grid td.num {
            text-align: right;
            white-space: nowrap;
        }
        .card table.grid tr.alt td {
            background: #f9fafb;
        }
        .card .empty {
            padding: 10px 8px;
            text-align: center;
            color: #6b7280;
            font-style: italic;
            font-size: 8.5px;
        }

        /* Signature Blocks */
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }
        .signatures td {
            border: none;
            width: 50%;
            vertical-align: bottom;
        }
        .sign-line {
            border-bottom: 1px solid #111827;
            height: 30px;
        }
        .sign-role {
            font-size: 8.5px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 2px;
        }
        .sign-meta {
            font-size: 7.5px;
            color: #6b7280;
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
                <div class="system-tag">Property &amp; Inventory Management System (IMS)</div>
            </td>
        </tr>
    </table>

    {{-- Metadata Banner --}}
    <table class="meta-table">
        <tr>
            <td style="width: 62%;">
                <div class="report-title">My Assigned Items</div>
                <div class="report-date">
                    Filters: <strong>{{ $filterSummary }}</strong>
                    &nbsp;|&nbsp; Generated: {{ $generatedAt }}
                </div>
            </td>
            <td style="width: 38%;" class="doc-tag">
                <div><strong>Document Code:</strong> DNHS-IMS-ASG-{{ now()->format('Ymd') }}</div>
                <div><strong>Holder:</strong> {{ $holder }}</div>
            </td>
        </tr>
    </table>

    {{-- Assigned Items --}}
    <div class="card">
        <div class="card-title">Items in Care <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 14%;">Type</th>
                    <th style="width: 34%;">Item</th>
                    <th style="width: 8%;" class="num">Qty</th>
                    <th style="width: 20%;">Date</th>
                    <th style="width: 24%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['type'] ?? '—' }}</td>
                        <td><strong>{{ $row['item_name'] ?? 'Unknown item' }}</strong></td>
                        <td class="num">{{ $row['quantity'] ?? 0 }}</td>
                        <td>{{ isset($row['date']) && $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('M d, Y') : '—' }}</td>
                        <td>{{ $row['status'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Signature Blocks --}}
    <table class="signatures">
        <tr>
            <td style="padding-right: 24px;">
                <div class="sign-line"></div>
                <div class="sign-role">End User</div>
                <div class="sign-meta">Issued to &nbsp;&middot;&nbsp; {{ $holder }}</div>
            </td>
            <td style="padding-left: 24px;">
                <div class="sign-line"></div>
                <div class="sign-role">Property Custodian</div>
                <div class="sign-meta">Reviewed and approved</div>
            </td>
        </tr>
    </table>
</body>
</html>
