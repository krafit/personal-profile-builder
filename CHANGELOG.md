# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.7.0] — 2026-05-21

### Added

- **QR codes for talk occurrences.** Each occurrence row in the admin UI has a "QR Code" button next to the shareable URL. Clicking it generates an SVG QR code inline using the bundled [qrcode-svg](https://github.com/papnkukn/qrcode-svg) library (MIT, pure JavaScript, no external services). The QR encodes the occurrence URL and can be downloaded as an SVG file. The inline preview hides itself when the occurrence date changes, and the downloaded `.svg` includes a proper XML declaration while the inline markup omits it (so it embeds correctly in the editor DOM).
- Bundled `qrcode-svg` v1.1.0 at `assets/js/vendor/qrcode-svg.min.js` with its MIT license file.
- **Per-occurrence language field.** Each row in `_talk_occurrences` may carry an optional `language` key holding a WordPress locale code (e.g. `de_DE`). Surfaced in the admin row UI as a dropdown, in the front-end occurrence list as a pill, and in the talks list table as a new "Languages" column. Read defensively (`$row['language'] ?? ''`).
- **Cross-subsite occurrence sync.** On a WordPress multisite running [Multisite Language Switcher](https://github.com/lloc/Multisite-Language-Switcher), saving the occurrence list on one talk propagates the same list to every MSLS-linked translation. Last-write-wins with empty-target protection (an empty sibling adopts the source; a populated sibling can only be overwritten by an explicit save). Re-entrancy is guarded by a static flag plus a hash-equality short-circuit. A new "Occurrence sync status" meta box on the talk edit screen flags divergence between linked talks and offers Push / Pull / Merge reconciliation actions.
- **Occurrence URL language redirect.** When a visitor opens `/talk/<slug>/YYYYMMDD` and the occurrence's `language` differs from the current subsite's locale, the visitor is bounced (`302`) to the matching-language subsite's occurrence URL — query strings preserved, so `?view=organiser` round-trips correctly. New `ppb_msls_redirect_target` filter lets themes/site code override the URL.
- **Front-end language filter.** When an occurrence list contains more than one distinct language, a row of toggle pills appears above the list. Filter state is reflected in the URL hash (`#lang=de_DE`), so a filtered view is shareable. Suppressible via the `ppb_occurrence_filter_enabled` filter (default `true`) or `Template_Tags::occurrence_list( [ 'with_filter' => false ] )`.
- **REST extensions.** Occurrence objects gain `language`, `language_name` (when set), and `language_flag_url` (when MSLS is available). New read-only route `personal-profile-builder/v1/talks/<id>/sync-status` mirrors the data behind the reconciliation meta box.
- **WP-CLI command.** `wp ppb sync-occurrences <talk_id>` (with optional `--source=<locale>` or `--merge`) drives the same fan-out / pull / merge logic from the command line. Useful after migrations or backup restores.
- **Internal: `MSLS_Integration::allowed_locales()`** — flat list of locale codes for membership testing in validation paths. Public API but primarily used by sanitisers and migrations that don't need labels.
- German translation updated with new strings.

### Changed

- **`_talk_language` is now locale-validated and multi-value.** The talk-level language field, free-form since 1.2.0, now accepts only WordPress locale codes (e.g. `de_DE`). It is also registered with `single => false` — a talk can be given in multiple languages, and reads return an array via `get_post_meta( $id, '_talk_language', false )`. The block editor sidebar surfaces it as a `FormTokenField` (the component used for post tags). Free-form values from earlier saves are preserved on upgrade in a temporary `_talk_language_legacy` meta key and surfaced to the editor as a notice asking the user to pick the equivalent locale(s). The legacy meta key will be removed in 1.8.0.
- **Theme implications for `_talk_language`:** if your theme renders this meta key directly, two things changed at once. (a) The value is now a locale code rather than free text — wrap in `format_code_lang()` to get a human-readable name. (b) The value is now an array — use `get_post_meta( $id, '_talk_language', false )` and iterate. See `THEME-MIGRATION.md` for a one-liner backward-compatible read pattern.

### Hardened

- `Query_Helpers::get_occurrences()` drops non-array rows before its typed sort/filter callbacks, preventing a possible `TypeError` under `strict_types` on malformed occurrence JSON (matching the meta-override filter's existing handling).
- The occurrence save handler verifies the posted value is a string before processing, avoiding an "Array to string conversion" warning on crafted input.
- The occurrence sanitiser now drops any row whose date is not a real Gregorian date, consistent with how URLs are validated at render time.
- Language names (e.g. "English", "German") shown for occurrences now render in the active locale. `format_code_lang()` returns hard-coded English names, so the result is passed through WordPress core's own `default` text domain via `translate()`. This reuses core's existing language-name translations rather than maintaining a plugin-side list; names without a core translation fall back to English.

### Implementation notes

`MSLS_Integration::locale_choices()` and `MSLS_Integration::locale_name()` lazy-load `wp-admin/includes/ms.php` on demand, so the `format_code_lang()` function they depend on is available on front-end requests as well as admin requests. The validation paths bypass labels entirely and use `allowed_locales()` instead. Because `format_code_lang()` returns untranslated English names, both label-producing methods pass the result through `translate( $name, 'default' )` so language names follow the active locale using core's own catalog.

All cross-subsite operations introduced by the sync feature follow the practices outlined at <https://epiph.yt/en/blog/2025/beware-when-using-switch_to_blog/>:

- Every `switch_to_blog()` paired with `restore_current_blog()` inside the same loop iteration, in a `try`/`finally`.
- Cross-blog calls limited to `get_post_meta()` and `update_post_meta()`.
- Debug-mode assertion catches developer regressions if the blog stack ever leaves the fan-out imbalanced.

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
