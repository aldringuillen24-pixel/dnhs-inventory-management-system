<?php

namespace App\Support;

use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\AuditLedgerService;

/**
 * Row builder for the per-tab Transactions PDF export.
 *
 * Reuses the same Eloquent builders as
 * PropertyCustodianController::transactions() and a server-side mirror of
 * the SPA's matchesSearch(), so each tab exports exactly the rows its
 * screen table would show for the same search (and History date range).
 */
class TransactionsExportRows
{
    public function __construct(protected AuditLedgerService $auditLedgerService) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTab(string $tab, int $custodianId, string $search = '', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return match ($tab) {
            'transfers' => $this->transferRows($search),
            'returns' => $this->returnRows($search),
            'history' => $this->historyRows($search, $dateFrom, $dateTo),
            'ledger' => $this->ledgerRows($search),
            default => $this->requestRows($custodianId, $search),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requestRows(int $custodianId, string $search): array
    {
        $incoming = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username,email',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit,serial_number',
                'item.category:category_id,category_name',
                'requestedCategory:category_id,category_name,requires_serial_number',
            ])
            ->whereIn('status', ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT])
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        // Same stock-health enrichment as transactions(): matched units,
        // holds from OTHER requests, and free stock per row.
        $incoming->each(function (AssignmentRequest $request): void {
            if ($request->requested_item_name) {
                $matching = RequestableItemMatcher::candidates(
                    (string) $request->requested_item_name,
                    (int) $request->requested_category_id,
                    $request->requested_unit,
                )->filter(fn (Inventory $item): bool => $item->status === 'available' && (int) $item->quantity > 0)
                    ->values();

                $request->total_available_stock = (int) $matching->sum('quantity');
                $request->pending_hold_stock = max(0, RequestableItemMatcher::pendingQuantity(
                    (string) $request->requested_item_name,
                    (int) $request->requested_category_id,
                    $request->requested_unit,
                ) - (int) $request->quantity);
                $request->total_free_stock = max(0, $request->total_available_stock - $request->pending_hold_stock);

                return;
            }

            $item = $request->item;
            $request->total_available_stock = (int) ($item?->status === 'available' ? $item->quantity : 0);
            $request->pending_hold_stock = $item
                ? (int) AssignmentRequest::query()
                    ->where('status', 'waiting for approval')
                    ->where('item_id', $item->item_id)
                    ->where('id', '!=', $request->id)
                    ->sum('quantity')
                : 0;
            $request->total_free_stock = max(0, $request->total_available_stock - $request->pending_hold_stock);
        });

        $assignments = AssignmentRequest::query()
            ->with([
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status',
                'item.category:category_id,category_name',
            ])
            ->where('user_id', $custodianId)
            ->where('status', 'waiting for approval')
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $rows = [];

        foreach ($incoming as $request) {
            if (! $this->matches($search, [
                $this->personName($request->user),
                $request->user?->email,
                $request->user?->username,
                $request->requested_item_name ?? $request->item?->item_name,
                $request->item?->inventory_item_no,
                $this->categoryLine($request),
                $request->requestedCategory?->category_name,
                $request->quantity,
                $request->status,
                $request->notes,
                $request->requested_at,
            ])) {
                continue;
            }

            $pending = (int) ($request->pending_hold_stock ?? 0);
            $rows[] = [
                'requester' => $this->personName($request->user),
                'item' => $request->requested_item_name ?? $request->item?->item_name ?? 'Unknown item',
                'item_no' => $request->item?->inventory_item_no,
                'category' => $this->categoryLine($request),
                'qty' => (int) $request->quantity,
                'stock' => $pending > 0
                    ? ((int) ($request->total_free_stock ?? 0)).' free ('.$pending.' pending)'
                    : ((int) ($request->total_available_stock ?? $request->item?->quantity ?? 0)).' in stock',
                'requested_at' => $request->requested_at,
            ];
        }

