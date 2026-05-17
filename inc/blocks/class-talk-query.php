<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Blocks;

use Personal_Profile_Builder\Meta;
use Personal_Profile_Builder\Occurrences;
use Personal_Profile_Builder\Post_Types;
use WP_Query;

/**
 * Talk Query block.
 *
 * Registers and renders the `personal-profile-builder/talk-query` block.
 * The block is entirely server-rendered so it always reflects current
 * data and respects the occurrence meta-override layer.
 *
 * @package	Personal_Profile_Builder
 */
final class Talk_Query {
	/**
	 * @var	string Block name including namespace.
	 */
	public const BLOCK_NAME = 'personal-profile-builder/talk-query';
	
	/**
	 * Register the block type and its assets.
	 */
	public static function register(): void {
		\register_block_type(
			PERSONAL_PROFILE_BUILDER_DIR . '/blocks/talk-query'
		);
		\wp_set_script_translations(
			'personal-profile-builder-talk-query-editor-script',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
	}
	
	/**
	 * Render the block on the front end.
	 *
	 * Called by the `render_callback` defined in `block.json`.
	 *
	 * @param	array<string,mixed>	$attributes Block attributes
	 * @return	string Rendered HTML
	 */
	public static function render( array $attributes ): string {
		$topic = isset( $attributes['topic'] )
			? \array_map( 'absint', (array) $attributes['topic'] )
			: [];
		$status = isset( $attributes['status'] )
			? \sanitize_key( (string) $attributes['status'] )
			: '';
		$max_items = isset( $attributes['maxItems'] )
			? \absint( $attributes['maxItems'] )
			: 6;
		$order_by = isset( $attributes['orderBy'] )
			? \sanitize_key( (string) $attributes['orderBy'] )
			: 'date';
		$layout = isset( $attributes['layout'] )
			? \sanitize_key( (string) $attributes['layout'] )
			: 'grid';
		$retired_last = ! empty( $attributes['retiredLast'] );
		
		if ( $max_items < 1 ) {
			$max_items = 6;
		}
		
		$query_args = [
			'post_type' => Post_Types::POST_TYPE_TALK,
			'posts_per_page' => $retired_last ? -1 : $max_items,
			'post_status' => 'publish',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];
		
		if ( $topic !== [] ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => Post_Types::TAXONOMY_TALK_TOPIC,
					'field' => 'term_id',
					'terms' => $topic,
				],
			];
		}
		
		if ( \in_array( $status, Meta::TALK_STATUSES, true ) ) {
			$query_args['meta_query'] = [
				[
					'key' => '_talk_status',
					'value' => $status,
					'compare' => '=',
				],
			];
		}
		
		switch ( $order_by ) {
			case 'title':
				$query_args['orderby'] = 'title';
				$query_args['order'] = 'ASC';
				break;
			case 'next_occurrence':
				$query_args['orderby'] = 'date';
				$query_args['order'] = 'DESC';
				break;
			default:
				$query_args['orderby'] = 'date';
				$query_args['order'] = 'DESC';
				break;
		}
		
		$query = new WP_Query( $query_args );
		
		if ( ! $query->have_posts() ) {
			return '';
		}
		
		$posts = $query->posts;
		\wp_reset_postdata();
		
		if ( $order_by === 'next_occurrence' ) {
			$posts = self::sort_by_next_occurrence( $posts );
		}
		
		if ( $retired_last ) {
			$posts = self::sort_retired_last( $posts );
		}
		
		if ( \count( $posts ) > $max_items ) {
			$posts = \array_slice( $posts, 0, $max_items );
		}
		
		$wrapper_class = 'wp-block-personal-profile-builder-talk-query';
		$wrapper_class .= ' ppb-talk-query';
		$wrapper_class .= ' ppb-talk-query--' . \esc_attr( $layout );
		
		$wrapper_attributes = \get_block_wrapper_attributes(
			[ 'class' => $wrapper_class ]
		);
		
		$output = '<div ' . $wrapper_attributes . '>';
		
