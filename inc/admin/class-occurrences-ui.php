<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

use Personal_Profile_Builder\Meta;
use Personal_Profile_Builder\Post_Types;
use WP_Post;

/**
 * Occurrences UI.
 *
 * Renders the "Occurrences" meta box on the talk edit screen. The
 * meta box keeps all state in the DOM rows themselves; the JS
 * serialises the rows to JSON in a hidden field on every change.
 * This avoids index-shift issues when adding or removing rows and
 * keeps the data flow simple.
 *
 * @package	Personal_Profile_Builder
 */
final class Occurrences_UI {
	/**
	 * @var	string Nonce action used for occurrence submissions.
	 */
	private const NONCE_ACTION = 'ppb_occurrences_meta_box';
	
	/**
	 * @var	string Nonce field name.
	 */
	private const NONCE_NAME = 'ppb_occurrences_meta_box_nonce';
	
	/**
	 * @var	string Hidden field name carrying the canonical JSON.
	 */
	private const FIELD_NAME = 'ppb_occurrences_json';
	
	/**
	 * Register hooks.
	 */
	public static function init(): void {
		\add_action( 'add_meta_boxes_' . Post_Types::POST_TYPE_TALK, [ self::class, 'register_meta_box' ] );
		\add_action( 'save_post_' . Post_Types::POST_TYPE_TALK, [ self::class, 'save' ], 10, 2 );
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}
	
	/**
	 * Register the occurrences meta box.
	 */
	public static function register_meta_box(): void {
		\add_meta_box(
			'ppb_talk_occurrences',
			\__( 'Occurrences', 'personal-profile-builder' ),
			[ self::class, 'render' ],
			Post_Types::POST_TYPE_TALK,
			'normal',
			'default'
		);
	}
	
	/**
	 * Enqueue the JS and CSS used by the occurrences UI.
	 *
	 * Only loaded on the talk edit screen, identified by the
	 * post type query var on the screen object.
	 *
	 * @param	string	$hook_suffix Current admin page hook suffix
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php' ) {
			return;
		}
		
		$screen = \get_current_screen();
		
		if ( $screen === null || $screen->post_type !== Post_Types::POST_TYPE_TALK ) {
			return;
		}
		
		\wp_enqueue_media();
		\wp_enqueue_style(
			'ppb-admin',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/css/admin.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
		\wp_enqueue_script(
			'ppb-occurrences',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/js/occurrences.js',
			[ 'jquery' ],
			PERSONAL_PROFILE_BUILDER_VERSION,
			true
		);
		\wp_localize_script(
			'ppb-occurrences',
			'ppbOccurrencesL10n',
			[
				'copy' => \__( 'Copy URL', 'personal-profile-builder' ),
				'copied' => \__( 'Copied!', 'personal-profile-builder' ),
				'remove' => \__( 'Remove', 'personal-profile-builder' ),
				'urlPlaceholder' => \__(
					'URL appears once a valid date is set.',
					'personal-profile-builder'
				),
				'selectSlides' => \__(
					'Select slides file',
					'personal-profile-builder'
				),
				'useFile' => \__(
					'Use this file',
					'personal-profile-builder'
				),
			]
		);
	}
	
	/**
	 * Render the occurrences meta box.
	 *
	 * @param	WP_Post	$post Current post object
	 */
	public static function render( WP_Post $post ): void {
		\wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		
		$raw_json = (string) \get_post_meta( $post->ID, '_talk_occurrences', true );
		$permalink = (string) \get_permalink( $post );
		$base_url = $permalink !== '' ? \trailingslashit( $permalink ) : '';
		
		?>
		<div class="ppb-occurrences"
			data-ppb-occurrences
			data-ppb-base-url="<?php echo \esc_attr( $base_url ); ?>">
			<input type="hidden"
				name="<?php echo \esc_attr( self::FIELD_NAME ); ?>"
				data-ppb-occurrences-field
				value="<?php echo \esc_attr( $raw_json ); ?>" />
			<p class="description">
				<?php echo \esc_html__( 'Each occurrence becomes a shareable URL of the form /talk/<slug>/YYYYMMDD.', 'personal-profile-builder' ); ?>
			</p>
			<div class="ppb-occurrences__rows" data-ppb-occurrences-rows></div>
			<p class="ppb-occurrences__actions">
				<button type="button"
					class="button button-secondary"
					data-ppb-occurrences-add>
					<?php echo \esc_html__( 'Add occurrence', 'personal-profile-builder' ); ?>
				</button>
			</p>
			<template data-ppb-occurrences-template>
				<?php self::render_row_template(); ?>
			</template>
		</div>
		<?php
	}
	
