<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Versioned upgrade routines.
 *
 * Runs once per plugin version bump. Triggered from
 * {@see Plugin::init()} on the `init` hook by comparing the stored
 * `ppb_version` option against {@see PERSONAL_PROFILE_BUILDER_VERSION}.
 *
 * Activation-hook upgrades are deliberately avoided: those don't
 * fire on WP.org auto-updates or on `wp plugin update`, so a
 * runtime-check pattern is the only reliable way to catch every
 * upgrade path.
 *
 * Routines are idempotent — each one re-checks the world before
 * making changes, so a routine that runs twice (e.g. through a
 * partial database restore) doesn't corrupt data.
 *
 * @package	Personal_Profile_Builder
 */
final class Upgrade {
	/**
	 * @var	string Option name that stores the last-seen plugin version.
	 */
	private const VERSION_OPTION = 'ppb_version';
	
	/**
	 * Run any upgrade routines whose target version is greater than
	 * the stored version.
	 *
	 * Should be hooked early in `init`, before any subsystem that
	 * might rely on migrated data. The version option is updated by
	 * {@see Plugin::maybe_flush_rewrites()} once per request — we
	 * don't update it here, so a partial run that errors mid-loop
	 * will retry on the next request.
	 */
	public static function run_pending(): void {
		$stored = (string) \get_option( self::VERSION_OPTION, '' );
		
		// 1.7.0 hop: copy free-form _talk_language values to legacy key.
		if ( \version_compare( $stored, '1.7.0', '<' ) ) {
			self::migrate_talk_language_legacy();
			self::cleanup_empty_talk_language_rows();
			
			return;
		}
		
		// Reconciliation for installs that briefly ran the broken
		// 1.7.0 or the patched 1.7.1 build before the multi-value
		// rebuild of 1.7.0. Those builds stored single-value rows;
		// if any are empty strings, surface as [''] to themes once
		// _talk_language flips to `single => false`. Clean them up.
		if ( $stored === '1.7.1' ) {
			self::cleanup_empty_talk_language_rows();
		}
	}
	
	/**
	 * Copy free-form `_talk_language` values into `_talk_language_legacy`.
	 *
	 * Up to 1.6.x, `_talk_language` accepted any string (examples in
	 * the theme migration guide include "English" and "Deutsch"). From
	 * 1.7.0 the sanitiser only accepts WordPress locale codes; anything
	 * else collapses to empty on the next save.
	 *
	 * This migration walks every talk with a non-empty `_talk_language`
	 * and, when the value doesn't match a known locale, copies it into
	 * a temporary `_talk_language_legacy` meta key. The block editor
	 * surfaces it as a notice asking the user to pick the equivalent.
	 * The legacy key will be removed in 1.8.0.
	 *
	 * Idempotency: re-running this on an already-migrated site is
	 * harmless. Values that already match a locale are skipped; values
	 * already in `_talk_language_legacy` are overwritten with the same
	 * string.
	 *
	 * Note: this routine uses `posts_per_page => -1`. That's safe for
	 * the realistic dataset on the target site (dozens of talks). If
	 * this plugin is ever adopted by a site with thousands of talks,
	 * the routine should be batched via `wp_schedule_single_event()`.
	 */
	private static function migrate_talk_language_legacy(): void {
		$allowed = MSLS_Integration::allowed_locales();
		$talk_ids = \get_posts( [
			'post_type' => Post_Types::POST_TYPE_TALK,
			'posts_per_page' => -1,
			'post_status' => 'any',
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				[
					'key' => '_talk_language',
					'value' => '',
					'compare' => '!=',
				],
			],
		] );
		
		if ( ! \is_array( $talk_ids ) ) {
			return;
		}
		
		foreach ( $talk_ids as $post_id ) {
			$current = (string) \get_post_meta(
				(int) $post_id,
				'_talk_language',
				true
			);
			
			if ( $current === '' ) {
				continue;
			}
			
			if ( \in_array( $current, $allowed, true ) ) {
				continue;
			}
			
			\update_post_meta(
				(int) $post_id,
				'_talk_language_legacy',
				$current
			);
		}
	}
	
	/**
	 * Delete `_talk_language` rows whose value is an empty string.
	 *
	 * Pre-1.7.0 (multi-row) builds stored an empty string when the
	 * field had no value. Now that `_talk_language` is registered
	 * with `single => false`, those empty rows would surface to
	 * themes as `[ '' ]` rather than `[]`. The cleanup walks every
	 * empty-string row and removes it. Idempotent — running on a
	 * site that has none is a single SQL query that finds nothing.
	 *
	 * Uses a direct `$wpdb` query because there's no native WP API
	 * to delete a specific meta value across all posts. The query
	 * is parameterised and limited to the one key, so it's safe.
	 */
	private static function cleanup_empty_talk_language_rows(): void {
		global $wpdb;
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot upgrade routine, no caching needed.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = ''",
				'_talk_language'
			)
		);
	}
}
