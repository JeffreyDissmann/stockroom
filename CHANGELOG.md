# Changelog

All notable changes to Stockroom are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [CalVer](https://calver.org/) versioning (`YYYY.MM.PATCH`).

## [Unreleased]

## [2026.05.01] — 2026-05-28

First public release.

### Added

- **Inventory model** — items, containers and rooms in a single recursive tree;
  every item belongs to one household and inherits permissions from it.
- **Multi-user households** — invite-only registration via single-use token links;
  admins and members share one household.
- **Custom fields** — household-wide field definitions (text, number, date,
  boolean, URL) attached per item, searchable when flagged.
- **Tags** — coloured labels with item counts; admin-only creation/editing.
- **Image management** — multiple images per item with thumbs / large / original
  variants, primary image, drag-to-reorder, "search for an image" via Brave
  Search.
- **Hybrid search** — keyword + semantic (vector) search via Meilisearch and
  app-side embeddings (Laravel AI SDK, `userProvided` vectors).
- **AI assistant** — multi-turn chat (slide-over panel + floating mobile FAB)
  backed by local Ollama with read/write tools (`SearchItems`, `GetItem`,
  `InventoryStats`, `CreateItem`, `UpdateItem`, `MoveItem`, `AssignTags`,
  `DeleteItem`). Conversations persist and are forgotten after a configurable
  retention window.
- **Activity log** — every create/update/delete and image upload recorded per
  user.
- **Localization** — German and English UI with per-user locale preference.
- **System settings** — backup/restore the household to a single archive.
- **Typed frontend routes** — Laravel Wayfinder generates a TypeScript route
  tree; CI guards against drift.

[Unreleased]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.01...HEAD
[2026.05.01]: https://github.com/JeffreyDissmann/stockroom/releases/tag/2026.05.01
