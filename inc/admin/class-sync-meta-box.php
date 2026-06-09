<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

use Personal_Profile_Builder\MSLS_Integration;
use Personal_Profile_Builder\Occurrence_Sync;
use Personal_Profile_Builder\Post_Types;
use Personal_Profile_Builder\Rest_Api;
use WP_Post;

/**
 * Reconciliation meta box for cross-subsite occurrence sync.
 *
 * Shows on the talk edit screen when MSLS is available and the
 * current talk has at least one linked translation. Surfaces
 * divergence between the source and its linked siblings and
 * offers three explicit resolution actions:
 *
 * - Push: overwrite every sibling with this talk's list
 * - Pull from {locale}: adopt the named sibling's list locally
 *   and re-fan-out
 * - Merge all: union all sibling lists by `(date, language)` and
 *   propagate the result everywhere
 *
 * The buttons drive the REST `sync-action` endpoint via fetch().
 * A nested HTML `<form>` would silently flatten inside the main
 * post-edit form, so we avoid that entirely.
 *
 * @package	Personal_Profile_Builder
 */
final class Sync_Meta_Box {
	/**
	 * Register hooks.
	 */
	public static function init(): void {
		\add_action( 'add_meta_boxes', [ self::class, 'register_meta_box' ] );
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}
	
	/**
	 * Register the meta box on talk edit screens.
	 */
	public static function register_meta_box(): void {
		if ( ! MSLS_Integration::is_available() ) {
			return;
		}
		
		\add_meta_box(
			'ppb_sync_status',
			\__( 'Occurrence sync status', 'personal-profile-builder' ),
			[ self::class, 'render' ],
			Post_Types::POST_TYPE_TALK,
			'side',
			'default'
		);
	}
	
	/**
	 * Enqueue the meta-box driver JS on talk edit screens.
	 *
	 * @param	string	$hook_suffix Current admin page hook suffix
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php' ) {
			return;
		}
		
		$screen = \get_current_screen();
		
		if (
			$screen === null
			|| $screen->post_type !== Post_Types::POST_TYPE_TALK
		) {
			return;
		}
		
		if ( ! MSLS_Integration::is_available() ) {
			return;
		}
		
		\wp_enqueue_script(
			'ppb-sync-meta-box',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/js/sync-meta-box.js',
			[ 'wp-api-fetch', 'wp-i18n' ],
			PERSONAL_PROFILE_BUILDER_VERSION,
			true
		);
		\wp_set_script_translations(
			'ppb-sync-meta-box',
			'personal-profile-builder',
			PERSONAL_PROFILE_BUILDER_DIR . '/languages'
		);
		\wp_localize_script(
			'ppb-sync-meta-box',
			'PPBSyncMetaBox',
			[
				'restNamespace' => Rest_Api::REST_NAMESPACE,
			]
		);
	}
	
	/**
	 * Render the meta box body.
	 *
	 * @param	WP_Post	$post Current post being edited
	 */
	public static function render( WP_Post $post ): void {
		$linked = MSLS_Integration::get_linked_post_ids( $post->ID );
		
		if ( $linked === [] ) {
			echo '<p>'
				. \esc_html__(
					'This talk has no linked translations yet. Once you link translations via the Multisite Language Switcher meta box, occurrence lists will sync automatically across them.',
					'personal-profile-builder'
				)
				. '</p>';
			
			return;
		}
		
		$source_json = (string) \get_post_meta(
			$post->ID,
			'_talk_occurrences',
			true
		);
		$source_count = self::count_rows( $source_json );
		$state = Occurrence_Sync::read_sibling_state( $post->ID );
		$has_divergence = false;
		
		echo '<div class="ppb-sync-status-wrap" data-ppb-sync-meta-box data-post-id="'
			. \esc_attr( (string) $post->ID ) . '">';
		echo '<table class="ppb-sync-status">';
		echo '<thead><tr>';
		echo '<th>' . \esc_html__( 'Language', 'personal-profile-builder' ) . '</th>';
		echo '<th>' . \esc_html__( 'Occurrences', 'personal-profile-builder' ) . '</th>';
		echo '<th>' . \esc_html__( 'Status', 'personal-profile-builder' ) . '</th>';
		echo '</tr></thead><tbody>';
		
		echo '<tr>';
		echo '<td><strong>' . \esc_html( MSLS_Integration::locale_name( MSLS_Integration::current_site_locale() ) ) . '</strong> ';
		echo '<span class="description">(' . \esc_html__( 'this talk', 'personal-profile-builder' ) . ')</span></td>';
		echo '<td>' . \esc_html( (string) $source_count ) . '</td>';
		echo '<td>—</td>';
		echo '</tr>';
		
		foreach ( $state as $locale => $entry ) {
			$sibling_json = (string) ( $entry['json'] ?? '' );
			$count = (int) ( $entry['count'] ?? 0 );
			$edit_url = (string) ( $entry['edit_url'] ?? '' );
			$status_label = '';
			$status_class = '';
			
			if ( $sibling_json === $source_json ) {
				$status_label = \__( 'In sync', 'personal-profile-builder' );
				$status_class = 'ppb-sync-status__pill--ok';
			}
			else if ( $sibling_json === '' ) {
				$status_label = \__( 'Sibling empty', 'personal-profile-builder' );
				$status_class = 'ppb-sync-status__pill--empty';
				$has_divergence = true;
			}
			else {
				$status_label = \__( 'Diverged', 'personal-profile-builder' );
				$status_class = 'ppb-sync-status__pill--warn';
				$has_divergence = true;
			}
			
			echo '<tr>';
			echo '<td>';
			
			if ( $edit_url !== '' ) {
				echo '<a href="' . \esc_url( $edit_url ) . '">'
					. \esc_html( MSLS_Integration::locale_name( $locale ) )
					. '</a>';
			}
			else {
				echo \esc_html( MSLS_Integration::locale_name( $locale ) );
			}
			
			echo '</td>';
			echo '<td>' . \esc_html( (string) $count ) . '</td>';
			echo '<td><span class="ppb-sync-status__pill ' . \esc_attr( $status_class ) . '">'
				. \esc_html( $status_label ) . '</span></td>';
			echo '</tr>';
		}
		
		echo '</tbody></table>';
		
		if ( ! $has_divergence ) {
			echo '<p class="ppb-sync-status__note">'
				. \esc_html__( 'All translations are in sync.', 'personal-profile-builder' )
				. '</p>';
			echo '</div>';
			
			return;
		}
		
		self::render_actions( $state );
		
		echo '</div>';
	}
	
