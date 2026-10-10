<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use App\Services\InventoryOperationService;
use App\Support\RequestableItemMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EndUserController extends Controller
{
    /**
     * See AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT. Aliased here so the
     * request flow reads in terms of the status it writes.
     */
    public const STATUS_WAITING_FOR_PROCUREMENT = AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT;

    public function dashboard(Request $request)
    {
        $user = $request->user();

        $assignedRequests = AssignmentRequest::query()
            ->with('item.category:category_id,category_name')
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['approved', 'accepted'])
            ->where('quantity', '>', 0)
            ->get();

        $pendingRequests = AssignmentRequest::query()
            ->with('targetUser.role')
            ->where('user_id', $user->id)
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval', self::STATUS_WAITING_FOR_PROCUREMENT])
            ->get(['status', 'target_user_id']);
        $pendingReturns = AssignmentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->count();

        $pendingRequestData = collect([
            [
                'label' => 'Item Requests',
                // A procurement request is an open item request too: the end user
                // is waiting on it, and it is reported separately below so the
                // unresolved ones are visible rather than hidden inside a
                // fulfilled-looking count.
                'value' => $pendingRequests->filter(fn ($request) => ($request->status === 'waiting for approval' || $request->status === self::STATUS_WAITING_FOR_PROCUREMENT) && $request->targetUser?->role?->role_name === 'Property Custodian')->count(),
            ],
            [
                'label' => 'Transfer Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for transfer approval' || ($request->status === 'waiting for approval' && $request->targetUser?->role?->role_name !== 'Property Custodian'))->count(),
            ],
            ['label' => 'Return Requests', 'value' => $pendingReturns],
            [
                'label' => 'Waiting on Procurement',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === self::STATUS_WAITING_FOR_PROCUREMENT)->count(),
            ],
        ]);

        $today = now()->startOfDay();
        $dueSoon = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'assigned')
            ->whereNotNull('return_date')
            ->whereBetween('return_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->sum('quantity');

        $assignedItems = (int) $assignedRequests->sum('quantity');
        $categoryData = $assignedRequests
            ->groupBy(fn ($request) => $request->item?->category?->category_name ?? 'Uncategorized')
            ->map(fn ($items, $label) => ['label' => $label, 'quantity' => (int) $items->sum('quantity')])
            ->sortByDesc('quantity')
            ->values();
        $statusData = $assignedRequests
            ->groupBy(fn ($request) => $request->item?->status ?? 'unknown')
            ->map(fn ($items, $status) => ['label' => ucfirst(str_replace('_', ' ', (string) $status)), 'quantity' => (int) $items->sum('quantity')])
            ->values();
        $conditionData = collect([
            ['label' => 'Damaged', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'damaged')->sum('quantity')],
            ['label' => 'Under Maintenance or Repair', 'value' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['under_maintenance', 'under_repair'], true))->sum('quantity')],
            ['label' => 'Lost', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'lost')->sum('quantity')],
            ['label' => 'Disposal Review', 'value' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['ready_to_dispose', 'disposal_review'], true))->sum('quantity')],
        ])->filter(fn ($condition) => $condition['value'] > 0)->values();

        $recentActivity = AssignmentRequest::query()
            ->with('item:item_id,item_name')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('target_user_id', $user->id);
            })
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'title' => 'End User Dashboard',
            'metrics' => [
                'assignedItems' => $assignedItems,
                'pendingRequests' => $pendingRequests->count(),
                'pendingReturns' => $pendingReturns,
                'dueSoon' => (int) $dueSoon,
                'attentionItems' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['damaged', 'under_maintenance', 'under_repair', 'lost', 'ready_to_dispose', 'disposal_review'], true))->sum('quantity'),
            ],
            'categoryData' => $categoryData,
            'statusData' => $statusData,
            'pendingRequestData' => $pendingRequestData,
            'conditionData' => $conditionData,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->username = $validated['username'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('endUser.profile')->with('success', 'Profile updated successfully.');
    }

    public function requests(Request $request)
    {
        $user = Auth::user();

        // 1. My Requests (requests created by this end user to custodian/others)
        $myRequests = AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'requestedCategory:category_id,category_name',
                'targetUser:id,first_name,last_name,role_id',
                'targetUser.role:role_id,role_name',
                'transaction',
                'fulfillmentRequests.item:item_id,item_name,inventory_item_no',
                'fulfillmentRequests.transaction',
            ])
            ->where('user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        // 2. Incoming Requests — custodian assignments AND transfer requests directed to this user
        $incomingRequests = AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'user:id,first_name,last_name,email,role_id',
                'user.role:role_id,role_name',
            ])
            ->where('target_user_id', $user->id)
            ->whereNull('parent_request_id')
            ->orderBy('requested_at', 'desc')
            ->get();

        // Catalogue of every requestable item type, not only the types holding
        // stock right now. An out-of-stock item has to stay selectable, or a
        // category with nothing available would offer the end user no name to
        // ask for and no way to record the demand at all.
        //
        // Availability is looked up per type rather than summed over this
        // query, because this query is deliberately NOT filtered to available
        // rows — that is the whole point of it.
        $availableByType = Inventory::query()
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->select('item_name', 'category_id', 'unit')
            ->selectRaw('SUM(quantity) as available_quantity')
            ->groupBy('item_name', 'category_id', 'unit')
            ->get()
            ->mapWithKeys(fn (Inventory $item): array => [
                self::itemTypeKey($item->item_name, $item->category_id, $item->unit) => (int) $item->available_quantity,
            ]);

        // Pending holds per type: requests sitting in `waiting for approval`
        // promise units the stock rows still show as available (deduction
        // happens at approval). Loaded once and matched in PHP with the same
        // normalisation the availability check uses.
        $pendingRequests = AssignmentRequest::query()
            ->where('status', 'waiting for approval')
            ->whereNotNull('requested_item_name')
            ->get(['requested_item_name', 'requested_category_id', 'requested_unit', 'quantity']);

        // Every category, not only the ones that happen to hold stock. A
        // category with no inventory rows at all is exactly the case an end user
        // needs to raise: they want something the school has never catalogued.
        // Deriving this list from inventory hid those entirely.
        $categories = Category::query()
            ->orderBy('category_name')
            ->get()
            ->map(function (Category $category) use ($availableByType, $pendingRequests): array {
                $listed = Inventory::query()
                    ->where('category_id', $category->category_id)
                    ->select('item_name', 'category_id', 'unit')
                    ->distinct()
                    ->orderBy('item_name')
                    ->orderBy('unit')
                    ->get()
                    // Grouped by name + category + unit, so same-named items in
                    // different categories or units remain distinct things.
                    ->map(function (Inventory $item) use ($availableByType, $pendingRequests, $category): array {
                        $available = (int) $availableByType->get(
                            self::itemTypeKey($item->item_name, $item->category_id, $item->unit),
                            0
                        );
                        $pending = (int) $pendingRequests
                            ->filter(fn ($request): bool => (int) $request->requested_category_id === (int) $item->category_id
                                && RequestableItemMatcher::nameMatches((string) $item->item_name, (string) $request->requested_item_name)
                                && RequestableItemMatcher::unitMatches((string) $request->requested_unit, $item->unit))
                            ->sum('quantity');

                        return [
                            'item_name' => $item->item_name,
                            'category_id' => (int) $category->category_id,
                            'unit' => $item->unit,
                            'available_quantity' => $available,
                            'pending_quantity' => $pending,
                            'free_quantity' => max(0, $available - $pending),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'category_id' => (int) $category->category_id,
                    'category_name' => $category->category_name,
                    // Drives the modal: a category with nothing available shows
                    // the out-of-stock message and the feedback box instead of
                    // a pickable list.
                    'available_item_count' => count(array_filter(
                        $listed,
                        fn (array $item): bool => $item['available_quantity'] > 0
                    )),
                    'items' => $listed,
                ];
            })
            ->values();

        $pendingIncomingCount = $incomingRequests
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval'])
            ->count();
        $myPendingCount = $myRequests
            ->whereIn('status', ['waiting for approval', self::STATUS_WAITING_FOR_PROCUREMENT])
            ->count();

        return response()->json([
            'title'                => 'Requests',
            'myRequests'           => $myRequests,
            'incomingRequests'     => $incomingRequests,
            'categories'           => $categories,
            'pendingIncomingCount' => $pendingIncomingCount,
            'myPendingCount'       => $myPendingCount,
        ]);
    }

    /**
     * Stable key for a requestable item type.
     *
     * An item name alone is not an identity: the same name can exist under two
     * categories or two units, and those are different requestable things.
     *
     * @see storeRequest()
     */
    private static function itemTypeKey(string $itemName, mixed $categoryId, mixed $unit): string
    {
        return $itemName.'|'.(int) $categoryId.'|'.$unit;
    }

    public function requestHistory()
    {
        $user = Auth::user();

        $requests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name'])
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['approved', 'declined', 'returned'])
            ->orderBy('responded_at', 'desc')
            ->get();

        return view('pages.endUser.requestHistory', [
            'title' => 'Request History',
            'requests' => $requests,
        ]);
    }

    public function myRequests(Request $request)
    {
        return $this->requests($request);
    }

    public function storeRequest(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,category_id'],
            'unit' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Availability is resolved through the same matcher the custodian's stock
        // health column and the allocation validation use. Exact string equality
        // would disagree with them: an end user typing "chair" against a
        // catalogue that says "Chairs" would be recorded as having no stock when
        // 19 chairs are sitting on the shelf.
        $availableQuantity = RequestableItemMatcher::availableQuantity(
            $validated['item_name'],
            (int) $validated['category_id'],
            $validated['unit'],
        );
        $pendingQuantity = RequestableItemMatcher::pendingQuantity(
            $validated['item_name'],
            (int) $validated['category_id'],
            $validated['unit'],
        );
        $freeQuantity = max(0, $availableQuantity - $pendingQuantity);

        $custodian = User::whereHas('role', function ($query) {
            $query->where('role_name', 'Property Custodian');
        })->first();

        if (!$custodian) {
            return redirect()->back()->with(['error' => 'No property custodian is available to receive this request.']);
        }

        // Three cases, not two. A total absence of stock is a legitimate need
        // the end user must still be able to state, so it is recorded as unmet
        // demand instead of being refused. A PARTIAL shortfall is different: it
        // is a quantity the end user can simply correct, so it stays an error.
        if ($availableQuantity === 0) {
            $status = self::STATUS_WAITING_FOR_PROCUREMENT;
        } elseif ($validated['quantity'] > $freeQuantity) {
            $holdNote = $pendingQuantity > 0 ? " ({$pendingQuantity} unit(s) already pending approval)" : '';

            return redirect()->back()->with(['error' => "Requested quantity exceeds available stock. Only {$freeQuantity} free{$holdNote}."]);
        } else {
            $status = 'waiting for approval';
        }

        $assignmentRequest = AssignmentRequest::create([
            'requested_item_name' => $validated['item_name'],
            'requested_category_id' => $validated['category_id'],
            'requested_unit' => $validated['unit'],
            'user_id' => Auth::id(),
            'target_user_id' => $custodian->id,
            'quantity' => $validated['quantity'],
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_name' => 'Failed to create request. Please try again.']);
        }

        // The confirmation must not promise an approval the custodian cannot
        // give. Unmet demand goes to procurement, not to an approval queue.
        if ($status === self::STATUS_WAITING_FOR_PROCUREMENT) {
            return redirect()->route('endUser.my-requests')
                ->with('success', 'No stock is available for this item. Your request was recorded and will inform procurement.');
        }

        return redirect()->route('endUser.my-requests')->with('success', 'Request submitted to property custodian.');
    }

    public function myAssignedItems(Request $request)
    {
        $user = Auth::user();

        $inventoryAssignments = Inventory::query()
            ->where('status', 'assigned')
            ->where('assigned_to_user_id', $user->id)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function (Inventory $inventory) use ($user) {
                $pendingReturnRequest = AssignmentRequest::where('item_id', $inventory->item_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'waiting for custodian approval')
                    ->first();

                return [
                    'type'      => 'Assigned',
                    'item_id'   => $inventory->item_id,
                    'item_name' => $inventory->item_name,
                    'quantity'  => $inventory->quantity,
                    'date'      => now(),
                    'status'    => $pendingReturnRequest ? 'Waiting for Return Approval' : 'Approved',
                    'request'   => $pendingReturnRequest,
                ];
            })->toBase();

        $assignmentRequests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'transaction'])
            ->where('target_user_id', $user->id)
            ->where('quantity', '>', 0)
            // Only assignments the end user already accepted. Custodian
            // assignments still awaiting acceptance live in the Requests
            // incoming tab; showing them here implies custody that never moved.
            // Hide transferred items — user no longer possesses them
            ->whereIn('status', ['approved', 'accepted'])
            ->get()
            ->map(function ($request) use ($user) {
                $returnRequestStatus = AssignmentRequest::where('item_id', $request->item_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'waiting for custodian approval')
                    ->first();

                return [
                    'type'      => 'Assigned',
                    'item_id'   => $request->item_id,
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
                    'quantity'  => $request->quantity,
                    'date'      => $request->responded_at ?? $request->requested_at ?? now(),
                    'status'    => $returnRequestStatus ? 'Waiting for Return Approval' : $request->status,
                    'request'   => $request,
                ];
            })->toBase();

        $assignedItems = $inventoryAssignments->merge($assignmentRequests);

        $requestedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser.role'])
            ->where('user_id', $user->id)
            // Only items requisitioned from the Property Custodian (not outgoing transfers sent to colleagues)
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            // Hide live/processed return requests from this table; they belong to the request list instead.
            // Cancelled requests are also excluded from the assigned-items activity list.
            ->whereNotIn('status', ['waiting for custodian approval', 'declined', 'cancelled'])
            // Exclude transferred items (no longer in possession)
            ->where('status', '!=', 'transferred')
            // Fulfilled item-type requests are represented by their exact inventory fulfillment rows.
            ->where(function ($query) {
                $query->whereNotNull('item_id')
                    ->orWhereIn('status', ['waiting for approval', self::STATUS_WAITING_FOR_PROCUREMENT]);
            })
            ->get()
            ->map(function ($request) {
                return [
                    'type'      => 'Requested',
                    'item_id'   => $request->item_id,
                    'item_name' => $request->requested_item_name ?? optional($request->item)->item_name ?? 'Unknown item',
                    'quantity'  => $request->quantity,
                    'date'      => $request->requested_at ?? now(),
                    'status'    => $request->status,
                    'request'   => $request,
                ];
            })->toBase();

        $endUsers = User::whereHas('role', function ($query) {
            $query->where('role_name', 'End User');
        })
        ->whereKeyNot(Auth::id())
        ->get();

        $activityRows = $assignedItems
            ->merge($requestedItems)
            ->groupBy(fn ($row) => $row['type'] . '|' . ($row['item_id'] ?? $row['item_name']))
            ->map(function ($group) {
                return $group->sortByDesc(fn ($row) => $row['date'])->first();
            })
            ->values()
            ->sortByDesc(fn ($row) => $row['date'])
            ->values();

        return response()->json([
            'title' => 'My Assigned Items',
            'activityRows' => $activityRows,
            'endUsers' => $endUsers,
        ]);
    }

    public function requestTransfer(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'target_user_id' => ['required', 'exists:users,id'],
        ]);

        $inventoryItem = Inventory::findOrFail($validated['item_id']);

        if ($inventoryItem->status !== 'available' || $validated['quantity'] > $inventoryItem->quantity) {
            return redirect()->back()->with(['error' => 'Requested quantity exceeds available stock.']);
        }

        $recipient = User::findOrFail($validated['target_user_id']);
        $assignmentRequest = AssignmentRequest::create([
            'item_id' => $inventoryItem->item_id,
            'user_id' => Auth::id(),
            'target_user_id' => $validated['target_user_id'],
            'building' => $recipient->building,
            'room' => $recipient->room,
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_id' => 'Failed to create request. Please try again.']);
        } else {
            return redirect()->route('endUser.my-requests')->with('success', 'Transfer request submitted to the selected end user.');
        }
    }

    public function transferAssignedItem(Request $request, InventoryOperationService $inventoryOperations)
    {
        $validated = $request->validate([
            'request_id'       => ['required', 'exists:requests,id'],
            'transfer_user_id' => ['required', 'exists:users,id'],
            'item_id'          => ['required', 'exists:inventory,item_id'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Find the original assignment belonging to the sender (approved/accepted)
        $original = AssignmentRequest::where('id', $validated['request_id'])
            ->where(function ($q) {
                $q->where('target_user_id', Auth::id())
                    ->orWhere(function ($q) {
                        $q->where('user_id', Auth::id())
                            ->whereHas('targetUser.role', fn ($role) => $role->where('role_name', 'Property Custodian'));
                    });
            })
            ->whereIn('status', ['approved', 'accepted'])
            ->firstOrFail();

        if ((int) $validated['item_id'] !== (int) $original->item_id) {
            return redirect()->back()
                ->withErrors(['item_id' => 'The selected item does not match the original assignment.'])
                ->withInput();
        }

        // Validate quantity does not exceed available quantity
        if ($validated['quantity'] > $original->quantity) {
            return redirect()->back()
                ->withErrors(['quantity' => "You cannot transfer more than {$original->quantity} unit(s)."])
                ->withInput();
        }

        $recipient = User::with('role')->findOrFail($validated['transfer_user_id']);

        if (
            $recipient->id === Auth::id()
            || $recipient->status !== 'active'
            || $recipient->role?->role_name !== 'End User'
        ) {
            return redirect()->back()
                ->withErrors(['transfer_user_id' => 'Transfers are only allowed to another active End User.'])
                ->withInput();
        }

        $transferResult = $inventoryOperations->requestTransfer(
            $original->id,
            (int) $validated['item_id'],
            (int) Auth::id(),
            (int) $validated['transfer_user_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
        );

        if ($transferResult === 'insufficient') {
            return redirect()->back()
                ->withErrors(['quantity' => "You cannot transfer more than {$original->quantity} unit(s)."])
                ->withInput();
        }
        if ($transferResult !== 'created') {
            return redirect()->back()
                ->withErrors(['request_id' => 'The assignment is no longer available for transfer.'])
                ->withInput();
        }

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request sent. Waiting for the recipient to accept.');
    }

    public function respondRequest(Request $request, $id, InventoryOperationService $inventoryOperations)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $assignment = AssignmentRequest::findOrFail($id);

        // Only target user may respond
        if ($assignment->target_user_id !== Auth::id()) {
            abort(403);
        }

        // ── TRANSFER REQUEST (step 2 of 3: recipient accepts/declines) ──────────
        if ($assignment->status === 'waiting for transfer approval') {

            if ($validated['action'] === 'decline') {
                if (! $inventoryOperations->declineTransferRequest($assignment->id, $assignment->notes)) {
                    return redirect()->route('endUser.requests')->with('error', 'Transfer request has already been processed.');
                }

                return redirect()->route('endUser.requests')->with('success', 'Transfer request declined.');
            }

            // Accept: advance to custodian approval (step 3 of 3) with race condition protection
            if (! $inventoryOperations->acceptTransferRequest($assignment->id)) {
                return redirect()->route('endUser.requests')->with('error', 'Transfer request is no longer awaiting approval.');
            }

            return redirect()->route('endUser.requests')->with('success', 'Transfer accepted. Waiting for Property Custodian approval.');
        }

        // ── NORMAL ASSIGNMENT REQUEST (from custodian) ───────────────────────────
        if ($assignment->status !== 'waiting for approval') {
            return redirect()->route('endUser.requests')->with('error', 'This request is no longer awaiting a response.');
        }

        if ($validated['action'] === 'decline') {
            $assignment->status = 'declined';
            $assignment->responded_at = now();
            $assignment->save();

            return redirect()->route('endUser.requests')->with('success', 'Request declined.');
        }

        // Accept: create transaction, decrement inventory, mark request approved
        $acceptanceResult = $inventoryOperations->acceptAssignment(
            $assignment->id,
            (int) Auth::id(),
        );

        if ($acceptanceResult === 'insufficient') {
            return redirect()->route('endUser.requests')->with('error', 'Insufficient stock to fulfill request.');
        }

        if ($acceptanceResult !== 'accepted') {
            return redirect()->route('endUser.requests')->with('error', 'This request is no longer awaiting a response.');
        }

        return redirect()->route('endUser.requests')->with('success', 'Request accepted and item assigned.');
    }

    public function requestReturn(Request $request, $itemId)
    {
        $inventory = Inventory::findOrFail($itemId);
        $user = Auth::user();

        $approvedAssignment = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('target_user_id', $user->id)
            ->where('status', 'approved')
            ->where('quantity', '>', 0)
            ->orderByDesc('responded_at')
            ->first();

        $hasApprovedAssignment = (bool) $approvedAssignment;
        $isAssignedToUser = (int) ($inventory->assigned_to_user_id ?? 0) === (int) $user->id;

        // Verify the item is assigned to the current user from either the inventory record
        // or the approved assignment record, which may lag behind on older data.
        if (!$isAssignedToUser && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'You can only request return of items assigned to you.');
        }

        if ($inventory->status !== 'assigned' && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'Only assigned items can be returned.');
        }

        $custodian = User::whereHas('role', function ($query) {
            $query->where('role_name', 'Property Custodian');
        })->first();

        if (!$custodian) {
            return redirect()->back()->with('error', 'No property custodian available to receive this return request.');
        }

        $assignmentTransaction = $approvedAssignment?->transaction_id
            ? Transaction::whereKey($approvedAssignment->transaction_id)->where('status', 'assigned')->first()
            : Transaction::query()
                ->where('item_id', $inventory->item_id)
                ->where('user_id', $user->id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->latest('transaction_date')
                ->first();

        // Prevent duplicate return requests for the same assignment transaction.
        $existingRequestQuery = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval');
        if ($assignmentTransaction) {
            $existingRequestQuery->where('transaction_id', $assignmentTransaction->id);
        }
        $existingRequest = $existingRequestQuery->first();

        if ($existingRequest) {
            // Return request already exists, no duplicate needed
            return redirect()->route('endUser.my-requests')->with('success', 'Return request submitted to property custodian.');
        }

        $returnQuantity = $approvedAssignment?->quantity ?? $assignmentTransaction?->quantity ?? $inventory->quantity;

        if ($approvedAssignment && $approvedAssignment->quantity < 1) {
            return redirect()->back()->with('error', 'The approved assignment quantity is invalid.');
        }

        if (!$approvedAssignment && !$isAssignedToUser) {
            return redirect()->back()->with('error', 'Only assigned items can be returned.');
        }

        // Create new return request using the approved assignment quantity as the source of truth,
        // with the assigned inventory row only as a fallback when the user is clearly assigned.
        AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $user->id,
            'target_user_id' => $custodian->id,
            'transaction_id' => $assignmentTransaction?->id,
            'quantity' => $returnQuantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        return redirect()->route('endUser.my-requests')->with('success', 'Return request submitted to property custodian.');
    }

    public function cancelReturnRequest(Request $request, $itemId)
    {
        $inventory = Inventory::findOrFail($itemId);
        $user = Auth::user();

        $returnRequest = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->first();

        if (!$returnRequest) {
            return redirect()->back()->with('error', 'No pending return request was found for this item.');
        }

        $returnRequest->update([
            'status' => 'cancelled',
            'responded_at' => now(),
        ]);

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Return request cancelled successfully.');
    }
}
