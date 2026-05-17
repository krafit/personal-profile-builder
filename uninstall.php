<?php
declare(strict_types=1);

/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes
 * plugin-owned options so no orphaned settings remain. Post type
 * content (talks, projects) is intentionally left in place so that
 * uninstalling the plugin doesn't destroy the user's data — they
 * remain as `talk` / `project` posts in the database and can be
 * restored by reinstalling the plugin.
 *
 * @package	Personal_Profile_Builder
 */

if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

\delete_option( 'ppb_speaker_bio' );
\delete_option( 'ppb_speaker_avatar_id' );
\delete_option( 'ppb_version' );
