<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Meta key registration.
 *
 * Registers all post meta keys for the `talk` and `project` post types,
 * including theme-facing keys that must remain stable, and plugin-added
 * keys that drive new behaviour.
 *
 * @package	Personal_Profile_Builder
 */
final class Meta {
	/**
	 * @var	string Talk status: available to book.
	 */
	public const STATUS_AVAILABLE = 'available';
	
	/**
	 * @var	string Talk status: retired.
	 */
	public const STATUS_RETIRED = 'retired';
	
	/**
	 * @var	array<int,string> Allowed values for `_talk_status`.
	 */
	public const TALK_STATUSES = [
		self::STATUS_AVAILABLE,
		self::STATUS_RETIRED,
	];
	
	/**
	 * Register all meta keys.
	 */
	public static function register(): void {
		self::register_talk_meta();
		self::register_project_meta();
	}
	
	/**
	 * Register runtime hooks that act on meta updates.
	 *
	 * Separate from {@see register()} because that method runs on
	 * `init` and registers the meta keys themselves; the runtime
	 * hooks here listen for changes to those keys at any time.
	 */
	public static function init_runtime_hooks(): void {
		\add_action(
			'updated_post_meta',
			[ self::class, 'clear_legacy_on_resolution' ],
			10,
			4
		);
		\add_action(
			'added_post_meta',
			[ self::class, 'clear_legacy_on_resolution' ],
			10,
			4
		);
	}
	
