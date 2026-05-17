<?php
declare(strict_types=1);

namespace Personal_Profile_Builder;

/**
 * Class autoloader.
 *
 * Maps the plugin namespace to files in the inc/ directory, following
 * WordPress file-naming conventions (class-{name}.php, all lowercase
 * with hyphens replacing underscores in the class portion).
 */
final class Autoloader {
	/**
	 * @var	string Root namespace prefix this autoloader handles.
	 */
	private const NAMESPACE_PREFIX = 'Personal_Profile_Builder\\';
	
	/**
	 * Register the autoloader with SPL.
	 */
	public static function register(): void {
		\spl_autoload_register( [ self::class, 'load' ] );
	}
	
	/**
	 * Load a class file based on its fully qualified name.
	 *
	 * @param	string	$class_name Fully qualified class name
	 */
	public static function load( string $class_name ): void {
		if ( \strpos( $class_name, self::NAMESPACE_PREFIX ) !== 0 ) {
			return;
		}
		
		$relative = \substr( $class_name, \strlen( self::NAMESPACE_PREFIX ) );
		$parts = \explode( '\\', $relative );
		$class_part = \array_pop( $parts );
		$class_file = 'class-' . \strtolower( \str_replace( '_', '-', $class_part ) ) . '.php';
		$sub_path = '';
		
		foreach ( $parts as $part ) {
			$sub_path .= \strtolower( $part ) . '/';
		}
		
		$file_path = PERSONAL_PROFILE_BUILDER_DIR . '/inc/' . $sub_path . $class_file;
		
		if ( \is_readable( $file_path ) ) {
			require_once $file_path;
		}
	}
}
