<?php

use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\StockOutHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('groups the sample stock-out data by item and calendar month', function () {
    $grouped = app(StockOutHistoryService::class)->groupMonthlyUsageFromCsv(
        base_path('docs/sample-stock-out-data.csv')
    );

    expect($grouped)->toHaveCount(70)
        ->and($grouped['Bond Paper A4'])->toBe([
            '2026-06' => 120,
            '2026-07' => 135,
            '2026-08' => 150,
            '2026-09' => 165,
        ])
        ->and($grouped['Teacher Desk'])->toBe([
            '2026-06' => 2,
            '2026-07' => 3,
            '2026-08' => 4,
            '2026-09' => 5,
        ]);
});

test('adds repeated stock-outs, keeps months and items separate, and ignores invalid or excluded records', function () {
    $path = tempnam(sys_get_temp_dir(), 'stock-out-history-');
    file_put_contents($path, implode(PHP_EOL, [
        'item_name,quantity_issued,stock_out_date,category,movement_type,status',
        'Bond Paper,10,2025-06-02,Learning Resources,stock_out,completed',
        'Bond Paper,15,2025-06-20,Learning Resources,stock_out,completed',
        'Bond Paper,7,2025-07-05,Learning Resources,stock_out,completed',
        'Printer Ink,3,2025-06-08,Learning Resources,consumable_issuance,approved',
        'Printer Ink,4,2025-07-08,Learning Resources,consumable_issuance,approved',
        'Bond Paper,99,2025-06-09,Learning Resources,transfer,completed',
        'Bond Paper,99,2025-06-10,Learning Resources,return,completed',
        'Bond Paper,99,2025-06-11,Learning Resources,stock_in,completed',
        'Bond Paper,99,2025-06-12,Learning Resources,stock_out,pending',
        'Bond Paper,0,2025-06-13,Learning Resources,stock_out,completed',
        'Bond Paper,-2,2025-06-14,Learning Resources,stock_out,completed',
        'Bond Paper,nope,2025-06-15,Learning Resources,stock_out,completed',
        'Bond Paper,5,not-a-date,Learning Resources,stock_out,completed',
        ',5,2025-06-16,Learning Resources,stock_out,completed',
    ]) . PHP_EOL);

    $stockMovementCount = StockMovement::count();
    $transactionCount = Transaction::count();

    try {
        $grouped = app(StockOutHistoryService::class)->groupMonthlyUsageFromCsv($path);
    } finally {
        unlink($path);
    }

    expect($grouped)->toBe([
        'Bond Paper' => [
            '2025-06' => 25,
            '2025-07' => 7,
        ],
        'Printer Ink' => [
            '2025-06' => 3,
            '2025-07' => 4,
        ],
    ])
        ->and(StockMovement::count())->toBe($stockMovementCount)
        ->and(Transaction::count())->toBe($transactionCount);
});

test('requires an existing file and the required CSV columns', function () {
    $service = app(StockOutHistoryService::class);

    expect(fn () => $service->groupMonthlyUsageFromCsv(base_path('docs/missing-stock-outs.csv')))
        ->toThrow(InvalidArgumentException::class);

    $path = tempnam(sys_get_temp_dir(), 'stock-out-header-');
    file_put_contents($path, "item_name,quantity_issued\nBond Paper,10\n");

    try {
        expect(fn () => $service->groupMonthlyUsageFromCsv($path))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        unlink($path);
    }
});
