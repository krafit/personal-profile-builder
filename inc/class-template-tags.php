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
		$html = '<div class="' . $class . '">';
		
		if ( $args['heading'] !== '' ) {
			$tag = \tag_escape( (string) $args['heading_tag'] );
			$html .= '<' . $tag
				. ' class="' . $class . '__heading">'
				. \esc_html( (string) $args['heading'] )
				. '</' . $tag . '>';
		}
		
		$html .= '<ul class="' . $class . '__list">';
		
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
		$class = \esc_attr( (string) $args['class'] );
		
		$li = '<li class="' . $class . '__item">';
		
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
		
		$li .= '</li>';
		
		return $li;
	}
}
