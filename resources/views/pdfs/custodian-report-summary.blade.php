<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Custodian Reports</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm 15mm 18mm 15mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.35;
        }

        * {
            box-sizing: border-box;
        }

        .report-meta {
            margin-bottom: 12pt;
        }

        .report-meta div {
            margin-bottom: 2pt;
        }

        .report-meta strong {
            font-weight: bold;
        }

        table.metrics {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            page-break-inside: avoid;
        }

        table.metrics td {
            width: 25%;
            border: 1px solid #000;
            padding: 6pt 8pt;
            vertical-align: top;
        }

        .metric-label {
            font-size: 10pt;
            text-transform: uppercase;
            font-weight: bold;
        }

        .metric-value {
            margin-top: 4pt;
            font-size: 16pt;
            font-weight: bold;
        }

        .metric-sub {
            margin-top: 3pt;
            font-size: 9pt;
        }

        .card {
            border: 1px solid #000;
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }

        .card--breakable {
            page-break-inside: auto;
        }

        .card--breakable .card-title {
            page-break-after: avoid;
        }

        .card--breakable .grid tr {
            page-break-inside: avoid;
        }

        .card-title {
            padding: 6pt 8pt;
            border-bottom: 1px solid #000;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-title .sub {
            font-size: 9pt;
            font-weight: normal;
            text-transform: none;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .grid th,
        .grid td {
            border: 1px solid #000;
            padding: 4pt 5pt;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .grid th {
            font-size: 11pt;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .grid td.num {
            text-align: right;
            white-space: nowrap;
        }

        .grid td.empty {
            padding: 10pt 8pt;
            text-align: center;
            font-style: italic;
        }

        .pill {
            display: inline-block;
            border: 1px solid #000;
            padding: 1pt 4pt;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .bridge {
            border: 1px solid #000;
            padding: 8pt 10pt;
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }

        .bridge-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4pt;
        }

        .bridge-text {
            font-size: 12pt;
        }

        .disclaimer {
            margin-top: 8pt;
            padding: 6pt 8pt;
            border-left: 2px solid #000;
            font-size: 10pt;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22pt;
        }

        .signatures td {
            width: 50%;
            vertical-align: bottom;
            padding: 0 12pt 0 0;
        }

        .signatures td:last-child {
            padding-right: 0;
            padding-left: 12pt;
        }

        .sign-line {
            border-bottom: 1px solid #000;
            height: 30pt;
        }

        .sign-role {
            margin-top: 4pt;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sign-meta {
            margin-top: 2pt;
            font-size: 10pt;
        }
    </style>
</head>
<body>
    <div class="report-meta">
        <div><strong>Property Custodian Reports</strong></div>
        <div>Period: {{ $periodLabel }}</div>
        <div>Generated: {{ $generatedAt }}</div>
        <div>Document Code: DNHS-IMS-REPT-{{ now()->format('Ymd') }}</div>
        <div>Prepared by: {{ $preparedBy }}</div>
    </div>

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

    <div class="card card--breakable">
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
                @forelse ($movementData as $day)
                    <tr>
                        <td>{{ $day['label'] ?? '?' }}</td>
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
                @forelse ($categoryData as $row)
                    <tr>
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
                @forelse ($statusData as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '?' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No status data recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                @forelse ($lowStockData as $row)
                    <tr>
                        <td>{{ $row['item_name'] ?? '?' }}</td>
                        <td>{{ $row['category'] ?? 'General' }}</td>
                        <td class="num"><strong>{{ number_format($row['quantity'] ?? 0) }} {{ $row['unit'] ?? 'units' }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">All item groups have adequate stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                @forelse ($attentionData as $row)
                    <tr>
                        <td>{{ $row['item_name'] ?? '?' }}</td>
                        <td>{{ $row['status'] ?? '?' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No items currently flagged for maintenance or inspection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                @forelse ($expiringData as $row)
                    <tr>
                        <td>{{ $row['item_name'] ?? '?' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                        <td>
                            <span class="pill">{{ ($row['tone'] ?? '') === 'expired' ? 'Expired' : \Carbon\Carbon::parse($row['expected_end_date'])->format('M d, Y') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No dated lifespans on record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                @forelse ($holderData as $row)
                    <tr>
                        <td>{{ $row['holder_name'] ?? '?' }}</td>
                        <td class="num">{{ number_format($row['quantity'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No custody holdings on record.</td></tr>
                @endforelse
            </tbody>
        </table>
        <table class="grid" style="margin-top: 6pt;">
            <thead>
                <tr>
                    <th style="width: 35%;">Overdue Item</th>
                    <th style="width: 35%;">Holder</th>
                    <th style="width: 30%;">Due Since</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($overdueData as $row)
                    <tr>
                        <td>{{ $row['item_name'] ?? '?' }}</td>
                        <td>{{ $row['holder_name'] ?? '?' }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['expected_return_date'])->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Nothing overdue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
        <strong>Note:</strong> This report reflects recorded inventory at generation time and is advisory only. Figures must be verified against current stock levels before any procurement, disposal, or audit action. Forecasts referenced here do not create orders.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line"></div>
                <div class="sign-role">Property Custodian</div>
                <div class="sign-meta">Prepared by {{ $preparedBy }}</div>
            </td>
            <td>
                <div class="sign-line"></div>
                <div class="sign-role">School Head</div>
                <div class="sign-meta">Reviewed and approved</div>
            </td>
        </tr>
    </table>
</body>
</html>
