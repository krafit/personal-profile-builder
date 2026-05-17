<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

use Personal_Profile_Builder\Post_Types;

/**
 * Talk editor sidebar.
 *
 * Enqueues the sidebar plugin script that adds Talk Details and
 * Talk Status panels to the block editor document sidebar.
 *
 * Meta saving is handled by the REST API through the registered
 * meta keys (see Meta class). No manual `save_post` hook is needed.
 *
 * @package	Personal_Profile_Builder
 */
final class Talk_Meta_Boxes {
	/**
	 * Register hooks.
	 */
	public static function init(): void {
		\add_action(
			'enqueue_block_editor_assets',
			[ self::class, 'enqueue_sidebar' ]
		);
	}
	
	/**
	 * Enqueue the talk sidebar plugin script on talk edit screens.
	 */
	public static function enqueue_sidebar(): void {
		$screen = \get_current_screen();
		
		if (
			$screen === null
			|| $screen->post_type !== Post_Types::POST_TYPE_TALK
		) {
			return;
		}
		
		\wp_enqueue_script(
			'ppb-talk-sidebar',
			PERSONAL_PROFILE_BUILDER_URL
				. 'assets/js/talk-sidebar.js',
			[
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
			],
			PERSONAL_PROFILE_BUILDER_VERSION,
			true
		);
		\wp_set_script_translations(
			'ppb-talk-sidebar',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
	}
}
