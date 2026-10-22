<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Custodian Slip - Dian-ay National High School</title>
    <style>
        @page {
            margin: 12mm;
            size: landscape;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 10pt;
            line-height: 1.2;
            color: #111;
        }
        @media screen {
            html {
                background: #e5e7eb;
            }
            body {
                margin: 0;
                padding: 16px;
            }
            .ics-slip {
                box-sizing: border-box;
                width: 10.75in;
                max-width: 100%;
                min-height: 8.27in;
                margin: 0 auto 16px;
                padding: 10mm;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
            }
        }
        .heading {
            position: relative;
            margin-bottom: 12px;
            text-align: center;
        }
        @media screen {
            .ics-slip.has-next {
                page-break-after: auto;
            }
        }
        .ics-slip.has-next {
            page-break-after: always;
        }
        .heading h1 {
            margin: 0;
            font-size: 18pt;
            font-weight: bold;
        }
        .appendix {
            position: absolute;
            top: 2px;
            right: 0;
            font-size: 12pt;
        }
        .form-fields {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .form-fields td {
            width: 50%;
            padding: 3px 2px;
            vertical-align: bottom;
        }
        .form-fields .ics-field {
            white-space: nowrap;
        }
        .form-fields .ics-field .field-line {
            width: auto;
            min-width: 48%;
            height: auto;
            min-height: 14px;
            padding-bottom: 2px;
            text-align: center;
        }
        .field-line {
            display: inline-block;
            width: 60%;
            height: 14px;
            border-bottom: 1px solid #111;
            vertical-align: bottom;
        }
        .field-line.short {
            width: 48%;
        }
        table.slip {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.slip th,
        table.slip td {
            border: 1px solid #111;
            padding: 5px 4px;
            overflow-wrap: break-word;
        }
        table.slip thead {
            display: table-header-group;
        }
        table.slip th {
            text-align: center;
            vertical-align: middle;
            font-size: 9pt;
        }
        table.slip td {
            vertical-align: top;
        }
        table.slip tr {
            page-break-inside: avoid;
        }
        table.slip .num {
            text-align: right;
            white-space: nowrap;
        }
        table.slip .center {
            text-align: center;
        }
        table.slip .description strong {
            display: block;
            margin-bottom: 2px;
        }
        table.slip .description small {
            display: block;
            margin-top: 2px;
            font-size: 8pt;
        }
        table.slip .blank-row td {
            height: 22px;
        }
        table.slip .empty {
            padding: 14px 8px;
            text-align: center;
        }
        table.slip .form-label {
            font-weight: bold;
            text-transform: uppercase;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 50%;
            height: 90px;
            padding: 7px 10px;
            border: 0;
            vertical-align: top;
        }
        .signatures td:first-child {
            border-right: 1px solid #111;
        }
        table.slip td.signature-cell {
            padding: 0;
        }
        table.slip td.signature-cell .signatures td {
            border: 0;
        }
        table.slip td.signature-cell .signatures td:first-child {
            border-right: 1px solid #111;
        }
        .signature-line {
            width: 48%;
            margin: 30px auto 3px;
            border-bottom: 1px solid #111;
        }
        .signature-line.received {
            margin-top: 2px;
        }
        .signature-role {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        .signature-note {
            text-align: center;
        }
        .signature-name {
            margin-top: 18px;
            text-align: center;
        }
        .signature-edit {
            box-sizing: border-box;
            width: 100%;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #111;
            font: inherit;
            text-align: center;
        }
        .signature-edit:focus {
            outline: 1px dashed #059669;
            outline-offset: 2px;
        }
        .signature-field-line .signature-edit {
            height: 16px;
        }
        .signature-name .signature-edit,
        .signature-role .signature-edit {
            display: block;
        }
        .signature-date-line {
            min-height: 16px;
            text-align: center;
        }
        .signature-date-line .signature-edit {
            height: 16px;
        }
        .signature-fields {
            width: 80%;
            margin: 8px auto 0;
            text-align: center;
            font-size: 10pt;
        }
        .signature-field {
            margin-top: 3px;
        }
        .signature-field-line {
            width: 65%;
            margin: 8px auto 0;
            min-height: 16px;
            border-bottom: 1px solid #111;
        }
        .signature-field-label {
            margin-top: 1px;
        }
        .date-line {
            width: 38%;
            margin: 14px auto 2px;
            border-bottom: 1px solid #111;
        }
    </style>
</head>
<body>
    @foreach ($icsPages as $icsPage)
        <div class="ics-slip{{ !$loop->last ? ' has-next' : '' }}">
            <div class="heading">
                <span class="appendix">Appendix 59</span>
                <h1>INVENTORY CUSTODIAN SLIP</h1>
            </div>

            <table class="form-fields">
                <tr>
                    <td><strong>Entity Name:</strong> <span class="field-line">Dian-ay National High School</span></td>
                    <td class="ics-field" style="text-align: right;"><strong>ICS No.:</strong> <span class="field-line short">{{ $icsPage['ics_no'] ?? '' }}</span></td>
                </tr>
                <tr>
                    <td><strong>Fund Cluster:</strong> <span class="field-line"></span></td>
                    <td></td>
                </tr>
            </table>

            <table class="slip">
                <colgroup>
                    <col style="width: 7%;">
                    <col style="width: 8%;">
                    <col style="width: 10%;">
                    <col style="width: 11%;">
                    <col style="width: 34%;">
                    <col style="width: 19%;">
                    <col style="width: 11%;">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">Quantity</th>
                        <th rowspan="2">Unit</th>
                        <th colspan="2">Amount</th>
                        <th rowspan="2">Description</th>
                        <th rowspan="2">Inventory Item No.</th>
                        <th rowspan="2">Estimated Useful Life</th>
                    </tr>
                    <tr>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($icsPage['groups'] as $group)
                        <tr>
                            <td class="center">{{ number_format($group['quantity']) }}</td>
                            <td class="center">{{ $group['unit'] ?? '' }}</td>
                            <td class="num">{{ number_format($group['unit_cost'], 2) }}</td>
                            <td class="num">{{ number_format($group['total_cost'], 2) }}</td>
                            <td class="description">
                                <strong>{{ $group['item_name'] }}</strong>
                                @if (!empty($group['description']))
                                    <small>{{ $group['description'] }}</small>
                                @endif
                                @if (($workspace ?? 'all') === 'assigned' && !empty($group['assignee']))
                                    <small>Assignee: {{ $group['assignee'] }}</small>
                                @endif
                            </td>
                            <td></td>
                            <td class="center">
                                {{ $group['lifespan_years'] !== null ? number_format($group['lifespan_years']) . ' years' : '' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">No records match the current filters.</td>
                        </tr>
                    @endforelse
                    <tr class="blank-row">
                        <td colspan="7"><span class="form-label">Purchase No.:</span></td>
                    </tr>
                    <tr class="blank-row">
                        <td colspan="7"><span class="form-label">Source of Fund:</span></td>
                    </tr>
                    <tr>
                        <td colspan="7" class="signature-cell">
                            <table class="signatures">
                                <tr>
                                    <td>
                                        Received from:
                                        <div class="signature-fields">
                                            <div class="signature-field">
                                                <div class="signature-field-line">
                                                    @if ($isPreview)
                                                        <input class="signature-edit" type="text" maxlength="255" data-report-field="received_from_name" value="{{ $receivedFromName }}" aria-label="Received from signature name">
                                                    @else
                                                        {{ $receivedFromName }}
                                                    @endif
                                                </div>
                                                <div class="signature-field-label">Signature Over Printed Name</div>
                                            </div>
                                            <div class="signature-field">
                                                <div class="signature-field-line">
                                                    @if ($isPreview)
                                                        <input class="signature-edit" type="text" maxlength="255" data-report-field="received_from_position" value="{{ $receivedFromPosition }}" aria-label="Received from position or office">
                                                    @else
                                                        {{ $receivedFromPosition }}
                                                    @endif
                                                </div>
                                                <div class="signature-field-label">Position/Office</div>
                                            </div>
                                            <div class="signature-field">
                                                <div class="signature-field-line">
                                                    @if ($isPreview)
                                                        <input class="signature-edit" type="text" maxlength="255" data-report-field="received_from_date" value="{{ $receivedFromDate }}" aria-label="Received from date">
                                                    @else
                                                        {{ $receivedFromDate }}
                                                    @endif
                                                </div>
                                                <div class="signature-field-label">Date</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        Received by:
                                        <div class="signature-name">
                                            @if ($isPreview)
                                                <input class="signature-edit" type="text" maxlength="255" data-report-field="received_by_name" value="{{ $preparedBy }}" aria-label="Received by name">
                                            @else
                                                {{ $preparedBy }}
                                            @endif
                                        </div>
                                        <div class="signature-line received"></div>
                                        <div class="signature-role">
                                            @if ($isPreview)
                                                <input class="signature-edit" type="text" maxlength="255" data-report-field="received_by_role" value="{{ $receivedByRole }}" aria-label="Received by role">
                                            @else
                                                {{ $receivedByRole }}
                                            @endif
                                        </div>
                                        <div class="date-line signature-date-line">
                                            @if ($isPreview)
                                                <input class="signature-edit" type="text" maxlength="255" data-report-field="received_by_date" value="{{ $receivedByDate }}" aria-label="Received by date">
                                            @else
                                                {{ $receivedByDate }}
                                            @endif
                                        </div>
                                        <div class="signature-note">Date</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
