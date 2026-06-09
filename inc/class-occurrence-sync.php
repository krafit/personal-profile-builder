<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Cross-subsite occurrence synchronisation.
 *
 * When the `_talk_occurrences` meta value changes on a talk, this
 * class propagates the same value to every MSLS-linked translation
 * of that talk on sibling subsites in the network.
 *
 * Why hook on meta updates rather than `save_post_talk`?
 * Block-editor saves go through the REST meta endpoint one key at a
 * time. By the time `save_post` fires, the meta might not yet be
 * written. Hooking `updated_post_meta` / `added_post_meta` for the
 * one key we care about fires at exactly the right moment, and
 * covers both classic and REST paths.
 *
 * Conflict policy: last-write-wins, with empty-target protection.
 * If a sibling has no `_talk_occurrences` value, it adopts the
 * source's list. If it has a non-empty value, the source overwrites
 * it. The reconciliation UI in {@see Admin\Sync_Meta_Box} surfaces
 * divergence before destructive saves happen.
 *
 * Re-entrancy: a static flag plus a hash-equality short-circuit
 * prevent the bounce-back save on the sibling from re-triggering
 * the sync.
 *
 * `switch_to_blog()` discipline (see
 * https://epiph.yt/en/blog/2025/beware-when-using-switch_to_blog/):
 * - every switch paired with restore inside the same iteration
 * - try/finally so an exception in the loop body doesn't leak the
 *   blog stack
 * - cross-blog calls limited to `update_post_meta()` and
 *   `get_post_meta()` — no permalinks, no globals, no rendering
 * - debug-mode assertion at fan-out exit catches developer mistakes
 *
 * @package	Personal_Profile_Builder
 */
final class Occurrence_Sync {
	/**
	 * @var	bool Re-entrancy flag: true while fan-out is running.
	 */
	private static bool $syncing = false;
	
	/**
	 * Register the meta-change hooks.
	 *
	 * Priority 20 to run after MSLS's own save action (default
	 * priority 10), so the translation link is in place by the time
	 * we look it up.
	 */
	public static function register(): void {
		\add_action(
			'updated_post_meta',
			[ self::class, 'on_meta_change' ],
			20,
			4
		);
		\add_action(
			'added_post_meta',
			[ self::class, 'on_meta_change' ],
			20,
			4
		);
	}
	
	/**
	 * React to a meta change and, if it's `_talk_occurrences` on a
	 * talk we should sync, fan it out to MSLS-linked siblings.
	 *
	 * @param	int	$meta_id ID of the updated metadata entry
	 * @param	int	$post_id Post the meta belongs to
	 * @param	string	$meta_key Meta key being changed
	 * @param	mixed	$meta_value New meta value
	 */
	public static function on_meta_change(
		int $meta_id,
		int $post_id,
		string $meta_key,
		$meta_value
	): void {
		unset( $meta_id );
		
		if ( $meta_key !== '_talk_occurrences' ) {
			return;
		}
		
		if ( self::$syncing ) {
			return;
		}
		
		if ( ! self::should_sync( $post_id ) ) {
			return;
		}
		
		$canonical = \is_string( $meta_value ) ? $meta_value : '';
		
		self::$syncing = true;
		
		try {
			self::fan_out( $post_id, $canonical );
		}
		finally {
			self::$syncing = false;
		}
	}
	
	/**
	 * Decide whether a meta change on this post should trigger sync.
	 *
	 * Skips autosaves, revisions, cron requests, posts of the wrong
	 * type, and posts the saving user can't edit.
	 *
	 * @param	int	$post_id Post ID being saved
	 * @return	bool Whether to proceed with the sync fan-out
	 */
	private static function should_sync( int $post_id ): bool {
		if ( ! MSLS_Integration::is_available() ) {
			return false;
		}
		
		if ( \wp_is_post_autosave( $post_id ) ) {
			return false;
		}
		
		if ( \wp_is_post_revision( $post_id ) ) {
			return false;
		}
		
		if ( \defined( 'DOING_CRON' ) && \DOING_CRON ) {
			return false;
		}
		
		$post = \get_post( $post_id );
		
		if ( $post === null ) {
			return false;
		}
		
		if ( $post->post_type !== Post_Types::POST_TYPE_TALK ) {
			return false;
		}
		
		if (
			\is_user_logged_in()
			&& ! \current_user_can( 'edit_post', $post_id )
		) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Push the canonical occurrence list to every linked sibling.
	 *
	 * Each iteration of the loop pairs `switch_to_blog()` with
	 * `restore_current_blog()` inside a try/finally. The debug-mode
	 * assertion at the end catches any imbalance — a developer
	 * regression that would otherwise silently corrupt later
	 * requests.
	 *
	 * @param	int	$post_id Source post ID on the current subsite
	 * @param	string	$canonical_json Sanitised JSON to propagate
	 */
	public static function fan_out(
		int $post_id,
		string $canonical_json
	): void {
		$entry_blog_id = \get_current_blog_id();
		$linked = MSLS_Integration::get_linked_post_ids( $post_id );
		
		try {
			foreach ( $linked as $locale => $sibling_id ) {
				$blog_id = MSLS_Integration::get_blog_id_for_locale(
					$locale
				);
				
				if ( $blog_id === null ) {
					continue;
				}
				
				\switch_to_blog( $blog_id );
				
				try {
					self::sync_one_sibling(
						$sibling_id,
						$canonical_json
					);
				}
				finally {
					\restore_current_blog();
				}
			}
		}
		finally {
			if (
				\defined( 'WP_DEBUG' )
				&& \WP_DEBUG
				&& \get_current_blog_id() !== $entry_blog_id
			) {
				\trigger_error(
					\sprintf(
						'PPB occurrence sync left the blog stack imbalanced (was %d, now %d). This is a bug.',
						$entry_blog_id,
						\get_current_blog_id()
					),
					\E_USER_WARNING
				);
			}
		}
	}
	
	/**
	 * Apply the canonical list to one sibling on the switched blog.
	 *
	 * Empty-target rule: if the sibling has no value, write the
	 * canonical. Hash equality: if the sibling already has the same
	 * bytes, skip. Otherwise overwrite.
	 *
	 * Runs inside a `switch_to_blog()` context. Cross-blog operations
	 * are limited to `get_post_meta()` and `update_post_meta()` —
	 * both safe database operations per
	 * https://epiph.yt/en/blog/2025/beware-when-using-switch_to_blog/.
	 *
	 * @param	int	$sibling_id Post ID on the currently-switched subsite
	 * @param	string	$canonical_json Sanitised JSON to write
	 */
	private static function sync_one_sibling(
		int $sibling_id,
		string $canonical_json
	): void {
		$existing = (string) \get_post_meta(
			$sibling_id,
			'_talk_occurrences',
			true
		);
		
		if ( $existing === $canonical_json ) {
			return;
		}
		
		\update_post_meta(
			$sibling_id,
			'_talk_occurrences',
			\wp_slash( $canonical_json )
		);
	}
	
	/**
	 * Read each linked sibling's occurrence list under the discipline.
	 *
	 * Used by the reconciliation meta box to display divergence status.
	 * Returns a `locale => [ 'post_id' => int, 'json' => string,
	 * 'count' => int, 'edit_url' => string ]` map.
	 *
	 * @param	int	$post_id Source post ID
	 * @return	array<string,array<string,mixed>> Sibling state per locale
	 */
	public static function read_sibling_state( int $post_id ): array {
		$state = [];
		
		if ( ! MSLS_Integration::is_available() ) {
			return $state;
		}
		
		$linked = MSLS_Integration::get_linked_post_ids( $post_id );
		$entry_blog_id = \get_current_blog_id();
		
		try {
			foreach ( $linked as $locale => $sibling_id ) {
				$blog_id = MSLS_Integration::get_blog_id_for_locale(
					$locale
				);
				
				if ( $blog_id === null ) {
					continue;
				}
				
				\switch_to_blog( $blog_id );
				
				try {
					$raw = (string) \get_post_meta(
						$sibling_id,
						'_talk_occurrences',
						true
					);
					$decoded = $raw !== ''
						? \json_decode( $raw, true )
						: [];
					$count = \is_array( $decoded ) ? \count( $decoded ) : 0;
					$edit_url = (string) \get_edit_post_link(
						$sibling_id,
						'raw'
					);
					
					$state[ $locale ] = [
						'post_id' => $sibling_id,
						'json' => $raw,
						'count' => $count,
						'edit_url' => $edit_url,
					];
				}
				finally {
					\restore_current_blog();
				}
			}
		}
		finally {
			if (
				\defined( 'WP_DEBUG' )
				&& \WP_DEBUG
				&& \get_current_blog_id() !== $entry_blog_id
			) {
				\trigger_error(
					\sprintf(
						'PPB occurrence sync (read_sibling_state) left the blog stack imbalanced (was %d, now %d).',
						$entry_blog_id,
						\get_current_blog_id()
					),
					\E_USER_WARNING
				);
			}
		}
		
		return $state;
	}
	
	/**
	 * Push the source list to every linked sibling, unconditionally.
	 *
	 * The manual counterpart to {@see fan_out()}, invoked by the
	 * "Push to translations" button on the reconciliation meta box.
	 * Bypasses the should-sync gating because it's an explicit user
	 * action, but still honours the re-entrancy flag.
	 *
	 * @param	int	$post_id Source post ID
	 */
	public static function push_from_source( int $post_id ): void {
		if ( ! MSLS_Integration::is_available() ) {
			return;
		}
		
		if ( self::$syncing ) {
			return;
		}
		
		$canonical = (string) \get_post_meta(
			$post_id,
			'_talk_occurrences',
			true
		);
		
		self::$syncing = true;
		
		try {
			self::fan_out( $post_id, $canonical );
		}
		finally {
			self::$syncing = false;
		}
	}
	
	/**
	 * Pull a sibling's list into the current talk and re-fan-out.
	 *
	 * Reads the named sibling's `_talk_occurrences`, writes it to
	 * the source post, then runs the standard fan-out so the other
	 * siblings catch up too. The "Pull from {locale}" button.
	 *
	 * @param	int	$post_id Source post ID on the current subsite
	 * @param	string	$source_locale Locale to pull from
	 * @return	bool Whether a pull occurred
	 */
	public static function pull_from_sibling(
		int $post_id,
		string $source_locale
	): bool {
		if ( ! MSLS_Integration::is_available() ) {
			return false;
		}
		
		if ( self::$syncing ) {
			return false;
		}
		
		$blog_id = MSLS_Integration::get_blog_id_for_locale(
			$source_locale
		);
		$sibling_id = MSLS_Integration::get_translated_post_id(
			$post_id,
			$source_locale
		);
		
		if ( $blog_id === null || $sibling_id === null ) {
			return false;
		}
		
		$entry_blog_id = \get_current_blog_id();
		$sibling_json = '';
		
		\switch_to_blog( $blog_id );
		
		try {
			$sibling_json = (string) \get_post_meta(
				$sibling_id,
				'_talk_occurrences',
				true
			);
		}
		finally {
			\restore_current_blog();
		}
		
		if (
			\defined( 'WP_DEBUG' )
			&& \WP_DEBUG
			&& \get_current_blog_id() !== $entry_blog_id
		) {
			\trigger_error(
				\sprintf(
					'PPB occurrence sync (pull) left the blog stack imbalanced (was %d, now %d).',
					$entry_blog_id,
					\get_current_blog_id()
				),
				\E_USER_WARNING
			);
		}
		
		self::$syncing = true;
		
		try {
			\update_post_meta(
				$post_id,
				'_talk_occurrences',
				\wp_slash( $sibling_json )
			);
			self::fan_out( $post_id, $sibling_json );
		}
		finally {
			self::$syncing = false;
		}
		
		return true;
	}
	
	/**
	 * Merge the source list with every sibling's list and propagate.
	 *
	 * Unions rows keyed by `(date, language)`. When the same key
	 * exists in multiple sources, the row with more populated string
	 * fields wins — a conservative tiebreaker that prefers data over
	 * absence. The resulting list is written to the source and then
	 * fanned out.
	 *
	 * @param	int	$post_id Source post ID
	 * @return	bool Whether a merge occurred
	 */
	public static function merge_all( int $post_id ): bool {
		if ( ! MSLS_Integration::is_available() ) {
			return false;
		}
		
		if ( self::$syncing ) {
			return false;
		}
		
		$source_rows = self::decode_rows(
			(string) \get_post_meta(
				$post_id,
				'_talk_occurrences',
				true
			)
		);
		$sibling_state = self::read_sibling_state( $post_id );
		$by_key = [];
		
		foreach ( $source_rows as $row ) {
			self::merge_row_into( $by_key, $row );
		}
		
		foreach ( $sibling_state as $entry ) {
			$rows = self::decode_rows( (string) ( $entry['json'] ?? '' ) );
			
			foreach ( $rows as $row ) {
				self::merge_row_into( $by_key, $row );
			}
		}
		
		$merged = \array_values( $by_key );
		
		\usort( $merged, static function ( array $a, array $b ): int {
			$da = (string) ( $a['date'] ?? '' );
			$db = (string) ( $b['date'] ?? '' );
			
			return $da <=> $db;
		} );
		
		$encoded = \wp_json_encode( $merged, JSON_UNESCAPED_UNICODE );
		$encoded = \is_string( $encoded ) ? $encoded : '';
		
		self::$syncing = true;
		
		try {
			\update_post_meta(
				$post_id,
				'_talk_occurrences',
				\wp_slash( $encoded )
			);
			self::fan_out( $post_id, $encoded );
		}
		finally {
			self::$syncing = false;
		}
		
		return true;
	}
	
	/**
	 * Decode a JSON-string list of occurrence rows.
	 *
	 * @param	string	$json Raw JSON string
	 * @return	array<int,array<string,mixed>> Decoded rows
	 */
	private static function decode_rows( string $json ): array {
		if ( $json === '' ) {
			return [];
		}
		
		$decoded = \json_decode( $json, true );
		
		if ( ! \is_array( $decoded ) ) {
			return [];
		}
		
		$rows = [];
		
		foreach ( $decoded as $row ) {
			if ( \is_array( $row ) ) {
				$rows[] = $row;
			}
		}
		
		return $rows;
	}
	
	/**
	 * Merge one row into a `(date, language)`-keyed accumulator.
	 *
	 * When the same key already exists, the row with the higher
	 * "populated string fields" count wins. Ties go to the incoming
	 * row, which means later sources win — `array_values()` after
	 * the loop guarantees we don't depend on key order.
	 *
	 * @param	array<string,array<string,mixed>>	$by_key Mutable accumulator
	 * @param	array<string,mixed>	$row Row to merge in
	 */
	private static function merge_row_into(
		array &$by_key,
		array $row
	): void {
		$date = isset( $row['date'] ) && \is_string( $row['date'] )
			? $row['date']
			: '';
		
		if ( $date === '' ) {
			return;
		}
		
		$language = isset( $row['language'] ) && \is_string( $row['language'] )
			? $row['language']
			: '';
		$key = $date . '|' . $language;
		
		if ( ! isset( $by_key[ $key ] ) ) {
			$by_key[ $key ] = $row;
			
			return;
		}
		
		$existing_score = self::populated_field_count( $by_key[ $key ] );
		$new_score = self::populated_field_count( $row );
		
		if ( $new_score >= $existing_score ) {
			$by_key[ $key ] = $row;
		}
	}
	
	/**
	 * Count the populated string fields in a row.
	 *
	 * @param	array<string,mixed>	$row Occurrence row
	 * @return	int Count of fields with non-empty string values
	 */
	private static function populated_field_count( array $row ): int {
		$count = 0;
		
		foreach ( $row as $value ) {
			if ( \is_string( $value ) && $value !== '' ) {
				$count++;
			}
		}
		
		return $count;
	}
}
