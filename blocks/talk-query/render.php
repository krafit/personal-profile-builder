<?php
/**
 * Server-side render callback for the Talk Query block.
 *
 * This file is referenced from `block.json` via the `render` property
 * and receives `$attributes` and `$content` from WordPress core.
 *
 * @var	array<string,mixed>	$attributes Block attributes.
 * @var	string	$content Inner block content (unused).
 * @var	WP_Block	$block Block instance.
 *
 * @package	Personal_Profile_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo Personal_Profile_Builder\Blocks\Talk_Query::render( $attributes );
