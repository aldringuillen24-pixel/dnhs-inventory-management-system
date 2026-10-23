<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inspection and Acceptance Report - {{ $inspection->iar_number }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 1in 14mm 20mm;
        }

        * { box-sizing: border-box; }
        body {
            color: #000;
            font-family: "Times New Roman", serif;
            font-size: 10pt;
            line-height: 1.12;
            margin: 0;
        }

        .letterhead { width: 100%; text-align: center; }
        .iar-page { position: relative; padding-top: 0.15in; }
        .rule { border-top: 1px solid #000; margin: 3mm 0 0; }
        .appendix { font-size: 10pt; font-style: italic; font-weight: bold; text-align: right; margin-top: 1mm; }
        h1 { font-size: 10pt; margin: 2mm 0 3mm; text-align: center; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .entity-row { margin-bottom: 3mm; }
        .entity-row td { border: 0; padding: 0 1mm; }
        .entity-label { font-weight: bold; white-space: nowrap; }
        .line-value { border-bottom: 1px solid #000; min-height: 5mm; padding: 0 1mm 1mm; }
        .entity-name { width: 57%; }
        .fund-cluster { width: 43%; }

        .details td { border: 1px solid #000; height: 5.5mm; padding: 0.5mm 1mm; }
        .details .label { font-weight: bold; white-space: nowrap; }
        .details .left-label { width: 23%; }
        .details .left-value { width: 51%; }
        .details .right-details { width: 26%; padding: 0.5mm 1.5mm !important; }
        .details .right-details-table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .details .right-details-table td { border: 0; height: 5mm; padding: 0.25mm 0; }
        .details .right-details-label { width: 42%; font-weight: bold; white-space: nowrap; }
        .details .right-details-value { width: 58%; border-bottom: 1px solid #000 !important; padding-left: 1mm !important; }
        .items { margin-top: 0; }
        .items th, .items td { border: 1px solid #000; padding: 1.2mm; }
        .items thead th { border-top: 0; }
        .items th { font-size: 10pt; height: 8mm; text-align: center; }
        .items td { vertical-align: top; }
        .items .stock { width: 23%; }
        .items .description { width: 51%; }
        .items .unit { width: 13%; }
        .items .quantity { width: 13%; }
        .items tbody tr.item-row { page-break-inside: avoid; }
        .items tbody tr.item-row td { min-height: 22mm; }
        .items .unit, .items .quantity { text-align: center; }
        .items .nothing-follows td { height: 6mm; padding: 0.5mm; text-align: center; font-size: 10pt; font-weight: bold; }

        .signatures { margin-top: 0; page-break-inside: avoid; }
        .signatures th { border: 1px solid #000; font-size: 10pt; font-style: italic; height: 5mm; }
        .signatures thead th { border-top: 0; }
        .signatures td { border: 1px solid #000; padding: 1.5mm; vertical-align: top; }
        .inspection-cell { width: 51%; }
        .acceptance-cell { width: 49%; }
        .date-line { margin-bottom: 2mm; }
        .checkbox { border: 1px solid #000; display: inline-block; height: 4mm; margin: 0 2mm 0 1mm; vertical-align: middle; width: 4mm; }
        .checked { background: #000; }
        .inspection-statement { display: inline-block; text-align: center; vertical-align: middle; width: 82%; }
        .committee { margin: 2mm auto 0; page-break-inside: avoid; text-align: center; width: 78%; }
        .signature-line { border-bottom: 1px solid #000; height: 5mm; }
        .signatory-name { font-weight: bold; margin-top: 0.5mm; text-transform: uppercase; }
        .signatory-designation { font-size: 10pt; }
        .acceptance-option { margin: 2mm 0; }
        .remarks { color: #b22222; font-size: 10pt; font-weight: bold; margin: 5mm 2mm 1mm; text-align: center; }
        .custodian { margin: 10mm auto 0; page-break-inside: avoid; text-align: center; width: 78%; }

    </style>
</head>
<body>
<div class="iar-page">
    @php
        $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('F d, Y') : '';
        $committee = [
            [$inspection->committee_member_1_name, $inspection->committee_member_1_designation],
            [$inspection->committee_member_2_name, $inspection->committee_member_2_designation],
            [$inspection->committee_member_3_name, $inspection->committee_member_3_designation],
        ];
    @endphp

    @include('pdfs.components.rfq-header')
    <div class="rule"></div>
    <div class="appendix">Appendix 62</div>
    <h1>INSPECTION AND ACCEPTANCE REPORT</h1>

    <table class="entity-row">
        <tr>
            <td class="entity-name">
                <span class="entity-label">Entity Name:</span>
                <span class="line-value">{{ $inspection->entity_name }}</span>
            </td>
            <td class="fund-cluster">
                <span class="entity-label">Fund Cluster:</span>
                <span class="line-value">{{ $inspection->fund_cluster }}</span>
            </td>
        </tr>
    </table>

    <table class="details">
        <tbody>
            <tr>
                <td class="label left-label">Supplier:</td>
                <td class="left-value">{{ $inspection->supplier_name }}</td>
                <td class="right-details" rowspan="4">
                    <table class="right-details-table">
                        <tbody>
                            <tr>
                                <td class="right-details-label">IAR No.:</td>
                                <td class="right-details-value">{{ $inspection->iar_number }}</td>
                            </tr>
                            <tr>
                                <td class="right-details-label">Date:</td>
                                <td class="right-details-value">{{ $formatDate($inspection->iar_date) }}</td>
                            </tr>
                            <tr>
                                <td class="right-details-label">Invoice No.:</td>
                                <td class="right-details-value">{{ $inspection->invoice_number }}</td>
                            </tr>
                            <tr>
                                <td class="right-details-label">Date:</td>
                                <td class="right-details-value">{{ $formatDate($inspection->invoice_date) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="label">Contract Title:</td>
                <td>{{ $inspection->contract_title }}</td>
            </tr>
            <tr>
                <td class="label">Requisitioning Office/Dept.:</td>
                <td>{{ $inspection->requesting_office }}</td>
            </tr>
            <tr>
                <td class="label">Responsibility Center Code:</td>
                <td>{{ $inspection->responsibility_center_code }}</td>
            </tr>
        </tbody>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="stock">Stock/Property No.</th>
                <th class="description">Description</th>
                <th class="unit">Unit</th>
                <th class="quantity">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inspection->items as $item)
                <tr class="item-row">
                    <td class="stock">{{ $item->stock_property_number }}</td>
                    <td class="description">{!! nl2br(e($item->description)) !!}</td>
                    <td class="unit">{{ $item->unit }}</td>
                    <td class="quantity">{{ number_format($item->quantity) }}</td>
                </tr>
            @endforeach
            <tr class="nothing-follows">
                <td colspan="4">***NOTHING FOLLOWS***</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <thead>
            <tr>
                <th class="inspection-cell">INSPECTION</th>
                <th class="acceptance-cell">ACCEPTANCE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="inspection-cell">
                    <div class="date-line">Date Inspected: <strong>{{ $formatDate($inspection->inspection_date) }}</strong></div>
                    <div>
                        <span class="checkbox {{ $inspection->inspection_verified ? 'checked' : '' }}"></span>
                        <span class="inspection-statement">Inspected, verified and found in order as to quantity and specifications</span>
                    </div>
                    @foreach ($committee as [$name, $designation])
                        <div class="committee">
                            <div class="signature-line"></div>
                            <div class="signatory-name">{{ $name }}</div>
                            <div class="signatory-designation">{{ $designation ?: 'Inspection Officer/Inspection Committee' }}</div>
                        </div>
                    @endforeach
                </td>
                <td class="acceptance-cell">
                    <div class="date-line">Date Received: <strong>{{ $formatDate($inspection->receipt_date) }}</strong></div>
                    <div class="acceptance-option">
                        <span class="checkbox {{ $inspection->acceptance_status === 'complete' ? 'checked' : '' }}"></span> Complete
                    </div>
                    <div class="acceptance-option">
                        <span class="checkbox {{ $inspection->acceptance_status === 'partial' ? 'checked' : '' }}"></span>
                        Partial (pls. specify quantity)
                    </div>
                    @if ($inspection->acceptance_remarks)
                        <div class="remarks">{{ $inspection->acceptance_remarks }}</div>
                    @endif
                    <div class="custodian">
                        <div class="signature-line"></div>
                        <div class="signatory-name">{{ $inspection->custodian_signature_name ?: ($inspection->status === 'approved' || $inspection->status === 'posted' ? $inspection->custodian_name : '') }}</div>
                        <div class="signatory-designation">{{ $inspection->custodian_designation ?: 'School Property Custodian' }}</div>
                        @if ($inspection->custodian_signature_name && $inspection->completed_at)
                            <div class="signatory-designation">Electronically signed {{ $inspection->completed_at->format('F d, Y g:i A') }}</div>
                        @endif
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
