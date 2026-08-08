<?php
/**
 * Plugin Name: Fake lazy-load (test harness)
 *
 * Simulates what WP Rocket, Smush, a3 Lazy Load and friends do to markup:
 * move the real URL into data-src and leave a placeholder (or nothing) in src.
 * Used to check whether the carousel survives the most common real-world
 * interference. Delete this file to turn it off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', 'c3dtest_fake_lazyload', 999 );

function c3dtest_fake_lazyload( $html ) {
	return preg_replace_callback(
		'#<img\s([^>]*?)src=(["\'])(.*?)\2([^>]*?)>#i',
		function ( $m ) {
			// Honour the opt-outs the real plugins use, so the harness reflects
			// how they actually behave rather than being unconditionally hostile.
			$whole = $m[0];
			if ( preg_match( '/\b(skip-lazy|no-lazy|no-lazyload)\b/i', $whole )
				|| false !== stripos( $whole, 'data-no-lazy' )
				|| false !== stripos( $whole, 'data-skip-lazy' ) ) {
				return $whole;
			}
			$placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
			return '<img ' . $m[1] . 'src="' . $placeholder . '" data-src="' . $m[3] . '"'
				. $m[4] . ' class="lazyload">';
		},
		$html
	);
}
