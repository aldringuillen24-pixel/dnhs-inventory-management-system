<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RCPI-PPE Draft Format Preview</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", serif;
            font-size: 8pt;
            line-height: 1.15;
        }

        .page {
            position: relative;
            width: 100%;
        }

        .draft-notice {
            margin: 0 0 5px;
            color: #8a3412;
            font-family: Arial, sans-serif;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
        }

        .report-title,
        .subtitle,
        .inventory-type,
        .as-at {
            margin: 2px 0;
            text-align: center;
            font-weight: bold;
        }

        .report-title {
            font-size: 13pt;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 11pt;
            text-transform: uppercase;
        }

        .inventory-type {
            font-size: 10pt;
            text-transform: uppercase;
        }

        .as-at {
            margin-bottom: 10px;
            font-size: 11pt;
        }

        .fund-value {
            width: 100%;
            min-height: 16px;
            border-bottom: 1px solid #000;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 4px 3px;
            text-align: center;
            vertical-align: middle;
            overflow-wrap: break-word;
        }

        .report-table th {
            font-weight: bold;
        }

        .report-table .item-row td {
            height: 26px;
        }

        .report-table .text-left {
            text-align: left;
        }

        .report-header {
            position: relative;
            padding: 8px;
        }

        .signature-cell {
            height: 100px;
            padding: 8px 10px;
            vertical-align: top;
        }

        .signature-label {
            text-align: left;
        }

        .signature-name {
            margin-top: 24px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .signature-title {
            margin-top: 4px;
        }

        .verification-line {
            height: 28px;
            margin: 16px 10px 4px;
            border-bottom: 1px solid #000;
        }
    </style>
</head>
<body>
    <main class="page">
        <p class="draft-notice">DRAFT FORMAT PREVIEW — SAMPLE DATA ONLY — NOT AN OFFICIAL COUNT OR CERTIFIED REPORT</p>
        <table class="report-table">
            <colgroup>
                <col style="width: 10%">
                <col style="width: 16%">
                <col style="width: 10%">
                <col style="width: 9%">
                <col style="width: 8%">
                <col style="width: 8%">
                <col style="width: 8%">
                <col style="width: 7%">
                <col style="width: 7%">
                <col style="width: 7%">
                <col style="width: 10%">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="11" class="report-header">
                        <span style="position: absolute; top: 8px; right: 8px; font-size: 9pt; font-weight: bold; font-style: italic;">Appendix 66</span>
                        <h1 class="report-title">Report on the Physical Count of Inventories</h1>
                        <h2 class="subtitle">Office Equipment (Low Valued)</h2>
                        <p class="inventory-type">{{ $inventory_type }}</p>
                        <p class="as-at">As at {{ \Illuminate\Support\Carbon::parse($report_date)->format('m/d/Y') }}</p>
                        <p style="margin: 8px 0; text-align: left;"><strong>Fund Cluster:</strong> <span class="fund-value" style="display: inline-block; width: 28%;">{{ $fund_cluster ?? '' }}</span></p>
                        <p style="margin: 8px 0; text-align: left;">
                            For which <strong>{{ $accountable_person ?? '' }}</strong> is accountable,
                            having assumed such accountability on
                            <strong>{{ !empty($accountability_date) ? \Illuminate\Support\Carbon::parse($accountability_date)->format('m/d/Y') : '' }}</strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th rowspan="2">Article</th>
                    <th rowspan="2">Description</th>
                    <th rowspan="2">Stock Number</th>
                    <th rowspan="2">Unit of Measurement</th>
                    <th rowspan="2">Unit Value</th>
                    <th>Balance Per Card</th>
                    <th>On Hand Per Count</th>
                    <th colspan="2">Shortage / Overage</th>
                    <th colspan="2">Remarks</th>
                </tr>
                <tr>
                    <th>Quantity</th>
                    <th>Quantity</th>
                    <th>Quantity</th>
                    <th>Value</th>
                    <th>Condition</th>
                    <th>Whereabouts</th>
                </tr>
                @forelse ($items as $item)
                    <tr class="item-row">
                        <td>{{ $item['article'] }}</td>
                        <td class="text-left">{{ $item['description'] ?? '' }}</td>
                        <td>{{ $item['stock_number'] ?? '' }}</td>
                        <td>{{ $item['unit'] ?? '' }}</td>
                        <td>{{ number_format((float) $item['unit_value'], 2) }}</td>
                        <td>{{ $item['book_quantity'] }}</td>
                        <td>{{ $item['counted_quantity'] }}</td>
                        <td>
                            {{ $item['variance_quantity'] }}
                            @if ($item['variance_type'])
                                <br><small>{{ $item['variance_type'] }}</small>
                            @endif
                        </td>
                        <td>{{ number_format((float) $item['variance_value'], 2) }}</td>
                        <td>{{ $item['condition'] ?? '' }}</td>
                        <td>{{ $item['whereabouts'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">No sample items selected.</td>
                    </tr>
                @endforelse
                <tr>
                    <td colspan="4" class="signature-cell">
                    <p class="signature-label">Recommending Approval:</p>
                    <p class="signature-name">{{ $recommending_name ?? '' }}</p>
                    <p class="signature-title">{{ $recommending_title ?? '' }}</p>
                    </td>
                    <td colspan="4" class="signature-cell">
                    <p class="signature-label">Approved by:</p>
                    <p class="signature-name">{{ $approving_name ?? '' }}</p>
                    <p class="signature-title">{{ $approving_title ?? '' }}</p>
                    </td>
                    <td colspan="3" class="signature-cell">
                    <p class="signature-label">Verified by:</p>
                    <div class="verification-line"></div>
                    <p class="signature-title">{{ $verifying_title ?? 'Signature over Printed Name of COA Representative' }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </main>
</body>
</html>