	/**
	 * Render the reconciliation action buttons.
	 *
	 * No `<form>` — the meta box renders inside the main post-edit
	 * form, and HTML forbids nesting forms. Instead, each button
	 * carries `data-ppb-sync-action` (and Pull also takes the
	 * selected locale from a sibling `<select>`); the
	 * `sync-meta-box.js` driver dispatches REST calls and reloads
	 * the page on success.
	 *
	 * @param	array<string,array<string,mixed>>	$state Sibling state
	 */
	private static function render_actions( array $state ): void {
		echo '<div class="ppb-sync-status__actions">';
		
		echo '<p>';
		echo '<button type="button" class="button button-primary"'
			. ' data-ppb-sync-action="push">'
			. \esc_html__( 'Push to translations', 'personal-profile-builder' )
			. '</button>';
		echo '</p>';
		
		echo '<p><label>';
		echo \esc_html__( 'Pull from:', 'personal-profile-builder' ) . ' ';
		echo '<select data-ppb-sync-source>';
		
		foreach ( $state as $locale => $entry ) {
			unset( $entry );
			echo '<option value="' . \esc_attr( $locale ) . '">'
				. \esc_html( MSLS_Integration::locale_name( $locale ) )
				. '</option>';
		}
		
		echo '</select>';
		echo '</label> ';
		echo '<button type="button" class="button"'
			. ' data-ppb-sync-action="pull">'
			. \esc_html__( 'Pull', 'personal-profile-builder' )
			. '</button>';
		echo '</p>';
		
		echo '<p>';
		echo '<button type="button" class="button"'
			. ' data-ppb-sync-action="merge">'
			. \esc_html__( 'Merge all', 'personal-profile-builder' )
			. '</button>';
		echo '</p>';
		
		echo '<p class="description">'
			. \esc_html__( 'Push overwrites all siblings with this talk\'s list. Pull replaces this talk\'s list with the selected sibling\'s, then propagates. Merge unions all lists by date + language.', 'personal-profile-builder' )
			. '</p>';
		
		echo '<p class="ppb-sync-status__feedback" data-ppb-sync-feedback hidden></p>';
		
		echo '</div>';
	}
	
	/**
	 * Count occurrence rows in a JSON-encoded list.
	 *
	 * @param	string	$json Raw JSON string from `_talk_occurrences`
	 * @return	int Number of rows
	 */
	private static function count_rows( string $json ): int {
		if ( $json === '' ) {
			return 0;
		}
		
		$decoded = \json_decode( $json, true );
		
		if ( ! \is_array( $decoded ) ) {
			return 0;
		}
		
		return \count( $decoded );
	}
}
