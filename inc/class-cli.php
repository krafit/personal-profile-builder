<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use WP_CLI;
use WP_CLI\Utils;

/**
 * WP-CLI commands.
 *
 * Registered only when `WP_CLI` is defined. Provides batch tooling
 * for the cross-subsite occurrence sync — useful after a database
 * restore, an import, or when many talks need a one-shot resync.
 *
 * Usage:
 *     wp ppb sync-occurrences <talk_id>
 *     wp ppb sync-occurrences <talk_id> --source=de_DE
 *     wp ppb sync-occurrences <talk_id> --merge
 *
 * @package	Personal_Profile_Builder
 */
final class CLI {
	/**
	 * Register the command(s) with WP-CLI.
	 *
	 * Called from {@see Plugin::init()} only when running under CLI.
	 */
	public static function register(): void {
		if ( ! \defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return;
		}
		
		WP_CLI::add_command( 'ppb sync-occurrences', [ self::class, 'sync_occurrences' ] );
	}
	
	/**
	 * Sync the occurrence list of a talk to its MSLS-linked translations.
	 *
	 * ## OPTIONS
	 *
	 * <talk_id>
	 * : The post ID of the talk on the current subsite to operate on.
	 *
	 * [--source=<locale>]
	 * : Pull the occurrence list from the sibling at this locale into
	 *   the source talk, then fan out. Mutually exclusive with --merge.
	 *
	 * [--merge]
	 * : Union all sibling lists by `(date, language)`, write the result
	 *   to the source, then fan out. Mutually exclusive with --source.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ppb sync-occurrences 42
	 *     wp ppb sync-occurrences 42 --source=de_DE
	 *     wp ppb sync-occurrences 42 --merge
	 *
	 * @param	array<int,string>	$args Positional CLI arguments
	 * @param	array<string,string>	$assoc_args Flag CLI arguments
	 */
	public static function sync_occurrences(
		array $args,
		array $assoc_args
	): void {
		if ( ! MSLS_Integration::is_available() ) {
			WP_CLI::error(
				'MSLS is not available on this site. Sync requires multisite + Multisite Language Switcher.'
			);
		}
		
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		
		if ( $post_id < 1 ) {
			WP_CLI::error( 'Missing or invalid <talk_id>.' );
		}
		
		$post = \get_post( $post_id );
		
		if (
			$post === null
			|| $post->post_type !== Post_Types::POST_TYPE_TALK
		) {
			WP_CLI::error(
				\sprintf(
					'Post %d is not a talk on this subsite.',
					$post_id
				)
			);
		}
		
		$source = (string) Utils\get_flag_value( $assoc_args, 'source', '' );
		$merge = (bool) Utils\get_flag_value( $assoc_args, 'merge', false );
		
		if ( $source !== '' && $merge ) {
			WP_CLI::error( '--source and --merge are mutually exclusive.' );
		}
		
		if ( $merge ) {
			$ok = Occurrence_Sync::merge_all( $post_id );
			
			if ( ! $ok ) {
				WP_CLI::error( 'Merge failed (MSLS unavailable or sync re-entrant).' );
			}
			
			WP_CLI::success( \sprintf( 'Merged occurrences for talk %d.', $post_id ) );
			
			return;
		}
		
		if ( $source !== '' ) {
			$ok = Occurrence_Sync::pull_from_sibling( $post_id, $source );
			
			if ( ! $ok ) {
				WP_CLI::error(
					\sprintf(
						'Pull from %s failed (no linked translation, or sync re-entrant).',
						$source
					)
				);
			}
			
			WP_CLI::success(
				\sprintf(
					'Pulled occurrences for talk %d from %s.',
					$post_id,
					$source
				)
			);
			
			return;
		}
		
		Occurrence_Sync::push_from_source( $post_id );
		
		WP_CLI::success(
			\sprintf( 'Pushed occurrences for talk %d to all linked translations.', $post_id )
		);
	}
}
