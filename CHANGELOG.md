# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.6.2] — 2026-05-17

### Fixed

- **Embed blocks no longer use grid layout.** The Talk Embed and Project Embed wrappers no longer inherit the query block's `display: grid` rule. A single card now renders at full width instead of being constrained to a grid column. The wrapper classes changed from `ppb-talk-query ppb-talk-query--grid` to `ppb-talk-embed` (and `ppb-project-embed` respectively).

## [1.6.1] — 2026-05-15

### Added

- **Talk Embed: occurrence selector.** The Talk Embed block now has an optional `occurrenceDate` attribute. When a talk is selected, its occurrences appear as a dropdown in the sidebar. Selecting one deeplinks the card to the occurrence URL and shows the occurrence's slides, recording, and event URL as inline links below the card meta.
- **Editor block styles.** `blocks.css` is now enqueued on `enqueue_block_editor_assets` so `ServerSideRender` previews match the front-end appearance.
- **CSS custom properties.** All card colours and dimensions use custom properties (e.g. `--ppb-card-bg`, `--ppb-card-border`, `--ppb-status-available-bg`) with the existing values as defaults. Themes can override them via `theme.json` or a stylesheet without specificity battles.
- CSS for the new occurrence links section (`.ppb-talk-embed__links`, `.ppb-talk-embed__link`).
- German translation updated with new strings.

### Changed

- **Readme consolidation for public release.** `readme.txt` is now the single source for the plugin description, FAQ, and changelog. `README.md` is a brief overview pointing to `readme.txt` and the theme integration docs.
- Pre-1.0 changelog entries condensed into a summary.

## [1.6.0] — 2026-05-15

### Added

- **Talk Embed block** (`personal-profile-builder/talk-embed`) — pick any published talk from a combobox search and display it as a single card on any page or post.
- **Project Embed block** (`personal-profile-builder/project-embed`) — same concept for projects.
- Both blocks are server-rendered with `theme.json` support.

### Changed

- `Talk_Query::render_card()` and `Project_Query::render_card()` are now `public` so the embed blocks can call them directly.

## [1.5.0] — 2026-05-10

### Changed

- Removed "Default event name" and "Default event URL" from the Talk details sidebar. These are now per-occurrence only. Meta keys remain registered.
- Talk Query block: "Next occurrence" ordering now performs a true occurrence-date sort.

### Added

- Slides upload button in occurrence rows (WP Media Library).
- "Sort retired talks to bottom" toggle in the Talk Query block.

## [1.4.0] — 2026-05-08

### Fixed

- UTF-8 encoding in occurrence JSON. Non-ASCII characters stored as literal UTF-8 via `JSON_UNESCAPED_UNICODE`.

### Added

- `_talk_format` meta key (talk format, e.g. "Workshop").
- `_talk_event_url` meta key with per-occurrence override.
- `event_url` field in occurrence data and REST API.

### Changed

- Occurrence list links now point to event URL instead of virtual occurrence URL.

## [1.3.0] — 2026-05-06

### Changed

- Status label "Available to book" shortened to "Available".
- German translation of "Retired" changed to "Archiviert".

### Added

- `noindex, nofollow` robots meta on occurrence URLs.

## [1.2.0] — 2026-05-04

### Added

- `_talk_language` and `_talk_target_audience` meta keys.
- Removed editor sidebar tooltips for cleaner UI.

## [1.1.0 – 1.1.2] — 2026-05-03

- Moved meta fields to the block editor sidebar.
- Added complete German (de_DE) translation (PO/MO/JSON).
- Fixed translation loading for block editor scripts.
- Added `_project_start_date` and `_project_end_date` meta keys.

## [1.0.0] — 2026-05-03

First stable release. Full security audit covering capability checks, nonce verification, input sanitisation, output escaping, autosave/revision bypass, occurrence resolution edge cases, translation strings, and type safety.

## Pre-1.0 (0.1.0 – 0.6.0)

Development phases building the plugin foundation:

- **0.1.0** — Plugin bootstrap, `talk` and `project` post types, taxonomies, meta keys, settings page, uninstall routine.
- **0.2.0** — Per-event URLs (`/talk/<slug>/YYYYMMDD`), meta override filter, rewrite rule flushing.
- **0.3.0** — Admin meta boxes, repeatable occurrence rows, list table columns and filters.
- **0.4.0** — Organiser view (`?view=organiser`), theme-overridable bio box template.
- **0.5.0** — Talk Query and Project Query blocks, block category, shared block CSS.
- **0.6.0** — Query helpers, template tags, REST API extensions (talk status, occurrences, speaker endpoint).
