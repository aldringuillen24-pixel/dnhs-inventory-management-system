---
description: "Review or improve a DNHS AI assistant behavior for scope, privacy, reliability, and fallback handling."
name: "AI Assistant Review"
argument-hint: "Describe the AI behavior to review"
agent: "DNHS AI Assistant Engineer"
---
Review this AI assistant behavior:

${input:behavior:Describe the prompt, role, provider request, context, history, fallback, or response behavior}

Trace the route, controller, service, configuration, and tests. Check role-scoped data, prompt injection boundaries, sensitive-data exposure, timeouts, retries, malformed provider responses, and deterministic fallback behavior. Lead with concrete findings ordered by severity, then propose the smallest fix and focused tests.
