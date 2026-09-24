@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Print QR Code</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select the labels to print for this inventory group.</p>
            </div>
            <button type="button" onclick="window.print()" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Print selected labels</button>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($items as $item)
                <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start gap-5">
                        <canvas id="qr-label-{{ $item->item_id }}" width="160" height="160" class="shrink-0"></canvas>
                        <div class="min-w-0 space-y-2">
                            <h2 class="font-semibold text-gray-900 dark:text-white">{{ $item->item_name }}</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $item->inventory_item_no }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->category->category_name ?? 'Uncategorized' }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @foreach ($items as $item)
                if (typeof QRCode !== 'undefined') {
                    QRCode.toCanvas(document.getElementById('qr-label-{{ $item->item_id }}'), @js($item->qr_code), {
                        width: 160,
                        margin: 1,
                    });
                }
            @endforeach
        });
    </script>
@endsection
