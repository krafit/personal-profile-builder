<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Multisite Language Switcher (MSLS) integration surface.
 *
 * Every MSLS call in the plugin goes through this class — no other
 * file imports anything from the `lloc\Msls` namespace. The class is
 * a soft dependency: when MSLS is not installed or the site is not
 * a multisite, every method returns a sensible empty value and the
 * features built on top (sync, redirect, language filter) no-op.
 *
 * @package	Personal_Profile_Builder
 */
final class MSLS_Integration {
	/**
	 * Check whether MSLS is available on the current site.
	 *
	 * The plugin must run on a multisite network and MSLS must be
	 * active for the current site (we detect this by looking for the
	 * `msls_blog_collection` global helper, which MSLS defines in
	 * its main file when initialised).
	 *
	 * @return	bool Whether MSLS features should be active
	 */
	public static function is_available(): bool {
		return \is_multisite() && \function_exists( 'msls_blog_collection' );
	}
	
	/**
	 * Get the locale of the current site.
	 *
	 * Uses MSLS's per-blog WPLANG option when available, falling
	 * back to `get_locale()` on misconfigured subsites or when MSLS
	 * is not installed.
	 *
	 * @return	string WordPress locale code (e.g. `de_DE`)
	 */
	public static function current_site_locale(): string {
		if ( ! self::is_available() ) {
			return \get_locale();
		}
		
		return \lloc\Msls\MslsBlogCollection::get_blog_language(
			null,
			\get_locale()
		);
	}
	
	/**
	 * Get the post ID of the translated talk on the matching subsite.
	 *
	 * @param	int	$post_id Source post ID on the current subsite
	 * @param	string	$target_locale Target locale, e.g. `de_DE`
	 * @return	int|null Linked post ID or null when no link exists
	 */
	public static function get_translated_post_id(
		int $post_id,
		string $target_locale
	): ?int {
		if ( ! self::is_available() ) {
			return null;
		}
		
		$opts = \msls_get_post( $post_id );
		
		if ( ! $opts->has_value( $target_locale ) ) {
			return null;
		}
		
		$linked_id = (int) $opts->__get( $target_locale );
		
		return $linked_id > 0 ? $linked_id : null;
	}
	
	/**
	 * Resolve the permalink of the translated post on its subsite.
	 *
	 * Reuses `MslsBlog::get_url()` because it handles the
	 * `switch_to_blog()` / `restore_current_blog()` discipline
	 * internally. Returns null when the target post is unpublished,
	 * trashed, or otherwise unresolvable.
	 *
	 * @param	int	$post_id Source post ID on the current subsite
	 * @param	string	$target_locale Target locale, e.g. `de_DE`
	 * @return	string|null Permalink on the target subsite, or null
	 */
	public static function get_translated_permalink(
		int $post_id,
		string $target_locale
	): ?string {
		if ( ! self::is_available() ) {
			return null;
		}
		
		$blog = \msls_blog( $target_locale );
		
		if ( $blog === null ) {
			return null;
		}
		
		$opts = \msls_get_post( $post_id );
		
		if ( ! $opts->has_value( $target_locale ) ) {
			return null;
		}
		
		$url = $blog->get_url( $opts );
		
		if ( ! \is_string( $url ) || $url === '' ) {
			return null;
		}
		
		return $url;
	}
	
	/**
	 * Append an occurrence date segment to a translated talk URL.
	 *
	 * Used by the occurrence redirect to preserve the per-event
	 * context when bouncing a visitor to the matching-language
	 * subsite. The date string is assumed pre-validated by the
	 * caller via {@see Occurrences::is_valid_date_string()}.
	 *
	 * @param	string	$base_url Translated talk permalink
	 * @param	string	$date_yyyymmdd 8-digit compact date
	 * @return	string Permalink with occurrence segment appended
	 */
	public static function append_occurrence_segment(
		string $base_url,
		string $date_yyyymmdd
	): string {
		return \trailingslashit( $base_url ) . $date_yyyymmdd;
	}
	
