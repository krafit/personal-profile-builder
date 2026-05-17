<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use Personal_Profile_Builder\Admin\List_Table_Filters;
use Personal_Profile_Builder\Admin\Occurrences_UI;
use Personal_Profile_Builder\Admin\Project_Meta_Boxes;
use Personal_Profile_Builder\Admin\Settings;
use Personal_Profile_Builder\Admin\Talk_Meta_Boxes;
use Personal_Profile_Builder\Blocks\Project_Embed;
use Personal_Profile_Builder\Blocks\Project_Query;
use Personal_Profile_Builder\Blocks\Talk_Embed;
use Personal_Profile_Builder\Blocks\Talk_Query;

/**
 * Main plugin class.
 *
 * Bootstraps the plugin and wires up all subcomponents.
 *
 * @package	Personal_Profile_Builder
 */
final class Plugin {
	/**
	 * @var	self|null Unique instance of the class.
	 */
	private static ?self $instance = null;
	
	/**
	 * @var	string Full path to the main plugin file.
	 */
	public string $plugin_file = '';
	
	/**
	 * Get the single instance of the class.
	 *
	 * @return	self The plugin instance
	 */
	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * @var	string Option name that stores the last-seen plugin version.
	 */
	private const VERSION_OPTION = 'ppb_version';
	
	/**
	 * Initialise the plugin and register hooks.
	 */
	public function init(): void {
		\add_action( 'init', [ $this, 'load_textdomain' ] );
		\add_action( 'init', [ Post_Types::class, 'register' ], 5 );
		\add_action( 'init', [ Meta::class, 'register' ], 6 );
		\add_action( 'init', [ Talk_Query::class, 'register' ] );
		\add_action( 'init', [ Project_Query::class, 'register' ] );
		\add_action( 'init', [ Talk_Embed::class, 'register' ] );
		\add_action(
			'init',
			[ Project_Embed::class, 'register' ]
		);
		\add_action( 'wp_loaded', [ $this, 'maybe_flush_rewrites' ], 9999 );
		\add_filter(
			'block_categories_all',
			[ $this, 'register_block_category' ],
			10,
			2
		);
		\add_action(
			'wp_enqueue_scripts',
			[ $this, 'enqueue_block_styles' ]
		);
		\add_action(
			'enqueue_block_editor_assets',
			[ $this, 'enqueue_editor_block_styles' ]
		);
		
		Occurrences::register();
		Organiser_View::register();
		Rest_Api::register();
		
		if ( \is_admin() ) {
			Settings::init();
			Talk_Meta_Boxes::init();
			Project_Meta_Boxes::init();
			Occurrences_UI::init();
			List_Table_Filters::init();
		}
	}
	
	/**
	 * Activation handler: register CPTs/rules and flush rewrite rules.
	 *
	 * Called from the main plugin file via `register_activation_hook`.
	 */
	public static function on_activation(): void {
		Post_Types::register();
		Occurrences::register_rewrite_rule();
		\flush_rewrite_rules();
		\update_option( self::VERSION_OPTION, PERSONAL_PROFILE_BUILDER_VERSION );
	}
	
	/**
	 * Deactivation handler: flush rewrite rules so our pattern is dropped.
	 *
	 * Called from the main plugin file via `register_deactivation_hook`.
	 */
	public static function on_deactivation(): void {
		\flush_rewrite_rules();
	}
	
	/**
	 * Detect plugin upgrades and soft-flush rewrite rules when the
	 * stored version differs from the running version.
	 *
	 * Skipped during AJAX, cron, and REST requests so an upgrade only
	 * triggers a flush on the next regular page view.
	 */
	public function maybe_flush_rewrites(): void {
		if ( \wp_doing_ajax() || \wp_doing_cron() ) {
			return;
		}
		
		if ( \defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		
		$stored = (string) \get_option( self::VERSION_OPTION, '' );
		
		if ( $stored === PERSONAL_PROFILE_BUILDER_VERSION ) {
			return;
		}
		
		\flush_rewrite_rules( false );
		\update_option( self::VERSION_OPTION, PERSONAL_PROFILE_BUILDER_VERSION );
	}
	
	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		\load_plugin_textdomain(
			'personal-profile-builder',
			false,
			\dirname( \plugin_basename( $this->plugin_file ) ) . '/languages'
		);
	}
	
	/**
	 * Register the plugin's block category.
	 *
	 * @param	array<int,array<string,string>>	$categories Existing block categories
	 * @param	mixed	$context Block editor context
	 * @return	array<int,array<string,string>> Updated categories
	 */
	public function register_block_category(
		array $categories,
		$context
	): array {
		unset( $context );
		
		\array_unshift( $categories, [
			'slug' => 'personal-profile-builder',
			'title' => \__( 'Personal Profile', 'personal-profile-builder' ),
			'icon' => null,
		] );
		
		return $categories;
	}
	
	/**
	 * Enqueue shared block styles on the front end.
	 *
	 * Only enqueues when at least one of the plugin's blocks is
	 * present in the current content. Falls back to always enqueuing
	 * because `has_block()` only checks the current post — the blocks
	 * can be placed in widget areas or templates.
	 */
	public function enqueue_block_styles(): void {
		\wp_enqueue_style(
			'ppb-blocks',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/css/blocks.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
	}
	
	/**
	 * Enqueue block styles in the editor so ServerSideRender
	 * previews match the front-end appearance.
	 */
	public function enqueue_editor_block_styles(): void {
		\wp_enqueue_style(
			'ppb-blocks',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/css/blocks.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
	}
	
	/**
	 * Get the plugin version.
	 *
	 * @return	string Current plugin version
	 */
	public function get_version(): string {
		return PERSONAL_PROFILE_BUILDER_VERSION;
	}
}
