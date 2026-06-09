<?php
declare(strict_types=1);

namespace Personal_Profile_Builder\Admin;

use Personal_Profile_Builder\Meta;
use Personal_Profile_Builder\MSLS_Integration;
use Personal_Profile_Builder\Post_Types;
use WP_Query;

/**
 * Talks list table filters and columns.
 *
 * Adds a status filter dropdown (Available / Retired / All), three
 * custom columns (status badge, occurrence count, next upcoming date),
 * and applies the status filter to the main admin query.
 *
 * The topic taxonomy filter is rendered automatically by core for
 * hierarchical taxonomies registered with `show_ui => true`, so it is
 * intentionally not added here.
 *
 * @package	Personal_Profile_Builder
 */
final class List_Table_Filters {
	/**
	 * @var	string Query var used for the status filter.
	 */
	private const STATUS_QUERY_VAR = 'ppb_status';
	
	/**
	 * Register hooks.
	 */
	public static function init(): void {
		\add_action( 'restrict_manage_posts', [ self::class, 'render_status_filter' ] );
		\add_action( 'pre_get_posts', [ self::class, 'apply_status_filter' ] );
		\add_filter(
			'manage_' . Post_Types::POST_TYPE_TALK . '_posts_columns',
			[ self::class, 'register_columns' ]
		);
		\add_action(
			'manage_' . Post_Types::POST_TYPE_TALK . '_posts_custom_column',
			[ self::class, 'render_column' ],
			10,
			2
		);
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}
	
	/**
	 * Enqueue the admin stylesheet on the talks list table.
	 *
	 * The same stylesheet is loaded by the occurrences UI on edit
	 * screens; the duplicate enqueue is harmless because WordPress
	 * deduplicates by handle.
	 *
	 * @param	string	$hook_suffix Current admin page hook suffix
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'edit.php' ) {
			return;
		}
		
		$screen = \get_current_screen();
		
		if ( $screen === null || $screen->post_type !== Post_Types::POST_TYPE_TALK ) {
			return;
		}
		
		\wp_enqueue_style(
			'ppb-admin',
			PERSONAL_PROFILE_BUILDER_URL . 'assets/css/admin.css',
			[],
			PERSONAL_PROFILE_BUILDER_VERSION
		);
	}
	
	/**
	 * Render the status filter dropdown above the talks list table.
	 *
	 * @param	string	$post_type Current post type slug
	 */
	public static function render_status_filter( string $post_type ): void {
		if ( $post_type !== Post_Types::POST_TYPE_TALK ) {
			return;
		}
		
		$current = isset( $_GET[ self::STATUS_QUERY_VAR ] )
			? \sanitize_key( \wp_unslash( (string) $_GET[ self::STATUS_QUERY_VAR ] ) )
			: '';
		
		?>
		<label class="screen-reader-text" for="<?php echo \esc_attr( self::STATUS_QUERY_VAR ); ?>">
			<?php echo \esc_html__( 'Filter by talk status', 'personal-profile-builder' ); ?>
		</label>
		<select name="<?php echo \esc_attr( self::STATUS_QUERY_VAR ); ?>"
			id="<?php echo \esc_attr( self::STATUS_QUERY_VAR ); ?>">
			<option value=""><?php echo \esc_html__( 'All statuses', 'personal-profile-builder' ); ?></option>
			<option value="<?php echo \esc_attr( Meta::STATUS_AVAILABLE ); ?>"
				<?php \selected( $current, Meta::STATUS_AVAILABLE ); ?>>
				<?php echo \esc_html__( 'Available', 'personal-profile-builder' ); ?>
			</option>
			<option value="<?php echo \esc_attr( Meta::STATUS_RETIRED ); ?>"
				<?php \selected( $current, Meta::STATUS_RETIRED ); ?>>
				<?php echo \esc_html__( 'Retired', 'personal-profile-builder' ); ?>
			</option>
		</select>
		<?php
	}
	
	/**
	 * Apply the status filter to the talks list query.
	 *
	 * @param	WP_Query	$query Current query (modified by reference)
	 */
	public static function apply_status_filter( WP_Query $query ): void {
		if ( ! \is_admin() || ! $query->is_main_query() ) {
			return;
		}
		
		$post_type = $query->get( 'post_type' );
		
		if ( $post_type !== Post_Types::POST_TYPE_TALK ) {
			return;
		}
		
		$requested = isset( $_GET[ self::STATUS_QUERY_VAR ] )
			? \sanitize_key( \wp_unslash( (string) $_GET[ self::STATUS_QUERY_VAR ] ) )
			: '';
		
		if ( ! \in_array( $requested, Meta::TALK_STATUSES, true ) ) {
			return;
		}
		
		$meta_query = (array) $query->get( 'meta_query', [] );
		$meta_query[] = [
			'key' => '_talk_status',
			'value' => $requested,
			'compare' => '=',
		];
		
		$query->set( 'meta_query', $meta_query );
	}
	
	/**
	 * Add the plugin's columns to the talks list table.
	 *
	 * Inserted after the title column. The taxonomy column for
	 * `talk_topic` is added automatically by core (the taxonomy is
	 * registered with `show_admin_column => true`).
	 *
	 * @param	array<string,string>	$columns Existing columns
	 * @return	array<string,string> Columns with plugin additions
	 */
	public static function register_columns( array $columns ): array {
		$out = [];
		
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			
			if ( $key === 'title' ) {
				$out['ppb_status'] = \__( 'Status', 'personal-profile-builder' );
				$out['ppb_occurrence_count'] = \__( 'Occurrences', 'personal-profile-builder' );
				$out['ppb_next_date'] = \__( 'Next date', 'personal-profile-builder' );
				$out['ppb_languages'] = \__( 'Languages', 'personal-profile-builder' );
			}
		}
		
