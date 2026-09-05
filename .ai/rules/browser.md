---
paths:
  - 'tests/Browser/**'
---

# Browser

## A hanging browser suite is usually leaked Playwright servers, not a bad test
Every `tests/Browser` run leaves a `playwright run-server` process alive in the Sail container, and they are never reaped. They accumulate across days: 43 of them once held 6.8 GB of the container's 7.8 GB, and a suite that normally takes 55s blew past a 600s timeout producing no output at all.

An interrupted run makes it worse — a `pest` process killed by a tool timeout keeps running inside the container and holds the test database, blocking every later run with the identical symptom.

Check this before debugging the test you changed last:
  vendor/bin/sail exec -T laravel.test bash -c "ps aux | grep -E 'pest|playwright run-server' | grep -v grep | wc -l; free -m | head -2"

Clear with `pkill -f 'playwright run-server'` and `pkill -f 'vendor/bin/pest'` inside the container, then re-run.

## Clicking a DropdownMenu trigger hangs the run — route the test around the ⋮ menu
`->click()` on a Radix `DropdownMenuTrigger` (e.g. `@item-actions-more` on the item page) never returns. It is not a 30s actionability failure that reports itself — the run deadlocks, `timeout` kills it with exit 124 and *zero* output, and it leaves a `pest` + `playwright run-server` behind.

That last part makes it look exactly like the leaked-server rule above, so check the process count first: if the container is clean and a dropdown click is in the test, this is the cause, not leakage.

Verified pre-existing at 6f01236 (2026-09). On the same page, at the same commit, `assertPresent('@item-actions-more')` passes in ~2.7s; adding `->click()` on it hangs. So no browser test can currently drive any dropdown menu.

Reach the action another way instead. ConfirmDialogTest uses `visit(...)->on()->desktop()` and the inline `@item-delete` button, which is `xl:`-only and therefore invisible at the default viewport.

Cause unconfirmed — the suspicion is Radix portaling its content and setting `pointer-events: none` on `document.body`, which defeats Playwright's actionability wait. Nobody has checked whether `force: true` gets past it.
