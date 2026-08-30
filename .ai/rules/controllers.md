---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Run wayfinder:check after any controller edit, not just before pushing
The generated route tree embeds `@see <file>:<line>` comments, so adding a single `use` statement or method shifts them and leaves the committed tree stale.

The drift is invisible in normal work — tests, Pint and vue-tsc all pass with a stale tree. Only the `.githooks/pre-push` hook and CI catch it, so it surfaces at the worst moment and any "ready to push" claim made without running the check is unfounded.

Run `vendor/bin/sail npm run wayfinder:check` before calling controller work done. If it fails, `wayfinder:generate`, confirm the diff is only `@see` line numbers, and include it in the commit. Regenerate last — doing it before the final controller edit is wasted work.
