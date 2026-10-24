<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Head Logs</title>
    <style>
        @page {
            margin: 14mm;
            size: landscape;
        }

        body {
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }

        @media screen {
            html {
                background: #e5e7eb;
            }

            body {
                margin: 0;
                padding: 16px;
            }

            .report-sheet {
                box-sizing: border-box;
                width: 11.69in;
                max-width: 100%;
                min-height: 8.27in;
                margin: 0 auto 16px;
                padding: 14mm;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
            }
        }

        h1 {
            margin: 0 0 4px;
            font-size: 17pt;
            text-align: center;
        }

        .subtitle {
            margin: 0 0 14px;
            text-align: center;
            font-size: 9pt;
        }

        .metadata {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }

        .metadata td {
            padding: 3px 0;
            vertical-align: top;
        }

        .metadata td:first-child {
            width: 105px;
            font-weight: bold;
        }

        table.logs {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .logs thead {
            display: table-header-group;
        }

        .logs tr {
            page-break-inside: avoid;
        }

        .logs th,
        .logs td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .logs th {
            font-weight: bold;
        }

        .logs .date {
            width: 14%;
        }

        .logs .type {
            width: 14%;
        }

        .logs .action {
            width: 14%;
        }

        .logs .target {
            width: 20%;
        }

        .logs .details {
            width: 24%;
        }

        .logs .actor {
            width: 14%;
        }

        .empty {
            padding: 18px 6px !important;
            text-align: center !important;
        }
    </style>
</head>
<body>
  <main class="report-sheet">
    <h1>School Head Logs</h1>
    <p class="subtitle">Inventory and system activity record</p>

    <table class="metadata">
        <tr>
            <td>Log Types:</td>
            <td>{{ isset($logTypes) && count($logTypes) ? implode(', ', $logTypes) : 'All log types' }}</td>
        </tr>
        <tr>
            <td>Generated:</td>
            <td>{{ $generatedAt ?? now()->format('F j, Y g:i A') }}</td>
        </tr>
    </table>

    <table class="logs">
        <thead>
            <tr>
                <th class="date">Date</th>
                <th class="type">Log Type</th>
                <th class="action">Action</th>
                <th class="target">Item / Target</th>
                <th class="details">Details</th>
                <th class="actor">Actor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs ?? [] as $log)
                <tr>
                    <td>
                        @if (!empty($log['occurred_at'] ?? $log['created_at'] ?? null))
                            {{ \Illuminate\Support\Carbon::parse($log['occurred_at'] ?? $log['created_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $log['type'] ?? '—' }}</td>
                    <td>{{ $log['action'] ?? '—' }}</td>
                    <td>
                        {{ $log['item_name'] ?? $log['target_name'] ?? '—' }}
                        @if (!empty($log['reference']))
                            <br>{{ $log['reference'] }}
                        @endif
                    </td>
                    <td>{{ $log['details'] ?? $log['reason'] ?? '—' }}</td>
                    <td>{{ $log['actor_name'] ?? $log['actor'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No log records match the selected log types.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
  </main>
</body>
</html>
