<?php

namespace Tests\Support;

class AssistantTestKeywordMatcher
{
    private const TOPIC_KEYWORDS = [
        'pendingRequests' => [
            'pending request', 'pending requests', 'requests pending', 'waiting for approval',
            'requests waiting', 'waiting requests', 'unprocessed request', 'unprocessed requests',
            'unprocessed item request', 'unprocessed item requests', 'outstanding request',
            'outstanding requests', 'awaiting approval', 'requests awaiting approval',
            'requests that are pending', 'requests that are waiting', 'assignment request',
            'assignment requests', 'return request', 'return requests', 'item request', 'item requests',
            'requests to approve', 'requests needing approval', 'requests waiting for approval',
        ],
        'lowStock' => [
            'low stock', 'low in stock', 'critical stock', 'running out', 'run out', 'running low',
            'nearly out', 'almost out', 'need restock', 'needs restocking', 'need to restock',
            'needs replenishment', 'replenishment needed', 'low quantity', 'low inventory',
            'critical inventory', 'insufficient stock', 'not enough stock', 'short on stock',
            'stock shortage', 'inventory shortage', 'below minimum stock', 'below reorder level',
            'reorder needed', 'needs reorder', 'should be reordered',
        ],
        'availability' => [
            'available', 'availability', 'in stock', 'stock available', 'currently available',
            'currently in stock', 'warehouse', 'on hand', 'on-hand', 'still have', 'do we have',
            'have any', 'remaining quantity', 'quantity left', 'stock left', 'stock is left',
            'stock remaining', 'remaining stock', 'remaining inventory', 'how many left',
            'how much left', 'current quantity', 'current stock', 'current inventory',
            'quantity available', 'number available', 'how many available', 'how many in stock',
        ],
        'assigned' => [
            'assigned', 'assigned to', 'who has', 'who is using', 'who is assigned',
            'who is responsible for', 'who is in charge of', 'who is in possession of',
            'assigned items', 'items assigned', 'items assigned to', 'items in possession of',
            'currently assigned', 'currently being used', 'being used by', 'issued to',
            'issued item', 'issued items', 'assigned equipment', 'assigned asset',
            'assigned assets', 'assigned inventory',
        ],
        'disposed' => [
            'disposed', 'disposed of', 'discarded', 'thrown away', 'trashed',
            'removed from inventory', 'no longer in inventory', 'no longer available',
            'disposal record', 'disposal records', 'items disposed', 'disposed items',
            'items discarded', 'discarded items', 'removed items', 'inventory disposal',
            'disposal history', 'what was disposed',
        ],
        'underMaintenance' => [
            'under maintenance', 'being repaired', 'in repair', 'in maintenance', 'not working',
            'broken', 'malfunctioning', 'needs repair', 'need repair', 'needs maintenance',
            'requires maintenance', 'repair needed', 'maintenance needed', 'currently being repaired',
            'currently under repair', 'out for repair', 'damaged', 'damaged items', 'broken items',
        ],
        'readyToDispose' => [
            'ready to dispose', 'ready for disposal', 'ready for discard', 'ready for removal',
            'ready to be discarded', 'ready to be removed', 'ready to be trashed',
            'items ready for disposal', 'should be disposed', 'should be discarded',
            'for disposal', 'for discard', 'for removal', 'recommended for disposal',
        ],
        'itemSearch' => [
            'find', 'search', 'look for', 'locate', 'show me', 'list', 'where can i find',
            'do we have a', 'is there a',
        ],
        'location' => [
            'where is', 'where are', 'where can i find', 'location of', 'located', 'location',
            'stored', 'storage location', 'which room', 'what room', 'room number',
            'where is it stored', 'where is this item',
        ],
        'purchaseHistory' => [
            'purchase history', 'purchases', 'purchased', 'bought', 'bought items',
            'what did we purchase', 'what have we purchased', 'purchase records',
            'procurement history', 'procurement records', 'recent purchases', 'previous purchases',
        ],
        'demandForecast' => [
            'forecast', 'forecasting', 'demand forecast', 'demand forecasting', 'future demand',
            'expected demand', 'predicted demand', 'forecasted demand', 'demand prediction',
            'predict demand', 'how much will we need', 'how many will we need', 'expected usage',
            'future inventory needs', 'projected demand',
        ],
        'procurement' => [
            'what should we buy', 'what should we purchase', 'what do we need to buy',
            'what needs to be purchased', 'what should we reorder', 'what should we restock',
            'purchase recommendation', 'purchasing recommendation', 'procurement recommendation',
            'procurement priority', 'procurement priorities', 'recommended purchases',
            'recommended items to buy', 'items to purchase', 'items to buy', 'what needs purchasing',
        ],
        'itemHistory' => [
            'item history', 'inventory history', 'history of', 'item activity', 'inventory activity',
            'transaction history', 'movement history', 'item transactions', 'inventory transactions',
            'what happened to', 'history for',
        ],
        'inventoryMovement' => [
            'inventory movement', 'inventory movements', 'stock movement', 'stock movements',
            'moved', 'transferred', 'transfer history', 'item transfer', 'item transfers',
            'transferred items', 'movement of items', 'items moved',
        ],
        'quantityChanges' => [
            'quantity changed', 'quantity change', 'stock change', 'stock changes',
            'increase in stock', 'decrease in stock', 'stock increased', 'stock decreased',
            'inventory increased', 'inventory decreased', 'added to inventory', 'removed from inventory',
        ],
        'expired' => [
            'expired', 'expired items', 'expiration', 'expiration date', 'past expiration',
            'already expired', 'near expiration', 'expiring soon', 'about to expire',
        ],
        'inventorySummary' => [
            'inventory summary', 'stock summary', 'inventory overview', 'stock overview',
            'overall inventory', 'inventory situation', 'inventory report', 'stock report',
            'how is our inventory', 'how are we doing', 'give me an inventory summary',
            'summarize the inventory', 'summarize our stock',
        ],
        'count' => [
            'how many', 'how much', 'total number', 'total quantity', 'total items',
            'number of items', 'count of', 'how many items', 'how many units',
        ],
        'reports' => [
            'report', 'reports', 'generate report', 'inventory report', 'stock report',
            'maintenance report', 'disposal report', 'procurement report', 'assignment report',
            'forecast report', 'summary report',
        ],
        'maintenanceHistory' => [
            'maintenance history', 'repair history', 'repair records', 'maintenance records',
            'past repairs', 'previous repairs', 'maintenance activity', 'repair activity',
        ],
        'assignmentHistory' => [
            'assignment history', 'assignment records', 'assignment history of', 'previously assigned',
            'previous assignment', 'past assignments', 'assignment activity', 'who previously had',
        ],
    ];

    public function matchTopics(string $normalizedQuestion): array
    {
        $matches = [];
        foreach (self::TOPIC_KEYWORDS as $topic => $phrases) {
            $matches[$topic] = $this->containsAny($normalizedQuestion, $phrases);
        }

        return $matches;
    }

    private function containsAny(string $question, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            $pattern = '/(?:^|\s)' . preg_quote($phrase, '/') . '(?:$|\s)/u';
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }
}