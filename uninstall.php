<?php
/**
 * Removes everything the plugin stored when it is deleted from the Plugins screen.
 *
 * On a network install this has to walk every site: uninstall.php runs once, in
 * the context of whichever site happens to be current, so a plain delete_option()
 * would leave every other site's rows behind as orphans.
 *
 * @package Carousel3D
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes everything the plugin owns on a single site.
 *
 * The option names are the 1.x ones on purpose. Renaming them would orphan the
 * configuration of every install that already exists.
 *
 * @return void
 */
function carousel3d_uninstall_site() {
	delete_option( 'c3d_settings' );
	delete_option( 'c3d_migrated_from_theme_mods' );

	// 1.x kept its configuration in theme mods; clear those from every theme,
	// without disturbing the settings that belong to the theme itself.
	$themes = wp_get_themes();
	foreach ( array_keys( $themes ) as $slug ) {
		$mods = get_option( "theme_mods_{$slug}" );
		if ( ! is_array( $mods ) ) {
			continue;
		}
		$changed = false;
		foreach ( array_keys( $mods ) as $key ) {
			if ( 0 === strpos( (string) $key, 'c3d_' ) ) {
				unset( $mods[ $key ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( "theme_mods_{$slug}", $mods );
		}
	}
}

/**
 * Runs the cleanup across the whole install, single site or network.
 *
 * Wrapped in a function rather than left at file scope so the loop variables do
 * not become globals.
 *
 * @return void
 */
function carousel3d_uninstall_everywhere() {
	if ( ! is_multisite() ) {
		carousel3d_uninstall_site();
		return;
	}

	// Batched, so a network with thousands of sites does not exhaust memory.
	$offset = 0;
	$batch  = 100;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $batch,
				'offset' => $offset,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			carousel3d_uninstall_site();
			restore_current_blog();
		}

		$found   = count( $site_ids );
		$offset += $batch;
	} while ( $found === $batch );
}

carousel3d_uninstall_everywhere();