	/**
	 * Register meta keys for the `talk` post type.
	 */
	private static function register_talk_meta(): void {
		$post_type = Post_Types::POST_TYPE_TALK;
		
		\register_post_meta(
			$post_type,
			'_talk_slides_url',
			[
				'type' => 'string',
				'description' => \__( 'Default URL to the talk slides.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'esc_url_raw',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_recording_url',
			[
				'type' => 'string',
				'description' => \__( 'Default URL to the talk recording.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'esc_url_raw',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_event_name',
			[
				'type' => 'string',
				'description' => \__( 'Default event name for the talk.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_duration',
			[
				'type' => 'string',
				'description' => \__( 'Talk duration as a free-form string (e.g. "30 min").', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_cover_emoji',
			[
				'type' => 'string',
				'description' => \__( 'Single emoji used as the talk cover.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => [ self::class, 'sanitize_emoji' ],
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_language',
			[
				'type' => 'string',
				'description' => \__(
					'Languages this talk is given in. Stored as one row per WordPress locale code (e.g. de_DE). Free-form values from versions prior to 1.7.0 are preserved in _talk_language_legacy. Exposed to the REST API as the synthesised `talk_languages` field on the talk endpoint.',
					'personal-profile-builder'
				),
				'single' => false,
				'show_in_rest' => false,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => [
					self::class,
					'sanitize_locale_or_empty',
				],
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_language_legacy',
			[
				'type' => 'string',
				'description' => \__(
					'Deprecated. Previous free-form value of _talk_language preserved on upgrade. Will be removed in 1.8.0.',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_target_audience',
			[
				'type' => 'string',
				'description' => \__(
					'Target audience for the talk.',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_format',
			[
				'type' => 'string',
				'description' => \__(
					'Talk format (e.g. workshop, lightning talk).',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_event_url',
			[
				'type' => 'string',
				'description' => \__(
					'Default event URL for the talk.',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => 'esc_url_raw',
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_status',
			[
				'type' => 'string',
				'description' => \__( 'Talk booking status (available, retired, or empty).', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => [ self::class, 'sanitize_talk_status' ],
			]
		);
		\register_post_meta(
			$post_type,
			'_talk_occurrences',
			[
				'type' => 'string',
				'description' => \__( 'JSON-encoded list of occurrences when this talk was given.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => [ self::class, 'sanitize_occurrences_json' ],
			]
		);
	}
	
	/**
	 * Register meta keys for the `project` post type.
	 */
	private static function register_project_meta(): void {
		$post_type = Post_Types::POST_TYPE_PROJECT;
		
		\register_post_meta(
			$post_type,
			'_project_url',
			[
				'type' => 'string',
				'description' => \__( 'External URL the project lives at.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'esc_url_raw',
			]
		);
		\register_post_meta(
			$post_type,
			'_project_icon',
			[
				'type' => 'string',
				'description' => \__( 'Icon identifier or emoji for the project.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_project_badge',
			[
				'type' => 'string',
				'description' => \__( 'Short badge label for the project.', 'personal-profile-builder' ),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [ self::class, 'auth_edit_post' ],
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		\register_post_meta(
			$post_type,
			'_project_start_date',
			[
				'type' => 'string',
				'description' => \__(
					'Project start date (YYYY-MM-DD).',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => [
					self::class,
					'sanitize_date',
				],
			]
		);
		\register_post_meta(
			$post_type,
			'_project_end_date',
			[
				'type' => 'string',
				'description' => \__(
					'Project end date (YYYY-MM-DD). Empty for ongoing.',
					'personal-profile-builder'
				),
				'single' => true,
				'default' => '',
				'show_in_rest' => true,
				'auth_callback' => [
					self::class,
					'auth_edit_post',
				],
				'sanitize_callback' => [
					self::class,
					'sanitize_date',
				],
			]
		);
	}
	
	/**
	 * Authorise meta edits for users who can edit the post.
	 *
	 * @param	bool	$allowed Whether the user can edit (default state)
	 * @param	string	$meta_key The meta key being edited
	 * @param	int	$post_id The post ID
	 * @return	bool Whether the current user may edit this meta value
	 */
	public static function auth_edit_post( bool $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key );
		
		return \current_user_can( 'edit_post', $post_id );
	}
	
	/**
	 * Sanitise a talk status string.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	string A valid status, or an empty string
	 */
	public static function sanitize_talk_status( $value ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}
		
		$value = \sanitize_key( $value );
		
		if ( ! \in_array( $value, self::TALK_STATUSES, true ) ) {
			return '';
		}
		
		return $value;
	}
	
	/**
	 * Sanitise a single emoji input.
	 *
	 * Strips tags and trims whitespace; the field is intentionally
	 * permissive about the underlying codepoint to allow any emoji.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	string Sanitised emoji string
	 */
	public static function sanitize_emoji( $value ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}
		
		return \trim( \wp_strip_all_tags( $value ) );
	}
	
	/**
	 * Sanitise the occurrences JSON blob.
	 *
	 * Accepts either a JSON string or an array; returns a canonical
	 * JSON string of validated occurrence rows. Invalid rows are
	 * dropped silently.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	string Canonical JSON-encoded occurrence list
	 */
	public static function sanitize_occurrences_json( $value ): string {
		if ( \is_string( $value ) && $value !== '' ) {
			$decoded = \json_decode( $value, true );
		}
		else if ( \is_array( $value ) ) {
			$decoded = $value;
		}
		else {
			$decoded = [];
		}
		
		if ( ! \is_array( $decoded ) ) {
			return '';
		}
		
		$clean = [];
		
		foreach ( $decoded as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}
			
			$date = isset( $row['date'] ) && \is_string( $row['date'] )
				? \sanitize_text_field( $row['date'] )
				: '';
			
			if ( $date === '' || Occurrences::compact_date( $date ) === '' ) {
				continue;
			}
			
			$clean[] = [
				'date' => $date,
				'event_name' => isset( $row['event_name'] )
					&& \is_string( $row['event_name'] )
					? \sanitize_text_field( $row['event_name'] )
					: '',
				'location' => isset( $row['location'] )
					&& \is_string( $row['location'] )
					? \sanitize_text_field( $row['location'] )
					: '',
				'event_url' => isset( $row['event_url'] )
					&& \is_string( $row['event_url'] )
					? \esc_url_raw( $row['event_url'] )
					: '',
				'slides_url' => isset( $row['slides_url'] )
					&& \is_string( $row['slides_url'] )
					? \esc_url_raw( $row['slides_url'] )
					: '',
				'recording_url' => isset( $row['recording_url'] )
					&& \is_string( $row['recording_url'] )
					? \esc_url_raw( $row['recording_url'] )
					: '',
				'language' => isset( $row['language'] )
					&& \is_string( $row['language'] )
					? self::sanitize_locale_or_empty( $row['language'] )
					: '',
			];
		}
		
		if ( $clean === [] ) {
			return '';
		}
		
		$encoded = \wp_json_encode(
			$clean,
			JSON_UNESCAPED_UNICODE
		);
		
		return \is_string( $encoded ) ? $encoded : '';
	}
	
	/**
	 * Sanitise a date string.
	 *
	 * Accepts YYYY-MM-DD format only. Returns an empty string for
	 * anything that does not parse as a valid Gregorian date.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	string A valid YYYY-MM-DD date or empty string
	 */
	public static function sanitize_date( $value ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}
		
		$value = \trim( $value );
		
		if ( $value === '' ) {
			return '';
		}
		
		if ( ! \preg_match(
			'/^\d{4}-\d{2}-\d{2}$/',
			$value
		) ) {
			return '';
		}
		
		$parts = \explode( '-', $value );
		$year = (int) $parts[0];
		$month = (int) $parts[1];
		$day = (int) $parts[2];
		
		if ( ! \checkdate( $month, $day, $year ) ) {
			return '';
		}
		
		return $value;
	}
	
	/**
	 * Sanitise a locale code, returning empty for any non-locale input.
	 *
	 * Used by both the per-occurrence `language` field and the
	 * talk-level `_talk_language` meta key. The allowed set is
	 * whatever {@see MSLS_Integration::allowed_locales()} returns —
	 * MSLS-linked subsite locales when MSLS is available, otherwise
	 * installed WordPress languages plus `en_US`.
	 *
	 * Anything that does not match a known locale collapses to an
	 * empty string. The empty string is itself a valid stored value
	 * representing "unspecified".
	 *
	 * @param	mixed	$value Raw input value
	 * @return	string A known locale code, or empty string
	 */
	public static function sanitize_locale_or_empty( $value ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}
		
		$value = \trim( $value );
		
		if ( $value === '' ) {
			return '';
		}
		
		$allowed = MSLS_Integration::allowed_locales();
		
		return \in_array( $value, $allowed, true ) ? $value : '';
	}
	
	/**
	 * Clear `_talk_language_legacy` when `_talk_language` is set.
	 *
	 * Hooked into `updated_post_meta` and `added_post_meta`. When the
	 * user picks one or more locales from the multi-select, the
	 * temporary legacy hint is no longer needed and is removed so
	 * the notice disappears on the next editor load. A save that
	 * empties the field is a no-op — the user can clear without
	 * losing the legacy hint.
	 *
	 * Because `_talk_language` is registered with `single => false`,
	 * the hook fires once per individual row. Picking two locales
	 * fires this twice; both fires reach the same `delete_post_meta`
	 * call, which is idempotent. No special handling needed.
	 *
	 * @param	int	$meta_id ID of the updated metadata entry
	 * @param	int	$post_id The post the meta belongs to
	 * @param	string	$meta_key The meta key being changed
	 * @param	mixed	$meta_value The new meta value
	 */
	public static function clear_legacy_on_resolution(
		int $meta_id,
		int $post_id,
		string $meta_key,
		$meta_value
	): void {
		unset( $meta_id );
		
		if ( $meta_key !== '_talk_language' ) {
			return;
		}
		
		if ( ! \is_string( $meta_value ) || $meta_value === '' ) {
			return;
		}
		
		\delete_post_meta( $post_id, '_talk_language_legacy' );
	}
}
