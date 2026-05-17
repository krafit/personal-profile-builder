<?php
/**
 * Default organiser bio box template.
 *
 * Theme override: copy this file to your active theme at the same path
 * (`templates/organiser-bio-box.php`) and customise as needed. The plugin
 * looks for the theme version first via `locate_template()` and falls
 * back to this file when none is found.
 *
 * The following variables are in scope:
 *
 * @var	string	$bio Speaker bio HTML, sanitised on save with wp_kses_post().
 * @var	int	$avatar_id Speaker avatar attachment ID, or 0 when unset.
 * @var	string	$avatar_url Direct URL to the avatar, or empty string when unset.
 *
 * @package	Personal_Profile_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<aside class="ppb-organiser-bio-box" aria-labelledby="ppb-organiser-bio-box-title">
	<h2 id="ppb-organiser-bio-box-title" class="ppb-organiser-bio-box__title">
		<?php esc_html_e( 'For event organisers', 'personal-profile-builder' ); ?>
	</h2>
	<div class="ppb-organiser-bio-box__inner">
		<?php if ( $avatar_id > 0 ) : ?>
			<figure class="ppb-organiser-bio-box__avatar">
				<?php
				echo wp_get_attachment_image(
					$avatar_id,
					'medium',
					false,
					[
						'class' => 'ppb-organiser-bio-box__avatar-img',
						'alt' => '',
					]
				);
				?>
				<?php if ( $avatar_url !== '' ) : ?>
					<figcaption class="ppb-organiser-bio-box__avatar-url">
						<label class="ppb-organiser-bio-box__avatar-url-label"
							for="ppb-organiser-bio-box-avatar-url">
							<?php esc_html_e( 'Avatar URL:', 'personal-profile-builder' ); ?>
						</label>
						<input type="text"
							id="ppb-organiser-bio-box-avatar-url"
							class="ppb-organiser-bio-box__avatar-url-input"
							value="<?php echo esc_attr( $avatar_url ); ?>"
							readonly
							data-ppb-bio-box-url />
						<button type="button"
							class="ppb-organiser-bio-box__copy-button"
							data-ppb-bio-box-copy
							hidden>
							<?php esc_html_e( 'Copy', 'personal-profile-builder' ); ?>
						</button>
					</figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>
		<div class="ppb-organiser-bio-box__body">
			<?php if ( $bio !== '' ) : ?>
				<div class="ppb-organiser-bio-box__bio">
					<?php echo wp_kses_post( $bio ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $bio === '' && $avatar_id === 0 ) : ?>
				<p class="ppb-organiser-bio-box__empty">
					<?php esc_html_e( 'No speaker profile is set up yet.', 'personal-profile-builder' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</aside>
