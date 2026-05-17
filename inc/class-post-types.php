<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Custom post type and taxonomy registration.
 *
 * Registers the `talk` and `project` post types and their associated
 * hierarchical taxonomies (`talk_topic`, `project_type`).
 *
 * @package	Personal_Profile_Builder
 */
final class Post_Types {
	/**
	 * @var	string Post type slug for talks.
	 */
	public const POST_TYPE_TALK = 'talk';
	
	/**
	 * @var	string Post type slug for projects.
	 */
	public const POST_TYPE_PROJECT = 'project';
	
	/**
	 * @var	string Taxonomy slug for talk topics.
	 */
	public const TAXONOMY_TALK_TOPIC = 'talk_topic';
	
	/**
	 * @var	string Taxonomy slug for project types.
	 */
	public const TAXONOMY_PROJECT_TYPE = 'project_type';
	
	/**
	 * Register all post types and taxonomies.
	 */
	public static function register(): void {
		self::register_talk();
		self::register_project();
		self::register_talk_topic();
		self::register_project_type();
	}
	
	/**
	 * Register the `talk` post type.
	 */
	private static function register_talk(): void {
		$labels = [
			'name' => \_x( 'Talks', 'post type general name', 'personal-profile-builder' ),
			'singular_name' => \_x( 'Talk', 'post type singular name', 'personal-profile-builder' ),
			'menu_name' => \_x( 'Talks', 'admin menu', 'personal-profile-builder' ),
			'name_admin_bar' => \_x( 'Talk', 'add new on admin bar', 'personal-profile-builder' ),
			'add_new' => \_x( 'Add New', 'talk', 'personal-profile-builder' ),
			'add_new_item' => \__( 'Add New Talk', 'personal-profile-builder' ),
			'new_item' => \__( 'New Talk', 'personal-profile-builder' ),
			'edit_item' => \__( 'Edit Talk', 'personal-profile-builder' ),
			'view_item' => \__( 'View Talk', 'personal-profile-builder' ),
			'all_items' => \__( 'All Talks', 'personal-profile-builder' ),
			'search_items' => \__( 'Search Talks', 'personal-profile-builder' ),
			'not_found' => \__( 'No talks found.', 'personal-profile-builder' ),
			'not_found_in_trash' => \__( 'No talks found in Trash.', 'personal-profile-builder' ),
			'featured_image' => \__( 'Talk cover image', 'personal-profile-builder' ),
			'archives' => \__( 'Talk archives', 'personal-profile-builder' ),
		];
		$args = [
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'rest_base' => 'talks',
			'menu_icon' => 'dashicons-microphone',
			'menu_position' => 20,
			'has_archive' => true,
			'rewrite' => [
				'slug' => 'talk',
				'with_front' => false,
			],
			'supports' => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
				'custom-fields',
			],
			'capability_type' => 'post',
		];
		
