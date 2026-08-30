# AI Assistant

This project includes a role-aware AI assistant that helps users answer inventory questions using live data from the application rather than a generic external chatbot.

## 1. Purpose

The AI assistant exists to support role-specific decision making in the DNHS inventory system. It is designed to answer questions such as:

- what inventory is currently available
- which items are low on stock
- which requests are waiting for approval
- what is assigned to a user
- what has been procured over time
- what should be prioritized for ordering

The key design principle is that the assistant should be grounded in the current system data and the authenticated user's role.

## 2. Main files

- [app/Services/AiInventoryService.php](../app/Services/AiInventoryService.php)
- [app/Http/Controllers/AiAssistantController.php](../app/Http/Controllers/AiAssistantController.php)
- [config/services.php](../config/services.php)
- [routes/web.php](../routes/web.php)

## 3. Request flow

The flow is simple:

1. The frontend sends a chat message to the AI endpoint.
2. The controller validates the incoming payload.
3. The authenticated user is loaded from the request.
4. The service builds role-scoped context from database records.
5. A system prompt is assembled with the role and current context.
6. The AI request is sent to OpenRouter if an API key is configured.
7. If the external request fails or the key is missing, the app falls back to a local grounded response.

The AI endpoint is defined in [routes/web.php](../routes/web.php):

```php
Route::middleware('auth')->post('/api/ai/chat', [AiAssistantController::class, 'chat'])->name('ai.chat');
```

## 4. Role-scoped context design

The most important part of the AI service is `buildRoleScopedContext()`. It chooses the correct dataset depending on the current user's role.

### End User context

Includes:

- assigned items
- recent requests
- warehouse availability

This is useful for asking things like:

- what items are assigned to me
- what is the current warehouse stock
- what are my recent requests

### Property Custodian context

Includes:

- grouped inventory data
- available vs assigned stock
- low stock critical items
- pending requisitions
- stock-in history by year

This is the richest context because the custodian manages inventory operations.

### School Head context

Includes:

- executive summary metrics
- category breakdown
- annual acquisition trend

This is for management-level reporting and high-level stock monitoring.

### Administrator context

Includes:

- registered users by role
- total inventory records
- total transactions logged
- category overview

This is for operational oversight of the system itself.

## 5. OpenRouter integration

The app sends requests to OpenRouter with the configured API key and preferred model.

Relevant config is in [config/services.php](../config/services.php):

```php
'openrouter' => [
    'api_key' => env('OPENROUTER_API_KEY'),
    'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
    'max_attempts' => env('OPENROUTER_MAX_ATTEMPTS', 2),
    'connect_timeout' => env('OPENROUTER_CONNECT_TIMEOUT', 5),
    'timeout' => env('OPENROUTER_TIMEOUT', 12),
    'site_url' => env('APP_URL', 'http://localhost'),
    'site_name' => env('APP_NAME', 'DNHS Inventory Management System'),
],
```

The service tries a primary model and a small list of fallback models to improve reliability.

## 6. Fallback behavior

A key requirement of the app is resilience.

If:

- the OpenRouter API key is missing
- the request times out
- the upstream model returns an error

then the system calls `generateLocalFallbackResponse()` instead of failing outright.

This fallback is intentionally grounded in local data and still provides useful operational answers such as:

- recommended procurement priorities
- available warehouse inventory
- assigned items for end users
- comparative stock-in summaries

This design prevents the feature from becoming unusable in a local dev or restricted environment.

## 7. Prompt engineering pattern

The assistant builds a system prompt containing:

- the user's full name
- the user's role
- a JSON snapshot of role-specific inventory context
- role-aware instructions for tone and decision making

The prompt tells the AI to:

- answer in a concise professional format
- tailor answers to role permissions
- prioritize critical low-stock items for procurement questions
- compare annual acquisition data carefully
- avoid exposing protected financial or sensitive information to end users

## 8. Data assumptions

The AI relies on real database models such as:

- [app/Models/Inventory.php](../app/Models/Inventory.php)
- [app/Models/AssignmentRequest.php](../app/Models/AssignmentRequest.php)
- [app/Models/Transaction.php](../app/Models/Transaction.php)
- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Category.php](../app/Models/Category.php)

It organizes this data into arrays for the model to reason over. This makes the assistant more reliable than asking an LLM to guess based only on a generic prompt.

## 9. Example use cases

### End User

> “What items are assigned to me?”

The assistant can inspect assigned requests and convert them into plain English output.

### Property Custodian

> “What items should I procure first?”

The assistant can identify critically low stock and ranking items by urgency.

### School Head

> “Show me stock-in trends for the past two years.”

The assistant can compare annual procurement summaries from the inventory data.

### Administrator

> “What is the current system overview?”

The assistant can summarize user counts, transaction totals, and inventory totals.

## 10. Important implementation note

The AI assistant is not a free-form general-purpose assistant. It is intentionally scoped to the project’s inventory system and uses domain-specific context to keep responses useful and grounded.

That is why the assistant is tightly coupled to:

- the authenticated user
- their role
- the current inventory and request state
- the system’s business rules

## 11. Recommended environment variables

Add the following to `.env` if you want live AI behavior:

```env
OPENROUTER_API_KEY=your_api_key_here
OPENROUTER_MODEL=meta-llama/llama-3.3-70b-instruct:free
OPENROUTER_MAX_ATTEMPTS=2
OPENROUTER_CONNECT_TIMEOUT=5
OPENROUTER_TIMEOUT=12
```

If these are not set, the app will continue functioning with local fallback responses.

## 12. Summary

The AI assistant is a domain-specific layer built around the application’s real inventory, request, and user data. It provides role-aware recommendations and summaries while keeping the system resilient when external AI services are unavailable.
