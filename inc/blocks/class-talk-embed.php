<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Blocks;

use Personal_Profile_Builder\Meta;
use Personal_Profile_Builder\Occurrences;
use Personal_Profile_Builder\Post_Types;
use Personal_Profile_Builder\Query_Helpers;

/**
 * Talk Embed block.
 *
 * Registers and renders the `personal-profile-builder/talk-embed` block.
 * Displays a single talk as a card, optionally deeplinked to a specific
 * occurrence with its slides, recording, and event URL shown inline.
 *
 * @package	Personal_Profile_Builder
 */
final class Talk_Embed {
	/**
	 * @var	string Block name including namespace.
	 */
	public const BLOCK_NAME = 'personal-profile-builder/talk-embed';
	
	/**
	 * Register the block type and its assets.
	 */
	public static function register(): void {
		\register_block_type(
			PERSONAL_PROFILE_BUILDER_DIR . '/blocks/talk-embed'
		);
		\wp_set_script_translations(
			'personal-profile-builder-talk-embed-editor-script',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
	}
	
	/**
	 * Render the block on the front end.
	 *
	 * When `occurrenceDate` is set and matches an occurrence, the card
	 * title links to the occurrence URL and the occurrence's slides,
	 * recording, and event URL are appended as a links section.
	 *
	 * @param	array<string,mixed>	$attributes Block attributes
	 * @return	string Rendered HTML
	 */
	public static function render( array $attributes ): string {
		$post_id = isset( $attributes['postId'] )
			? \absint( $attributes['postId'] )
			: 0;
		$occurrence_date = isset( $attributes['occurrenceDate'] )
			? \sanitize_text_field( (string) $attributes['occurrenceDate'] )
			: '';
		
		if ( $post_id === 0 ) {
			return '';
		}
		
		$post = \get_post( $post_id );
		
		if (
			$post === null
			|| $post->post_type !== Post_Types::POST_TYPE_TALK
			|| $post->post_status !== 'publish'
		) {
			return '';
		}
		
		$occurrence = null;
		
		if (
			$occurrence_date !== ''
			&& Occurrences::is_valid_date_string(
				Occurrences::compact_date( $occurrence_date )
			)
		) {
			$occurrence = self::find_occurrence(
				$post_id,
				$occurrence_date
			);
		}
		
		$wrapper_class = 'wp-block-personal-profile-builder-talk-embed';
		$wrapper_class .= ' ppb-talk-embed';
		
		$wrapper_attributes = \get_block_wrapper_attributes(
			[ 'class' => $wrapper_class ]
		);
		
		$card = self::render_card( $post_id, $occurrence );
		
		return '<div ' . $wrapper_attributes . '>'
			. $card
			. '</div>';
	}
	
	/**
	 * Find an occurrence row by date.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	string	$date ISO date (YYYY-MM-DD)
	 * @return	array<string,string>|null The occurrence row, or null
	 */
	private static function find_occurrence(
		int $post_id,
		string $date
	): ?array {
		$rows = Query_Helpers::get_occurrences( $post_id, 'all' );
		
		foreach ( $rows as $row ) {
			if ( ( $row['date'] ?? '' ) === $date ) {
				return $row;
			}
		}
		
		return null;
	}
	
	/**
	 * Render a single talk card with optional occurrence details.
	 *
	 * Reuses the same CSS classes as {@see Talk_Query::render_card()}
	 * for consistent styling, with added occurrence link section.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	array<string,string>|null	$occurrence Matched row
	 * @return	string Card HTML
	 */
	private static function render_card(
		int $post_id,
		?array $occurrence
	): string {
		$title = \get_the_title( $post_id );
		$emoji = (string) \get_post_meta(
			$post_id,
			'_talk_cover_emoji',
			true
		);
		$status = (string) \get_post_meta(
			$post_id,
			'_talk_status',
			true
		);
		$topics = \get_the_terms(
			$post_id,
			Post_Types::TAXONOMY_TALK_TOPIC
		);
		
		if ( $occurrence !== null ) {
			$date = $occurrence['date'] ?? '';
			$compact = Occurrences::compact_date( $date );
			$permalink = Occurrences::get_occurrence_url(
				$post_id,
				$compact
			);
		}
		else {
			$permalink = (string) \get_permalink( $post_id );
		}
		
		$card = '<article class="ppb-talk-query__card">';
		
		if ( $emoji !== '' ) {
			$card .= '<span class="ppb-talk-query__emoji"'
				. ' aria-hidden="true">'
				. \esc_html( $emoji )
				. '</span>';
		}
		
		$card .= '<h3 class="ppb-talk-query__title">';
		$card .= '<a href="'
			. \esc_url( $permalink ) . '">';
		$card .= \esc_html( $title );
		$card .= '</a>';
		$card .= '</h3>';
		
		if (
			\is_array( $topics )
			&& $topics !== []
		) {
			$card .= '<div class="ppb-talk-query__topics">';
			
			foreach ( $topics as $term ) {
				$card .= '<span class="ppb-talk-query__topic">'
					. \esc_html( $term->name )
					. '</span>';
			}
			
			$card .= '</div>';
		}
		
		$card .= '<div class="ppb-talk-query__meta">';
		
		if ( $status !== '' ) {
			$status_label = $status === Meta::STATUS_AVAILABLE
				? \__( 'Available', 'personal-profile-builder' )
				: \__( 'Retired', 'personal-profile-builder' );
			$card .= '<span class="ppb-talk-query__status'
				. ' ppb-talk-query__status--'
				. \esc_attr( $status ) . '">'
				. \esc_html( $status_label )
				. '</span>';
		}
		
		if ( $occurrence !== null ) {
			$card .= self::render_occurrence_meta(
				$occurrence
			);
		}
		
		$card .= '</div>';
		
		$card .= self::render_occurrence_links( $occurrence );
		
		$card .= '</article>';
		
		return $card;
	}
	
	/**
	 * Render the occurrence date and event name in the meta section.
	 *
	 * @param	array<string,string>	$occurrence The occurrence row
	 * @return	string HTML fragment
	 */
	private static function render_occurrence_meta(
		array $occurrence
	): string {
		$date = $occurrence['date'] ?? '';
		$event_name = $occurrence['event_name'] ?? '';
		$location = $occurrence['location'] ?? '';
		$html = '';
		
		if ( $date !== '' ) {
			$formatted = \mysql2date(
				\get_option( 'date_format', 'Y-m-d' ),
				$date . ' 00:00:00'
			);
			$html .= '<span class="ppb-talk-query__next-date">'
				. \esc_html( (string) $formatted )
				. '</span>';
		}
		
		if ( $event_name !== '' ) {
			$html .= '<span class="ppb-talk-embed__event">'
				. \esc_html( $event_name )
				. '</span>';
		}
		
		if ( $location !== '' ) {
			$html .= '<span class="ppb-talk-embed__location">'
				. \esc_html( $location )
				. '</span>';
		}
		
		return $html;
	}
	
	/**
	 * Render slides, recording, and event URL links.
	 *
	 * Only rendered when an occurrence is selected and at least one
	 * of the three URLs is non-empty.
	 *
	 * @param	array<string,string>|null	$occurrence The occurrence row
	 * @return	string HTML fragment (may be empty)
	 */
	private static function render_occurrence_links(
		?array $occurrence
	): string {
		if ( $occurrence === null ) {
			return '';
		}
		
		$slides = $occurrence['slides_url'] ?? '';
		$recording = $occurrence['recording_url'] ?? '';
		$event_url = $occurrence['event_url'] ?? '';
		
		if (
			$slides === ''
			&& $recording === ''
			&& $event_url === ''
		) {
			return '';
		}
		
		$html = '<div class="ppb-talk-embed__links">';
		
		if ( $slides !== '' ) {
			$html .= '<a href="' . \esc_url( $slides )
				. '" class="ppb-talk-embed__link">'
				. \esc_html__(
					'Slides',
					'personal-profile-builder'
				)
				. '</a>';
		}
		
		if ( $recording !== '' ) {
			$html .= '<a href="' . \esc_url( $recording )
				. '" class="ppb-talk-embed__link">'
				. \esc_html__(
					'Recording',
					'personal-profile-builder'
				)
				. '</a>';
		}
		
		if ( $event_url !== '' ) {
			$html .= '<a href="' . \esc_url( $event_url )
				. '" class="ppb-talk-embed__link">'
				. \esc_html__(
					'Event',
					'personal-profile-builder'
				)
				. '</a>';
		}
		
		$html .= '</div>';
		
		return $html;
	}
}
