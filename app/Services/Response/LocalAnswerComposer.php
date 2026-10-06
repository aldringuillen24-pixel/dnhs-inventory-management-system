<?php

namespace App\Services\Response;

/**
 * The deterministic answer path: no provider, no network, no variation.
 *
 * This is what runs whenever generation is not permitted, the key is missing,
 * the provider fails, or the grounding validator rejects what came back. It is
 * the floor rather than the exception, which is why every question that could
 * be answered before this refactor still can be.
 *
 * It owns the fact-packet formatter outright rather than delegating, so the
 * generation layer can depend on it without depending back on the service that
 * drives generation.
 */
class LocalAnswerComposer
{
    /**
     * Render an answer packet as plain language, deterministically.
     *
     * @param  array<string, mixed>  $packet
     */
public function compose(array $packet): string
    {
        $answer = $packet['answer'] ?? [];
        $items = $answer['items'] ?? [];
        if ($items !== []) {
            $lines = collect($items)->map(function (array $item): ?string {
                $parts = [];
                if (isset($item['item_name']) && is_string($item['item_name'])) {
                    $parts[] = $item['item_name'];
                }
                if (isset($item['available_quantity']) && is_numeric($item['available_quantity'])) {
                    $parts[] = $item['available_quantity'] . ' ' . ($item['unit'] ?? 'units') . ' available';
                } elseif (isset($item['quantity']) && is_numeric($item['quantity'])) {
                    $parts[] = 'quantity: ' . $item['quantity'] . ' ' . ($item['unit'] ?? 'units');
                }
                if (isset($item['pending_quantity']) && is_numeric($item['pending_quantity'])) {
                    $parts[] = 'pending demand: ' . $item['pending_quantity'];
                }
                if (isset($item['inventory_ids']) && is_array($item['inventory_ids'])) {
                    $parts[] = 'inventory records: ' . implode(', ', $item['inventory_ids']);
                }
                if (isset($item['calculated_at']) && is_string($item['calculated_at'])) {
                    $parts[] = 'calculated at: ' . $item['calculated_at'];
                }
                if (! empty($item['discrepancies']) && is_array($item['discrepancies'])) {
                    $parts[] = 'discrepancy: ' . implode('; ', $item['discrepancies']);
                }
                if (isset($item['forecasted_stockout']) && is_scalar($item['forecasted_stockout'])) {
                    $parts[] = 'forecast: ' . $item['forecasted_stockout'];
                }

                return $parts === [] ? null : implode('; ', $parts) . '.';
            })->filter()->values();

            if ($lines->isNotEmpty()) {
                return $lines->implode("\n");
            }
        }

        if (isset($answer['total_items'], $answer['available_items'], $answer['assigned_items'])) {
            return "The approved inventory summary contains {$answer['total_items']} total items, {$answer['available_items']} available, and {$answer['assigned_items']} assigned.";
        }

        foreach ([
            'status_items',
            'assignment_records',
            'maintenance_records',
            'disposal_records',
            'ready_to_dispose_items',
            'purchase_history',
        ] as $key) {
            if (! empty($answer[$key])) {
                return collect($answer[$key])->map(function (array $record): string {
                    $facts = array_filter([
                        $record['item_name'] ?? null,
                        isset($record['status']) ? 'status: ' . $record['status'] : null,
                        isset($record['assigned_to']) ? 'assigned to: ' . $record['assigned_to'] : null,
                        isset($record['quantity']) ? 'quantity: ' . $record['quantity'] . ' ' . ($record['unit'] ?? 'units') : null,
                        isset($record['issue']) ? 'recorded issue: ' . $record['issue'] : null,
                        isset($record['notes']) ? 'recorded notes: ' . $record['notes'] : null,
                        isset($record['date']) ? 'date: ' . $record['date'] : null,
                    ]);

                    return implode('; ', $facts) . '.';
                })->implode("\n");
            }
        }

        return 'Based on the calculated inventory data, no additional explanation is available.';
    }
}
