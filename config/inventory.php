<?php

return [
    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 5),
    'ai_context_ttl_minutes' => (int) env('AI_INVENTORY_CONTEXT_TTL_MINUTES', 15),

    /*
     * How long an idle conversation is kept before it is purged.
     *
     * This governs retention, not access. The short session snapshot above still
     * expires; when it does, the recorded turns rebuild it, so a user who comes
     * back later is not treated as having no history at all.
     */
    'ai_conversation_retention_days' => (int) env('AI_INVENTORY_CONVERSATION_RETENTION_DAYS', 7),
];