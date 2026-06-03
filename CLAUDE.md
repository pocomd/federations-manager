# Project rules — enforced, no exceptions

## Git: never commit or push without explicit approval

1. Make changes.
2. Show a summary of what changed (files + what each does).
3. **STOP. Do not run `git add`, `git commit`, or `git push`.**
4. Wait for the user to explicitly say "commit" or "ok, commit it".
5. Only then commit.
6. Show `git log --oneline origin/main..HEAD` and wait for explicit "push" approval.
7. Only then push.

**Phrases like "commit all the X work" said during planning do NOT count as approval to commit.**
Only commit after the full task is done AND the user explicitly approves.
