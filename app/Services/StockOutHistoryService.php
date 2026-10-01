<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use SplFileObject;

class StockOutHistoryService
{
    private const REQUIRED_COLUMNS = [
        'item_name',
        'quantity_issued',
        'stock_out_date',
        'category',
    ];

    private const TYPE_COLUMNS = [
        'movement_type',
        'movement',
        'record_type',
        'event_type',
    ];

    private const INCLUDED_TYPES = [
        'stock out',
        'stockout',
        'issue',
        'issuance',
        'consumable issue',
        'consumable issuance',
    ];

    private const COMPLETED_STATUSES = [
        'complete',
        'completed',
        'approved',
        'approved complete',
        'approved completed',
    ];

    public function groupMonthlyUsageFromCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("CSV file does not exist or cannot be read: {$path}");
        }

        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $header = $file->fgetcsv();
        if (! is_array($header)) {
            throw new InvalidArgumentException('CSV file does not contain a header row.');
        }

        $columns = $this->normaliseHeaders($header);
        $missingColumns = array_diff(self::REQUIRED_COLUMNS, $columns);
        if ($missingColumns !== []) {
            throw new InvalidArgumentException('CSV is missing required columns: ' . implode(', ', $missingColumns));
        }

        $columnIndexes = array_flip($columns);
        $grouped = [];

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $record = $this->recordFromRow($row, $columnIndexes);
            if ($record === null || ! $this->isIncludedRecord($record, $columnIndexes)) {
                continue;
            }

            $itemKey = strtolower($record['item_name']);
            if (! isset($grouped[$itemKey])) {
                $grouped[$itemKey] = [
                    'item_name' => $record['item_name'],
                    'months' => [],
                ];
            }

            $grouped[$itemKey]['months'][$record['month']] = $this->normaliseNumber(
                ($grouped[$itemKey]['months'][$record['month']] ?? 0) + $record['quantity']
            );
        }

        $result = [];
        foreach ($grouped as $item) {
            ksort($item['months'], SORT_STRING);
            $result[$item['item_name']] = $item['months'];
        }

        return $result;
    }

    private function normaliseHeaders(array $header): array
    {
        return array_map(function ($value): string {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

            return strtolower(trim($value));
        }, $header);
    }

    private function recordFromRow(array $row, array $columnIndexes): ?array
    {
        $itemName = trim((string) ($row[$columnIndexes['item_name']] ?? ''));
        $dateValue = trim((string) ($row[$columnIndexes['stock_out_date']] ?? ''));
        $quantityValue = trim((string) ($row[$columnIndexes['quantity_issued']] ?? ''));
        $category = trim((string) ($row[$columnIndexes['category']] ?? ''));

        if ($itemName === '' || $category === '' || $dateValue === '' || $quantityValue === '') {
            return null;
        }

        $quantity = (float) $quantityValue;
        if (! is_numeric($quantityValue) || ! is_finite($quantity) || $quantity <= 0) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $dateValue);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($date === false || $date->format('Y-m-d') !== $dateValue) {
            return null;
        }

        return [
            'item_name' => $itemName,
            'quantity' => $this->normaliseNumber($quantity),
            'month' => $date->format('Y-m'),
            'row' => $row,
        ];
    }

    private function isIncludedRecord(array $record, array $columnIndexes): bool
    {
        foreach (self::TYPE_COLUMNS as $column) {
            if (! array_key_exists($column, $columnIndexes)) {
                continue;
            }

            $type = $this->normaliseValue($record['row'][$columnIndexes[$column]] ?? '');
            if ($type !== '' && ! in_array($type, self::INCLUDED_TYPES, true)) {
                return false;
            }
        }

        if (array_key_exists('status', $columnIndexes)) {
            $status = $this->normaliseValue($record['row'][$columnIndexes['status']] ?? '');
            if ($status !== '' && ! in_array($status, self::COMPLETED_STATUSES, true)) {
                return false;
            }
        }

        return true;
    }

    private function normaliseValue(string $value): string
    {
        return preg_replace('/[\-_]+/', ' ', strtolower(trim($value))) ?? strtolower(trim($value));
    }

    private function normaliseNumber(float $value): int|float
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : $value;
    }

    private function isEmptyRow(array $row): bool
    {
        return count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) === 0;
    }
}
