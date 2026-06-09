<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use WP_Post;
use WP_Query;

/**
 * WP_Query helpers for talks.
 *
 * Provides convenience methods for querying talks by status and
 * upcoming occurrences. All methods return standard WordPress data
 * structures (WP_Query, arrays of WP_Post, etc.) so they integrate
 * naturally with existing theme code.
 *
 * @package	Personal_Profile_Builder
 */
final class Query_Helpers {
	/**
	 * Query talks by booking status.
	 *
	 * Returns a WP_Query instance with the given status filter
	 * applied as a meta query. The caller owns the query and is
	 * responsible for calling `wp_reset_postdata()` when done.
	 *
	 * @param	string	$status One of Meta::STATUS_AVAILABLE,
	 *                         Meta::STATUS_RETIRED, or empty
	 *                         for all talks
	 * @param	array<string,mixed>	$args Additional WP_Query arguments
	 *                                    merged over the defaults
	 * @return	WP_Query Configured query instance
	 */
	public static function talks_by_status(
		string $status = '',
		array $args = []
	): WP_Query {
		$defaults = [
			'post_type' => Post_Types::POST_TYPE_TALK,
			'posts_per_page' => 10,
			'post_status' => 'publish',
			'orderby' => 'date',
			'order' => 'DESC',
		];
		
		if (
			$status !== ''
			&& \in_array( $status, Meta::TALK_STATUSES, true )
		) {
			$defaults['meta_query'] = [
				[
					'key' => '_talk_status',
					'value' => $status,
					'compare' => '=',
				],
			];
		}
		
		$merged = \wp_parse_args( $args, $defaults );
		
		return new WP_Query( $merged );
	}
	
	/**
	 * Get talks that have upcoming occurrences.
	 *
	 * Fetches all published talks, decodes their occurrences, and
	 * returns those that have at least one occurrence on or after
	 * today, sorted by the nearest upcoming date.
	 *
	 * Because occurrence dates live inside a serialised JSON blob,
	 * this method cannot filter at the SQL level and instead
	 * post-processes in PHP. For sites with very large numbers of
	 * talks, consider caching the result.
	 *
	 * @param	int	$limit Maximum number of talks to return
	 * @param	string	$status Optional status filter
	 * @return	array<int,array{post: WP_Post, next_date: string}>
	 *          Sorted by nearest upcoming date (ascending)
	 */
	public static function upcoming_talks(
		int $limit = 10,
		string $status = ''
	): array {
		$query_args = [
			'post_type' => Post_Types::POST_TYPE_TALK,
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'no_found_rows' => true,
		];
		
		if (
			$status !== ''
			&& \in_array( $status, Meta::TALK_STATUSES, true )
		) {
			$query_args['meta_query'] = [
				[
					'key' => '_talk_status',
					'value' => $status,
					'compare' => '=',
				],
			];
		}
		
		$query = new WP_Query( $query_args );
		$today = \current_time( 'Y-m-d' );
		$results = [];
		
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			
			$next = self::get_next_date_for_talk(
				$post->ID,
				$today
			);
			
			if ( $next === '' ) {
				continue;
			}
			
			$results[] = [
				'post' => $post,
				'next_date' => $next,
			];
		}
		
		\wp_reset_postdata();
		
		\usort( $results, function ( array $a, array $b ): int {
			return $a['next_date'] <=> $b['next_date'];
		} );
		
		if ( $limit > 0 && \count( $results ) > $limit ) {
			$results = \array_slice( $results, 0, $limit );
		}
		
		return $results;
	}
	
	/**
	 * Get the next upcoming occurrence date for a single talk.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	string	$reference_date ISO date to compare against
	 *                                 (defaults to today)
	 * @return	string ISO date (YYYY-MM-DD) or empty string
	 */
	public static function get_next_date_for_talk(
		int $post_id,
		string $reference_date = ''
	): string {
		if ( $reference_date === '' ) {
			$reference_date = \current_time( 'Y-m-d' );
		}
		
		$rows = self::decode_occurrences( $post_id );
		
		if ( $rows === [] ) {
			return '';
		}
		
		$candidates = [];
		
		foreach ( $rows as $row ) {
			if (
				! isset( $row['date'] )
				|| ! \is_string( $row['date'] )
			) {
				continue;
			}
			
			if ( $row['date'] >= $reference_date ) {
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
	 * Get all occurrences for a talk, optionally filtered.
	 *
	 * @param	int	$post_id Talk post ID
	 * @param	string	$filter One of 'all', 'upcoming', or 'past'
	 * @return	array<int,array<string,string>> Sorted by date ascending
	 */
	public static function get_occurrences(
		int $post_id,
		string $filter = 'all'
	): array {
		$rows = self::decode_occurrences( $post_id );
		
		if ( $rows === [] ) {
			return [];
		}
		
		// Drop any non-array rows so the typed callbacks below are safe.
		$rows = \array_filter(
			$rows,
			function ( $row ): bool {
				return \is_array( $row );
			}
		);
		
		if ( $rows === [] ) {
			return [];
		}
		
		$today = \current_time( 'Y-m-d' );
		
		if ( $filter === 'upcoming' ) {
			$rows = \array_filter(
				$rows,
				function ( array $row ) use ( $today ): bool {
					return isset( $row['date'] )
						&& \is_string( $row['date'] )
						&& $row['date'] >= $today;
				}
			);
		}
		else if ( $filter === 'past' ) {
			$rows = \array_filter(
				$rows,
				function ( array $row ) use ( $today ): bool {
					return isset( $row['date'] )
						&& \is_string( $row['date'] )
						&& $row['date'] < $today;
				}
			);
		}
		
		$rows = \array_values( $rows );
		
		\usort(
			$rows,
			function ( array $a, array $b ): int {
				$da = $a['date'] ?? '';
				$db = $b['date'] ?? '';
				
				return $da <=> $db;
			}
		);
		
		return $rows;
	}
	
	/**
	 * Decode the occurrences JSON for a talk.
	 *
	 * @param	int	$post_id Talk post ID
	 * @return	array<int,array<string,string>> Occurrence rows
	 */
	private static function decode_occurrences(
		int $post_id
	): array {
		$raw = \get_post_meta(
			$post_id,
			'_talk_occurrences',
			true
		);
		
		if ( ! \is_string( $raw ) || $raw === '' ) {
			return [];
		}
		
		$decoded = \json_decode( $raw, true );
		
		if ( ! \is_array( $decoded ) ) {
			return [];
		}
		
		return $decoded;
	}
}
