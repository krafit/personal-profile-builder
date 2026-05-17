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
					'Language the talk is given in.',
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
			
			if ( $date === '' ) {
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
}
