<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Custodian Reports - Dian-ay National High School</title>
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

        /* Cards: one bordered block per section that never splits pages */
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
        .basis {
            font-size: 8px;
            color: #4b5563;
        }

        /* Metric tiles */
        table.metrics {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        table.metrics td {
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            padding: 7px 9px;
            width: 25%;
            vertical-align: top;
        }
        .metric-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f766e;
        }
        .metric-value {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-top: 1px;
        }
        .metric-sub {
            font-size: 7.5px;
            color: #6b7280;
        }

        /* Tone pills */
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
        .pill-expired { background: #b91c1c; }
        .pill-approaching { background: #b45309; }
        .pill-healthy { background: #047857; }

        /* Forecast bridge */
        .bridge {
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            padding: 8px 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .bridge .bridge-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #115e59;
        }
        .bridge .bridge-text {
            font-size: 8.5px;
            color: #1f2937;
            margin-top: 2px;
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
                <div class="report-title">Property Custodian Reports</div>
                <div class="report-date">
                    Period: <strong>{{ $periodLabel }}</strong>
                    &nbsp;|&nbsp; Generated: {{ $generatedAt }}
                </div>
            </td>
            <td style="width: 38%;" class="doc-tag">
                <div><strong>Document Code:</strong> DNHS-IMS-REPT-{{ now()->format('Ymd') }}</div>
                <div><strong>Prepared by:</strong> {{ $preparedBy }}</div>
            </td>
        </tr>
    </table>

    {{-- Metric Tiles --}}
    <table class="metrics">
        <tr>
            <td>
                <div class="metric-label">Total Units</div>
                <div class="metric-value">{{ number_format($metrics['totalUnits'] ?? 0) }}</div>
                <div class="metric-sub">Non-disposed stockroom units</div>
            </td>
            <td>
                <div class="metric-label">Available Stock</div>
                <div class="metric-value">{{ number_format($metrics['availableUnits'] ?? 0) }}</div>
                <div class="metric-sub">Ready for immediate issuance</div>
            </td>
            <td>
                <div class="metric-label">Assigned Units</div>
                <div class="metric-value">{{ number_format($metrics['assignedUnits'] ?? 0) }}</div>
                <div class="metric-sub">In end-user care</div>
            </td>
            <td>
                <div class="metric-label">Stockroom Value</div>
                <div class="metric-value">PHP {{ number_format((float) ($metrics['totalValue'] ?? 0), 2) }}</div>
                <div class="metric-sub">Total inventory capital</div>
            </td>
        </tr>
    </table>

    {{-- Movement Trend --}}
    <div class="card">
        <div class="card-title">Stock Movement Trend <span class="sub">{{ $periodLabel }}</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 25%;">Date</th>
                    <th style="width: 25%;" class="num">Stock In</th>
                    <th style="width: 25%;" class="num">Stock Out</th>
                    <th style="width: 25%;" class="num">Disposals</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movementData as $index => $day)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $day['label'] ?? '—' }}</td>
                        <td class="num">{{ number_format($day['stock_in'] ?? 0) }}</td>
                        <td class="num">{{ number_format($day['stock_out'] ?? 0) }}</td>
                        <td class="num">{{ number_format($day['disposals'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No stock movements recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Category + Status --}}
    <div class="card">
        <div class="card-title">Stock by Category <span class="sub">Units and capital per category</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 50%;">Category</th>
                    <th style="width: 25%;" class="num">Units</th>
                    <th style="width: 25%;" class="num">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categoryData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['label'] ?? 'Uncategorized' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                        <td class="num">PHP {{ number_format((float) ($row['value'] ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No category data recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-title">Status Breakdown <span class="sub">Units by physical and assignment state</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 60%;">Status</th>
                    <th style="width: 40%;" class="num">Units</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statusData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['label'] ?? '—' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No status data recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Low Stock --}}
    <div class="card">
        <div class="card-title">Low Stock Groups <span class="sub">3 units or fewer available</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 45%;">Item</th>
                    <th style="width: 30%;">Category</th>
                    <th style="width: 25%;" class="num">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lowStockData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['item_name'] ?? '—' }}</td>
                        <td>{{ $row['category'] ?? 'General' }}</td>
                        <td class="num"><strong>{{ number_format($row['quantity'] ?? 0) }} {{ $row['unit'] ?? 'units' }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">All item groups have adequate stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Attention --}}
    <div class="card">
        <div class="card-title">Items Needing Attention <span class="sub">Maintenance, inspection, or disposal</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 45%;">Item</th>
                    <th style="width: 30%;">Status</th>
                    <th style="width: 25%;" class="num">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attentionData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['item_name'] ?? '—' }}</td>
                        <td>{{ $row['status'] ?? '—' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No items currently flagged for maintenance or inspection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Expiring Lifespan --}}
    <div class="card">
        <div class="card-title">Expiring Lifespan <span class="sub">Closest expected end dates first</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 40%;">Item</th>
                    <th style="width: 22%;" class="num">Quantity</th>
                    <th style="width: 38%;">Expected End</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expiringData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['item_name'] ?? '—' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                        <td>
                            <span class="pill pill-{{ $row['tone'] ?? 'healthy' }}">{{ ($row['tone'] ?? '') === 'expired' ? 'Expired' : \Carbon\Carbon::parse($row['expected_end_date'])->format('M d, Y') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No dated lifespans on record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Custody & Overdue --}}
    <div class="card">
        <div class="card-title">Custody &amp; Returns <span class="sub">Units held per person; {{ $overdueCount }} overdue</span></div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 60%;">Holder</th>
                    <th style="width: 40%;" class="num">Units in Custody</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($holderData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['holder_name'] ?? '—' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No custody holdings on record.</td></tr>
                @endforelse
            </tbody>
        </table>
        <table class="grid" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 35%;">Overdue Item</th>
                    <th style="width: 35%;">Holder</th>
                    <th style="width: 30%;">Due Since</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($overdueData as $index => $row)
                    <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                        <td>{{ $row['item_name'] ?? '—' }}</td>
                        <td>{{ $row['holder_name'] ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['expected_return_date'])->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Nothing overdue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Forecast Bridge --}}
    <div class="bridge">
        <div class="bridge-title">Demand Forecast</div>
        <div class="bridge-text">
            @if ($forecastAvailable)
                {{ $forecastGaps }} {{ $forecastGaps === 1 ? 'item shows' : 'items show' }} a procurement gap{{ $forecastPeriod ? " for {$forecastPeriod}" : '' }}; {{ $forecastWeak }} {{ $forecastWeak === 1 ? 'needs' : 'need' }} verification before ordering. See the Demand Forecast page for the ranked list and export.
            @else
                No trained forecast is available yet. Model training runs separately from reports.
            @endif
        </div>
    </div>

    <div class="disclaimer">
        <strong>Note:</strong> This report reflects recorded inventory at generation time and is
        advisory only. Figures must be verified against current stock levels before any procurement,
        disposal, or audit action. Forecasts referenced here do not create orders.
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
