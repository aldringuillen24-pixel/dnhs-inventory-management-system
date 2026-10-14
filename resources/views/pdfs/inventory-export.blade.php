<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Export - Dian-ay National High School</title>
    <style>
        @page {
            margin: 20mm;
            size: portrait;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.3;
        }
        .report-meta {
            margin-bottom: 10px;
        }
        .report-meta div {
            margin-bottom: 2px;
        }
        table.grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        table.grid col {
            width: auto;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #000;
            padding: 3px;
            overflow-wrap: break-word;
        }
        table.grid th {
            text-align: left;
            font-weight: bold;
            font-size: 14pt;
        }
        table.grid td {
            vertical-align: top;
        }
        table.grid.assigned th,
        table.grid.assigned td {
            padding: 2px;
        }
        table.grid td.num {
            text-align: right;
            white-space: nowrap;
        }
        table.grid .empty {
            padding: 10px 8px;
            text-align: center;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }
        .signatures td {
            width: 50%;
            vertical-align: bottom;
        }
        .sign-line {
            border-bottom: 1px solid #000;
            height: 30px;
        }
        .sign-role {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="report-meta">
        <div>Filters: {{ $filterSummary }}</div>
        <div>Generated: {{ $generatedAt }}</div>
        <div>Prepared by: {{ $preparedBy }}</div>
    </div>

    <table class="grid {{ ($workspace ?? 'all') === 'assigned' ? 'assigned' : '' }}">
        <colgroup>
            @if (($workspace ?? 'all') === 'assigned')
                <col style="width: 3%;">
                <col style="width: 20%;">
                <col style="width: 15%;">
                <col style="width: 5%;">
                <col style="width: 12%;">
                <col style="width: 14%;">
                <col style="width: 12%;">
                <col style="width: 18%;">
            @else
                <col style="width: 2%;">
                <col style="width: 25%;">
                <col style="width: 20%;">
                <col style="width: 4%;">
                <col style="width: 20%;">
                <col style="width: 26%;">
                <col style="width: 12%;">
            @endif
        </colgroup>
        <thead>
            <tr>
                <th>Item No.</th>
                <th>Item &amp; ICS</th>
                <th>Category</th>
                <th class="num">Qty</th>
                <th class="num">Unit Cost</th>
                <th class="num" style="width: {{ ($workspace ?? 'all') === 'assigned' ? '14%' : '16%' }};">Total</th>
                <th>Status</th>
                @if (($workspace ?? 'all') === 'assigned')
                    <th>Assignee</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $index => $group)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $group['item_name'] }}</strong>
                        <div>ICS: {{ $group['ics_no'] ?: '—' }}</div>
                    </td>
                    <td>{{ $group['category'] }}</td>
                    <td class="num">{{ number_format($group['quantity']) }} {{ $group['unit'] ?? '' }}</td>
                    <td class="num">{{ number_format($group['unit_cost'], 2) }}</td>
                    <td class="num">{{ number_format($group['total_cost'], 2) }}</td>
                    <td>{{ $group['status'] }}</td>
                    @if (($workspace ?? 'all') === 'assigned')
                        <td>{{ $group['assignee'] ?? '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ ($workspace ?? 'all') === 'assigned' ? 8 : 7 }}" class="empty">No records match the current filters.</td></tr>
            @endforelse
            @if ($groups->isNotEmpty())
                <tr>
                    <td colspan="6">
                        <strong>Total:</strong>
                        {{ number_format($totalQuantity) }} units;
                        {{ number_format($totalValue, 2) }}
                    </td>
                    <td colspan="{{ ($workspace ?? 'all') === 'assigned' ? 2 : 1 }}"></td>
                </tr>
            @endif
        </tbody>
    </table>

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
