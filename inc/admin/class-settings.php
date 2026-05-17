<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

/**
 * Speaker profile settings page.
 *
 * Provides the Settings → Speaker Profile screen where the speaker's bio
 * and avatar attachment ID are stored. The avatar URL is derived from
 * the attachment at read time and surfaced in the UI for easy copying.
 *
 * @package	Personal_Profile_Builder
 */
final class Settings {
	/**
	 * @var	string Option name for the speaker bio (HTML).
	 */
	public const OPTION_BIO = 'ppb_speaker_bio';
	
	/**
	 * @var	string Option name for the avatar attachment ID.
	 */
	public const OPTION_AVATAR_ID = 'ppb_speaker_avatar_id';
	
	/**
	 * @var	string Option group used by the Settings API.
	 */
	private const OPTION_GROUP = 'ppb_speaker_profile';
	
	/**
	 * @var	string Slug of the settings page.
	 */
	private const PAGE_SLUG = 'ppb-speaker-profile';
	
	/**
	 * Register hooks for the settings page.
	 */
	public static function init(): void {
		\add_action( 'admin_init', [ self::class, 'register_settings' ] );
		\add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}
	
	/**
	 * Register the options with the Settings API.
	 */
	public static function register_settings(): void {
		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_BIO,
			[
				'type' => 'string',
				'description' => \__( 'Speaker bio (HTML allowed).', 'personal-profile-builder' ),
				'default' => '',
				'sanitize_callback' => [ self::class, 'sanitize_bio' ],
				'show_in_rest' => false,
			]
		);
		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_AVATAR_ID,
			[
				'type' => 'integer',
				'description' => \__( 'Attachment ID of the speaker avatar.', 'personal-profile-builder' ),
				'default' => 0,
				'sanitize_callback' => [ self::class, 'sanitize_avatar_id' ],
				'show_in_rest' => false,
			]
		);
	}
	
	/**
	 * Add the settings page under the Settings menu.
	 */
	public static function register_menu(): void {
		\add_options_page(
			\__( 'Speaker Profile', 'personal-profile-builder' ),
			\__( 'Speaker Profile', 'personal-profile-builder' ),
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}
	
	/**
	 * Enqueue the scripts and styles needed for the media uploader.
	 *
	 * @param	string	$hook_suffix Current admin page hook suffix
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'settings_page_' . self::PAGE_SLUG ) {
			return;
		}
		
		\wp_enqueue_media();
		\wp_enqueue_editor();
	}
	
	/**
	 * Render the settings page HTML.
	 */
	public static function render_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$bio = (string) \get_option( self::OPTION_BIO, '' );
		$avatar_id = (int) \get_option( self::OPTION_AVATAR_ID, 0 );
		$avatar_url = self::get_avatar_url( $avatar_id );
		
		?>
		<div class="wrap">
			<h1><?php echo \esc_html__( 'Speaker Profile', 'personal-profile-builder' ); ?></h1>
			<p class="description">
				<?php echo \esc_html__( 'These details power the organiser-facing info box that appears when ?view=organiser is appended to a talk URL.', 'personal-profile-builder' ); ?>
			</p>
			<form method="post" action="<?php echo \esc_url( \admin_url( 'options.php' ) ); ?>">
				<?php \settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="<?php echo \esc_attr( self::OPTION_BIO ); ?>">
									<?php echo \esc_html__( 'Speaker bio', 'personal-profile-builder' ); ?>
								</label>
							</th>
							<td>
								<?php
								\wp_editor(
									$bio,
									self::OPTION_BIO,
									[
										'textarea_name' => self::OPTION_BIO,
										'textarea_rows' => 10,
										'media_buttons' => false,
										'teeny' => false,
									]
								);
								?>
								<p class="description">
									<?php echo \esc_html__( 'Short biography shown alongside your avatar to event organisers.', 'personal-profile-builder' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo \esc_html__( 'Speaker avatar', 'personal-profile-builder' ); ?>
							</th>
							<td>
								<input type="hidden"
									id="<?php echo \esc_attr( self::OPTION_AVATAR_ID ); ?>"
									name="<?php echo \esc_attr( self::OPTION_AVATAR_ID ); ?>"
									value="<?php echo \esc_attr( (string) $avatar_id ); ?>" />
								<div id="ppb-avatar-preview" style="margin-bottom: 8px;">
									<?php if ( $avatar_url !== '' ) : ?>
										<img src="<?php echo \esc_url( $avatar_url ); ?>"
											alt=""
											style="max-width: 160px; height: auto; display: block; margin-bottom: 8px;" />
									<?php endif; ?>
								</div>
								<button type="button" class="button" id="ppb-avatar-select">
									<?php echo \esc_html__( 'Select avatar', 'personal-profile-builder' ); ?>
								</button>
								<button type="button" class="button" id="ppb-avatar-remove" <?php \disabled( $avatar_id, 0 ); ?>>
									<?php echo \esc_html__( 'Remove avatar', 'personal-profile-builder' ); ?>
								</button>
								<p class="description" style="margin-top: 12px;">
									<label for="ppb-avatar-url" style="display: block; font-weight: 600;">
										<?php echo \esc_html__( 'Direct avatar URL', 'personal-profile-builder' ); ?>
									</label>
									<input type="text"
										id="ppb-avatar-url"
										readonly
										value="<?php echo \esc_attr( $avatar_url ); ?>"
										class="regular-text code"
										style="width: 100%; max-width: 480px;" />
									<button type="button" class="button button-small" id="ppb-avatar-copy">
										<?php echo \esc_html__( 'Copy URL', 'personal-profile-builder' ); ?>
									</button>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php \submit_button(); ?>
			</form>
		</div>
		<script>
		( function() {
			var $select = document.getElementById( 'ppb-avatar-select' );
			var $remove = document.getElementById( 'ppb-avatar-remove' );
			var $copy = document.getElementById( 'ppb-avatar-copy' );
			var $idInput = document.getElementById( '<?php echo \esc_js( self::OPTION_AVATAR_ID ); ?>' );
			var $urlInput = document.getElementById( 'ppb-avatar-url' );
			var $preview = document.getElementById( 'ppb-avatar-preview' );
			var frame = null;
			
			if ( $select ) {
				$select.addEventListener( 'click', function( event ) {
					event.preventDefault();
					
					if ( frame ) {
						frame.open();
						return;
					}
					
					frame = wp.media({
						title: '<?php echo \esc_js( \__( 'Select speaker avatar', 'personal-profile-builder' ) ); ?>',
						button: { text: '<?php echo \esc_js( \__( 'Use this image', 'personal-profile-builder' ) ); ?>' },
						library: { type: 'image' },
						multiple: false
					});
					
					frame.on( 'select', function() {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						
						$idInput.value = attachment.id;
						$urlInput.value = attachment.url;
						$preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="max-width: 160px; height: auto; display: block; margin-bottom: 8px;" />';
						$remove.disabled = false;
					});
					
					frame.open();
				});
			}
			
			if ( $remove ) {
				$remove.addEventListener( 'click', function( event ) {
					event.preventDefault();
					$idInput.value = '0';
					$urlInput.value = '';
					$preview.innerHTML = '';
					$remove.disabled = true;
				});
			}
			
			if ( $copy ) {
				$copy.addEventListener( 'click', function( event ) {
					event.preventDefault();
					
					if ( ! $urlInput.value ) {
						return;
					}
					
					$urlInput.select();
					
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( $urlInput.value );
					}
					else {
						document.execCommand( 'copy' );
					}
				});
			}
		} )();
		</script>
		<?php
	}
	
	/**
	 * Sanitise the bio value, allowing a safe subset of HTML.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	string Sanitised HTML
	 */
	public static function sanitize_bio( $value ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}
		
		return \wp_kses_post( $value );
	}
	
	/**
	 * Sanitise the avatar attachment ID.
	 *
	 * @param	mixed	$value The raw input value
	 * @return	int Attachment ID, or 0 if invalid
	 */
	public static function sanitize_avatar_id( $value ): int {
		$id = (int) $value;
		
		if ( $id < 1 ) {
			return 0;
		}
		
		if ( \get_post_type( $id ) !== 'attachment' ) {
			return 0;
		}
		
		return $id;
	}
	
	/**
	 * Get the avatar URL for a given attachment ID.
	 *
	 * @param	int	$attachment_id Attachment ID
	 * @return	string The full-size avatar URL, or empty string
	 */
	public static function get_avatar_url( int $attachment_id ): string {
		if ( $attachment_id < 1 ) {
			return '';
		}
		
		$url = \wp_get_attachment_url( $attachment_id );
		
		return \is_string( $url ) ? $url : '';
	}
}
