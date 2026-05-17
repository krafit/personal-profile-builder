<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use WP_Post;

/**
 * Occurrences subsystem.
 *
 * Implements the per-event URL pattern `/talk/<slug>/YYYYMMDD` and the
 * `get_post_metadata` filter that transparently overrides the three
 * theme-facing URL/event meta keys when a `talk_date` query var is set
 * on the current request.
 *
 * Themes need no awareness of this — the same `get_post_meta()` calls
 * they already make will return occurrence-specific values when a
 * matching occurrence exists, and the stored fallback otherwise.
 *
 * @package	Personal_Profile_Builder
 */
final class Occurrences {
	/**
	 * @var	string Query var name for the date segment.
	 */
	public const QUERY_VAR = 'talk_date';
	
	/**
	 * @var	array<string,string> Mapping of overrideable meta keys to occurrence fields.
	 */
	private const META_FIELD_MAP = [
		'_talk_slides_url' => 'slides_url',
		'_talk_recording_url' => 'recording_url',
		'_talk_event_name' => 'event_name',
		'_talk_event_url' => 'event_url',
	];
	
	/**
	 * Register all hooks for this subsystem.
	 */
	public static function register(): void {
		\add_action( 'init', [ self::class, 'register_rewrite_rule' ], 10 );
		\add_filter( 'query_vars', [ self::class, 'add_query_var' ] );
		\add_filter( 'get_post_metadata', [ self::class, 'filter_meta_value' ], 10, 4 );
		\add_action( 'wp_head', [ self::class, 'render_occurrence_robots_meta' ] );
	}
	
	/**
	 * Register the rewrite rule that captures the date segment.
	 *
	 * Pattern: `/talk/<slug>/YYYYMMDD/?`
	 *
	 * The captured slug resolves the post via the standard `talk` query
	 * var, and the date is stashed in `talk_date` for the meta filter to
	 * read on the same request.
	 */
	public static function register_rewrite_rule(): void {
		\add_rewrite_rule(
			'^talk/([^/]+)/([0-9]{8})/?$',
			'index.php?' . Post_Types::POST_TYPE_TALK . '=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
			'top'
		);
	}
	
	/**
	 * Register the public query var.
	 *
	 * @param	array<int,string>	$vars Existing public query vars
	 * @return	array<int,string> Query vars including `talk_date`
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		
		return $vars;
	}
	
	/**
	 * Override the three theme-facing URL/event meta keys when an
	 * occurrence URL is in use.
	 *
	 * If `talk_date` is set on the main query, the post is a talk, and
	 * its `_talk_occurrences` contains a row whose date matches, then
	 * the row's value for the corresponding field is returned. If the
	 * row's field is empty, or no occurrence matches, the filter
	 * returns null and the stored default meta value is used unchanged.
	 *
	 * @param	mixed	$value Pre-filtered meta value (null by default)
	 * @param	int	$object_id Post ID
	 * @param	string	$meta_key Meta key being requested
	 * @param	bool	$single Whether a single value is wanted
	 * @return	mixed The override value, or null to fall through
	 */
	public static function filter_meta_value( $value, int $object_id, string $meta_key, bool $single ) {
		if ( ! \array_key_exists( $meta_key, self::META_FIELD_MAP ) ) {
			return $value;
		}
		
		$date = self::get_active_date();
		
		if ( $date === '' ) {
			return $value;
		}
		
		if ( \get_post_type( $object_id ) !== Post_Types::POST_TYPE_TALK ) {
			return $value;
		}
		
		$override = self::resolve_occurrence_value( $object_id, $date, $meta_key );
		
		if ( $override === null ) {
			return $value;
		}
		
		return $single ? $override : [ $override ];
	}
	
	/**
	 * Read the active occurrence date from the main query, validated.
	 *
	 * @return	string Validated 8-digit date, or empty string
	 */
	public static function get_active_date(): string {
		$raw = \get_query_var( self::QUERY_VAR, '' );
		
		if ( ! \is_string( $raw ) || $raw === '' ) {
			return '';
		}
		
		return self::is_valid_date_string( $raw ) ? $raw : '';
	}
	
