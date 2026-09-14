---
name: sumrize
description: 'Create a necessary, comprehensive handoff summary of the previous conversation for the next chat and save it as a Markdown file in the chathis folder. Use when the user types sumrize, /sumrize, summarize, or asks for conversation continuity, context compression, or a next-chat handoff.'
argument-hint: 'Optional focus, such as code changes, decisions, or unresolved issues'
user-invocable: true
disable-model-invocation: false
---

# Conversation Handoff Summary

## Purpose

Turn the previous conversation into a reliable handoff for a new chat. Preserve facts needed to continue the work while removing greetings, repetition, filler, abandoned ideas, and irrelevant detail.

## Procedure

1. Review the complete available conversation, including user requests, assistant decisions, tool results, edits, test results, errors, and the latest user intent.
2. Identify the active task and distinguish completed work from proposed or abandoned work.
3. Record exact project facts, paths, symbols, routes, commands, and behavior only when they are needed to continue.
4. Record changed files and the reason for each change. Do not claim a change was made unless the conversation or tool result confirms it.
5. Record validation commands and their actual outcomes, including failed or skipped checks.
6. Record unresolved issues, known regressions, assumptions, blockers, and the smallest next action.
7. Remove secrets, passwords, tokens, private personal data, and unnecessary raw logs. Refer to a secret as `[redacted]`.
8. Produce the handoff in the format below. Keep it self-contained and readable in a new chat.
9. Always create the `chathis/` folder at the workspace root if it does not exist.
10. Always generate and save the complete handoff as a Markdown file at `chathis/conversation-handoff-YYYY-MM-DD-HHmm.md`, using the current local date and time. Do not overwrite an existing handoff; add seconds or a numeric suffix when needed.
11. Return the same handoff in the chat response and identify the exact `.md` file that was created inside `chathis/`.

## Output Format

# Conversation Handoff

## Objective
- The current goal in one or two sentences.

## Context
- Framework, repository, relevant role, feature, or constraints.

## Decisions
- Decisions that should not be revisited without new evidence.

## Completed Changes
- `path/to/file`: change and behavioral effect.

## Current State
- What works now.
- What remains uncertain.

## Validation
- `command`: result.
- Include skipped commands explicitly.

## Known Issues
- Each unresolved issue with its evidence and impact.

## Next Action
- The smallest concrete action that should happen first in the next chat.

## Working Rules

- Prefer facts over interpretation; label assumptions as assumptions.
- Use workspace-relative file paths and exact symbol or route names.
- Mention only details that help implement, debug, review, or validate the next step.
- Keep the summary comprehensive enough to resume work without rereading the old chat, but do not reproduce the conversation.
- If the previous conversation contains no actionable work, state that plainly and summarize the latest request.
- Do not modify files when running this skill unless the user separately asks for implementation.
- The handoff Markdown file inside `chathis/` is the required output of this skill; creating that file and its containing folder is allowed.
