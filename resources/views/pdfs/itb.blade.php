@php
    $metadata = $metadata ?? [];
    $items = $items ?? [];
    $formatDate = static fn ($value) => $value
        ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y')
        : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invitation to Bid - Disposal of Unserviceable Properties</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5in 0.55in 1.15in;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Calibri, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.35;
        }

        .rfq-header {
            position: fixed !important;
            top: -1.1in !important;
        }

        .itb-footer {
            position: fixed !important;
            bottom: -0.35in !important;
        }

        .itb-title {
            margin: 0 0 3px;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
        }

        .itb-subtitle {
            margin: 0;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
        }

        .itb-intro {
            margin: 16px 0 12px;
            text-align: justify;
            font-size: 10pt;
        }

        .itb-items {
            width: 100%;
            margin: 0 0 16px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10pt;
        }

        .itb-items th,
        .itb-items td {
            border: 1px solid #000;
            padding: 5px 7px;
            vertical-align: top;
        }

        .itb-items th {
            text-align: left;
            font-weight: bold;
        }

        .itb-items .number {
            text-align: right;
        }

        .itb-items tr {
            page-break-inside: avoid;
        }

        .itb-clause {
            margin: 0 0 10px;
            page-break-inside: avoid;
            text-align: justify;
            font-size: 10pt;
        }

        .itb-clause-compact {
            font-size: 10pt;
        }

        .itb-clause-continuation {
            display: block;
            margin-left: 0.25in;
        }

        .itb-signatories {
            width: 100%;
            margin-top: 56px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11pt;
            line-height: 1.35;
            page-break-inside: avoid;
        }

        .itb-signatories td {
            width: 50%;
            padding: 0;
            vertical-align: top;
            text-align: left;
        }

        .itb-signatories strong {
            display: inline-block;
            margin-top: 40px;
        }

        .itb-signatories tr:first-child {
            height: 120px;
        }

        .itb-signatories tr:nth-child(2) {
            height: 90px;
        }
    </style>