	/**
	 * Get the set of locales with a linked subsite in the network.
	 *
	 * @return	array<int,string> List of locale codes
	 */
	public static function linked_locales(): array {
		if ( ! self::is_available() ) {
			return [];
		}
		
		$locales = [];
		
		foreach ( \msls_blog_collection()->get_objects() as $blog ) {
			$locales[] = $blog->get_language();
		}
		
		return $locales;
	}
	
	/**
	 * Get a flat list of allowed locale codes, without labels.
	 *
	 * Used by validation paths (sanitisers, migrations) that only
	 * need to test membership. Cheap and safe on every request type
	 * because it does not call any admin-only functions.
	 *
	 * On MSLS-enabled networks the list mirrors the linked subsites.
	 * Otherwise it falls back to the set of locales installed in
	 * WordPress, plus `en_US`, so the per-occurrence language field
	 * still works on single-site installs.
	 *
	 * @return	array<int,string> Locale codes
	 */
	public static function allowed_locales(): array {
		if ( self::is_available() ) {
			$locales = [];
			
			foreach ( \msls_blog_collection()->get_objects() as $blog ) {
				$locales[] = $blog->get_language();
			}
			
			if ( $locales !== [] ) {
				return $locales;
			}
		}
		
		$locales = [ 'en_US' ];
		
		foreach ( \get_available_languages() as $code ) {
			$locales[] = $code;
		}
		
		return \array_values( \array_unique( $locales ) );
	}
	
	/**
	 * Ensure `format_code_lang()` is available.
	 *
	 * The function lives in `wp-admin/includes/ms.php`, which is only
	 * loaded on admin requests. We require it on demand when a label
	 * is genuinely needed (admin UIs, REST responses). When even the
	 * file is missing — exotic but possible on stripped-down WP
	 * builds — we degrade gracefully by returning false; callers
	 * fall back to the raw locale code.
	 *
	 * @return	bool Whether `format_code_lang()` is callable after the call
	 */
	private static function ensure_format_code_lang_loaded(): bool {
		if ( \function_exists( 'format_code_lang' ) ) {
			return true;
		}
		
		if ( ! \defined( 'ABSPATH' ) ) {
			return false;
		}
		
		$file = ABSPATH . 'wp-admin/includes/ms.php';
		
		if ( \is_readable( $file ) ) {
			require_once $file;
		}
		
		return \function_exists( 'format_code_lang' );
	}
	
	/**
	 * Get a `locale => human-readable name` map for admin pickers.
	 *
	 * On MSLS-enabled networks the list mirrors the linked subsites.
	 * Otherwise it falls back to the set of locales installed in
	 * WordPress, plus `en_US`, so the per-occurrence language field
	 * still works on single-site installs.
	 *
	 * Labels come from `format_code_lang()`, which lives in an
	 * admin-only file. The helper loads that file on demand and
	 * falls back to the raw locale code when the function is still
	 * not callable afterwards (extremely defensive — shouldn't
	 * happen on a normal WP install).
	 *
	 * Front-end callers should usually prefer {@see allowed_locales()}
	 * instead of this method, since they typically just need membership
	 * testing rather than human-readable labels.
	 *
	 * @return	array<string,string> Map of locale code to display name
	 */
	public static function locale_choices(): array {
		$can_format = self::ensure_format_code_lang_loaded();
		
		if ( self::is_available() ) {
			$choices = [];
			
			foreach ( \msls_blog_collection()->get_objects() as $blog ) {
				$locale = $blog->get_language();
				$label = $can_format
					? self::translate_language_name(
						\format_code_lang( $locale )
					)
					: $locale;
				$choices[ $locale ] = $label !== '' ? $label : $locale;
			}
			
			if ( $choices !== [] ) {
				return $choices;
			}
		}
		
		$choices = [
			'en_US' => $can_format
				? ( self::translate_language_name(
					\format_code_lang( 'en_US' )
				) ?: 'en_US' )
				: 'en_US',
		];
		
		foreach ( \get_available_languages() as $code ) {
			$label = $can_format
				? self::translate_language_name(
					\format_code_lang( $code )
				)
				: $code;
			$choices[ $code ] = $label !== '' ? $label : $code;
		}
		
		return $choices;
	}
	
