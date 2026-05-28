# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Email **jeffrey@dissmann.net** with:

- a description of the issue,
- steps to reproduce,
- the affected version (Stockroom uses CalVer — e.g. `2026.05.01`),
- and, if you have one, a suggested fix.

You can expect an acknowledgement within a few days. Stockroom is a side
project maintained by one person, so fix timelines are best-effort.

## Supported versions

Only the most recent CalVer release receives security fixes. Older releases
are not patched — upgrade to the latest tag.

## Scope

Stockroom is self-hosted. The threat model assumes:

- The host operator is trusted and controls the network boundary.
- Registration is invite-only; the first admin is bootstrapped from
  environment variables on first boot.
- The optional AI assistant talks to an Ollama endpoint that the operator
  controls — Stockroom does not send inventory data to third parties unless
  the operator configures a hosted AI provider.

Reports about issues that require an attacker to already control the host,
the database, or an admin account are still welcome but lower priority.
