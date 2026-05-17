=== Personal Profile Builder ===
Contributors: krafit
Tags: talks, speaking, conferences, portfolio, projects
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.6.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage talks and projects on your personal website with per-event URLs, an organiser view, and editor blocks.

== Description ==

Personal Profile Builder adds two custom post types — **Talks** and **Projects** — to your WordPress site.

**Talks** support hierarchical topics, a booking status, and structured occurrences (same talk, different events). Each occurrence gets a shareable URL that surfaces that event's slides, recording, and event name without changing your theme.

**Projects** support hierarchical types, external URLs, icons, and badges.

= Highlights =

* Per-event URLs of the form `/talk/my-talk/20260502` with transparent meta override — your theme reads the same meta keys it always has.
* Organiser view: append `?view=organiser` to any talk URL for a bio box with your speaker photo and bio, ready to share with event organisers.
* Speaker profile settings page for your bio and avatar.
* Four editor blocks: Talk Query, Project Query, Talk Embed, and Project Embed.
* Server-rendered blocks with `theme.json` support for colours, spacing, and typography.
* Complete German (de_DE) translation.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate through the **Plugins** screen.
3. Go to **Settings → Speaker Profile** to set your bio and avatar.
4. Start adding talks and projects from the admin menu.

== Frequently Asked Questions ==

= Does this work with my theme? =

Yes. The plugin registers stable meta keys and post types that any theme can read. Existing templates continue to work. Blocks and template tags are available for deeper integration.

= What happens if I uninstall? =

Plugin options are removed. Your talks, projects, and taxonomy terms stay in the database.

== Changelog ==

= 1.6.2 =
* Talk Embed and Project Embed blocks no longer use `display: grid`. A single card now renders at full width.

= 1.6.1 =
* Talk Embed block: adds an optional occurrence selector. When an occurrence is selected, the card links to the occurrence URL and shows slides, recording, and event URL inline.
* Block styles now load in the editor so previews match the front end.
* Consolidates readme files and trims pre-1.0 changelog entries for the first public release.

= 1.6.0 =
* New blocks: Talk Embed and Project Embed — pick a single talk or project and display it as a card on any page or post.

= 1.5.0 =
* Removes default event name and event URL from the sidebar — these are now per-occurrence only.
* Adds a slides upload button in occurrence rows (WP Media Library).
* Talk Query block: "Sort retired to bottom" toggle and working "Next occurrence" sort.

= 1.4.0 =
* Fixes UTF-8 encoding in occurrence data (e.g. "München" stored correctly).
* Adds `_talk_format` and `_talk_event_url` meta keys with per-occurrence override.
* Occurrence list links now point to the event URL instead of occurrence permalinks.

= 1.3.0 =
* Renames "Available to book" to "Available".
* Adds `noindex, nofollow` on occurrence URLs.
* German: "Retired" translated as "Archiviert".

= 1.2.0 =
* Adds `_talk_language` and `_talk_target_audience` meta keys.

= 1.1.0 – 1.1.2 =
* Moves meta fields to the block editor sidebar.
* Adds German translation and fixes translation loading.

= 1.0.0 =
* First stable release. Full security audit, capability checks, and input/output validation.

= Pre-1.0 (0.1.0 – 0.6.0) =
* Foundation: post types, taxonomies, meta keys, settings page.
* Occurrences: rewrite rules, meta override filter, date validation.
* Admin UI: meta boxes, repeatable occurrence rows, list table filters.
* Organiser view: bio box with theme-overridable template.
* Blocks: Talk Query and Project Query with editor previews.
* Query helpers, template tags, and REST API extensions.
