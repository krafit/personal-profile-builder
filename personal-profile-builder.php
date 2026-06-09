<?php
declare(strict_types=1);

/**
 * Plugin Name:	Personal Profile Builder
 * Plugin URI:	https://simon.blog
 * Description:	Manage talks given at conferences and meetups, plus personal projects, with shareable per-event URLs and an organiser-facing speaker info view.
 * Version:	1.7.0
 * Requires at least:	6.4
 * Requires PHP:	8.1
 * Author:	Simon Kraft
 * Author URI:	https://simon.blog
 * License:	GPL-2.0-or-later
 * License URI:	https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:	personal-profile-builder
 * Domain Path:	/languages
 *
 * @package	Personal_Profile_Builder
 */

namespace Personal_Profile_Builder;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

\define( 'PERSONAL_PROFILE_BUILDER_VERSION', '1.7.0' );
\define( 'PERSONAL_PROFILE_BUILDER_FILE', __FILE__ );
\define( 'PERSONAL_PROFILE_BUILDER_DIR', __DIR__ );
\define( 'PERSONAL_PROFILE_BUILDER_URL', \plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/inc/class-autoloader.php';

Autoloader::register();

\register_activation_hook( __FILE__, [ Plugin::class, 'on_activation' ] );
\register_deactivation_hook( __FILE__, [ Plugin::class, 'on_deactivation' ] );

CLI::register();

$plugin = Plugin::get_instance();
$plugin->plugin_file = __FILE__;
$plugin->init();
