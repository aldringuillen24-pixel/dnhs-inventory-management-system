<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory and Inspection Report of Unserviceable Property - IIRUP</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 8pt;
                color: #000;
            }
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 9pt;
            line-height: 1.15;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .header-block {
            width: 100%;
            margin-bottom: 6px;
        }
        .appendix {
            text-align: right;
            font-size: 10pt;
            font-style: italic;
            margin-bottom: 2px;
        }
        .title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .as-at {
            text-align: center;
            font-size: 10pt;
            margin-bottom: 6px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 5px 0 8px;
        }
        .info-grid td {
            vertical-align: bottom;
            padding: 0 5px;
        }
        .info-grid .entity-cell {
            width: 72%;
            
            padding-left: 0;
        }
        .info-grid .fund-cell {
            width: 28%;
            padding-right: 0;
        }
        .info-line {
            display: flex;
            align-items: baseline;
            gap: 4px;
            min-height: 15px;
            width: 100%;
        }
        .info-label {
            font-weight: bold;
            white-space: nowrap;
        }
        .info-value {
            display: inline-block;
            flex: 1 1 auto;
            min-width: 0;
            border-bottom: 1px solid #000;
            min-height: 13px;
            padding: 0 3px 1px;
        }
        .officer-grid {
            width: 70%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 6px;
        }
        .officer-grid td {
            width: 33.333%;
            text-align: center;
            vertical-align: top;
            padding: 0 16px 0 8px;
        }
        .officer-value {
            display: block;
            width: 80%;
            margin: 0 auto;
            min-height: 15px;
            border-bottom: 1px solid #000;
            text-align: center;
            padding: 0 3px 1px;
            white-space: nowrap;
            overflow: hidden;
        }
        .officer-label {
            display: block;
            font-size: 8pt;
            font-style: italic;
            text-align: center;
            margin-top: 2px;
        }
        .tables-wrapper {
            width: 100%;
            border-collapse: collapse;
        }
        .tables-wrapper td {
            vertical-align: top;
            padding: 0;
        }
        .left-table-cell {
            width: 55%;
            padding-right: 2px;
        }
        .right-table-cell {
            width: 45%;
            padding-left: 2px;
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        table.grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.grid th {
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }
        table.grid td {
            font-size: 8pt;
        }
        table.grid td.num {
            text-align: right;
            white-space: nowrap;
        }
        .iirup-grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 2px;
        }
        .iirup-grid th,
        .iirup-grid td {
            border: 1px solid #000;
            padding: 2px 1px;
            font-size: 7pt;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        .iirup-grid thead tr:nth-child(2) th:nth-child(7),
        .iirup-grid thead tr:nth-child(2) th:nth-child(8),
        .iirup-grid tbody tr td:nth-child(7),
        .iirup-grid tbody tr td:nth-child(8) {
            padding-left: 8px !important;
            padding-right: 8px !important;
            line-height: 1.15;
        }
        .iirup-grid thead tr:nth-child(2) th:nth-child(7),
        .iirup-grid thead tr:nth-child(2) th:nth-child(8) {
            font-size: 7pt;
            letter-spacing: 0.01em;
        }
        .iirup-grid .main-group-header {
            font-size: 7pt;
            padding: 2px 1px;
            vertical-align: middle;
        }
        .iirup-grid thead th {
            vertical-align: middle;
            line-height: 1.05;
            overflow-wrap: normal;
            word-wrap: normal;
        }
        .iirup-grid .reference-row th {
            font-size: 6pt;
            font-weight: normal;
            padding: 1px;
            line-height: 1;
        }
        .ref-num {
            font-size: 7pt;
            font-weight: normal;
            display: block;
            text-align: center;
        }
        .group-header {
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }
        .totals-row td {
            font-weight: bold;
        }
        .legal-text {
            margin: 10px 0 24px;
            font-size: 9pt;
            text-align: left;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .signatures > tbody > tr > td {
            vertical-align: top;
            padding: 0 6px 4px;
            border: 1px solid #000;
            border-top: 0;
        }
        .request-signatures {
            width: 50%;
        }
        .certification-signatures {
            width: 40%;
        }
        .signature-columns {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 12px;
        }
        .signature-columns td {
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }
        .sign-block {
            text-align: center;
        }
        .sign-caption {
            text-align: left;
            font-size: 8pt;
            margin-bottom: 2px;
        }
        .sign-name {
            font-weight: bold;
            min-height: 0;
            margin: 0;
            line-height: 1;
        }
        .sign-line {
            border-bottom: 1px solid #000;
            height: 0;
            margin: 0 auto 2px;
            width: 90%;
        }
        .sign-role-label {
            font-size: 8pt;
            min-height: 24px;
            margin-top: 0;
        }
        .sign-designation {
            font-size: 8pt;
            width: 90%;
            margin: 2px auto 0;
            padding-bottom: 1px;
            border-bottom: 1px solid #000;
        }
        .designation-label {
            font-size: 8pt;
            margin-top: 2px;
        }
        .certification-text {
            margin-top: 10px;
            min-height: 58px;
            font-size: 8pt;
            line-height: 1.15;
            text-align: left;
        }
        tr {
            page-break-inside: avoid;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-footer-group;
        }
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="header-block">
        <div class="appendix">Appendix 74</div>
        <h1 class="title">INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY</h1>
        <div class="as-at">As at {{ $as_at_date ?? '' }}</div>

            <table class="info-grid">
                <tr>
                    <td class="entity-cell">
                        <div class="info-line">
                            <span class="info-label">Entity Name:</span>
                            <span class="info-value">{{ $entity_name ?? '' }}</span>
                        </div>
                    </td>
                <td class="fund-cell">
                    <div class="info-line">
                        <span class="info-label">Fund Cluster:</span>
                        <span class="info-value">{{ $fund_cluster ?? '' }}</span>
                    </div>
                </td>
            </tr>
        </table>
        <table class="officer-grid">
            <tr>
                <td>
                    <span class="officer-value">{{ $accountable_officer['name'] ?? '' }}</span>
                    <span class="officer-label">(Name of Accountable Officer)</span>
                </td>
                <td>
                    <span class="officer-value">{{ $accountable_officer['designation'] ?? '' }}</span>
                    <span class="officer-label">(Designation)</span>
                </td>
                <td>
                    <span class="officer-value">{{ $accountable_officer['station'] ?? '' }}</span>
                    <span class="officer-label">(Station)</span>
                </td>
            </tr>
        </table>
    </div>

    @if (!empty($groups))
        @foreach ($groups as $index => $group)
            @if ($index > 0)
                <div style="page-break-before: always;"></div>
            @endif
            <table class="grid iirup-grid">
                <colgroup>
                    <col style="width: 5%;">
                    <col style="width: 10%;">
                    <col style="width: 5%;">
                    <col style="width: 1%;">
                    <col style="width: 2.8%;">
                    <col style="width: 4.2%;">
                    <col style="width: 10%;">
                    <col style="width: 12%;">
                    <col style="width: 5.8%;">
                    <col style="width: 5.2%;">
                    <col style="width: 4.7%;">
                    <col style="width: 4.7%;">
                    <col style="width: 4.7%;">
                    <col style="width: 4.7%;">
                    <col style="width: 4.7%;">
                    <col style="width: 6%;">
                    <col style="width: 6%;">
                    <col style="width: 4.5%;">
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="10" class="main-group-header">INVENTORY</th>
                        <th colspan="8" class="main-group-header">INSPECTION AND DISPOSAL</th>
                    </tr>
                    <tr>
                        <th rowspan="2">Date Acquired</th>
                        <th rowspan="2">Particulars / Articles</th>
                        <th rowspan="2">Property No.</th>
                        <th rowspan="2">Qty</th>
                        <th rowspan="2">Unit Cost</th>
                        <th rowspan="2">Total Cost</th>
                        <th rowspan="2">Accumulated<br>Depreciation</th>
                        <th rowspan="2">Accumulated<br>Impairment<br>Losses</th>
                        <th rowspan="2">Carrying Amount</th>
                        <th rowspan="2">Remarks</th>
                        <th colspan="5" class="group-header">DISPOSAL</th>
                        <th rowspan="2">Appraised Value</th>
                        <th colspan="2" class="group-header">RECORD OF SALES</th>
                    </tr>
                    <tr>
                        <th>Sale</th>
                        <th>Transfer</th>
                        <th>Destruction</th>
                        <th>Others (Specify)</th>
                        <th>Total</th>
                        <th>OR No.</th>
                        <th>Amount</th>
                    </tr>
                    <tr class="reference-row">
                        <th>(1)</th>
                        <th>(2)</th>
                        <th>(3)</th>
                        <th>(4)</th>
                        <th>(5)</th>
                        <th>(6)</th>
                        <th>(7)</th>
                        <th>(8)</th>
                        <th>(9)</th>
                        <th>(10)</th>
                        <th>(11)</th>
                        <th>(12)</th>
                        <th>(13)</th>
                        <th>(14)</th>
                        <th>(15)</th>
                        <th>(16)</th>
                        <th>(17)</th>
                        <th>(18)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group['items'] as $item)
                        <tr>
                            <td>{{ $item['date_acquired'] ?? '' }}</td>
                            <td>{{ $item['item_name'] ?? '' }}</td>
                            <td>{{ $item['inventory_item_no'] ?? '' }}</td>
                            <td class="num">{{ number_format($item['quantity'] ?? 0) }}</td>
                            <td class="num">{{ number_format($item['unit_cost'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['total_cost'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['accumulated_depreciation'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['accumulated_impairment'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['carrying_amount'] ?? 0, 2) }}</td>
                            <td>{{ $item['remarks'] ?? '' }}</td>
                            <td class="num">{{ number_format($item['disposal_sale'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['disposal_transfer'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['disposal_destruction'] ?? 0, 2) }}</td>
                            <td>{{ $item['disposal_others'] ?? '' }}</td>
                            <td class="num">{{ number_format($item['disposal_total'] ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($item['appraised_value'] ?? 0, 2) }}</td>
                            <td>{{ $item['or_no'] ?? '' }}</td>
                            <td class="num">{{ number_format($item['sales_amount'] ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18" style="padding: 8px;">
                                <span class="info-label" style="font-weight: bold;">Entity Name:</span>
                                <span class="info-value">&nbsp;</span>
                                <span class="info-label" style="font-weight: bold; margin-left: 24px;">Fund Cluster:</span>
                                <span class="info-value">&nbsp;</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @else
        <table class="grid iirup-grid">
            <colgroup>
                <col style="width: 5%;">
                <col style="width: 10%;">
                <col style="width: 5%;">
                <col style="width: 1%;">
                <col style="width: 3%;">
                <col style="width: 4.5%;">
                <col style="width: 9%;">
                <col style="width: 11%;">
                <col style="width: 6%;">
                <col style="width: 5.5%;">
                <col style="width: 4.7%;">
                <col style="width: 4.7%;">
                <col style="width: 4.7%;">
                <col style="width: 4.7%;">
                <col style="width: 4.7%;">
                <col style="width: 6%;">
                <col style="width: 6%;">
                <col style="width: 4.5%;">
            </colgroup>
            <thead>
                <tr>
                    <th colspan="10" class="main-group-header">INVENTORY</th>
                    <th colspan="8" class="main-group-header">INSPECTION AND DISPOSAL</th>
                </tr>
                <tr>
                    <th rowspan="2"><span class="ref-num">(1)</span>Date Acquired</th>
                    <th rowspan="2"><span class="ref-num">(2)</span>Particulars / Articles</th>
                    <th rowspan="2"><span class="ref-num">(3)</span>Property No.</th>
                    <th rowspan="2"><span class="ref-num">(4)</span>Qty</th>
                    <th rowspan="2"><span class="ref-num">(5)</span>Unit Cost</th>
                    <th rowspan="2"><span class="ref-num">(6)</span>Total Cost</th>
                    <th rowspan="2"><span class="ref-num">(7)</span>Accumulated<br>Depreciation</th>
                    <th rowspan="2"><span class="ref-num">(8)</span>Accumulated<br>Impairment<br>Losses</th>
                    <th rowspan="2"><span class="ref-num">(9)</span>Carrying Amount</th>
                    <th rowspan="2"><span class="ref-num">(10)</span>Remarks</th>
                    <th colspan="5" class="group-header">DISPOSAL</th>
                    <th rowspan="2"><span class="ref-num">(16)</span>Appraised Value</th>
                    <th colspan="2" class="group-header">RECORD OF SALES</th>
                </tr>
                <tr>
                    <th><span class="ref-num">(11)</span>Sale</th>
                    <th><span class="ref-num">(12)</span>Transfer</th>
                    <th><span class="ref-num">(13)</span>Destruction</th>
                    <th><span class="ref-num">(14)</span>Others (Specify)</th>
                    <th><span class="ref-num">(15)</span>Total</th>
                    <th><span class="ref-num">(17)</span>OR No.</th>
                    <th><span class="ref-num">(18)</span>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="18" style="padding: 8px;">
                        <span class="info-label" style="font-weight: bold;">Entity Name:</span>
                        <span class="info-value">&nbsp;</span>
                        <span class="info-label" style="font-weight: bold; margin-left: 24px;">Fund Cluster:</span>
                        <span class="info-value">&nbsp;</span>
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="signatures">
        <tr>
            <td class="request-signatures">
                <div class="legal-text">
                    I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
                </div>
                <table class="signature-columns">
                    <tr>
                        <td>
                            <div class="sign-caption">Requested by:</div>
                            <div class="sign-block">
                                <div class="sign-name">{{ $requested_by_name ?? '' }}</div>
                                <div class="sign-line"></div>
                                <div class="sign-role-label">Signature over Printed Name of Accountable Officer</div>
                                <div class="sign-designation">{{ $requested_by_designation ?? 'SCHOOL PROPERTY CUSTODIAN' }}</div>
                                <div class="designation-label">(Designation of Accountable Officer)</div>
                            </div>
                        </td>
                        <td>
                            <div class="sign-caption">Approved by:</div>
                            <div class="sign-block">
                                <div class="sign-name">{{ $approved_by_name ?? '' }}</div>
                                <div class="sign-line"></div>
                                <div class="sign-role-label">Signature over Printed Name of Authorized Official</div>
                                <div class="sign-designation">{{ $approved_by_designation ?? 'PRINCIPAL I' }}</div>
                                <div class="designation-label">(Designation of Authorized Official)</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="certification-signatures">
                <table class="signature-columns" style="margin-top: 0;">
                    <tr>
                        <td>
                            <div class="certification-text">
                                I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.
                            </div>
                            <div class="sign-block">
                                <div class="sign-name">{{ $inspection_officer_name ?? '' }}</div>
                                <div class="sign-line"></div>
                                <div class="sign-role-label">Signature over Printed Name of Inspection Officer</div>
                                <div class="sign-designation">{{ $inspection_designation ?? '' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="certification-text">
                                I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this {{ $witness_disposition_day ?? '_____' }} day of {{ $witness_disposition_date ?? '____________________' }}.
                            </div>
                            <div class="sign-block">
                                <div class="sign-name">{{ $witness_name ?? '' }}</div>
                                <div class="sign-line"></div>
                                <div class="sign-role-label">Signature over Printed Name of Witness</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>