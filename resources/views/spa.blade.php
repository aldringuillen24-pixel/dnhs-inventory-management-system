<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/dnhs_school_logo.svg') }}">
    <title>DNHS Inventory Management System</title>
    @vite(['resources/css/app.css', 'resources/js/spa/main.js'])
    <script>
        // Light mode only: clear any previously saved dark preference.
        (function () {
            try { localStorage.removeItem('theme'); } catch (e) {}
            document.documentElement.classList.remove('dark');
        })();
    </script>
</head>
<body>
    <div id="app"></div>
</body>
</html>
