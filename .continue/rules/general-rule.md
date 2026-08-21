---
description: General development rules for maintaining clean, safe, consistent, and maintainable code
---

# General Development Rules

## 1. Understand Before Changing
- Always inspect the relevant existing code before making changes.
- Understand the current architecture, dependencies, and data flow before implementing a solution.
- Reuse existing components, utilities, functions, and patterns when appropriate.
- Do not make assumptions about code that has not been inspected.

## 2. Preserve Existing Functionality
- Do not remove or break existing functionality unless explicitly requested.
- Avoid unnecessary changes to unrelated files.
- Prefer small, focused changes over large rewrites.
- Preserve existing behavior unless the requested change requires otherwise.

## 3. Code Quality
- Write clean, readable, maintainable, and modular code.
- Follow the conventions and patterns already established in the project.
- Use meaningful names for variables, functions, classes, methods, and files.
- Avoid duplicated code when a reusable solution is appropriate.
- Keep functions and classes focused on a clear responsibility.
- Avoid unnecessary complexity, abstractions, and dependencies.

## 4. Error Handling
- Handle expected errors appropriately.
- Do not silently ignore errors.
- Provide useful error messages when appropriate.
- Consider edge cases and invalid input before implementing a solution.

## 5. Security
- Never expose passwords, API keys, tokens, secrets, or other sensitive information.
- Validate and sanitize user-controlled input.
- Follow the security practices appropriate for the language, framework, and application.
- Do not disable security mechanisms simply to make an error disappear.

## 6. Dependencies
- Prefer existing project dependencies when they can solve the problem.
- Do not install or introduce a new dependency unless it provides a clear benefit.
- Before adding a dependency, check whether the project already has an equivalent solution.

## 7. Configuration
- Do not hardcode environment-specific values.
- Use the project's existing configuration and environment-variable conventions.
- Do not modify environment or deployment configuration unless it is relevant to the requested task.

## 8. Testing and Verification
- Consider how the change can be tested before implementing it.
- After making changes, check for syntax errors, obvious regressions, and inconsistencies.
- When tests exist, follow the project's existing testing conventions.
- Do not claim that something works unless it has been reasonably verified.

## 9. Communication
- Explain what you are changing and why when the change is significant.
- If requirements are ambiguous, identify the ambiguity instead of making risky assumptions.
- If an approach has important trade-offs, explain them briefly.
- If a requested change conflicts with the existing architecture, point it out before proceeding.

## 10. Development Approach
- Follow a structured, incremental approach.
- Analyze → Plan → Implement → Verify.
- Prefer the simplest solution that correctly satisfies the requirement.
- Do not over-engineer simple features.
- Keep the project consistent as it grows.