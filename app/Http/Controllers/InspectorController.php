<?php

namespace App\Http\Controllers;

use App\Models\InspectionRecord;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectorController extends Controller
{
    public function __construct(
        private readonly \App\Services\InventoryOperationService $inventoryOperations,
    ) {
    }

    /**
     * Item-level queue of everything a custodian flagged for inspection.
     *
     * Quantities are resolved through Inventory::effectiveQuantity() because a
     * record flagged from `assigned` keeps 0 in inventory.quantity.
     */
    public function inspectionQueue(Request $request): JsonResponse
    {
        $records = InspectionRecord::query()
            ->with([
                'inventory:item_id,item_name,category_id,unit,quantity,unit_cost,status,serial_number,inventory_item_no,ics_no,building,room,qr_code,date_acquired',
                'inventory.category:category_id,category_name',
                'flaggedBy:id,first_name,last_name,username',
            ])
            ->where('status', 'flagged')
            ->whereHas('inventory', fn ($query) => $query->where('status', 'under_inspection'))
            ->latest('flagged_at')
            ->latest('id')
            ->get()
            ->map(fn (InspectionRecord $record): array => [
                'inspection_id' => $record->id,
                'inventory_id' => $record->inventory_id,
                'inventory_item_no' => $record->inventory?->inventory_item_no,
                'item_name' => $record->inventory?->item_name,
                'category' => $record->inventory?->category?->category_name ?? 'Uncategorized',
                'unit' => $record->inventory?->unit,
                'serial_number' => $record->inventory?->serial_number,
                'status_before' => $record->status_before,
                'reason' => $record->finding_notes,
                'building' => $record->inventory?->building,
                'room' => $record->inventory?->room,
                'flagged_at' => $record->flagged_at?->toDateTimeString(),
                'flagged_by' => $this->personName($record->flaggedBy),
                'quantity' => $record->inventory?->effectiveQuantity() ?? 0,
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'records' => $records,
            'total' => $records->count(),
        ]);
    }

    /**
     * Resolve a scanned or typed token to a single flagged item. Uses the same
     * token rules as the custodian scanner but returns only inspection-safe fields.
     */
    public function qrLookup(Request $request): JsonResponse
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return response()->json([
                'found' => false,
                'message' => 'No QR token provided.',
            ], 422);
        }

        $item = Inventory::byQrToken($token)->with('category')->first();

        if (! $item) {
            return response()->json([
                'found' => false,
                'message' => 'No item found for this QR code.',
            ], 404);
        }

        $record = InspectionRecord::query()
            ->where('inventory_id', $item->item_id)
            ->where('status', 'flagged')
            ->latest('id')
            ->first();

        if (! $record || $item->status !== 'under_inspection') {
            return response()->json([
                'found' => false,
                'message' => 'That item is not awaiting inspection.',
            ], 404);
        }

        $item->loadMissing('category');

        return response()->json([
            'found' => true,
            'inspection_id' => $record->id,
            'inventory_id' => $item->item_id,
            'inventory_item_no' => $item->inventory_item_no,
            'item_name' => $item->item_name,
            'category' => $item->category?->category_name ?? 'Uncategorized',
            'serial_number' => $item->serial_number,
            'ics_no' => $item->ics_no,
            'unit' => $item->unit,
            'quantity' => $item->effectiveQuantity(),
            'unit_cost' => $item->unit_cost,
            'building' => $item->building,
            'room' => $item->room,
            'date_acquired' => $item->date_acquired?->toDateString(),
            'status_before' => $record->status_before,
            'reason' => $record->finding_notes,
            'flagged_at' => $record->flagged_at?->toDateTimeString(),
        ]);
    }

    public function markInspected(Request $request, $inspectionId): JsonResponse
    {
        $validated = $request->validate([
            'finding_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $inspection = InspectionRecord::findOrFail($inspectionId);
        $error = $this->inventoryOperations->markInspected(
            (int) $inspection->id,
            (int) $request->user()->id,
            $validated['finding_notes'] ?? null,
        );

        if ($error !== null) {
            return response()->json([
                'status' => 'error',
                'message' => $error,
            ], 422);
        }

        $inventory = Inventory::findOrFail($inspection->inventory_id);

        return response()->json([
            'status' => 'success',
            'message' => ($inventory->item_name ?? 'Item') . ' marked as inspected and returned to ' . str_replace('_', ' ', (string) $inventory->status) . '.',
            'inventory_id' => $inventory->item_id,
            'inventory_status' => $inventory->status,
        ]);
    }

    private function personName(?object $user): string
    {
        if (! $user) {
            return 'Unknown';
        }

        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $full !== '' ? $full : (string) ($user->username ?? 'Unknown');
    }
}