		foreach ( $posts as $post ) {
			$output .= self::render_card( $post->ID );
		}
		
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Render a single talk card.
	 *
	 * Public so the Talk Embed block can reuse the same card markup.
	 *
	 * @param	int	$post_id Talk post ID
	 * @return	string Card HTML
	 */
	public static function render_card( int $post_id ): string {
		$title = \get_the_title( $post_id );
		$permalink = \get_permalink( $post_id );
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
		$next_date = self::get_next_occurrence_date( $post_id );
		
		$card = '<article class="ppb-talk-query__card">';
		
		if ( $emoji !== '' ) {
			$card .= '<span class="ppb-talk-query__emoji"'
				. ' aria-hidden="true">'
				. \esc_html( $emoji )
				. '</span>';
		}
		
		$card .= '<h3 class="ppb-talk-query__title">';
		$card .= '<a href="' . \esc_url( (string) $permalink ) . '">';
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
		
		if ( $next_date !== '' ) {
			$formatted = \mysql2date(
				\get_option( 'date_format', 'Y-m-d' ),
				$next_date . ' 00:00:00'
			);
			$card .= '<span class="ppb-talk-query__next-date">'
				. \esc_html(
					\sprintf(
						/* translators: %s: formatted date */
						\__( 'Next: %s', 'personal-profile-builder' ),
						$formatted
					)
				)
				. '</span>';
		}
		
		$card .= '</div>';
		$card .= '</article>';
		
		return $card;
	}
	
	/**
	 * Get the next upcoming occurrence date for a talk.
	 *
	 * @param	int	$post_id Talk post ID
	 * @return	string ISO date (YYYY-MM-DD) or empty string
	 */
	private static function get_next_occurrence_date(
		int $post_id
	): string {
		$raw = \get_post_meta(
			$post_id,
			'_talk_occurrences',
			true
		);
		
		if ( ! \is_string( $raw ) || $raw === '' ) {
			return '';
		}
		
		$rows = \json_decode( $raw, true );
		
		if ( ! \is_array( $rows ) ) {
			return '';
		}
		
		$today = \current_time( 'Y-m-d' );
		$candidates = [];
		
		foreach ( $rows as $row ) {
			if (
				! \is_array( $row )
				|| ! isset( $row['date'] )
				|| ! \is_string( $row['date'] )
			) {
				continue;
			}
			
			if ( $row['date'] >= $today ) {
				$candidates[] = $row['date'];
			}
		}
		
		if ( $candidates === [] ) {
			return '';
		}
		
		\sort( $candidates );
		
		return $candidates[0];
	}
	
	/**
	 * Sort posts by their next upcoming occurrence date.
	 *
	 * Posts with upcoming dates come first (ascending), followed
	 * by posts without any upcoming occurrence.
	 *
	 * @param	array<int,\WP_Post>	$posts Posts to sort
	 * @return	array<int,\WP_Post> Sorted posts
	 */
	private static function sort_by_next_occurrence(
		array $posts
	): array {
		\usort(
			$posts,
			function ( \WP_Post $a, \WP_Post $b ): int {
				$da = self::get_next_occurrence_date( $a->ID );
				$db = self::get_next_occurrence_date( $b->ID );
				
				if ( $da === '' && $db === '' ) {
					return 0;
				}
				
				if ( $da === '' ) {
					return 1;
				}
				
				if ( $db === '' ) {
					return -1;
				}
				
				return $da <=> $db;
			}
		);
		
		return $posts;
	}
	
	/**
	 * Sort posts so retired talks sink to the bottom.
	 *
	 * Within each group (non-retired, retired) the existing order
	 * is preserved — this is a stable partition, not a full re-sort.
	 *
	 * @param	array<int,\WP_Post>	$posts Posts to sort
	 * @return	array<int,\WP_Post> Partitioned posts
	 */
	private static function sort_retired_last(
		array $posts
	): array {
		$active = [];
		$retired = [];
		
		foreach ( $posts as $post ) {
			$status = (string) \get_post_meta(
				$post->ID,
				'_talk_status',
				true
			);
			
			if ( $status === Meta::STATUS_RETIRED ) {
				$retired[] = $post;
			}
			else {
				$active[] = $post;
			}
		}
		
		return \array_merge( $active, $retired );
	}
}
