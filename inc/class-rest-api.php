<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

use Personal_Profile_Builder\Admin\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API extensions.
 *
 * Exposes `_talk_status` and a normalised `occurrences` array as
 * additional fields on the `talk` REST endpoint, and registers a
 * read-only speaker profile endpoint at
 * `personal-profile-builder/v1/speaker`.
 *
 * @package	Personal_Profile_Builder
 */
final class Rest_Api {
	/**
	 * @var	string REST namespace for plugin endpoints.
	 */
	public const REST_NAMESPACE = 'personal-profile-builder/v1';
	
	/**
	 * Register hooks.
	 */
	public static function register(): void {
		\add_action(
			'rest_api_init',
			[ self::class, 'register_talk_fields' ]
		);
		\add_action(
			'rest_api_init',
			[ self::class, 'register_speaker_route' ]
		);
		\add_action(
			'rest_api_init',
			[ self::class, 'register_sync_status_route' ]
		);
	}
	
	/**
	 * Register additional REST fields on the talk post type.
	 *
	 * Adds `talk_status` and `occurrences` to the talk endpoint
	 * response. Both are read-only at the REST level — writes go
	 * through the existing meta registration.
	 */
	public static function register_talk_fields(): void {
		\register_rest_field(
			Post_Types::POST_TYPE_TALK,
			'talk_status',
			[
				'get_callback' => [ self::class, 'get_talk_status' ],
				'update_callback' => null,
				'schema' => [
					'description' => \__(
						'Talk booking status.',
						'personal-profile-builder'
					),
					'type' => 'string',
					'enum' => [ '', 'available', 'retired' ],
					'context' => [ 'view', 'edit' ],
					'readonly' => true,
				],
			]
		);
		
		\register_rest_field(
			Post_Types::POST_TYPE_TALK,
			'occurrences',
			[
				'get_callback' => [
					self::class,
					'get_talk_occurrences',
				],
				'update_callback' => null,
				'schema' => [
					'description' => \__(
						'Normalised list of talk occurrences.',
						'personal-profile-builder'
					),
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'date' => [
								'type' => 'string',
								'description' => \__(
									'Occurrence date (YYYY-MM-DD).',
									'personal-profile-builder'
								),
							],
							'event_name' => [
								'type' => 'string',
								'description' => \__(
									'Event name.',
									'personal-profile-builder'
								),
							],
							'location' => [
								'type' => 'string',
								'description' => \__(
									'Event location.',
									'personal-profile-builder'
								),
							],
							'event_url' => [
								'type' => 'string',
								'format' => 'uri',
								'description' => \__(
									'Event URL for this occurrence.',
									'personal-profile-builder'
								),
							],
							'slides_url' => [
								'type' => 'string',
								'format' => 'uri',
								'description' => \__(
									'Slides URL for this occurrence.',
									'personal-profile-builder'
								),
							],
							'recording_url' => [
								'type' => 'string',
								'format' => 'uri',
								'description' => \__(
									'Recording URL for this occurrence.',
									'personal-profile-builder'
								),
							],
							'language' => [
								'type' => 'string',
								'description' => \__(
									'WordPress locale code recording the delivery language. Empty string when unspecified.',
									'personal-profile-builder'
								),
							],
							'language_name' => [
								'type' => 'string',
								'description' => \__(
									'Human-readable name of the language. Omitted when language is empty.',
									'personal-profile-builder'
								),
							],
							'language_flag_url' => [
								'type' => 'string',
								'format' => 'uri',
								'description' => \__(
									'Flag icon URL for the language. Omitted when language is empty or MSLS is unavailable.',
									'personal-profile-builder'
								),
							],
							'url' => [
								'type' => 'string',
								'format' => 'uri',
								'description' => \__(
									'Shareable occurrence URL.',
									'personal-profile-builder'
								),
							],
						],
					],
					'context' => [ 'view', 'edit' ],
					'readonly' => true,
				],
			]
		);
		
		\register_rest_field(
			Post_Types::POST_TYPE_TALK,
			'talk_languages',
			[
				'get_callback' => [ self::class, 'get_talk_languages' ],
				'update_callback' => [ self::class, 'update_talk_languages' ],
				'schema' => [
					'description' => \__(
						'Languages this talk is given in. Multi-value: each entry is a WordPress locale code (e.g. de_DE). Backed by the `_talk_language` post meta key, which is registered with `single => false`. Exposed as a synthesised REST field because WordPress core does not reliably round-trip multi-row meta through the standard `meta` object in the block editor.',
						'personal-profile-builder'
					),
					'type' => 'array',
					'items' => [
						'type' => 'string',
					],
					'context' => [ 'view', 'edit' ],
				],
			]
		);
	}
	
	/**
	 * Get the talk_languages field for REST.
	 *
	 * Returns the locale codes from `_talk_language` post meta as a
	 * de-duplicated, empty-stripped array. The empty-stripping is
	 * defensive — the sanitiser already collapses bad inputs to '',
	 * but legacy rows from older installs may still exist.
	 *
	 * @param	array<string,mixed>	$object REST post object
	 * @return	array<int,string> Locale codes
	 */
	public static function get_talk_languages( array $object ): array {
		$post_id = (int) ( $object['id'] ?? 0 );
		
		if ( $post_id < 1 ) {
			return [];
		}
		
		$raw = \get_post_meta( $post_id, '_talk_language', false );
		
		if ( ! \is_array( $raw ) ) {
			return [];
		}
		
		$seen = [];
		$out = [];
		
		foreach ( $raw as $value ) {
			if ( ! \is_string( $value ) || $value === '' ) {
				continue;
			}
			
			if ( isset( $seen[ $value ] ) ) {
				continue;
			}
			
			$seen[ $value ] = true;
			$out[] = $value;
		}
		
		return $out;
	}
	
	/**
	 * Update the talk_languages field via REST.
	 *
	 * Replaces the entire `_talk_language` row set with the incoming
	 * values. Each value is sanitised through
	 * {@see Meta::sanitize_locale_or_empty()} — unknown locales are
	 * dropped silently, which is the same behaviour as the underlying
	 * meta key's sanitiser when written directly.
	 *
	 * The full-overwrite approach (delete all, re-add survivors) is
	 * the standard workaround for the limitation in
	 * https://github.com/WordPress/gutenberg/issues/17692 where
	 * `editPost({ meta: { ... } })` cannot reliably remove already-
	 * saved elements from multi-value meta.
	 *
	 * @param	mixed	$value Incoming value from the REST request
	 * @param	\WP_Post	$post Target post object
	 * @return	bool|\WP_Error True on success, WP_Error on capability failure
	 */
	public static function update_talk_languages( $value, $post ) {
		if ( ! \current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error(
				'rest_cannot_update',
				\__(
					'You do not have permission to edit this talk.',
					'personal-profile-builder'
				),
				[ 'status' => 403 ]
			);
		}
		
		$incoming = \is_array( $value ) ? $value : [];
		$clean = [];
		$seen = [];
		
		foreach ( $incoming as $candidate ) {
			if ( ! \is_string( $candidate ) ) {
				continue;
			}
			
			$sanitised = Meta::sanitize_locale_or_empty( $candidate );
			
			if ( $sanitised === '' ) {
				continue;
			}
			
			if ( isset( $seen[ $sanitised ] ) ) {
				continue;
			}
			
			$seen[ $sanitised ] = true;
			$clean[] = $sanitised;
		}
		
		// Delete all existing rows, then add the survivors back. This
		// is the only reliable way to drop removed locales — calling
		// update_post_meta() on a single => false key only adds, never
		// removes.
		\delete_post_meta( $post->ID, '_talk_language' );
		
		foreach ( $clean as $locale ) {
			\add_post_meta( $post->ID, '_talk_language', $locale, false );
		}
		
		return true;
	}
	
	/**
	 * Get the talk status for the REST response.
	 *
	 * @param	array<string,mixed>	$object REST post object
	 * @return	string Talk status or empty string
	 */
	public static function get_talk_status(
		array $object
	): string {
		$post_id = (int) ( $object['id'] ?? 0 );
		
		if ( $post_id < 1 ) {
			return '';
		}
		
		$status = (string) \get_post_meta(
			$post_id,
			'_talk_status',
			true
		);
		
		if (
			! \in_array( $status, Meta::TALK_STATUSES, true )
		) {
			return '';
		}
		
		return $status;
	}
	
	/**
	 * Get normalised occurrences for the REST response.
	 *
	 * Each occurrence includes the shareable URL in addition to the
	 * stored fields, so consumers don't need to construct it.
	 *
	 * @param	array<string,mixed>	$object REST post object
	 * @return	array<int,array<string,string>> Normalised occurrences
	 */
	public static function get_talk_occurrences(
		array $object
	): array {
		$post_id = (int) ( $object['id'] ?? 0 );
		
		if ( $post_id < 1 ) {
			return [];
		}
		
		$rows = Query_Helpers::get_occurrences(
			$post_id,
			'all'
		);
		
		$normalised = [];
		
		foreach ( $rows as $row ) {
			$date = $row['date'] ?? '';
			$occurrence_url = '';
			
			if ( $date !== '' ) {
				$occurrence_url = Occurrences::get_occurrence_url(
					$post_id,
					$date
				);
			}
			
			$language = isset( $row['language'] ) && \is_string( $row['language'] )
				? $row['language']
				: '';
			$entry = [
				'date' => $date,
				'event_name' => $row['event_name'] ?? '',
				'location' => $row['location'] ?? '',
				'event_url' => $row['event_url'] ?? '',
				'slides_url' => $row['slides_url'] ?? '',
				'recording_url' => $row['recording_url'] ?? '',
				'language' => $language,
				'url' => $occurrence_url,
			];
			
			if ( $language !== '' ) {
				$entry['language_name'] = MSLS_Integration::locale_name(
					$language
				);
				$flag_url = MSLS_Integration::flag_url( $language );
				
				if ( $flag_url !== '' ) {
					$entry['language_flag_url'] = $flag_url;
				}
			}
			
			$normalised[] = $entry;
		}
		
		return $normalised;
	}
	
	/**
	 * Register the speaker profile REST route.
	 */
	public static function register_speaker_route(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			'/speaker',
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [
					self::class,
					'handle_speaker_request',
				],
				'permission_callback' => '__return_true',
				'schema' => [
					self::class,
					'get_speaker_schema',
				],
			]
		);
	}
	
	/**
	 * Handle the speaker profile request.
	 *
	 * Returns the speaker bio and avatar data. The endpoint is
	 * publicly readable — the data is intended for display on the
	 * front-end or consumption by external services.
	 *
	 * @param	WP_REST_Request	$request REST request object
	 * @return	WP_REST_Response Speaker profile data
	 */
	public static function handle_speaker_request(
		WP_REST_Request $request
	): WP_REST_Response {
		unset( $request );
		
		$bio = (string) \get_option(
			Settings::OPTION_BIO,
			''
		);
		$avatar_id = (int) \get_option(
			Settings::OPTION_AVATAR_ID,
			0
		);
		$avatar_url = Settings::get_avatar_url( $avatar_id );
		
		$avatar_data = null;
		
		if ( $avatar_id > 0 ) {
			$avatar_data = [
				'id' => $avatar_id,
				'url' => $avatar_url,
			];
			
			$metadata = \wp_get_attachment_metadata( $avatar_id );
			
			if ( \is_array( $metadata ) ) {
				$avatar_data['width'] = $metadata['width'] ?? 0;
				$avatar_data['height'] = $metadata['height'] ?? 0;
			}
			
			$alt = (string) \get_post_meta(
				$avatar_id,
				'_wp_attachment_image_alt',
				true
			);
			$avatar_data['alt'] = $alt;
		}
		
		$data = [
			'bio' => $bio,
			'bio_rendered' => \wpautop( $bio ),
			'avatar' => $avatar_data,
		];
		
		return new WP_REST_Response( $data, 200 );
	}
	
	/**
	 * Schema for the speaker endpoint.
	 *
	 * @return	array<string,mixed> JSON Schema
	 */
	public static function get_speaker_schema(): array {
		return [
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title' => 'speaker-profile',
			'type' => 'object',
			'properties' => [
				'bio' => [
					'description' => \__(
						'Speaker bio as stored HTML.',
						'personal-profile-builder'
					),
					'type' => 'string',
					'readonly' => true,
				],
				'bio_rendered' => [
					'description' => \__(
						'Speaker bio with wpautop applied.',
						'personal-profile-builder'
					),
					'type' => 'string',
					'readonly' => true,
				],
				'avatar' => [
					'description' => \__(
						'Speaker avatar data, or null if unset.',
						'personal-profile-builder'
					),
					'type' => [ 'object', 'null' ],
					'readonly' => true,
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => \__(
								'Attachment ID.',
								'personal-profile-builder'
							),
						],
						'url' => [
							'type' => 'string',
							'format' => 'uri',
							'description' => \__(
								'Full-size avatar URL.',
								'personal-profile-builder'
							),
						],
						'width' => [
							'type' => 'integer',
							'description' => \__(
								'Image width in pixels.',
								'personal-profile-builder'
							),
						],
						'height' => [
							'type' => 'integer',
							'description' => \__(
								'Image height in pixels.',
								'personal-profile-builder'
							),
						],
						'alt' => [
							'type' => 'string',
							'description' => \__(
								'Alt text from the Media Library.',
								'personal-profile-builder'
							),
						],
					],
				],
			],
		];
	}
	
	/**
	 * Register the read-only sync-status route.
	 *
	 * Returns the same data the {@see Admin\Sync_Meta_Box} surfaces:
	 * for each MSLS-linked translation, the locale label, the
	 * occurrence count on the sibling, and a `synced` boolean.
	 *
	 * Useful for a future block-editor sidebar panel; for now it's
	 * an additional API surface for site automation.
	 */
	public static function register_sync_status_route(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			'/talks/(?P<id>\d+)/sync-status',
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [
					self::class,
					'handle_sync_status_request',
				],
				'permission_callback' => [
					self::class,
					'sync_status_permission_check',
				],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
				],
			]
		);
		\register_rest_route(
			self::REST_NAMESPACE,
			'/talks/(?P<id>\d+)/sync-action',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [
					self::class,
					'handle_sync_action_request',
				],
				'permission_callback' => [
					self::class,
					'sync_status_permission_check',
				],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
					'sync_action' => [
						'type' => 'string',
						'required' => true,
						'enum' => [ 'push', 'pull', 'merge' ],
					],
					'source_locale' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);
	}
	
	/**
	 * Permission check for the sync-status endpoint.
	 *
	 * Restricted to users with `edit_post` on the talk — same
	 * threshold as editing it via the editor.
	 *
	 * @param	WP_REST_Request	$request REST request object
	 * @return	bool Whether the request may proceed
	 */
	public static function sync_status_permission_check(
		WP_REST_Request $request
	): bool {
		$post_id = (int) $request->get_param( 'id' );
		
		if ( $post_id < 1 ) {
			return false;
		}
		
		return \current_user_can( 'edit_post', $post_id );
	}
	
	/**
	 * Handle the sync-status request.
	 *
	 * @param	WP_REST_Request	$request REST request object
	 * @return	WP_REST_Response Sync status payload
	 */
	public static function handle_sync_status_request(
		WP_REST_Request $request
	): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post = \get_post( $post_id );
		
		if (
			$post === null
			|| $post->post_type !== Post_Types::POST_TYPE_TALK
		) {
			return new WP_REST_Response(
				[
					'available' => false,
					'translations' => [],
				],
				404
			);
		}
		
		if ( ! MSLS_Integration::is_available() ) {
			return new WP_REST_Response( [
				'available' => false,
				'translations' => [],
			] );
		}
		
		$source_json = (string) \get_post_meta(
			$post_id,
			'_talk_occurrences',
			true
		);
		$state = Occurrence_Sync::read_sibling_state( $post_id );
		$translations = [];
		
		foreach ( $state as $locale => $entry ) {
			$sibling_json = (string) ( $entry['json'] ?? '' );
			$translations[] = [
				'locale' => $locale,
				'language_name' => MSLS_Integration::locale_name( $locale ),
				'post_id' => (int) ( $entry['post_id'] ?? 0 ),
				'occurrence_count' => (int) ( $entry['count'] ?? 0 ),
				'synced' => $sibling_json === $source_json,
				'empty' => $sibling_json === '',
			];
		}
		
		return new WP_REST_Response( [
			'available' => true,
			'translations' => $translations,
		] );
	}
	
	/**
	 * Handle a sync action request (push / pull / merge).
	 *
	 * Dispatches to the matching {@see Occurrence_Sync} method based
	 * on the `sync_action` parameter. The permission check is shared
	 * with the read endpoint — same `edit_post` capability threshold.
	 *
	 * Returns a payload mirroring `sync-status` so the caller can
	 * update its UI without a second round-trip.
	 *
	 * @param	WP_REST_Request	$request REST request object
	 * @return	WP_REST_Response Action result and refreshed sync state
	 */
	public static function handle_sync_action_request(
		WP_REST_Request $request
	): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$action = (string) $request->get_param( 'sync_action' );
		$post = \get_post( $post_id );
		
		if (
			$post === null
			|| $post->post_type !== Post_Types::POST_TYPE_TALK
		) {
			return new WP_REST_Response(
				[
					'ok' => false,
					'error' => 'invalid_post',
				],
				404
			);
		}
		
		if ( ! MSLS_Integration::is_available() ) {
			return new WP_REST_Response(
				[
					'ok' => false,
					'error' => 'msls_unavailable',
				],
				400
			);
		}
		
		$result = false;
		
		switch ( $action ) {
			case 'push':
				Occurrence_Sync::push_from_source( $post_id );
				$result = true;
				
				break;
			case 'pull':
				$source_locale = (string) $request->get_param(
					'source_locale'
				);
				
				if ( $source_locale === '' ) {
					return new WP_REST_Response(
						[
							'ok' => false,
							'error' => 'missing_source_locale',
						],
						400
					);
				}
				
				$result = Occurrence_Sync::pull_from_sibling(
					$post_id,
					$source_locale
				);
				
				break;
			case 'merge':
				$result = Occurrence_Sync::merge_all( $post_id );
				
				break;
		}
		
		if ( ! $result ) {
			return new WP_REST_Response(
				[
					'ok' => false,
					'error' => 'action_failed',
				],
				500
			);
		}
		
		return new WP_REST_Response( [
			'ok' => true,
			'action' => $action,
		] );
	}
}
