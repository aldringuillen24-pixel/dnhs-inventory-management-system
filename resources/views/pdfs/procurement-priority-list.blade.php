<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement Priority List - Dian-ay National High School</title>
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
        .question-line {
            font-size: 9px;
            color: #111827;
            margin-top: 3px;
        }

        /* Recommendation Table */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.items th {
            background: #0f766e;
            color: #ffffff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 4px;
            text-align: left;
            border: 1px solid #0b5f58;
        }
        table.items td {
            border: 1px solid #d1d5db;
            padding: 5px 4px;
            font-size: 8.5px;
            vertical-align: top;
        }
        table.items td.num {
            text-align: right;
            white-space: nowrap;
        }
        table.items tr.alt td {
            background: #f9fafb;
        }
        .basis {
            font-size: 8px;
            color: #4b5563;
        }
        .none-cell {
            padding: 14px 4px;
            text-align: center;
            color: #6b7280;
            font-style: italic;
        }

        /* Priority badges */
        .pill {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #ffffff;
        }
        .pill-urgent { background: #b91c1c; }
        .pill-high { background: #c2410c; }
        .pill-medium { background: #b45309; }
        .pill-normal { background: #4b5563; }

        /* Totals */
        table.totals {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.totals td {
            border: 1px solid #0f766e;
            padding: 5px 8px;
            font-size: 9px;
            background: #f0fdfa;
            color: #115e59;
        }
        table.totals td.right {
            text-align: right;
            font-weight: bold;
        }

        /* Disclaimer */
        .disclaimer {
            margin-top: 10px;
            border-left: 2.5px solid #b45309;
            background: #fffbeb;
            padding: 6px 9px;
            font-size: 8px;
            color: #78350f;
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
                <div class="report-title">Procurement Priority List</div>
                <div class="report-date">
                    Forecast period: <strong>{{ $forecastPeriod }}</strong>
                    @if ($generatedAt)
                        &nbsp;|&nbsp; Model generated: {{ $generatedAt }}
                    @endif
                </div>
                <div class="question-line"><strong>Decision question:</strong> {{ $question }}</div>
            </td>
            <td style="width: 38%;" class="doc-tag">
                <div><strong>Document Code:</strong> DNHS-IMS-PROC-{{ now()->format('Ymd') }}</div>
                <div><strong>Prepared by:</strong> {{ $preparedBy }}</div>
                <div><strong>Source:</strong> ML demand forecast (advisory)</div>
            </td>
        </tr>
    </table>

    {{-- Recommendation Rows --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 22%;">Item</th>
                <th style="width: 13%;">Category</th>
                <th style="width: 9%; text-align: right;">Demand</th>
                <th style="width: 9%; text-align: right;">Buffer</th>
                <th style="width: 9%; text-align: right;">Suggested</th>
                <th style="width: 8%;">Priority</th>
                <th style="width: 26%;">Basis</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td class="num">{{ $index + 1 }}</td>
                    <td>
                        {{ $item['item_name'] ?? 'Item' }}
                        <div class="basis">ID {{ $item['inventory_id'] ?? '—' }}</div>
                    </td>
                    <td>{{ $item['category'] ?? '—' }}</td>
                    <td class="num">{{ $item['forecast_demand'] ?? 'N/A' }} {{ $item['unit'] ?? '' }}</td>
                    <td class="num">{{ $item['safety_stock'] ?? 'N/A' }} {{ $item['unit'] ?? '' }}</td>
                    <td class="num"><strong>{{ $item['suggested_procurement'] ?? 'N/A' }} {{ $item['unit'] ?? '' }}</strong></td>
                    <td>
                        <span class="pill pill-{{ strtolower($item['priority'] ?? 'normal') }}">{{ $item['priority'] ?? '—' }}</span>
                    </td>
                    <td class="basis">
                        @if (($item['status'] ?? null) !== 'success')
                            Insufficient verified history; no ML estimate available.
                        @else
                            Demand {{ $item['forecast_demand'] }} + buffer {{ $item['safety_stock'] }}
                            − stock {{ $item['available_stock'] }} − pending {{ $item['pending_demand'] }};
                            {{ $item['advisory_status'] ?? '—' }} ({{ $item['confidence'] ?? '—' }} confidence).
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="none-cell">
                        No items were selected for this procurement list. Refresh model training or ask again.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td style="width: 70%;">
                <strong>Total items listed:</strong> {{ $itemCount }}
                &nbsp;|&nbsp; <strong>Total units suggested:</strong> {{ $totalUnits }}
            </td>
            <td class="right">Advisory only</td>
        </tr>
    </table>

    <div class="disclaimer">
        <strong>Note:</strong> This list is generated from the machine learning demand forecast and is
        advisory only. It does not create, approve, or place purchase orders and does not change inventory
        records. Quantities must be verified against current stock levels and approved through the
        Property Custodian and School Head workflow before any procurement is made.
    </div>

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
