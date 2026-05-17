<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use Personal_Profile_Builder\Admin\Settings;

/**
 * Organiser view subsystem.
 *
 * When a talk single is viewed with `?view=organiser` appended, an
 * info box is appended to the post content. The box contains the
 * speaker bio, avatar, and the avatar's direct download URL ready
 * for the organiser to copy. The box is rendered from a theme-
 * overridable template at `templates/organiser-bio-box.php`.
 *
 * @package	Personal_Profile_Builder
 */
final class Organiser_View {
	/**
	 * @var	string Query var that activates the organiser view.
	 */
	public const QUERY_VAR = 'view';
	
	/**
	 * @var	string Value the query var must equal to activate the view.
	 */
	public const QUERY_VALUE = 'organiser';
	
	/**
	 * @var	string Relative template path resolved against theme then plugin.
	 */
	private const TEMPLATE = 'templates/organiser-bio-box.php';
	
	/**
	 * Register hooks.
	 */
	public static function register(): void {
		\add_filter( 'query_vars', [ self::class, 'add_query_var' ] );
		\add_filter( 'the_content', [ self::class, 'append_bio_box' ], 20 );
		\add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}
	
	/**
	 * Register the public query var.
	 *
	 * @param	array<int,string>	$vars Existing query vars
	 * @return	array<int,string> Query vars including `view`
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		
		return $vars;
	}
	
	/**
	 * Whether the organiser view is currently active.
	 *
	 * Active when:
	 * - we are on the front-end (not admin or feed),
	 * - the main query is a talk single,
	 * - and the `view` query var equals `organiser`.
	 *
	 * @return	bool Whether the bio box should render
	 */
	public static function is_active(): bool {
		if ( \is_admin() || \is_feed() ) {
			return false;
		}
		
		if ( ! \is_singular( Post_Types::POST_TYPE_TALK ) ) {
			return false;
		}
		
		$value = \get_query_var( self::QUERY_VAR, '' );
		
		return \is_string( $value ) && $value === self::QUERY_VALUE;
	}
	
	/**
	 * Append the organiser bio box to the post content.
	 *
	 * Gated on `is_active()` plus loop checks so the box is appended
	 * only to the singular post in the main loop, never to sub-loops
	 * or sidebar widgets that might also render talk content.
	 *
	 * @param	string	$content The current post content
	 * @return	string Possibly augmented content
	 */
	public static function append_bio_box( string $content ): string {
		if ( ! self::is_active() ) {
			return $content;
		}
		
		if ( ! \in_the_loop() || ! \is_main_query() ) {
			return $content;
		}
		
		return $content . self::render_bio_box();
	}
	
	/**
	 * Render the bio box markup.
	 *
	 * Resolves the template against the active theme first, falling
	 * back to the plugin's default. The template receives `$bio`,
	 * `$avatar_id`, and `$avatar_url` in scope.
	 *
	 * @return	string Rendered HTML, or empty on failure
	 */
	public static function render_bio_box(): string {
		$bio = (string) \get_option( Settings::OPTION_BIO, '' );
		$avatar_id = (int) \get_option( Settings::OPTION_AVATAR_ID, 0 );
		$avatar_url = Settings::get_avatar_url( $avatar_id );
		$template = self::locate_template_file();
		
		if ( $template === '' || ! \is_readable( $template ) ) {
			return '';
		}
		
		\ob_start();
		include $template;
		
		return (string) \ob_get_clean();
	}
	
	/**
	 * Locate the template file: theme override first, plugin default second.
	 *
	 * @return	string Absolute path or empty string when neither exists
	 */
	private static function locate_template_file(): string {
		$theme_template = \locate_template( [ self::TEMPLATE ] );
		
		if ( \is_string( $theme_template ) && $theme_template !== '' ) {
			return $theme_template;
		}
		
		$plugin_template = PERSONAL_PROFILE_BUILDER_DIR . '/' . self::TEMPLATE;
		
		return \file_exists( $plugin_template ) ? $plugin_template : '';
	}
	
	/**
	 * Enqueue the front-end stylesheet and copy-button script.
	 *
	 * Only loads on talk singles when the organiser view is active.
	 * Themes that override the template can still benefit from the
	 * default styles, or `wp_dequeue_style( 'ppb-frontend' )` to opt out.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_active() ) {
			return;
		}
		
		\wp_enqueue_style(
			'ppb-frontend',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/css/frontend.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
		\wp_enqueue_script(
			'ppb-organiser-bio-box',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/js/organiser-bio-box.js',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION,
			true
		);
		\wp_localize_script(
			'ppb-organiser-bio-box',
			'ppbBioBoxL10n',
			[
				'copied' => \__( 'Copied!', 'personal-profile-builder' ),
			]
		);
	}
}
