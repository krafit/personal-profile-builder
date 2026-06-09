<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Occurrence URL language redirect.
 *
 * When the visitor lands on an occurrence URL (`/talk/<slug>/YYYYMMDD`)
 * whose `language` differs from the current subsite's locale and a
 * matching MSLS translation exists, the visitor is bounced to the
 * occurrence URL on the matching-language subsite.
 *
 * Bare talk URLs are never redirected — only per-event shareable
 * URLs trigger this. The page-level switch is the visitor's job
 * (and MSLS's built-in language switcher).
 *
 * Loop prevention is structural: because the occurrence list is
 * synced across linked translations (see {@see Occurrence_Sync}),
 * the target subsite has the same occurrence row with the same
 * language value as the source. After the redirect lands, the
 * `current_site_locale === occurrence['language']` check fails and
 * the chain terminates.
 *
 * @package	Personal_Profile_Builder
 */
final class Occurrence_Redirect {
	/**
	 * Register the template_redirect handler.
	 *
	 * Priority 10. Runs before MSLS's `the_content` filter at 20,
	 * which is irrelevant in any case because we redirect away
	 * before content rendering happens.
	 */
	public static function register(): void {
		\add_action( 'template_redirect', [ self::class, 'maybe_redirect' ] );
	}
	
	/**
	 * Inspect the current request and redirect when conditions match.
	 */
	public static function maybe_redirect(): void {
		if ( ! MSLS_Integration::is_available() ) {
			return;
		}
		
		if ( ! \is_singular( Post_Types::POST_TYPE_TALK ) ) {
			return;
		}
		
		$active_date = Occurrences::get_active_date();
		
		if ( $active_date === '' ) {
			return;
		}
		
		$post_id = (int) \get_queried_object_id();
		
		if ( $post_id < 1 ) {
			return;
		}
		
		$occurrence = self::find_matching_occurrence(
			$post_id,
			$active_date
		);
		
		if ( $occurrence === null ) {
			return;
		}
		
		$target_locale = (string) ( $occurrence['language'] ?? '' );
		
		if ( $target_locale === '' ) {
			return;
		}
		
		$current_locale = MSLS_Integration::current_site_locale();
		
		if ( $target_locale === $current_locale ) {
			return;
		}
		
		$target_url = self::build_redirect_target(
			$post_id,
			$active_date,
			$occurrence,
			$target_locale
		);
		
		if ( $target_url === '' ) {
			return;
		}
		
		\wp_safe_redirect( $target_url, 302 );
		exit;
	}
	
	/**
	 * Look up the occurrence row matching the active date.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	string	$compact_date 8-digit YYYYMMDD date
	 * @return	array<string,mixed>|null Matching row, or null
	 */
	private static function find_matching_occurrence(
		int $post_id,
		string $compact_date
	): ?array {
		$raw = \get_post_meta( $post_id, '_talk_occurrences', true );
		
		if ( ! \is_string( $raw ) || $raw === '' ) {
			return null;
		}
		
		$rows = \json_decode( $raw, true );
		
		if ( ! \is_array( $rows ) ) {
			return null;
		}
		
		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}
			
			if ( ! isset( $row['date'] ) || ! \is_string( $row['date'] ) ) {
				continue;
			}
			
			$row_compact = \str_replace( '-', '', $row['date'] );
			
			if ( $row_compact === $compact_date ) {
				return $row;
			}
		}
		
		return null;
	}
	
	/**
	 * Build the redirect target URL.
	 *
	 * Resolves the translated talk's permalink on the target
	 * subsite, appends the occurrence date segment, preserves the
	 * incoming query string (notably `?view=organiser`), and passes
	 * the result through the `ppb_msls_redirect_target` filter for
	 * theme overrides.
	 *
	 * @param	int	$post_id Source talk post ID
	 * @param	string	$compact_date 8-digit date
	 * @param	array<string,mixed>	$occurrence Matched occurrence row
	 * @param	string	$target_locale Locale to redirect to
	 * @return	string Final redirect URL, or empty string if unresolvable
	 */
	private static function build_redirect_target(
		int $post_id,
		string $compact_date,
		array $occurrence,
		string $target_locale
	): string {
		$base = MSLS_Integration::get_translated_permalink(
			$post_id,
			$target_locale
		);
		
		if ( $base === null ) {
			return '';
		}
		
		$with_segment = MSLS_Integration::append_occurrence_segment(
			$base,
			$compact_date
		);
		$url = self::preserve_query_string( $with_segment );
		
		/**
		 * Filter the URL the occurrence redirect targets.
		 *
		 * @since	1.7.0
		 *
		 * @param	string	$url The fully-built redirect URL
		 * @param	int	$post_id The source talk post ID
		 * @param	array	$occurrence The matched occurrence row
		 * @param	string	$target_locale The destination locale
		 */
		$url = (string) \apply_filters(
			'ppb_msls_redirect_target',
			$url,
			$post_id,
			$occurrence,
			$target_locale
		);
		
		return $url;
	}
	
	/**
	 * Append the current request's query string to a URL.
	 *
	 * Used to keep `?view=organiser` and other consumer-supplied
	 * query parameters intact across the redirect.
	 *
	 * @param	string	$url Base URL (which may already have its own query)
	 * @return	string URL with the current request's query string merged in
	 */
	private static function preserve_query_string( string $url ): string {
		$raw = isset( $_SERVER['QUERY_STRING'] )
			? (string) $_SERVER['QUERY_STRING']
			: '';
		
		if ( $raw === '' ) {
			return $url;
		}
		
		\parse_str( \wp_unslash( $raw ), $args );
		
		if ( ! \is_array( $args ) || $args === [] ) {
			return $url;
		}
		
		return \add_query_arg( $args, $url );
	}
}