		\register_post_type( self::POST_TYPE_TALK, $args );
	}
	
	/**
	 * Register the `project` post type.
	 */
	private static function register_project(): void {
		$labels = [
			'name' => \_x( 'Projects', 'post type general name', 'personal-profile-builder' ),
			'singular_name' => \_x( 'Project', 'post type singular name', 'personal-profile-builder' ),
			'menu_name' => \_x( 'Projects', 'admin menu', 'personal-profile-builder' ),
			'name_admin_bar' => \_x( 'Project', 'add new on admin bar', 'personal-profile-builder' ),
			'add_new' => \_x( 'Add New', 'project', 'personal-profile-builder' ),
			'add_new_item' => \__( 'Add New Project', 'personal-profile-builder' ),
			'new_item' => \__( 'New Project', 'personal-profile-builder' ),
			'edit_item' => \__( 'Edit Project', 'personal-profile-builder' ),
			'view_item' => \__( 'View Project', 'personal-profile-builder' ),
			'all_items' => \__( 'All Projects', 'personal-profile-builder' ),
			'search_items' => \__( 'Search Projects', 'personal-profile-builder' ),
			'not_found' => \__( 'No projects found.', 'personal-profile-builder' ),
			'not_found_in_trash' => \__( 'No projects found in Trash.', 'personal-profile-builder' ),
			'featured_image' => \__( 'Project cover image', 'personal-profile-builder' ),
			'archives' => \__( 'Project archives', 'personal-profile-builder' ),
		];
		$args = [
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'rest_base' => 'projects',
			'menu_icon' => 'dashicons-portfolio',
			'menu_position' => 21,
			'has_archive' => true,
			'rewrite' => [
				'slug' => 'project',
				'with_front' => false,
			],
			'supports' => [
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
				'custom-fields',
			],
			'capability_type' => 'post',
		];
		
		\register_post_type( self::POST_TYPE_PROJECT, $args );
	}
	
	/**
	 * Register the `talk_topic` hierarchical taxonomy.
	 */
	private static function register_talk_topic(): void {
		$labels = [
			'name' => \_x( 'Talk topics', 'taxonomy general name', 'personal-profile-builder' ),
			'singular_name' => \_x( 'Talk topic', 'taxonomy singular name', 'personal-profile-builder' ),
			'search_items' => \__( 'Search talk topics', 'personal-profile-builder' ),
			'all_items' => \__( 'All talk topics', 'personal-profile-builder' ),
			'parent_item' => \__( 'Parent talk topic', 'personal-profile-builder' ),
			'parent_item_colon' => \__( 'Parent talk topic:', 'personal-profile-builder' ),
			'edit_item' => \__( 'Edit talk topic', 'personal-profile-builder' ),
			'update_item' => \__( 'Update talk topic', 'personal-profile-builder' ),
			'add_new_item' => \__( 'Add new talk topic', 'personal-profile-builder' ),
			'new_item_name' => \__( 'New talk topic name', 'personal-profile-builder' ),
			'menu_name' => \__( 'Topics', 'personal-profile-builder' ),
		];
		$args = [
			'hierarchical' => true,
			'labels' => $labels,
			'public' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'show_in_rest' => true,
			'rest_base' => 'talk-topics',
			'query_var' => true,
			'rewrite' => [
				'slug' => 'talk-topic',
				'with_front' => false,
			],
		];
		
		\register_taxonomy(
			self::TAXONOMY_TALK_TOPIC,
			[ self::POST_TYPE_TALK ],
			$args
		);
	}
	
	/**
	 * Register the `project_type` hierarchical taxonomy.
	 */
	private static function register_project_type(): void {
		$labels = [
			'name' => \_x( 'Project types', 'taxonomy general name', 'personal-profile-builder' ),
			'singular_name' => \_x( 'Project type', 'taxonomy singular name', 'personal-profile-builder' ),
			'search_items' => \__( 'Search project types', 'personal-profile-builder' ),
			'all_items' => \__( 'All project types', 'personal-profile-builder' ),
			'parent_item' => \__( 'Parent project type', 'personal-profile-builder' ),
			'parent_item_colon' => \__( 'Parent project type:', 'personal-profile-builder' ),
			'edit_item' => \__( 'Edit project type', 'personal-profile-builder' ),
			'update_item' => \__( 'Update project type', 'personal-profile-builder' ),
			'add_new_item' => \__( 'Add new project type', 'personal-profile-builder' ),
			'new_item_name' => \__( 'New project type name', 'personal-profile-builder' ),
			'menu_name' => \__( 'Types', 'personal-profile-builder' ),
		];
		$args = [
			'hierarchical' => true,
			'labels' => $labels,
			'public' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'show_in_rest' => true,
			'rest_base' => 'project-types',
			'query_var' => true,
			'rewrite' => [
				'slug' => 'project-type',
				'with_front' => false,
			],
		];
		
		\register_taxonomy(
			self::TAXONOMY_PROJECT_TYPE,
			[ self::POST_TYPE_PROJECT ],
			$args
		);
	}
}
