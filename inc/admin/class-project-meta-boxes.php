<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

use Personal_Profile_Builder\Post_Types;

/**
 * Project editor sidebar.
 *
 * Enqueues the sidebar plugin script that adds the Project Details
 * panel to the block editor document sidebar.
 *
 * Meta saving is handled by the REST API through the registered
 * meta keys (see Meta class). No manual `save_post` hook is needed.
 *
 * @package	Personal_Profile_Builder
 */
final class Project_Meta_Boxes {
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
	 * Enqueue the project sidebar plugin script on project screens.
	 */
	public static function enqueue_sidebar(): void {
		$screen = \get_current_screen();
		
		if (
			$screen === null
			|| $screen->post_type !== Post_Types::POST_TYPE_PROJECT
		) {
			return;
		}
		
		\wp_enqueue_script(
			'ppb-project-sidebar',
			PERSONAL_PROFILE_BUILDER_URL
				. 'assets/js/project-sidebar.js',
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
			'ppb-project-sidebar',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
	}
}
