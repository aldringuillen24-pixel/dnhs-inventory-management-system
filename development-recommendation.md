<!-- ## Work Breakdown: Reliable Follow-Up Questions

**Goal:** The assistant keeps track of the current topic, understands common follow-ups, and asks instead of guessing when meaning is unclear.

1. **Agree on supported follow-ups**
   - Cover clarification choices: “the first one,” category, serial number, or inventory ID.
   - Cover follow-up questions: “where is it?”, “how many are available?”, “why?”, and “what about paper?”
   - Define which messages start a new topic.
   - **Done when:** There’s an agreed list of examples and expected responses.

2. **Define what conversation memory must retain**
   - Keep the original question’s purpose, selected item or request, filters, and clarification choices.
   - Keep it scoped to the signed-in user and expire it after a reasonable period.
   - **Done when:** Each supported follow-up has enough context to continue accurately.

3. **Handle clarification replies consistently**
   - Interpret numbered choices and item details against the candidates the assistant actually showed.
   - If a reply matches one candidate, select it and continue the original question.
   - If it matches multiple or none, ask again rather than switching questions silently.
   - **Done when:** A clarification can’t accidentally turn into an unrelated answer. -->

<!-- 4. **Interpret follow-ups against the current topic**
   - Recognize common short references such as “it,” “those,” “why?”, and “what about paper?”
   - Apply the new request to the remembered topic and item.
   - Clear or replace memory when the user clearly changes topics.
   - **Done when:** Supported follow-ups continue the intended conversation, while new questions start cleanly. -->

5. **Keep answers grounded and role-safe**
   - Recheck permissions on every message.
   - Fetch current facts from the database; don’t rely on old answer text.
   - Ask for clarification if the remembered reference is no longer unique.
   - **Done when:** Follow-ups never expose unauthorized data or invent missing facts.

6. **Add focused conversation tests**
   - Test successful selections, “first one,” unclear selections, common follow-ups, topic changes, expired memory, and role restrictions.
   - Verify that ambiguous messages prompt clarification instead of returning a different answer.
   - **Done when:** The tests cover the agreed examples and existing assistant tests still pass.

7. **Roll out gradually**
   - Start with the most common inventory follow-ups.
   - Review real usage and add new phrases only when their intended behavior is clear.
   - **Done when:** New follow-up types are supported by examples and tests before release.

The main implementation areas are the conversation handling in `AiAssistantController.php`, question interpretation in `InventoryQuestionRouter.php`, and clarification/context logic in `InventoryAnswerService.php`.






Implement work breakdown item 5: keep inventory assistant follow-up answers grounded and role-safe.

Files:
- app/Http/Controllers/AiAssistantController.php
- app/Services/InventoryAnswerService.php
- app/Services/AiCapabilityPolicy.php
- tests/Feature/AiAssistantTest.php
- tests/Feature/AiAssistantRoleScopeTest.php
- docs/ai-follow-up-behavior.md

Requirements:
- Recheck the signed-in user’s permission for every message before protected data is queried or returned.
- Reload current inventory data from the database for each follow-up; never treat saved context or previous answer text as current facts.
- If the referenced record is missing, stale, disposed, ambiguous, or no longer authorized, discard the context and ask a short clarification.
- Never guess or expose unauthorized data. Keep external AI explanations limited to authorized, database-derived facts.

Notes:
Build on the existing per-user context and role policy. Keep changes focused. Add tests for changed permissions, stale or ambiguous references, fresh database values, and denial before data access. Run relevant assistant tests and report unrelated failures without fixing them.

Final response:
No filler. Give a brief change summary and concise verification tips, including test commands and key scenarios.






Implement work breakdown item 6: add focused tests for inventory assistant conversations.

Files:
- tests/Feature/AiAssistantTest.php
- tests/Feature/AiAssistantRoleScopeTest.php
- tests/Unit/AiAssistantControllerTest.php, only if controller-specific coverage is needed

Requirements:
- Test valid clarification choices: “the first one,” category, serial number, and inventory ID.
- Test invalid or ambiguous choices ask again and never select an unrelated item.
- Test common follow-ups: “Where is it?”, “How many are available?”, “Why?”, and “What about paper?”
- Test topic changes, missing/expired context, current database values, per-user isolation, and role restrictions.
- Assert unauthorized follow-ups are denied before protected inventory data is queried.
- Use real database state. Confirm factual answers do not call the external AI provider.

Notes:
Add tests for gaps in the existing coverage; avoid duplicating tests that already prove the behavior. Change production code only to fix a defect revealed by a test, and keep that fix narrowly scoped.

Verification:
Run:
- `php artisan test tests/Feature/AiAssistantTest.php --compact`
- `php artisan test tests/Feature/AiAssistantRoleScopeTest.php --compact`
- `php artisan test tests/Unit/AiAssistantControllerTest.php --compact`

Final response:
No filler. Briefly list test coverage added, commands run, and any failures.