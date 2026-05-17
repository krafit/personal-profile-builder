<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Blocks;

use Personal_Profile_Builder\Post_Types;

/**
 * Project Embed block.
 *
 * Registers and renders the `personal-profile-builder/project-embed`
 * block. Displays a single project as a card, reusing the card markup
 * from {@see Project_Query::render_card()} for visual consistency.
 *
 * @package	Personal_Profile_Builder
 */
final class Project_Embed {
	/**
	 * @var	string Block name including namespace.
	 */
	public const BLOCK_NAME = 'personal-profile-builder/project-embed';
	
	/**
	 * Register the block type and its assets.
	 */
	public static function register(): void {
		\register_block_type(
			PERSONAL_PROFILE_BUILDER_DIR . '/blocks/project-embed'
		);
		\wp_set_script_translations(
			'personal-profile-builder-project-embed-editor-script',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
	}
	
	/**
	 * Render the block on the front end.
	 *
	 * @param	array<string,mixed>	$attributes Block attributes
	 * @return	string Rendered HTML
	 */
	public static function render( array $attributes ): string {
		$post_id = isset( $attributes['postId'] )
			? \absint( $attributes['postId'] )
			: 0;
		
		if ( $post_id === 0 ) {
			return '';
		}
		
		$post = \get_post( $post_id );
		
		if (
			$post === null
			|| $post->post_type !== Post_Types::POST_TYPE_PROJECT
			|| $post->post_status !== 'publish'
		) {
			return '';
		}
		
		$wrapper_class = 'wp-block-personal-profile-builder-project-embed';
		$wrapper_class .= ' ppb-project-embed';
		
		$wrapper_attributes = \get_block_wrapper_attributes(
			[ 'class' => $wrapper_class ]
		);
		
		return '<div ' . $wrapper_attributes . '>'
			. Project_Query::render_card( $post_id )
			. '</div>';
	}
}
