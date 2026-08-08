<?php
/**
 * Finds the plugin, whichever way round the repository is laid out.
 *
 * The plugin lives in a 3d-product-carousel/ subfolder in the development
 * monorepo, which also holds the paid add-on, and at the root of the public
 * repository, which holds nothing else. Resolving it here means one copy of
 * every test file works in both.
 */

/**
 * The plugin's directory, with no trailing slash.
 *
 * @return string
 */
function carousel3d_test_dir() {
	$root = dirname( __DIR__ );
	return file_exists( $root . '/3d-product-carousel.php' )
		? $root
		: $root . '/3d-product-carousel';
}
