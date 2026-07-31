<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Account Slips</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 12px;
        }

        .sheet {
            width: 100%;
            page-break-after: always;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .block {
            min-height: 110px;
            padding: 10px;
            border: 1px dashed #111827;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            text-align: center;
        }

        .label {
            font-weight: bold;
            margin-top: 4px;
        }

        .value {
            margin-bottom: 4px;
            word-break: break-all;
        }

        .note {
            font-size: 9px;
            margin-top: 5px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    @php
        $chunkedUsers = $users->chunk(10);
    @endphp

    @foreach($chunkedUsers as $chunk)
        <div class="sheet">
            @foreach($chunk as $user)
                <div class="block">
                    <div class="title">Account Slip</div>
                    <div class="label">Username</div>
                    <div class="value">{{ $user->username }}</div>

                    <div class="label">Password</div>
                    <div class="value">{{ $user->temporary_password ?? 'Not available' }}</div>

                    <div class="note">Visit {{ url('/') }} to login and change your password after your first sign in.</div>
                    <div class="note">Do not share this slip with anyone.</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
