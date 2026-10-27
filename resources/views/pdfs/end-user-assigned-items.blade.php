<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Assigned Items</title>
    <style>
        @page {
            margin: 32px;
        }

        body {
            color: #111;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 8px;
            text-align: center;
        }

        .details {
            margin-bottom: 14px;
            text-align: center;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #111;
            padding: 7px 6px;
        }

        th {
            font-weight: bold;
            text-align: center;
        }

        td.quantity {
            text-align: right;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .empty {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>My Assigned Items</h1>
    <div class="details">
        Holder: {{ $holder }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Description</th>
                <th>Inventory Item No.</th>
                <th>Date Issued</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="quantity">{{ $row['quantity'] ?? 0 }}</td>
                    <td>{{ $row['unit'] ?? '—' }}</td>
                    <td>{{ $row['item_name'] ?? 'Unknown item' }}</td>
                    <td>{{ $row['inventory_item_no'] ?? '—' }}</td>
                    <td>{{ isset($row['date']) && $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('M d, Y') : '—' }}</td>
                    <td>{{ $row['status'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No records match the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
