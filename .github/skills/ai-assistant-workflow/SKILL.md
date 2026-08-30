---
name: ai-assistant-workflow
description: "Use when improving or reviewing the DNHS AI Inventory Assistant, OpenRouter requests, prompts, role-scoped context, chat history, fallbacks, privacy, reliability, or AI tests."
---
# AI Assistant Workflow

Use this workflow for changes to the AI assistant and its data context.

## Inspect
- Trace the request route and controller into `app/Services/AiInventoryService.php`.
- Read `config/services.php` and relevant models, views, and tests.
- Identify the authenticated role and the exact context fields sent to the provider.

## Safety Checks
- Enforce least-privilege context for every role.
- Never send passwords, temporary passwords, secrets, tokens, or unnecessary personal data.
- Treat user prompts and model output as untrusted.
- Bound input length, history size, provider timeouts, retries, and token output.
- Preserve a useful deterministic fallback when the key is absent or the provider fails.
- Do not claim an action or live result that was not verified.

## Implementation
1. State the controlling code path and one focused behavior check.
2. Keep provider-specific code isolated.
3. Add tests for role scope, fallback behavior, malformed provider responses, and sensitive-data boundaries as relevant.
4. Make the smallest focused edit.

## Validation
Run focused AI tests, then `php artisan test` when practical. Inspect logs and responses for sensitive data. Report provider assumptions, privacy risks, and fallback behavior.
