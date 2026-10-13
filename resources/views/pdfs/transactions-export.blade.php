<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions Export - Dian-ay National High School</title>
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

        /* Export Card */
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
                <div class="report-title">Transactions Export — {{ ucfirst($tab) }}</div>
                <div class="report-date">
                    Filters: <strong>{{ $filterSummary }}</strong>
                    &nbsp;|&nbsp; Generated: {{ $generatedAt }}
                </div>
            </td>
            <td style="width: 38%;" class="doc-tag">
                <div><strong>Document Code:</strong> DNHS-IMS-TX-{{ now()->format('Ymd') }}</div>
                <div><strong>Prepared by:</strong> {{ $preparedBy }}</div>
            </td>
        </tr>
    </table>

    @if ($tab === 'requests')
    <div class="card">
        <div class="card-title">Requests <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 20%;">Requester</th>
                    <th style="width: 26%;">Item Requested</th>
                    <th style="width: 18%;">Category</th>
                    <th style="width: 7%;" class="num">Qty</th>
                    <th style="width: 15%;">Stock Health</th>
                    <th style="width: 14%;">Requested On</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td><strong>{{ $row['requester'] }}</strong></td>
                        <td>
                            <strong>{{ $row['item'] }}</strong>
                            @if (! empty($row['item_no']))<div>{{ $row['item_no'] }}</div>@endif
                        </td>
                        <td>{{ $row['category'] }}</td>
                        <td class="num">{{ number_format($row['qty']) }}</td>
                        <td>{{ $row['stock'] }}</td>
                        <td>{{ $row['requested_at'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @elseif ($tab === 'transfers')
    <div class="card">
        <div class="card-title">Transfers <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 30%;">Transfer Flow</th>
                    <th style="width: 36%;">Item Details</th>
                    <th style="width: 10%;" class="num">Qty</th>
                    <th style="width: 24%;">Requested At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td><strong>{{ $row['flow'] }}</strong></td>
                        <td>
                            <strong>{{ $row['item'] }}</strong>
                            @if (! empty($row['item_no']))<div>{{ $row['item_no'] }}</div>@endif
                        </td>
                        <td class="num">{{ number_format($row['qty']) }}</td>
                        <td>{{ $row['requested_at'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @elseif ($tab === 'returns')
    <div class="card">
        <div class="card-title">Returns <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 26%;">End User</th>
                    <th style="width: 38%;">Item to Return</th>
                    <th style="width: 10%;" class="num">Qty</th>
                    <th style="width: 26%;">Requested At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td><strong>{{ $row['user'] }}</strong></td>
                        <td>
                            <strong>{{ $row['item'] }}</strong>
                            @if (! empty($row['item_no']))<div>{{ $row['item_no'] }}</div>@endif
                        </td>
                        <td class="num">{{ number_format($row['qty']) }}</td>
                        <td>{{ $row['requested_at'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @elseif ($tab === 'history')
    <div class="card">
        <div class="card-title">Transaction History <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 7%;" class="num">Qty</th>
                    <th style="width: 23%;">Item</th>
                    <th style="width: 18%;">From</th>
                    <th style="width: 18%;">To</th>
                    <th style="width: 18%;">Transaction Date</th>
                    <th style="width: 16%;">Activity Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td class="num"><strong>{{ number_format($row['qty']) }}</strong></td>
                        <td>
                            <strong>{{ $row['item'] }}</strong>
                            @if (! empty($row['item_no']))<div>{{ $row['item_no'] }}</div>@endif
                        </td>
                        <td>{{ $row['from'] }}</td>
                        <td>
                            <strong>{{ $row['to'] }}</strong>
                            @if (! empty($row['dept']))<div>{{ $row['dept'] }}</div>@endif
                        </td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', (string) $row['status'])) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @else
    <div class="card">
        <div class="card-title">Audit Ledger <span class="sub">{{ $rows->count() }} record(s)</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 12%;">Timestamp</th>
                    <th style="width: 14%;">Item</th>
                    <th style="width: 10%;">Movement Type</th>
                    <th style="width: 7%;" class="num">Qty Diff</th>
                    <th style="width: 7%;" class="num">Before</th>
                    <th style="width: 7%;" class="num">After</th>
                    <th style="width: 13%;">Authorized By</th>
                    <th style="width: 30%;">Movement Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['at'] }}</td>
                        <td>
                            <strong>{{ $row['item'] }}</strong>
                            @if (! empty($row['item_no']))<div>{{ $row['item_no'] }}</div>@endif
                        </td>
                        <td>{{ ucwords(str_replace('_', ' ', (string) $row['type'])) }}</td>
                        <td class="num"><strong>{{ $row['diff'] > 0 ? '+'.$row['diff'] : $row['diff'] }}</strong></td>
                        <td class="num">{{ $row['before'] }}</td>
                        <td class="num">{{ $row['after'] }}</td>
                        <td>{{ $row['actor'] }}</td>
                        <td>{{ $row['details'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Signature Blocks --}}
    <table class="signatures">
        <tr>
            <td style="padding-right: 24px;">
                <div class="sign-line"></div>
                <div class="sign-role">Property Custodian</div>
                <div class="sign-meta">Prepared by &nbsp;&middot;&nbsp; {{ $preparedBy }}</div>
            </td>
            <td style="padding-left: 24px;">
                <div class="sign-line"></div>
                <div class="sign-role">School Head</div>
                <div class="sign-meta">Reviewed and approved</div>
            </td>
        </tr>
    </table>
</body>
</html>
