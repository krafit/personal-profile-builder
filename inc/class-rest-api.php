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
			
			$normalised[] = [
				'date' => $date,
				'event_name' => $row['event_name'] ?? '',
				'location' => $row['location'] ?? '',
				'event_url' => $row['event_url'] ?? '',
				'slides_url' => $row['slides_url'] ?? '',
				'recording_url' => $row['recording_url'] ?? '',
				'url' => $occurrence_url,
			];
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
}
