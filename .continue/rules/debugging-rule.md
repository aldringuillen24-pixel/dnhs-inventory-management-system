---
description: Rules for diagnosing and fixing errors systematically
---

# Debugging Rules

- Identify the root cause before modifying code.
- Read the complete error message and relevant stack trace.
- Inspect the code responsible for the error before proposing a fix.
- Do not blindly change multiple files to eliminate an error.
- Reproduce the problem when possible.
- Make the smallest appropriate change to fix the root cause.
- Do not hide errors by suppressing exceptions or warnings.
- Do not remove functionality simply because it causes an error.
- After fixing an issue, check for possible side effects.
- Verify that the original problem has actually been resolved.
- If the cause cannot be confirmed, clearly state the uncertainty.