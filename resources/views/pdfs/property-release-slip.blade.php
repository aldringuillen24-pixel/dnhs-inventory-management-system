<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Release Slip - {{ $docNo }}</title>
    <style>
        @page { size: letter portrait; margin: 1in; }
        body { color: #000; font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.15; }
        p { margin: 0 0 2pt; }
        .title { font-weight: bold; text-align: center; margin-bottom: 12pt; }
        .section { font-weight: bold; margin-top: 2pt; }
        .recipient-information { margin-top: 12pt; }
        .authorization { margin-top: 12pt; }
        .purpose { margin-bottom: 8pt; }
        table.items { border-collapse: collapse; table-layout: fixed; width: 100%; margin: 4pt 0 8pt; }
        table.items th, table.items td { border: 1px solid #000; padding: 4pt 3pt; vertical-align: top; }
        table.items th { text-align: center; font-size: 9pt; }
        table.items tbody td { text-align: center; }
        table.items tbody tr.total-row td { text-align: left; }
        table.items tbody tr.total-row td.number { text-align: right; }
        table.items td { height: 22pt; }
        .number { text-align: right; white-space: nowrap; }
        .total-label { font-weight: bold; }
        .total-amount { float: right; }
        .signature-block { margin-top: 8pt; page-break-inside: avoid; }
        .signature-block.recipient { width: 40%; margin-left: 70%; }
        .signature-block p { margin-bottom: 2pt; }
    </style>
</head>
<body>
    @php
        $slipFields = $slipFields ?? [];
        $releasePurposes = ['Official Use (Off-site)', 'Transfer / Relocation', 'Repair / Maintenance', 'Disposal'];
        // Underline only a filled value; a blank stays as plain underscores so
        // the field never renders an underline beneath underscores (double line).
        $fieldLine = fn ($value, $blank) => ! empty($value) ? '<u>'.e($value).'</u>' : $blank;
    @endphp

    <p class="title">PROPERTY RELEASE SLIP</p>

    <p><strong>Document Control No.</strong> <u>{{ $docNo }}</u></p>
    <p><strong>Date:</strong> <u>{{ $documentDate }}</u></p>

    <p class="section recipient-information">RECIPIENT INFORMATION</p>
    <p>Name of Requestor / Recipient: <u>{{ $recipientName }}</u></p>
    @if (!empty($transferFromName))
        <p>Transferred From: <u>{{ $transferFromName }}</u></p>
    @endif
    <p>Department / Unit: {!! $fieldLine($slipFields['department_unit'] ?? null, '_______________________') !!}</p>
    <p>Designation: {!! $fieldLine($slipFields['designation'] ?? null, '___________________________') !!}</p>
    <p class="purpose">
        Purpose of Release:
        @foreach ($releasePurposes as $purpose)
            [{{ ($slipFields['purpose'] ?? '') === $purpose ? 'X' : ' ' }}] {{ $purpose }}@if (!$loop->last)&nbsp;&nbsp;@endif
        @endforeach
    </p>

    <p class="section">ITEM DETAILS</p>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 11%;">ITEM NO.</th>
                <th style="width: 16%;">PROPERTY</th>
                <th style="width: 20%;">ITEM DESCRIPTION &amp; SERIAL NO.</th>
                <th style="width: 10%;">QTY</th>
                <th style="width: 11%;">UNIT COST</th>
                <th style="width: 14%;">AMOUNT</th>
                <th style="width: 18%;">CONDITION UPON RELEASE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $line['item_no'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="number">{{ number_format($line['qty']) }}</td>
                    <td class="number">PHP {{ number_format((float) $line['unit_cost'], 2) }}</td>
                    <td class="number">PHP {{ number_format((float) $line['amount'], 2) }}</td>
                    <td>{{ $line['condition'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="6" class="total-label">TOTAL: <span class="total-amount">PHP {{ number_format((float) $total, 2) }}</span></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <p class="section">RETURN INFORMATION (If temporary release)</p>
    <p>Expected Return Date: {!! $fieldLine($expectedReturnDate ?? null, '_________________') !!}</p>
    <p>Actual Return Date: {!! $fieldLine($actualReturnDate ?? null, '___________________') !!}</p>

    <p class="section authorization">AUTHORIZATION &amp; ACKNOWLEDGEMENT</p>
    <p>Recipient Acknowledgement:</p>
    <p>I hereby acknowledge receipt of the above-listed items(s) in good order and condition unless otherwise stated. I accept responsibility for the proper care, safe custody, and timely return of these properties.</p>

    <div class="signature-block">
        <p>Released By (Property Custodian):</p>
        <p>Signature: {!! $fieldLine($slipFields['released_by_signature'] ?? null, '________________') !!}</p>
        <p>Printed Name: <u>{{ $slipFields['released_by_name'] ?? $custodianName }}</u></p>
        <p>Date: {!! $fieldLine($slipFields['released_by_date'] ?? null, '___________________') !!}</p>
    </div>

    <div class="signature-block recipient">
        <p>Received By (Recipient):</p>
        <p>Signature: {!! $fieldLine($slipFields['received_by_signature'] ?? null, '______________') !!}</p>
        <p>Printed Name: <u>{{ $slipFields['received_by_name'] ?? $recipientName }}</u></p>
        <p>Date: {!! $fieldLine($slipFields['received_by_date'] ?? null, '___________________') !!}</p>
    </div>

    <div class="signature-block">
        <p>Approved By (Department Head / Supervisor)</p>
        <p>Signature: {!! $fieldLine($slipFields['approved_by_signature'] ?? null, '________________') !!}</p>
        <p>Printed Name: {!! $fieldLine($slipFields['approved_by_name'] ?? null, '_____________') !!}</p>
        <p>Date: {!! $fieldLine($slipFields['approved_by_date'] ?? null, '___________________') !!}</p>
    </div>
</body>
</html>
