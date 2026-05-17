<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Blocks;

use Personal_Profile_Builder\Post_Types;
use WP_Query;

/**
 * Project Query block.
 *
 * Registers and renders the `personal-profile-builder/project-query`
 * block. Server-rendered so it always reflects current data.
 *
 * @package	Personal_Profile_Builder
 */
final class Project_Query {
	/**
	 * @var	string Block name including namespace.
	 */
	public const BLOCK_NAME = 'personal-profile-builder/project-query';
	
	/**
	 * Register the block type and its assets.
	 */
	public static function register(): void {
		\register_block_type(
			PERSONAL_PROFILE_BUILDER_DIR . '/blocks/project-query'
		);
		\wp_set_script_translations(
			'personal-profile-builder-project-query-editor-script',
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
		$type = isset( $attributes['type'] )
			? \array_map( 'absint', (array) $attributes['type'] )
			: [];
		$max_items = isset( $attributes['maxItems'] )
			? \absint( $attributes['maxItems'] )
			: 6;
		$order_by = isset( $attributes['orderBy'] )
			? \sanitize_key( (string) $attributes['orderBy'] )
			: 'date';
		$layout = isset( $attributes['layout'] )
			? \sanitize_key( (string) $attributes['layout'] )
			: 'grid';
		
		if ( $max_items < 1 ) {
			$max_items = 6;
		}
		
		$query_args = [
			'post_type' => Post_Types::POST_TYPE_PROJECT,
			'posts_per_page' => $max_items,
			'post_status' => 'publish',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];
		
		if ( $type !== [] ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => Post_Types::TAXONOMY_PROJECT_TYPE,
					'field' => 'term_id',
					'terms' => $type,
				],
			];
		}
		
		switch ( $order_by ) {
			case 'title':
				$query_args['orderby'] = 'title';
				$query_args['order'] = 'ASC';
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
		
		$wrapper_class = 'wp-block-personal-profile-builder-project-query';
		$wrapper_class .= ' ppb-project-query';
		$wrapper_class .= ' ppb-project-query--' . \esc_attr( $layout );
		
		$wrapper_attributes = \get_block_wrapper_attributes(
			[ 'class' => $wrapper_class ]
		);
		
		$output = '<div ' . $wrapper_attributes . '>';
		
		while ( $query->have_posts() ) {
			$query->the_post();
			$output .= self::render_card( \get_the_ID() );
		}
		
		\wp_reset_postdata();
		
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Render a single project card.
	 *
	 * Public so the Project Embed block can reuse the same card
	 * markup.
	 *
	 * @param	int	$post_id Project post ID
	 * @return	string Card HTML
	 */
	public static function render_card( int $post_id ): string {
		$title = \get_the_title( $post_id );
		$permalink = (string) \get_permalink( $post_id );
		$icon = (string) \get_post_meta(
			$post_id,
			'_project_icon',
			true
		);
		$badge = (string) \get_post_meta(
			$post_id,
			'_project_badge',
			true
		);
		
		$card = '<article class="ppb-project-query__card">';
		
		if ( $icon !== '' ) {
			$card .= '<span class="ppb-project-query__icon"'
				. ' aria-hidden="true">'
				. \esc_html( $icon )
				. '</span>';
		}
		
		$card .= '<h3 class="ppb-project-query__title">';
		$card .= '<a href="'
			. \esc_url( $permalink ) . '">';
		$card .= \esc_html( $title );
		$card .= '</a>';
		$card .= '</h3>';
		
		if ( $badge !== '' ) {
			$card .= '<span class="ppb-project-query__badge">'
				. \esc_html( $badge )
				. '</span>';
		}
		
		$excerpt = \get_the_excerpt( $post_id );
		
		if ( $excerpt !== '' ) {
			$card .= '<p class="ppb-project-query__excerpt">'
				. \esc_html( $excerpt )
				. '</p>';
		}
		
		$card .= '</article>';
		
		return $card;
	}
}