	/**
	 * Get the full map of `locale => post_id` for sibling translations.
	 *
	 * Used by the cross-subsite sync fan-out. The current subsite's
	 * own locale is filtered out — the source talk is the one we just
	 * saved; we only push to siblings.
	 *
	 * @param	int	$post_id Source post ID on the current subsite
	 * @return	array<string,int> Locale-keyed map of linked sibling IDs
	 */
	public static function get_linked_post_ids( int $post_id ): array {
		if ( ! self::is_available() ) {
			return [];
		}
		
		$opts = \msls_get_post( $post_id );
		$map = $opts->get_arr();
		$current_locale = self::current_site_locale();
		$result = [];
		
		foreach ( $map as $locale => $linked_id ) {
			if ( $locale === $current_locale ) {
				continue;
			}
			
			$linked_id = (int) $linked_id;
			
			if ( $linked_id > 0 ) {
				$result[ $locale ] = $linked_id;
			}
		}
		
		return $result;
	}
	
	/**
	 * Get the WordPress blog ID for a given locale.
	 *
	 * @param	string	$locale Locale code
	 * @return	int|null Blog ID or null when the locale isn't in the network
	 */
	public static function get_blog_id_for_locale( string $locale ): ?int {
		if ( ! self::is_available() ) {
			return null;
		}
		
		$blog = \msls_blog( $locale );
		
		if ( $blog === null ) {
			return null;
		}
		
		$blog_id = (int) $blog->userblog_id;
		
		return $blog_id > 0 ? $blog_id : null;
	}
	
	/**
	 * Get the URL of the country flag icon for a locale.
	 *
	 * Returns an empty string when MSLS is unavailable; the front-end
	 * code that consumes this falls back to a flag-less pill.
	 *
	 * @param	string	$locale Locale code
	 * @return	string Flag image URL, or empty string
	 */
	public static function flag_url( string $locale ): string {
		if ( ! self::is_available() || $locale === '' ) {
			return '';
		}
		
		return \msls_get_flag_url( $locale );
	}
	
	/**
	 * Get the human-readable name of a locale.
	 *
	 * Wrapper over `format_code_lang()` that returns an empty string
	 * for empty input rather than the formatted code, so callers can
	 * `?? ''` on it without thinking.
	 *
	 * `format_code_lang()` returns the English language name (e.g.
	 * "German"). To present it in the active locale, the result is
	 * run through WordPress core's own `default` text domain via
	 * {@see translate()} — the same strings core uses on the
	 * install and language-picker screens. Names without a core
	 * translation fall back to the English original.
	 *
	 * `format_code_lang()` lives in an admin-only file, so the helper
	 * loads it on demand. When still unavailable, falls back to the
	 * raw locale code.
	 *
	 * @param	string	$locale Locale code
	 * @return	string Human-readable language name, or empty
	 */
	public static function locale_name( string $locale ): string {
		if ( $locale === '' ) {
			return '';
		}
		
		if ( ! self::ensure_format_code_lang_loaded() ) {
			return $locale;
		}
		
		$name = \format_code_lang( $locale );
		
		if ( $name === '' ) {
			return $locale;
		}
		
		return self::translate_language_name( $name );
	}
	
	/**
	 * Translate an English language name via WordPress core.
	 *
	 * `format_code_lang()` hard-codes English names that are not
	 * themselves run through a translation function. WordPress core
	 * does carry translations for the common language names in its
	 * `default` text domain (used by the language-picker UI), so
	 * passing the English name through `translate( $name, 'default' )`
	 * yields the localised name for the active locale where one
	 * exists, and the English original otherwise. This means the
	 * plugin never has to maintain its own list of language names.
	 *
	 * @param	string	$name English language name from format_code_lang()
	 * @return	string Localised name, or the English original
	 */
	private static function translate_language_name( string $name ): string {
		if ( $name === '' ) {
			return '';
		}
		
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		return \translate( $name, 'default' );
	}
}