	/**
	 * Output a noindex, nofollow robots meta tag on occurrence URLs.
	 *
	 * Occurrence pages are alternate views of the parent talk and
	 * should not be indexed separately. The canonical talk single
	 * remains fully indexable.
	 */
	public static function render_occurrence_robots_meta(): void {
		if ( self::get_active_date() === '' ) {
			return;
		}
		
		echo '<meta name="robots" content="noindex,nofollow">' . "\n";
	}
	
	/**
	 * Look up an occurrence on the given talk and return the requested field.
	 *
	 * Returns null when no occurrence matches the date, or when the
	 * matching occurrence has an empty value for the field (so the
	 * caller can fall through to the default stored meta).
	 *
	 * @param	int	$talk_id The talk post ID
	 * @param	string	$compact_date 8-digit date in YYYYMMDD form
	 * @param	string	$meta_key The meta key being resolved
	 * @return	string|null Override value or null
	 */
	private static function resolve_occurrence_value( int $talk_id, string $compact_date, string $meta_key ): ?string {
		$occurrences_json = \get_post_meta( $talk_id, '_talk_occurrences', true );
		
		if ( ! \is_string( $occurrences_json ) || $occurrences_json === '' ) {
			return null;
		}
		
		$occurrences = \json_decode( $occurrences_json, true );
		
		if ( ! \is_array( $occurrences ) ) {
			return null;
		}
		
		$field = self::META_FIELD_MAP[ $meta_key ];
		
		foreach ( $occurrences as $row ) {
			if ( ! \is_array( $row ) || ! isset( $row['date'] ) || ! \is_string( $row['date'] ) ) {
				continue;
			}
			
			$row_compact = \str_replace( '-', '', $row['date'] );
			
			if ( $row_compact !== $compact_date ) {
				continue;
			}
			
			if ( ! isset( $row[ $field ] ) || ! \is_string( $row[ $field ] ) || $row[ $field ] === '' ) {
				return null;
			}
			
			return $row[ $field ];
		}
		
		return null;
	}
	
	/**
	 * Validate a compact date string.
	 *
	 * Must be exactly 8 characters, all digits, and represent a real
	 * Gregorian date.
	 *
	 * @param	string	$date Candidate date string
	 * @return	bool Whether the date is well-formed and real
	 */
	public static function is_valid_date_string( string $date ): bool {
		if ( \strlen( $date ) !== 8 ) {
			return false;
		}
		
		if ( ! \ctype_digit( $date ) ) {
			return false;
		}
		
		$year = (int) \substr( $date, 0, 4 );
		$month = (int) \substr( $date, 4, 2 );
		$day = (int) \substr( $date, 6, 2 );
		
		return \checkdate( $month, $day, $year );
	}
	
	/**
	 * Normalise a date input into the 8-digit URL form.
	 *
	 * Accepts either `YYYY-MM-DD` (the storage form) or `YYYYMMDD`
	 * (the URL form). Returns an empty string for anything invalid.
	 *
	 * @param	string	$date Candidate date string
	 * @return	string 8-digit date or empty string
	 */
	public static function compact_date( string $date ): string {
		$stripped = \str_replace( '-', '', $date );
		
		return self::is_valid_date_string( $stripped ) ? $stripped : '';
	}
	
	/**
	 * Build the canonical URL for a given talk occurrence.
	 *
	 * @param	int|WP_Post	$talk Either a talk post ID or a `WP_Post` instance
	 * @param	string	$date Date in `YYYY-MM-DD` or `YYYYMMDD` form
	 * @return	string Permalink for the occurrence, or empty string on failure
	 */
	public static function get_occurrence_url( $talk, string $date ): string {
		$post = \is_int( $talk ) ? \get_post( $talk ) : $talk;
		
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		
		if ( $post->post_type !== Post_Types::POST_TYPE_TALK ) {
			return '';
		}
		
		$compact = self::compact_date( $date );
		
		if ( $compact === '' ) {
			return '';
		}
		
		$permalink = \get_permalink( $post );
		
		if ( ! \is_string( $permalink ) || $permalink === '' ) {
			return '';
		}
		
		return \trailingslashit( $permalink ) . $compact;
	}
}
