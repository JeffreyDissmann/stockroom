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