		return $out;
	}
	
	/**
	 * Render the value for one of the plugin's custom columns.
	 *
	 * @param	string	$column Column key
	 * @param	int	$post_id Post ID for the current row
	 */
	public static function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ppb_status':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_status_badge returns pre-escaped HTML.
				echo self::render_status_badge( $post_id );
				
				return;
			case 'ppb_occurrence_count':
				echo \esc_html( (string) self::count_occurrences( $post_id ) );
				
				return;
			case 'ppb_next_date':
				$next = self::get_next_upcoming_date( $post_id );
				
				if ( $next === '' ) {
					echo '<span aria-hidden="true">&#x2014;</span>';
					echo '<span class="screen-reader-text">'
						. \esc_html__(
							'No upcoming date',
							'personal-profile-builder'
						) . '</span>';
					
					return;
				}
				
				echo \esc_html(
					\mysql2date(
						\get_option( 'date_format', 'Y-m-d' ),
						$next . ' 00:00:00'
					)
				);
				
				return;
			case 'ppb_languages':
				$languages = self::collect_occurrence_languages( $post_id );
				
				if ( $languages === [] ) {
					echo '<span aria-hidden="true">&#x2014;</span>';
					echo '<span class="screen-reader-text">'
						. \esc_html__(
							'No languages set',
							'personal-profile-builder'
						) . '</span>';
					
					return;
				}
				
				echo \esc_html( \implode( ', ', $languages ) );
				
				return;
		}
	}
	
	/**
	 * Render the HTML badge for a talk's status.
	 *
	 * @param	int	$post_id Post ID
	 * @return	string Already-escaped HTML
	 */
	private static function render_status_badge( int $post_id ): string {
		$status = (string) \get_post_meta( $post_id, '_talk_status', true );
		
		if ( $status === Meta::STATUS_AVAILABLE ) {
			return '<span class="ppb-status-badge ppb-status-badge--available">'
				. \esc_html__( 'Available', 'personal-profile-builder' )
				. '</span>';
		}
		
		if ( $status === Meta::STATUS_RETIRED ) {
			return '<span class="ppb-status-badge ppb-status-badge--retired">'
				. \esc_html__( 'Retired', 'personal-profile-builder' )
				. '</span>';
		}
		
		return '<span aria-hidden="true">&#x2014;</span>'
			. '<span class="screen-reader-text">'
			. \esc_html__( 'No status', 'personal-profile-builder' )
			. '</span>';
	}
	
	/**
	 * Count occurrence rows on a talk.
	 *
	 * @param	int	$post_id Post ID
	 * @return	int Number of valid occurrence rows
	 */
	private static function count_occurrences( int $post_id ): int {
		$rows = self::read_occurrences( $post_id );
		
		return \count( $rows );
	}
	
	/**
	 * Find the soonest occurrence date that is on or after today.
	 *
	 * @param	int	$post_id Post ID
	 * @return	string ISO date (YYYY-MM-DD) or empty string when none
	 */
	private static function get_next_upcoming_date( int $post_id ): string {
		$rows = self::read_occurrences( $post_id );
		
		if ( $rows === [] ) {
			return '';
		}
		
		$today = \current_time( 'Y-m-d' );
		$candidates = [];
		
		foreach ( $rows as $row ) {
			if ( ! isset( $row['date'] ) || ! \is_string( $row['date'] ) ) {
				continue;
			}
			
			if ( $row['date'] >= $today ) {
				$candidates[] = $row['date'];
			}
		}
		
		if ( $candidates === [] ) {
			return '';
		}
		
		\sort( $candidates );
		
		return $candidates[0];
	}
	
	/**
	 * Decode and return the occurrences array for a post.
	 *
	 * @param	int	$post_id Post ID
	 * @return	array<int,array<string,string>> List of occurrence rows
	 */
	private static function read_occurrences( int $post_id ): array {
		$raw = \get_post_meta( $post_id, '_talk_occurrences', true );
		
		if ( ! \is_string( $raw ) || $raw === '' ) {
			return [];
		}
		
		$decoded = \json_decode( $raw, true );
		
		if ( ! \is_array( $decoded ) ) {
			return [];
		}
		
		return $decoded;
	}
	
	/**
	 * Collect the unique locale names across a talk's occurrences.
	 *
	 * Returns a list of human-readable language names (e.g.
	 * `"English", "Deutsch"`) sorted alphabetically. Locales that
	 * don't resolve to a name (because they're not in the current
	 * site's installed languages) fall back to the raw code.
	 *
	 * @param	int	$post_id Post ID
	 * @return	array<int,string> Sorted, deduplicated language names
	 */
	private static function collect_occurrence_languages( int $post_id ): array {
		$rows = self::read_occurrences( $post_id );
		
		if ( $rows === [] ) {
			return [];
		}
		
		$names = [];
		
		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}
			
			$locale = isset( $row['language'] ) && \is_string( $row['language'] )
				? $row['language']
				: '';
			
			if ( $locale === '' ) {
				continue;
			}
			
			$name = MSLS_Integration::locale_name( $locale );
			
			if ( $name === '' ) {
				continue;
			}
			
			$names[ $name ] = true;
		}
		
		if ( $names === [] ) {
			return [];
		}
		
		$out = \array_keys( $names );
		\sort( $out );
		
		return $out;
	}
}