</head>
<body>
    @include('pdfs.components.itb-header')

    <main>
        <h1 class="itb-title">INVITATION TO BID</h1>
        <p class="itb-subtitle">Disposal of Unserviceable Properties Through Public Bidding</p>

        <p class="itb-intro">
            1. The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the
            <strong><u>{{ $metadata['bid_description'] ?? 'public bidding for one (1) lot of Disposal of various unserviceable properties and equipment' }}</u></strong>
            as indicated below:
        </p>

        <table class="itb-items">
            <thead>
                <tr>
                    <th style="width: 10%;">Item No.</th>
                    <th style="width: 50%;">Item Description</th>
                    <th class="number" style="width: 18%;">Quantity</th>
                    <th class="number" style="width: 22%;">Floor Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $index => $item)
                    @php
                        $quantity = (float) data_get($item, 'quantity', 0);
                        $unitPrice = (float) data_get($item, 'unit_price', data_get($item, 'unit_cost', 0));
                        $floorPrice = data_get($item, 'floor_price', $quantity * $unitPrice);
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ data_get($item, 'item_name', data_get($item, 'description', '')) }}</td>
                        <td class="number">{{ rtrim(rtrim(number_format($quantity, 2, '.', ','), '0'), '.') }}</td>
                        <td class="number">{{ number_format((float) $floorPrice, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="itb-clause itb-clause-compact">
            2. Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before
            {{ $formatDate($metadata['submission_deadline'] ?? null) }}
            at {{ $metadata['school_name'] ?? '' }}, {{ $metadata['school_address'] ?? '' }}, and look for {{ $metadata['secretariat_name'] ?? '' }}.
        </p>

        <div class="itb-clause itb-clause-compact">
            3. Attach to your Bid either the following:
            <ul style="margin: 3px 0 0 0.5in; padding-left: 16px;">
                <li>{{ $metadata['corporation_requirement'] ?? 'SEC Registration Certificate for Corporation; or' }}</li>
                <li>{{ $metadata['proprietorship_requirement'] ?? 'DTI Registration Certificate for Sole Proprietorship;' }}</li>
                <li>{{ $metadata['business_permit_requirement'] ?? 'Valid Business Permit.' }}</li>
                @foreach ($metadata['additional_requirements'] ?? [] as $requirement)
                    <li>{{ $requirement }}</li>
                @endforeach
            </ul>
        </div>

        <p class="itb-clause itb-clause-compact">
            4. Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.
        </p>

        <p class="itb-clause itb-clause-compact">
            5. A complete set of Bid Form may be acquired by interested bidder from
            {{ $formatDate($metadata['form_acquisition_from'] ?? null) }} to
            {{ $formatDate($metadata['form_acquisition_to'] ?? null) }}
            at School Head office, {{ $metadata['school_name'] ?? '' }}.
        </p>

        <div class="itb-clause itb-clause-compact">
            6. For further information, please refer to:
            <p style="margin: 5px 0 0 0.25in;">
                <strong>{{ $metadata['secretariat_name'] ?? '' }}</strong><br>
                {{ $metadata['secretariat_role'] ?? '' }}<br>
                {{ $metadata['secretariat_role_detail'] ?? '' }}<br>
                {{ $metadata['contact_numbers'] ?? '' }}
            </p>
        </div>

        <p class="itb-clause itb-clause-compact">
            7. Inspection of Unserviceable Properties are available for inspection on
            {{ $formatDate($metadata['inspection_day'] ?? null) }} @
            {{ $metadata['inspection_time'] ?? '' }}, {{ $metadata['school_name'] ?? '' }}.
        </p>

        <div class="itb-clause itb-clause-compact">
            8. Schedule of Public Auction:
            <span class="itb-clause-continuation">
                The public auction shall be conducted on an “as is, where is” basis on
                {{ $formatDate($metadata['auction_date'] ?? null) }} at
                {{ $metadata['auction_time'] ?? '' }}, {{ $metadata['school_name'] ?? '' }}.
            </span>
        </div>

        <div class="itb-clause itb-clause-compact">
            9. Submission of Bid:
            <span class="itb-clause-continuation">
                The Bid must be submitted in three {{ $metadata['bid_submission_copies'] ?? '(3) (original copy (1) and 2 photocopies)' }} using the prescribed Bid Form.
            </span>
        </div>

        <p class="itb-clause" style="text-align: left;">
            10. Deadline of submission of Bid is on {{ $formatDate($metadata['submission_deadline'] ?? null) }}
            {{ $metadata['submission_time'] ?? '' }}. Bid submitted beyond said deadline shall not be accepted.
        </p>

        <p class="itb-clause" style="text-align: left;">
            11. Opening of Bids at {{ $formatDate($metadata['bid_opening_date'] ?? null) }}
            {{ $metadata['bid_opening_time'] ?? '' }} in the presence of BAC and Division.
        </p>

        <p class="itb-clause">
            12. Disposal Committee.
        </p>

        <div class="itb-clause">
            13. Awarding of Bids:
            <span class="itb-clause-continuation">Award shall be made to the highest bidder.</span>
        </div>

        <div class="itb-clause">
            14. Pull-out/Taking of Awarded Items:
            <span class="itb-clause-continuation">
                The winning bidder shall be given {{ $metadata['pullout_period'] ?? 'seven (7) calendar days' }} from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.
            </span>
        </div>

        <table class="itb-signatories">
            <tbody>
                @foreach (array_chunk($metadata['signatories'] ?? [], 2) as $row)
                    <tr>
                        @foreach ($row as $signatory)
                            <td>
                                <strong>{{ $signatory['name'] ?? '' }}</strong><br>
                                {{ $signatory['title'] ?? '' }}
                                @if (!empty($signatory['role']))
                                    <br>{{ $signatory['role'] }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>

    @include('pdfs.components.itb-footer')
</body>
</html>
