<?php
/**
 * Plugin Name: Fake optimizer (test harness)
 *
 * Reproduces what caching and optimisation plugins do to asset tags:
 * defer or async the script, and move the stylesheet to the footer.
 * Pick the behaviour with ?c3dopt=defer|async|footer-css|all on any URL.
 * Delete this file to turn it off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c3dtest_opt_mode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['c3dopt'] ) ? sanitize_key( wp_unslash( $_GET['c3dopt'] ) ) : '';
}

add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( 'carousel-3d' !== $handle ) {
		return $tag;
	}
	$mode = c3dtest_opt_mode();
	if ( 'defer' === $mode || 'all' === $mode ) {
		$tag = str_replace( '<script ', '<script defer ', $tag );
	}
	if ( 'async' === $mode ) {
		$tag = str_replace( '<script ', '<script async ', $tag );
	}
	return $tag;
}, 10, 2 );

// Push the stylesheet to the footer, as "optimise CSS delivery" features do.
add_action( 'wp_enqueue_scripts', function () {
	$mode = c3dtest_opt_mode();
	if ( 'footer-css' !== $mode && 'all' !== $mode ) {
		return;
	}
	add_filter( 'style_loader_tag', function ( $tag, $handle ) {
		if ( 'carousel-3d' !== $handle ) {
			return $tag;
		}
		add_action( 'wp_footer', function () use ( $tag ) {
			echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput
		}, 5 );
		return '';
	}, 10, 2 );
}, 999 );
