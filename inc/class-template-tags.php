<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Template tags for themes.
 *
 * Convenience functions that themes can call directly to render
 * occurrence data in their templates without needing to decode
 * JSON or build markup manually.
 *
 * All methods are static and stateless. They read from the database
 * on each call (no internal caching), so callers that render the
 * same data multiple times should cache the result themselves.
 *
 * @package	Personal_Profile_Builder
 */
final class Template_Tags {
	/**
	 * Render an HTML list of occurrences for a talk.
	 *
	 * Outputs a `<ul>` with one `<li>` per occurrence, each showing
	 * the date, event name, and location. The list is wrapped in a
	 * `<div>` with a configurable CSS class.
	 *
	 * @param	int	$post_id Talk post ID (defaults to current post)
	 * @param	array<string,mixed>	$args {
	 *     Optional rendering arguments.
	 *
	 *     @type	string	$filter 'all', 'upcoming', or 'past'. Default 'all'.
	 *     @type	string	$class CSS class for the wrapper div. Default 'ppb-occurrences'.
	 *     @type	string	$heading Optional heading text above the list.
	 *     @type	string	$heading_tag HTML tag for the heading. Default 'h3'.
	 *     @type	string	$empty_text Text to show when there are no occurrences.
	 *                     Empty string suppresses any empty-state output.
	 *     @type	string	$date_format PHP date format string. Defaults to the
	 *                     site's date_format option.
	 *     @type	bool	$show_links Whether to link each occurrence to its
	 *                    shareable URL. Default true.
	 *     @type	bool	$with_filter Whether to render the language filter
	 *                    pill row above the list when more than one
	 *                    language is present. Default true.
	 *     @type	bool	$echo Whether to echo (true) or return (false). Default true.
	 * }
	 * @return	string Rendered HTML (also echoed if $args['echo'] is true)
	 */
	public static function occurrence_list(
		int $post_id = 0,
		array $args = []
	): string {
		if ( $post_id < 1 ) {
			$post_id = \get_the_ID();
		}
		
		if ( ! \is_int( $post_id ) || $post_id < 1 ) {
			return '';
		}
		
		$defaults = [
			'filter' => 'all',
			'class' => 'ppb-occurrences',
			'heading' => '',
			'heading_tag' => 'h3',
			'empty_text' => '',
			'date_format' => (string) \get_option(
				'date_format',
				'Y-m-d'
			),
			'show_links' => true,
			'with_filter' => true,
			'echo' => true,
		];
		$args = \wp_parse_args( $args, $defaults );
		
		$rows = Query_Helpers::get_occurrences(
			$post_id,
			(string) $args['filter']
		);
		
		if ( $rows === [] ) {
			if ( $args['empty_text'] === '' ) {
				return '';
			}
			
			$html = '<div class="'
				. \esc_attr( (string) $args['class'] ) . '">'
				. '<p class="'
				. \esc_attr( (string) $args['class'] )
				. '__empty">'
				. \esc_html( (string) $args['empty_text'] )
				. '</p></div>';
			
			if ( $args['echo'] ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			
			return $html;
		}
		
		$class = \esc_attr( (string) $args['class'] );
		$languages = self::collect_distinct_languages( $rows );
		$show_filter_ui = self::should_show_filter( $languages, $args );
		$wrapper_attrs = 'class="' . $class . '"';
		
		if ( $show_filter_ui ) {
			$wrapper_attrs .= ' data-ppb-occurrence-filter';
			
			/* phpcs:ignore */
			self::enqueue_filter_assets();
		}
		
		$html = '<div ' . $wrapper_attrs . '>';
		
		if ( $args['heading'] !== '' ) {
			$tag = \tag_escape( (string) $args['heading_tag'] );
			$html .= '<' . $tag
				. ' class="' . $class . '__heading">'
				. \esc_html( (string) $args['heading'] )
				. '</' . $tag . '>';
		}
		
		if ( $show_filter_ui ) {
			$html .= self::render_filter_row( $languages, $class );
		}
		
		$list_attrs = 'class="' . $class . '__list"';
		
		if ( $show_filter_ui ) {
			$list_attrs .= ' data-ppb-occurrence-list';
		}
		
		$html .= '<ul ' . $list_attrs . '>';
		
		foreach ( $rows as $row ) {
			$html .= self::render_occurrence_item(
				$post_id,
				$row,
				$args
			);
		}
		
		$html .= '</ul></div>';
		
		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		return $html;
	}
	
	/**
	 * Get the formatted next occurrence date for a talk.
	 *
	 * Returns a human-readable date string for the soonest upcoming
	 * occurrence, or the specified fallback when there is none.
	 *
	 * @param	int	$post_id Talk post ID (defaults to current post)
	 * @param	string	$fallback Text to return when no upcoming date exists
	 * @param	string	$date_format PHP date format (site default if empty)
	 * @return	string Formatted date or fallback
	 */
	public static function next_occurrence_date(
		int $post_id = 0,
		string $fallback = '',
		string $date_format = ''
	): string {
		if ( $post_id < 1 ) {
			$post_id = \get_the_ID();
		}
		
		if ( ! \is_int( $post_id ) || $post_id < 1 ) {
			return $fallback;
		}
		
		$next = Query_Helpers::get_next_date_for_talk(
			$post_id
		);
		
		if ( $next === '' ) {
			return $fallback;
		}
		
		if ( $date_format === '' ) {
			$date_format = (string) \get_option(
				'date_format',
				'Y-m-d'
			);
		}
		
		$formatted = \mysql2date(
			$date_format,
			$next . ' 00:00:00'
		);
		
		return \is_string( $formatted )
			? $formatted
			: $fallback;
	}
	
	/**
	 * Get the occurrence count for a talk.
	 *
	 * @param	int	$post_id Talk post ID (defaults to current post)
	 * @param	string	$filter One of 'all', 'upcoming', or 'past'
	 * @return	int Number of occurrences matching the filter
	 */
	public static function occurrence_count(
		int $post_id = 0,
		string $filter = 'all'
	): int {
		if ( $post_id < 1 ) {
			$post_id = \get_the_ID();
		}
		
		if ( ! \is_int( $post_id ) || $post_id < 1 ) {
			return 0;
		}
		
		return \count(
			Query_Helpers::get_occurrences( $post_id, $filter )
		);
	}
	
	/**
	 * Render a single occurrence list item.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	array<string,string>	$row Occurrence row data
	 * @param	array<string,mixed>	$args Template arguments
	 * @return	string HTML for one `<li>`
	 */
	private static function render_occurrence_item(
		int $post_id,
		array $row,
		array $args
	): string {
		$date = $row['date'] ?? '';
		
		if ( $date === '' ) {
			return '';
		}
		
		$formatted_date = \mysql2date(
			(string) $args['date_format'],
			$date . ' 00:00:00'
		);
		
		if ( ! \is_string( $formatted_date ) ) {
			$formatted_date = $date;
		}
		
		$event_name = $row['event_name'] ?? '';
		$location = $row['location'] ?? '';
		$event_url = $row['event_url'] ?? '';
		$language = isset( $row['language'] ) && \is_string( $row['language'] )
			? $row['language']
			: '';
		$class = \esc_attr( (string) $args['class'] );
		
		$li = '<li class="' . $class . '__item"'
			. ' data-language="' . \esc_attr( $language ) . '">';
		
		$date_html = '<time class="' . $class . '__date"'
			. ' datetime="' . \esc_attr( $date ) . '">'
			. \esc_html( $formatted_date )
			. '</time>';
		
		if (
			$args['show_links']
			&& $event_url !== ''
		) {
			$date_html = '<a href="'
				. \esc_url( $event_url ) . '" class="'
				. $class . '__link">'
				. $date_html . '</a>';
		}
		
		$li .= $date_html;
		
		if ( $event_name !== '' ) {
			$li .= ' <span class="' . $class
				. '__event">'
				. \esc_html( $event_name )
				. '</span>';
		}
		
		if ( $location !== '' ) {
			$li .= ' <span class="' . $class
				. '__location">'
				. \esc_html( $location )
				. '</span>';
		}
		
		if ( $language !== '' ) {
			$li .= ' ' . self::render_language_pill( $language, $class );
		}
		
		$li .= '</li>';
		
		return $li;
	}
	
	/**
	 * Render the language pill for an occurrence.
	 *
	 * Shows the language flag (when MSLS is available) and name.
	 * Display-only — never a link.
	 *
	 * @param	string	$locale Locale code, e.g. `de_DE`
	 * @param	string	$class Already-escaped wrapper CSS class
	 * @return	string Escaped HTML for the pill
	 */
	private static function render_language_pill(
		string $locale,
		string $class
	): string {
		$name = MSLS_Integration::locale_name( $locale );
		
		if ( $name === '' ) {
			return '';
		}
		
		$flag_url = MSLS_Integration::flag_url( $locale );
		$html = '<span class="' . $class . '__language" data-language="'
			. \esc_attr( $locale ) . '">';
		
		if ( $flag_url !== '' ) {
			$html .= '<img class="' . $class . '__language-flag" src="'
				. \esc_url( $flag_url ) . '" alt="" />';
		}
		
		$html .= '<span class="' . $class . '__language-name">'
			. \esc_html( $name )
			. '</span></span>';
		
		return $html;
	}
	
	/**
	 * Collect distinct locale codes across the row set.
	 *
	 * Returns a sorted list with the empty string at the end (so the
	 * "Unspecified" pill renders last when present).
	 *
	 * @param	array<int,array<string,mixed>>	$rows Occurrence rows
	 * @return	array<int,string> Distinct locale codes, sorted
	 */
	private static function collect_distinct_languages( array $rows ): array {
		$set = [];
		
		foreach ( $rows as $row ) {
			$locale = isset( $row['language'] ) && \is_string( $row['language'] )
				? $row['language']
				: '';
			$set[ $locale ] = true;
		}
		
		$has_empty = isset( $set[''] );
		unset( $set[''] );
		$out = \array_keys( $set );
		\sort( $out );
		
		if ( $has_empty ) {
			$out[] = '';
		}
		
		return $out;
	}
	
	/**
	 * Decide whether to render the language filter UI.
	 *
	 * Shown when (a) the caller opted in via `with_filter`, (b) the
	 * `ppb_occurrence_filter_enabled` site filter is true, and (c)
	 * more than one distinct language is present in the row set.
	 * Rows with empty language count toward the distinctness check
	 * because the "Unspecified" pill is a meaningful filter target.
	 *
	 * @param	array<int,string>	$languages Distinct language list
	 * @param	array<string,mixed>	$args Template args
	 * @return	bool Whether to render the filter UI
	 */
	private static function should_show_filter(
		array $languages,
		array $args
	): bool {
		if ( ! (bool) $args['with_filter'] ) {
			return false;
		}
		
		/**
		 * Filter whether the language pill UI may render on the front-end.
		 *
		 * @since	1.7.0
		 *
		 * @param	bool	$enabled Whether the filter UI is allowed
		 */
		$enabled = (bool) \apply_filters(
			'ppb_occurrence_filter_enabled',
			true
		);
		
		if ( ! $enabled ) {
			return false;
		}
		
		return \count( $languages ) > 1;
	}
	
	/**
	 * Render the filter pill row.
	 *
	 * Outputs an "All" pill followed by one pill per language. The
	 * "Unspecified" pill is rendered for occurrences with empty
	 * language. JS toggles `data-active="true"` on the chosen pill
	 * and filters the list items by their `data-language` attribute.
	 *
	 * @param	array<int,string>	$languages Distinct locales (sorted, empty last)
	 * @param	string	$class Already-escaped wrapper CSS class
	 * @return	string Escaped HTML for the pill row
	 */
	private static function render_filter_row(
		array $languages,
		string $class
	): string {
		$nav_label = \esc_attr__(
			'Show occurrences in:',
			'personal-profile-builder'
		);
		$html = '<div class="' . $class . '__filter"'
			. ' role="group"'
			. ' aria-label="' . $nav_label . '"'
			. ' data-ppb-occurrence-filter-controls>';
		$html .= '<button type="button"'
			. ' class="' . $class . '__filter-pill"'
			. ' data-ppb-filter-value="*"'
			. ' data-active="true">'
			. \esc_html__( 'All', 'personal-profile-builder' )
			. '</button>';
		
		foreach ( $languages as $locale ) {
			$label = $locale === ''
				? \__( 'Unspecified', 'personal-profile-builder' )
				: MSLS_Integration::locale_name( $locale );
			$flag_url = $locale === '' ? '' : MSLS_Integration::flag_url( $locale );
			
			$html .= '<button type="button"'
				. ' class="' . $class . '__filter-pill"'
				. ' data-ppb-filter-value="' . \esc_attr( $locale ) . '">';
			
			if ( $flag_url !== '' ) {
				$html .= '<img class="' . $class . '__filter-flag" src="'
					. \esc_url( $flag_url ) . '" alt="" />';
			}
			
			$html .= '<span class="' . $class . '__filter-label">'
				. \esc_html( $label )
				. '</span>'
				. '</button>';
		}
		
		$html .= '</div>';
		
		return $html;
	}
	
	/**
	 * Enqueue the front-end filter script and stylesheet.
	 *
	 * Idempotent — `wp_enqueue_*` deduplicates by handle.
	 */
	private static function enqueue_filter_assets(): void {
		\wp_enqueue_style(
			'ppb-occurrence-filter',
			PERSONAL_PROFILE_BUILDER_URL
				. 'assets/css/occurrence-filter.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
		\wp_enqueue_script(
			'ppb-occurrence-filter',
			PERSONAL_PROFILE_BUILDER_URL
				. 'assets/js/occurrence-filter.js',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION,
			true
		);
	}
}
