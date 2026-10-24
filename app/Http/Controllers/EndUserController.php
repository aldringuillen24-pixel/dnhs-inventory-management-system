<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvidence;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use App\Services\IncidentLifecycleService;
use App\Services\InventoryOperationService;
use App\Support\RequestableItemMatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

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
            ->whereIn('status', [
                'waiting for approval',
                'waiting for transfer approval',
                'waiting for custodian approval',
                'transfer draft',
                self::STATUS_WAITING_FOR_PROCUREMENT,
            ])
            ->get(['status', 'target_user_id']);
        $pendingReturns = AssignmentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->whereHas('targetUser.role', fn ($query) => $query->where('role_name', 'Property Custodian'))
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
                'value' => $pendingRequests->filter(fn ($request) => in_array($request->status, ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'], true)
                    && $request->targetUser?->role?->role_name === 'End User')->count(),
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
            ->whereNotNull('expected_return_date')
            ->whereBetween('expected_return_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
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
        // Open damage/loss reports the user filed on items they still hold.
        // These drive the Damaged/Lost condition rows, which item statuses
        // alone can never populate (reports never change inventory status).
        $openIssueReports = MaintenanceRecord::query()
            ->where('reported_by', $user->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->whereIn('inventory_id', Transaction::query()
                ->where('user_id', $user->id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->select('item_id'))
            ->get(['diagnosis']);
        $conditionData = collect([
            ['label' => 'Damaged', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'damaged')->sum('quantity') + $openIssueReports->where('diagnosis', 'damaged')->count()],
            ['label' => 'Under Repair', 'value' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['under_repair', 'under_maintenance'], true))->sum('quantity')],
            ['label' => 'Lost', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'lost')->sum('quantity') + $openIssueReports->where('diagnosis', 'lost')->count()],
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
                'attentionItems' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['damaged', 'under_repair', 'lost', 'ready_to_dispose', 'disposal_review'], true))->sum('quantity'),
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
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->building = $validated['building'] ?? null;
        $user->room = $validated['room'] ?? null;

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
        $myRequests = $this->myRequestsQuery($user->id)->get();

        // 2. Incoming Requests — custodian assignments AND transfer requests directed to this user
        $incomingRequests = $this->incomingRequestsQuery($user->id)->get();

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

    private function myRequestsQuery(int $userId)
    {
        return AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'requestedCategory:category_id,category_name',
                'targetUser:id,first_name,last_name,role_id',
                'targetUser.role:role_id,role_name',
                'transaction',
                'fulfillmentRequests.item:item_id,item_name,inventory_item_no',
                'fulfillmentRequests.transaction',
            ])
            ->where('user_id', $userId)
            ->orderBy('requested_at', 'desc');
    }

    private function incomingRequestsQuery(int $userId)
    {
        return AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'user:id,first_name,last_name,email,role_id',
                'user.role:role_id,role_name',
            ])
            ->where('target_user_id', $userId)
            ->whereNull('parent_request_id')
            ->whereNotIn('status', ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'])
            // Draft allocations are custodian-held prints, not requests the
            // recipient can act on. They live in the custodian Drafts tab.
            ->where('status', '!=', 'draft')
            ->orderBy('requested_at', 'desc');
    }

    private function applyRequestFilters($query, ?string $status, string $search)
    {
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($scope) use ($search): void {
                $scope->where('requested_item_name', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($item) => $item
                        ->where('item_name', 'like', "%{$search}%")
                        ->orWhere('inventory_item_no', 'like', "%{$search}%"))
                    ->orWhereHas('requestedCategory', fn ($category) => $category
                        ->where('category_name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function exportRequestsPdf(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(['my', 'incoming', 'all'])],
            'status' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tab = $validated['tab'] ?? 'all';
        $status = $validated['status'] ?? null;
        $search = strtolower(trim($validated['search'] ?? ''));

        $myRequests = collect();
        $incomingRequests = collect();
        if (in_array($tab, ['my', 'all'], true)) {
            $myRequests = $this->applyRequestFilters($this->myRequestsQuery($user->id), $status, $search)->get();
        }
        if (in_array($tab, ['incoming', 'all'], true)) {
            $incomingRequests = $this->applyRequestFilters($this->incomingRequestsQuery($user->id), $status, $search)->get();
        }

        $requester = trim($user->first_name . ' ' . $user->last_name) ?: $user->username;
        $filterSummary = 'Tab: ' . ($tab === 'my' ? 'My Requests' : ($tab === 'incoming' ? 'Incoming Requests' : 'All'))
            . ($status ? "; Status: {$status}" : '')
            . ($search !== '' ? "; Search: \"{$validated['search']}\"" : '');

        $pdf = Pdf::loadView('pdfs.end-user-requests', [
            'tab' => $tab,
            'myRequests' => $myRequests,
            'incomingRequests' => $incomingRequests,
            'filterSummary' => $filterSummary,
            'generatedAt' => now()->format('F j, Y g:i A'),
            'requester' => $requester,
        ]);

        return $pdf->download('my-requests_' . now()->format('Y-m-d_His') . '.pdf');
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

        $activityRows = $this->buildAssignedActivityRows($user);

        $endUsers = User::whereHas('role', function ($query) {
            $query->where('role_name', 'End User');
        })
        ->whereKeyNot(Auth::id())
        ->get();

        return response()->json([
            'title' => 'My Assigned Items',
            'activityRows' => $activityRows,
            'incidentReports' => MaintenanceRecord::query()
                ->with('inventory:item_id,item_name,inventory_item_no')
                ->with('evidence:id,maintenance_record_id,original_filename')
                ->with('events.actor:id,first_name,last_name,username')
                ->where('reported_by', $user->id)
                ->whereIn('diagnosis', MaintenanceRecord::INCIDENT_TYPES)
                ->latest('created_at')
                ->get()
                ->map(fn (MaintenanceRecord $report): array => [
                    'id' => $report->id,
                    'item_name' => $report->inventory?->item_name ?? 'Unknown item',
                    'inventory_item_no' => $report->inventory?->inventory_item_no,
                    'type' => $report->diagnosis,
                    'quantity' => $report->quantity,
                    'details' => $report->issue_description,
                    'status' => $report->incident_status ?? $report->status,
                    'outcome' => $report->incident_outcome,
                    'submitted_at' => $report->created_at,
                    'timeline' => $report->events->map(fn ($event): array => [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'from_status' => $event->from_status,
                        'to_status' => $event->to_status,
                        'details' => $event->details,
                        'metadata' => $event->metadata,
                        'actor_id' => $event->actor_id,
                        'actor' => $event->actor
                            ? ($event->actor->full_name ?: $event->actor->username)
                            : null,
                        'created_at' => $event->created_at,
                    ])->values(),
                    'evidence' => $report->evidence
                        ->map(fn (MaintenanceRecordEvidence $evidence): array => [
                            'id' => $evidence->id,
                            'filename' => $evidence->original_filename,
                            'download_url' => route('api.incident-evidence.download', $evidence->id),
                        ])
                        ->values(),
                ])
                ->values(),
            'endUsers' => $endUsers,
        ]);
    }

    public function respondToIncidentInformation(
        Request $request,
        $id,
        IncidentLifecycleService $incidentLifecycle,
    ) {
        $validated = $request->validate([
            'details' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $record = MaintenanceRecord::query()
            ->whereKey($id)
            ->where('reported_by', $request->user()->id)
            ->firstOrFail();

        $record = $incidentLifecycle->transition(
            $record,
            'reported',
            (int) $request->user()->id,
            $validated['details'],
        );

        return response()->json([
            'status' => 'success',
            'incident_status' => $record->incident_status,
            'message' => 'Your response was added to the report history.',
        ]);
    }

    private function inventoryAssignmentRows($user)
    {
        return Inventory::query()
            ->where('status', 'assigned')
            ->where('assigned_to_user_id', $user->id)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function (Inventory $inventory) use ($user) {
                $pendingReturnRequest = AssignmentRequest::where('item_id', $inventory->item_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'waiting for custodian approval')
                    ->whereHas('targetUser.role', fn ($query) => $query->where('role_name', 'Property Custodian'))
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
    }

    private function userAssignmentRows($user)
    {
        return AssignmentRequest::query()
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
                    ->whereHas('targetUser.role', fn ($query) => $query->where('role_name', 'Property Custodian'))
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
    }

    private function requestedItemRows($user)
    {
        return AssignmentRequest::query()
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
    }

    private function buildAssignedActivityRows($user)
    {
        $assignedItems = $this->inventoryAssignmentRows($user)->merge($this->userAssignmentRows($user));

        return $assignedItems
            ->merge($this->requestedItemRows($user))
            ->groupBy(fn ($row) => $row['type'] . '|' . ($row['item_id'] ?? $row['item_name']))
            ->map(function ($group) {
                return $group->sortByDesc(fn ($row) => $row['date'])->first();
            })
            ->values()
            ->sortByDesc(fn ($row) => $row['date'])
            ->values();
    }

    public function exportAssignedItemsPdf(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $validated['status'] ?? null;
        $search = strtolower(trim($validated['search'] ?? ''));

        $rows = $this->buildAssignedActivityRows($user)->filter(function ($row) use ($status, $search): bool {
            if ($status !== null && $status !== '' && ($row['status'] ?? null) !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            return collect([$row['item_name'] ?? null, $row['type'] ?? null])
                ->filter()
                ->contains(fn ($value): bool => str_contains(strtolower((string) $value), $search));
        })->values();

        $holder = trim($user->first_name . ' ' . $user->last_name) ?: $user->username;
        $filterSummary = 'Holder: ' . $holder
            . ($status ? "; Status: {$status}" : '')
            . ($search !== '' ? "; Search: \"{$validated['search']}\"" : '');

        $pdf = Pdf::loadView('pdfs.end-user-assigned-items', [
            'rows' => $rows,
            'filterSummary' => $filterSummary,
            'generatedAt' => now()->format('F j, Y g:i A'),
            'holder' => $holder,
        ]);

        return $pdf->download('my-assigned-items_' . now()->format('Y-m-d_His') . '.pdf');
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

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request sent to the Property Custodian.');
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

        // Transfers are handled by the Property Custodian; the recipient
        // acknowledges the physical handoff with a handwritten slip signature.
        if ($assignment->status === 'waiting for transfer approval') {
            return redirect()->route('endUser.requests')->with('error', 'Transfer requests are handled by the Property Custodian.');
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
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $inventory = Inventory::findOrFail($itemId);
        $user = Auth::user();

        $approvedAssignment = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('target_user_id', $user->id)
            // 'accepted' is a legacy-status fallback; acceptAssignment writes 'approved'.
            ->whereIn('status', ['approved', 'accepted'])
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

        // Partial returns: the requester may return fewer units than assigned.
        // The custodian's approval path already decrements and keeps the
        // remainder assigned, so only the entry-point cap is new here.
        $outstandingQuantity = $approvedAssignment?->quantity ?? $assignmentTransaction?->quantity ?? $inventory->quantity;
        $returnQuantity = $validated['quantity'] ?? $outstandingQuantity;
        if ($returnQuantity < 1 || $returnQuantity > $outstandingQuantity) {
            return redirect()->back()->with('error', 'Return quantity must be between 1 and the assigned quantity.');
        }

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

    public function cancelTransfer(Request $request, $id, InventoryOperationService $inventoryOperations)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $transferRequest = AssignmentRequest::findOrFail($id);

        // Only the sender may withdraw their own transfer.
        if ((int) $transferRequest->user_id !== (int) Auth::id()) {
            abort(403);
        }

        if (! $inventoryOperations->cancelTransferRequest($transferRequest->id, $validated['cancellation_reason'] ?? null)) {
            return redirect()->back()->with('error', 'Only transfers awaiting custodian review can be cancelled.');
        }

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request cancelled.');
    }

    /**
     * Report a damage/loss issue on an assigned item. Report-only: the item
     * stays assigned (custody is preserved) and no stock movement is recorded
     * because nothing moved. The report lands in `maintenance_records` with
     * the reporter and type (`diagnosis`), which the custodian triages from
     * the inventory Records view.
     */
    public function reportIssue(Request $request, $itemId, IncidentLifecycleService $incidentLifecycle)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:damaged,needs_repair,lost,stolen'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

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

        if (!$isAssignedToUser && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'You can only report issues on items assigned to you.');
        }

        if ($inventory->status !== 'assigned' && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'Only assigned items can be reported.');
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

        $assignedQuantity = $incidentLifecycle->currentAssignedQuantity(
            (int) $inventory->item_id,
            (int) $user->id,
            $assignmentTransaction?->id,
        );
        $reportQuantity = $validated['quantity'] ?? $assignedQuantity;
        if ($reportQuantity < 1 || $reportQuantity > $assignedQuantity) {
            return redirect()->back()->with('error', 'Report quantity must be between 1 and the assigned quantity.');
        }

        $alreadyReported = MaintenanceRecord::where('inventory_id', $inventory->item_id)
            ->where('reported_by', $user->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->exists();

        if ($alreadyReported) {
            return redirect()->back()->with('error', 'You already have an open report for this item.');
        }

        $evidence = $request->file('evidence');
        $storedEvidencePath = null;

        try {
            DB::transaction(function () use ($inventory, $reportQuantity, $user, $validated, $assignmentTransaction, $incidentLifecycle, $evidence, &$storedEvidencePath): void {
                $record = MaintenanceRecord::create([
                    'inventory_id' => $inventory->item_id,
                    'quantity' => $reportQuantity,
                    'reported_by' => $user->id,
                    'status' => 'reported',
                    'issue_description' => $validated['notes'],
                    'diagnosis' => $validated['type'],
                    'started_at' => now(),
                ]);

                $incidentLifecycle->recordSubmission($record, (int) $user->id, $assignmentTransaction?->id);

                if ($evidence) {
                    $storedEvidencePath = $evidence->store("incident-evidence/{$record->id}", 'local');
                    if (! is_string($storedEvidencePath) || $storedEvidencePath === '') {
                        throw new \RuntimeException('Incident evidence could not be stored.');
                    }

                    $record->evidence()->create([
                        'uploaded_by' => $user->id,
                        'disk' => 'local',
                        'path' => $storedEvidencePath,
                        'original_filename' => mb_substr(basename($evidence->getClientOriginalName()), 0, 255),
                        'mime_type' => $evidence->getMimeType() ?? 'application/octet-stream',
                        'size' => $evidence->getSize(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if (is_string($storedEvidencePath) && $storedEvidencePath !== '') {
                Storage::disk('local')->delete($storedEvidencePath);
            }

            throw $exception;
        }

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Issue reported for custodian triage.');
    }

    public function downloadIncidentEvidence(Request $request, MaintenanceRecordEvidence $evidence)
    {
        $record = $evidence->maintenanceRecord()->with('reportedBy.role')->firstOrFail();
        $user = $request->user();
        $isReporter = (int) $record->reported_by === (int) $user->id;
        $isCustodian = $user->role?->role_name === 'Property Custodian';

        abort_unless($record->isIncidentReport() && ($isReporter || $isCustodian), 403);

        $disk = Storage::disk($evidence->disk);
        abort_unless($disk->exists($evidence->path), 404);

        return $disk->download(
            $evidence->path,
            basename($evidence->original_filename),
            ['Content-Type' => $evidence->mime_type],
        );
    }

    public function cancelRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignmentRequest = AssignmentRequest::findOrFail($id);

        // Only the requester may cancel their own request.
        if ((int) $assignmentRequest->user_id !== (int) Auth::id()) {
            abort(403);
        }

        // Only requests still awaiting a custodian decision can be cancelled.
        if (! in_array($assignmentRequest->status, ['waiting for approval', self::STATUS_WAITING_FOR_PROCUREMENT], true)) {
            return redirect()->back()->with('error', 'Only requests waiting for approval can be cancelled.');
        }

        $assignmentRequest->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            'responded_at' => now(),
        ]);

        return redirect()->route('endUser.my-requests')->with('success', 'Request cancelled.');
    }
}
