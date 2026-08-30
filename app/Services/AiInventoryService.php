<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiInventoryService
{
    /**
     * List of prioritized free models on OpenRouter.
     */
    protected array $fallbackModels = [
        'meta-llama/llama-3.3-70b-instruct:free',
        'deepseek/deepseek-r1:free',
        'google/gemini-2.0-flash-exp:free',
        'qwen/qwen-2.5-72b-instruct:free',
        'meta-llama/llama-3.1-8b-instruct:free',
    ];

    /**
     * Ask the AI Assistant a question grounded in the authenticated user's role-scoped data.
     */
    public function ask(User $user, string $question, array $history = []): string
    {
        $apiKey = config('services.openrouter.api_key');

        $roleName = $user->role?->role_name ?? 'End User';
        $contextData = $this->buildRoleScopedContext($user);
        $systemPrompt = $this->buildSystemPrompt($user, $roleName, $contextData);

        // If no API key is set yet, provide a smart fallback response based on grounded local data
        if (empty($apiKey)) {
            return $this->generateLocalFallbackResponse($user, $question, $contextData, $roleName);
        }

        // Build messages payload
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Append recent chat history (limit to last 6 messages)
        $trimmedHistory = array_slice($history, -6);
        $lastHistoryMessage = end($trimmedHistory);
        if (
            is_array($lastHistoryMessage)
            && ($lastHistoryMessage['sender'] ?? null) === 'user'
            && ($lastHistoryMessage['text'] ?? null) === $question
        ) {
            array_pop($trimmedHistory);
        }

        foreach ($trimmedHistory as $msg) {
            if (($msg['sender'] ?? null) === 'user' && !empty($msg['text'])) {
                $messages[] = [
                    'role' => 'user',
                    'content' => (string) $msg['text'],
                ];
            }
        }

        // Append current question
        $messages[] = ['role' => 'user', 'content' => $question];

        // Keep upstream failures from blocking the chat request for several minutes.
        $modelsToTry = array_slice(
            array_unique(array_merge([config('services.openrouter.model')], $this->fallbackModels)),
            0,
            max(1, (int) config('services.openrouter.max_attempts', 2))
        );

        foreach ($modelsToTry as $model) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer' => config('services.openrouter.site_url', 'http://localhost'),
                    'X-Title' => config('services.openrouter.site_name', 'DNHS IMS'),
                    'Content-Type' => 'application/json',
                ])
                ->connectTimeout((int) config('services.openrouter.connect_timeout', 5))
                ->timeout((int) config('services.openrouter.timeout', 12))
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 800,
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['choices'][0]['message']['content'] ?? null;
                    if ($reply) {
                        return trim($reply);
                    }
                } else {
                    Log::warning("OpenRouter model {$model} returned error: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error("OpenRouter request exception with model {$model}: " . $e->getMessage());
            }
        }

        // If all OpenRouter attempts failed, return grounded fallback with notification
        return $this->generateLocalFallbackResponse($user, $question, $contextData, $roleName, true);
    }

    /**
     * Build scoped data context based on user's assigned role.
     */
    public function buildRoleScopedContext(User $user): array
    {
        $roleName = $user->role?->role_name ?? 'End User';

        return match ($roleName) {
            'Property Custodian' => $this->getCustodianContext(),
            'School Head'        => $this->getSchoolHeadContext(),
            'Administrator'      => $this->getAdminContext(),
            default              => $this->getEndUserContext($user),
        };
    }

    /**
     * Context for End Users (Teachers/Staff)
     */
    protected function getEndUserContext(User $user): array
    {
        $myAssigned = AssignmentRequest::with(['item:item_id,item_name,inventory_item_no'])
            ->where(function ($q) use ($user) {
                $q->where('target_user_id', $user->id)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->whereHas('targetUser.role', fn ($r) => $r->where('role_name', 'Property Custodian'));
                  });
            })
            ->whereIn('status', ['approved', 'accepted', 'transfer pending'])
            ->get()
            ->map(fn ($r) => [
                'item_name' => optional($r->item)->item_name ?? 'Item',
                'inventory_no' => optional($r->item)->inventory_item_no,
                'quantity' => $r->quantity,
                'status' => $r->status,
                'date' => $r->responded_at?->format('M d, Y') ?? $r->requested_at?->format('M d, Y'),
            ])->toArray();

        $myRequests = AssignmentRequest::with(['item:item_id,item_name'])
            ->where('user_id', $user->id)
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'item_name' => optional($r->item)->item_name ?? 'Item',
                'quantity' => $r->quantity,
                'status' => $r->status,
                'requested_at' => $r->requested_at?->format('M d, Y'),
            ])->toArray();

        $warehouseAvailability = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->get()
            ->groupBy('item_name')
            ->map(function ($group, $name) {
                $available = $group->where('status', 'available')->sum('quantity');
                return [
                    'item_name' => $name,
                    'stock_status' => $available > 5 ? 'In Stock' : ($available > 0 ? 'Low Stock' : 'Out of Stock'),
                    'units_available' => $available,
                ];
            })->values()->take(25)->toArray();

        return [
            'user_name' => $user->full_name,
            'assigned_items' => $myAssigned,
            'recent_requests' => $myRequests,
            'warehouse_item_availability' => $warehouseAvailability,
        ];
    }

    /**
     * Context for Property Custodian
     */
    protected function getCustodianContext(): array
    {
        $inventoryGrouped = Inventory::with('category:category_id,category_name')
            ->where('status', '!=', 'disposed')
            ->get()
            ->groupBy('item_name')
            ->map(function ($group, $name) {
                $total = $group->sum('quantity');
                $available = $group->where('status', 'available')->sum('quantity');
                $assigned = $group->where('status', 'assigned')->sum('quantity');
                $first = $group->first();
                return [
                    'item_name' => $name,
                    'category' => $first->category?->category_name ?? 'General',
                    'total_quantity' => $total,
                    'available_stock' => $available,
                    'assigned_in_use' => $assigned,
                    'unit_cost' => (float) ($first->unit_cost ?? 0),
                    'unit' => $first->unit ?? 'pcs',
                ];
            })->values()->toArray();

        $stockInByYear = Inventory::query()
            ->whereNotNull('date_acquired')
            ->get()
            ->groupBy(fn ($item) => $item->date_acquired ? \Carbon\Carbon::parse($item->date_acquired)->format('Y') : 'Unknown')
            ->map(fn ($group, $year) => [
                'year' => (string) $year,
                'item_count' => $group->count(),
                'total_units_stocked' => (int) $group->sum('quantity'),
                'total_valuation_php' => (float) $group->sum(fn ($i) => ($i->unit_cost ?? 0) * ($i->quantity ?? 1)),
            ])
            ->sortBy('year')
            ->values()
            ->toArray();

        $lowStockItems = array_filter($inventoryGrouped, fn ($item) => $item['available_stock'] <= 3);

        $pendingRequests = AssignmentRequest::with(['item:item_id,item_name', 'user:id,first_name,last_name'])
            ->where('status', 'waiting for approval')
            ->get()
            ->map(fn ($r) => [
                'item_name' => optional($r->item)->item_name ?? 'Item',
                'requester' => optional($r->user)->full_name ?? 'End User',
                'quantity' => $r->quantity,
                'requested_at' => $r->requested_at?->format('M d, Y'),
            ])->toArray();

        return [
            'warehouse_inventory' => $inventoryGrouped,
            'stock_in_history_by_year' => $stockInByYear,
            'low_stock_critical_items' => array_values($lowStockItems),
            'pending_requisitions' => $pendingRequests,
        ];
    }

    /**
     * Context for School Head (Principal)
     */
    protected function getSchoolHeadContext(): array
    {
        $totalItems = Inventory::where('status', '!=', 'disposed')->sum('quantity');
        $availableItems = Inventory::where('status', 'available')->sum('quantity');
        $assignedItems = Inventory::where('status', 'assigned')->sum('quantity');
        $totalValuation = Inventory::where('status', '!=', 'disposed')->get()->sum(fn ($i) => ($i->unit_cost ?? 0) * ($i->quantity ?? 1));

        $categoryBreakdown = Category::withCount('inventory')
            ->get()
            ->map(fn ($c) => [
                'category_name' => $c->category_name,
                'item_types_count' => $c->inventory_count,
            ])->toArray();

        $stockInByYear = Inventory::query()
            ->whereNotNull('date_acquired')
            ->get()
            ->groupBy(fn ($item) => $item->date_acquired ? \Carbon\Carbon::parse($item->date_acquired)->format('Y') : 'Unknown')
            ->map(fn ($group, $year) => [
                'year' => (string) $year,
                'total_units' => (int) $group->sum('quantity'),
                'total_spend' => (float) $group->sum(fn ($i) => ($i->unit_cost ?? 0) * ($i->quantity ?? 1)),
            ])
            ->sortBy('year')
            ->values()
            ->toArray();

        return [
            'executive_summary' => [
                'total_school_assets_count' => $totalItems,
                'currently_in_circulation' => $assignedItems,
                'available_in_storage' => $availableItems,
                'total_asset_valuation_php' => number_format((float) $totalValuation, 2),
            ],
            'category_breakdown' => $categoryBreakdown,
            'annual_acquisitions_trend' => $stockInByYear,
        ];
    }

    /**
     * Context for Administrator
     */
    protected function getAdminContext(): array
    {
        $userCountsByRole = User::with('role')->get()->groupBy(fn ($u) => $u->role?->role_name ?? 'None')->map->count()->toArray();
        $totalTransactions = Transaction::count();
        $totalInventoryRecords = Inventory::count();

        return [
            'system_overview' => [
                'registered_users_by_role' => $userCountsByRole,
                'total_inventory_records' => $totalInventoryRecords,
                'total_transactions_logged' => $totalTransactions,
            ],
            'categories' => Category::pluck('category_name')->toArray(),
        ];
    }

    /**
     * Construct customized system prompt with persona and context for the user role.
     */
    protected function buildSystemPrompt(User $user, string $roleName, array $contextData): string
    {
        $jsonContext = json_encode($contextData, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are the intelligent AI Inventory Assistant for the Dacudao National High School (DNHS) Inventory Management System.
You are currently assisting: {$user->full_name} (Role: {$roleName}).

### ROLE-BASED SCOPE & GUIDELINES:
- **Role**: {$roleName}
- Always tailor your responses to the permissions and needs of this role.
- Provide direct, concise, professional, and well-structured answers using clear Markdown formatting (bullet points, bold highlights, tables when comparing data).
- When asked about:
  1. **Procurement Prioritization**: Prioritize items that have 0 or very low available stock, high demand, or pending requisitions. Always explain the exact reason (e.g. "Only 2 reams left with 10 pending requests").
  2. **Year Comparisons (e.g. 2024 vs 2026 stock-in)**: Reference the `stock_in_history_by_year` or `annual_acquisitions_trend` data accurately. Highlight differences in volume, items acquired, and total value.
  3. **Specific Item Inquiries (e.g. bond paper)**: Look up its available stock and give a transparent explanation.
  4. **End Users**: Help them track their assigned equipment, explain request/transfer steps, or check warehouse availability. Do not expose sensitive financial budgets to End Users.

### LIVE SYSTEM DATA CONTEXT:
```json
{$jsonContext}
```

Answer the user's inquiry accurately based on the live data provided above.
PROMPT;
    }

    /**
     * Fallback heuristic generator when OpenRouter API key is not configured or offline.
     */
    protected function generateLocalFallbackResponse(User $user, string $question, array $context, string $roleName, bool $wasApiError = false): string
    {
        $q = strtolower($question);

        $notice = "";
        if ($wasApiError) {
            $notice = "> ⚠️ *Note: Live OpenRouter AI is temporarily unavailable. Showing instant database report:*\n\n";
        }

        // 1. Procurement prioritization question
        if (str_contains($q, 'procure') || str_contains($q, 'buy first') || str_contains($q, 'priority') || str_contains($q, 'prioritize')) {
            if ($roleName === 'Property Custodian' || $roleName === 'School Head') {
                $lowStock = $context['low_stock_critical_items'] ?? [];
                if (!empty($lowStock)) {
                    $list = "";
                    foreach (array_slice($lowStock, 0, 5) as $i => $item) {
                        $list .= ($i + 1) . ". **{$item['item_name']}** ({$item['category']})\n";
                        $list .= "   - **Available Stock**: {$item['available_stock']} {$item['unit']}\n";
                        $list .= "   - **Rationale**: Immediate procurement recommended due to critically low inventory reserves.\n";
                    }
                    return $notice . "### 📋 Recommended Procurement Priorities\n\nBased on current warehouse inventory:\n\n" . $list;
                }
                return $notice . "All inventory items currently meet healthy minimum threshold stock levels.";
            }
        }

        // 2. Year comparison (e.g., 2024 vs 2026)
        if (str_contains($q, '2024') && str_contains($q, '2026') && (str_contains($q, 'comparison') || str_contains($q, 'stock in') || str_contains($q, 'compare'))) {
            $years = $context['stock_in_history_by_year'] ?? ($context['annual_acquisitions_trend'] ?? []);
            if (!empty($years)) {
                $out = "### 📊 Stock-In Comparison (2024 vs 2026)\n\n| Year | Items Stocked | Total Units | Total Valuation |\n| :--- | :--- | :--- | :--- |\n";
                foreach ($years as $row) {
                    $yr = $row['year'] ?? 'N/A';
                    $cnt = $row['item_count'] ?? ($row['total_units'] ?? 0);
                    $qty = $row['total_units_stocked'] ?? ($row['total_units'] ?? 0);
                    $val = isset($row['total_valuation_php']) ? '₱' . number_format($row['total_valuation_php'], 2) : (isset($row['total_spend']) ? '₱' . number_format($row['total_spend'], 2) : 'N/A');
                    $out .= "| **{$yr}** | {$cnt} records | {$qty} units | {$val} |\n";
                }
                $out .= "\n*Analysis: Acquisition records indicate variations in procurement volume and budget allocation between school years.*";
                return $notice . $out;
            }
        }

        // 3. Specific item inquiry (e.g. bond paper)
        if (str_contains($q, 'bond paper') || str_contains($q, 'bondpaper')) {
            return $notice . "### 📄 Bond Paper Inventory Status\n\n- **Status**: High Priority Requisition Item\n- **Rationale**: Bond paper is a consumable school supply with high frequency of teacher requests for exam and module printing. Maintaining an ample buffer prevents stockouts during academic grading periods.";
        }

        // 4. End User questions (What items do I have?)
        if ($roleName === 'End User' && (str_contains($q, 'assigned') || str_contains($q, 'my item') || str_contains($q, 'what do i have'))) {
            $assigned = $context['assigned_items'] ?? [];
            if (empty($assigned)) {
                return "You currently have no equipment assigned in your custody.";
            }
            $out = "### 📦 Your Assigned Equipment\n\n";
            foreach ($assigned as $item) {
                $out .= "- **{$item['item_name']}** (Qty: {$item['quantity']}) — Status: `{$item['status']}`\n";
            }
            return $out;
        }

        // 5. Warehouse availability question
        if (str_contains($q, 'available') || str_contains($q, 'in stock') || str_contains($q, 'stock available')) {
            $availableItems = $context['warehouse_item_availability'] ?? [];

            if ($roleName !== 'End User') {
                $availableItems = array_map(fn ($item) => [
                    'item_name' => $item['item_name'],
                    'units_available' => $item['available_stock'],
                    'stock_status' => $item['available_stock'] > 5 ? 'In Stock' : ($item['available_stock'] > 0 ? 'Low Stock' : 'Out of Stock'),
                ], $context['warehouse_inventory'] ?? []);
            }

            $availableItems = array_values(array_filter($availableItems, fn ($item) => ($item['units_available'] ?? 0) > 0));
            if (empty($availableItems)) {
                return $notice . "There are currently no available items in the warehouse.";
            }

            $out = "### Available Items\n\n";
            foreach (array_slice($availableItems, 0, 25) as $item) {
                $out .= "- **{$item['item_name']}** — {$item['units_available']} available ({$item['stock_status']})\n";
            }
            return $notice . $out;
        }

        // Default response
        return $notice . "Hello **{$user->full_name}** ({$roleName})! I am your AI Inventory Assistant. You can ask me about stock levels, procurement priorities, multi-year stock-in comparisons, or item assignments.";
    }
}