        foreach ($assignments as $assignment) {
            if (! $this->matches($search, [
                $this->personName($assignment->targetUser),
                $assignment->item?->item_name,
                $assignment->item?->inventory_item_no,
                $assignment->quantity,
                $assignment->status,
                $assignment->requested_at,
            ])) {
                continue;
            }

            $rows[] = [
                'requester' => $this->personName($assignment->targetUser),
                'item' => $assignment->item?->item_name ?? 'Unknown item',
                'item_no' => $assignment->item?->inventory_item_no,
                'category' => $assignment->item?->category?->category_name ?? 'General',
                'qty' => (int) $assignment->quantity,
                'stock' => '—',
                'requested_at' => $assignment->requested_at,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transferRows(string $search): array
    {
        $transfers = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status',
                'item.category:category_id,category_name',
            ])
            ->where('status', 'waiting for custodian approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $rows = [];

        foreach ($transfers as $transfer) {
            if (! $this->matches($search, [
                $this->personName($transfer->user),
                $this->personName($transfer->targetUser),
                $transfer->item?->item_name,
                $transfer->item?->inventory_item_no,
                $transfer->quantity,
                $transfer->status,
                $transfer->notes,
                $transfer->requested_at,
            ])) {
                continue;
            }

            $rows[] = [
                'flow' => $this->personName($transfer->user).' → '.$this->personName($transfer->targetUser),
                'item' => $transfer->item?->item_name ?? 'Unknown item',
                'item_no' => $transfer->item?->inventory_item_no,
                'qty' => (int) $transfer->quantity,
                'requested_at' => $transfer->requested_at,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function returnRows(string $search): array
    {
        $returns = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,status,assigned_to_user_id',
            ])
            ->where('status', 'waiting for custodian approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $rows = [];

        foreach ($returns as $return) {
            if (! $this->matches($search, [
                $return->user?->full_name,
                $this->personName($return->user),
                $return->user?->username,
                $return->item?->item_name,
                $return->item?->inventory_item_no,
                $return->quantity,
                $return->status,
                $return->notes,
                $return->requested_at,
            ])) {
                continue;
            }

            $rows[] = [
                'user' => $return->user?->full_name ?? $this->personName($return->user),
                'item' => $return->item?->item_name ?? 'Unknown item',
                'item_no' => $return->item?->inventory_item_no,
                'qty' => (int) $return->quantity,
                'requested_at' => $return->requested_at,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(string $search, ?string $dateFrom, ?string $dateTo): array
    {
        $transactions = Transaction::query()
            ->with([
                'user:id,first_name,last_name,username',
                'fromUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,category_id',
                'item.category:category_id,category_name',
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $rows = [];

        foreach ($transactions as $transaction) {
            $day = substr((string) ($transaction->transaction_date ?? ''), 0, 10);
            if ($dateFrom && (! $day || $day < $dateFrom)) {
                continue;
            }
            if ($dateTo && (! $day || $day > $dateTo)) {
                continue;
            }
            if (! $this->matches($search, [
                $transaction->quantity,
                $transaction->item?->item_name,
                $transaction->item?->inventory_item_no,
                $transaction->fromUser ? $this->personName($transaction->fromUser) : 'Stockroom / Warehouse',
                $transaction->manual_recipient_name ?? $this->personName($transaction->user),
                $transaction->manual_department,
                $transaction->transaction_date,
            ])) {
                continue;
            }

            $rows[] = [
                'qty' => (int) $transaction->quantity,
                'item' => $transaction->item?->item_name ?? 'Unknown item',
                'item_no' => $transaction->item?->inventory_item_no,
                'from' => $transaction->fromUser ? $this->personName($transaction->fromUser) : 'Stockroom / Warehouse',
                'to' => $transaction->manual_recipient_name ?? $this->personName($transaction->user),
                'dept' => $transaction->manual_department,
                'date' => $transaction->transaction_date,
                'status' => $transaction->status,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows(string $search): array
    {
        // Same relations as AuditLedgerService::latest(), without the
        // 25-row screen limit, so the export covers the whole ledger.
        $movements = StockMovement::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'user:id,first_name,last_name,username',
            ])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->each(fn (StockMovement $movement): StockMovement => $this->auditLedgerService->describe($movement));

        $rows = [];

        foreach ($movements as $entry) {
            if (! $this->matches($search, [
                $entry->inventory?->item_name,
                $entry->inventory?->inventory_item_no,
                $entry->movement_type,
                $entry->quantity,
                $entry->quantity_before,
                $entry->quantity_after,
                $entry->user ? $this->personName($entry->user) : 'System Automated',
                $entry->display_details,
                $entry->notes,
                $entry->created_at,
            ])) {
                continue;
            }

            $rows[] = [
                'at' => $entry->created_at,
                'item' => $entry->inventory?->item_name ?? 'Unknown item',
                'item_no' => $entry->inventory?->inventory_item_no,
                'type' => $entry->movement_type,
                'diff' => (int) $entry->quantity,
                'before' => $entry->quantity_before,
                'after' => $entry->quantity_after,
                'actor' => $entry->user ? $this->personName($entry->user) : 'System Automated',
                'details' => $entry->display_details ?? $entry->notes ?? 'No additional details recorded.',
            ];
        }

        return $rows;
    }

    /**
     * Server mirror of the SPA's matchesSearch(): case-insensitive
     * substring match across the row's display values.
     */
    private function matches(string $query, array $values): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return true;
        }

        foreach ($values as $value) {
            if ($value === null || $value === '' || $value instanceof \BackedEnum) {
                continue;
            }
            if (str_contains(strtolower((string) $value), $q)) {
                return true;
            }
        }

        return false;
    }

    private function personName($user): string
    {
        if (! $user) {
            return 'Unknown';
        }
        $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $full !== '' ? $full : ($user->username ?? 'Unknown');
    }

    private function categoryLine(AssignmentRequest $request): string
    {
        $category = $request->requestedCategory?->category_name
            ?? $request->item?->category?->category_name
            ?? 'General';
        $unit = $request->requested_unit ?? $request->item?->unit ?? '';

        return $unit !== '' ? "{$category} · {$unit}" : $category;
    }
}