	/**
	 * Render the markup used as a row template.
	 *
	 * Lives inside a `<template>` element so it doesn't render as part
	 * of the form. The JS clones it for each new row.
	 */
	private static function render_row_template(): void {
		?>
		<div class="ppb-occurrence" data-ppb-occurrence>
			<div class="ppb-occurrence__grid">
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Date', 'personal-profile-builder' ); ?>
					</span>
					<input type="date"
						class="ppb-occurrence__date"
						data-ppb-field="date" />
				</label>
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Event name', 'personal-profile-builder' ); ?>
					</span>
					<input type="text"
						class="widefat"
						data-ppb-field="event_name" />
				</label>
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Location', 'personal-profile-builder' ); ?>
					</span>
					<input type="text"
						class="widefat"
						data-ppb-field="location" />
				</label>
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Event URL', 'personal-profile-builder' ); ?>
					</span>
					<input type="url"
						class="widefat"
						data-ppb-field="event_url"
						placeholder="https://" />
				</label>
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Slides URL', 'personal-profile-builder' ); ?>
					</span>
					<span class="ppb-field__row">
						<input type="url"
							class="widefat"
							data-ppb-field="slides_url"
							placeholder="https://" />
						<button type="button"
							class="button button-small ppb-occurrence__upload"
							data-ppb-upload-slides>
							<?php echo \esc_html__( 'Upload', 'personal-profile-builder' ); ?>
						</button>
					</span>
				</label>
				<label class="ppb-field">
					<span class="ppb-field__label">
						<?php echo \esc_html__( 'Recording URL', 'personal-profile-builder' ); ?>
					</span>
					<input type="url"
						class="widefat"
						data-ppb-field="recording_url"
						placeholder="https://" />
				</label>
			</div>
			<div class="ppb-occurrence__url">
				<span class="ppb-occurrence__url-label">
					<?php echo \esc_html__( 'Shareable URL:', 'personal-profile-builder' ); ?>
				</span>
				<code class="ppb-occurrence__url-display" data-ppb-url-display>
					<?php echo \esc_html__( 'URL appears once a valid date is set.', 'personal-profile-builder' ); ?>
				</code>
				<button type="button"
					class="button button-small ppb-occurrence__copy"
					data-ppb-copy
					hidden>
					<?php echo \esc_html__( 'Copy URL', 'personal-profile-builder' ); ?>
				</button>
			</div>
			<div class="ppb-occurrence__actions">
				<button type="button"
					class="button-link button-link-delete"
					data-ppb-remove>
					<?php echo \esc_html__( 'Remove', 'personal-profile-builder' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Save the occurrences JSON on post save.
	 *
	 * The registered `_talk_occurrences` sanitiser canonicalises the
	 * payload (drops malformed rows, ensures the JSON shape), so this
	 * method just hands the raw value to `update_post_meta` after the
	 * usual capability and nonce checks.
	 *
	 * @param	int	$post_id Post being saved
	 * @param	WP_Post	$post Post object
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! self::should_save( $post_id, $post ) ) {
			return;
		}
		
		$raw = isset( $_POST[ self::FIELD_NAME ] )
			? \wp_unslash( (string) $_POST[ self::FIELD_NAME ] )
			: '';
		$sanitised = Meta::sanitize_occurrences_json( $raw );
		
		\update_post_meta( $post_id, '_talk_occurrences', $sanitised );
		
		unset( $post );
	}
	
	/**
	 * Decide whether the current request should write the occurrences.
	 *
	 * @param	int	$post_id Post being saved
	 * @param	WP_Post	$post Post object
	 * @return	bool Whether to proceed
	 */
	private static function should_save( int $post_id, WP_Post $post ): bool {
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return false;
		}
		
		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		
		if ( $post->post_type !== Post_Types::POST_TYPE_TALK ) {
			return false;
		}
		
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return false;
		}
		
		$nonce = \sanitize_text_field( \wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		
		if ( ! \wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}
		
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		
		unset( $post );
		
		return true;
	}
}
