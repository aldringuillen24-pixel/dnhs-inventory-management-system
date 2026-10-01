<?php

return [
    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 5),
    'ai_context_ttl_minutes' => (int) env('AI_INVENTORY_CONTEXT_TTL_MINUTES', 15),
];