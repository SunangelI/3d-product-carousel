<?php
/**
 * Elementor integration.
 *
 * The carousel already renders inside Elementor through its shortcode widget —
 * every builder runs shortcodes. What Elementor lacks is a place to configure
 * it, so this adds a native widget with real controls. Nothing here changes how
 * the carousel is rendered; it is a second way to fill in the same attributes,
 * and it goes through the shortcode so it cannot drift away from what the block
 * produces.
 *
 * @package Carousel3D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Elementor release this integration is written against. */
define( 'CAROUSEL3D_NEEDS_ELEMENTOR', '3.5.0' );

/**
 * Whether Elementor is present and new enough to register a widget with.
 *
 * 3.5 introduced elementor/widgets/register; the hook before it was deprecated
 * and then removed, and supporting both is not worth carrying for a release
 * from 2021.
 *
 * @return bool
 */
function carousel3d_elementor_ready() {
	return defined( 'ELEMENTOR_VERSION' )
		&& version_compare( ELEMENTOR_VERSION, CAROUSEL3D_NEEDS_ELEMENTOR, '>=' );
}

add_action( 'elementor/elements/categories_registered', 'carousel3d_elementor_category' );

/**
 * Gives the widget a panel category of its own.
 *
 * @param \Elementor\Elements_Manager $elements_manager Elementor's category registry.
 * @return void
 */
function carousel3d_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'carousel-3d',
		array(
			'title' => __( '3D Carousel', '3d-product-carousel' ),
			'icon'  => 'eicon-slides',
		)
	);
}

add_action( 'elementor/widgets/register', 'carousel3d_elementor_register' );

/**
 * Registers the widget.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widget registry.
 * @return void
 */
function carousel3d_elementor_register( $widgets_manager ) {
	if ( ! carousel3d_elementor_ready() ) {
		return;
	}
	require_once CAROUSEL3D_PATH . 'includes/class-carousel3d-elementor-widget.php';
	$widgets_manager->register( new Carousel3D_Elementor_Widget() );
}

add_action( 'elementor/frontend/after_register_scripts', 'carousel3d_elementor_register_script' );

/**
 * Registers the script that re-initialises a carousel Elementor has just drawn.
 *
 * The carousel normally starts itself on DOMContentLoaded. Elementor injects
 * widgets long after that — on every settings change in the editor, and after
 * lazy-loaded sections on the front end — so each one has to be started by hand.
 *
 * @return void
 */
function carousel3d_elementor_register_script() {
	$js = CAROUSEL3D_PATH . 'assets/js/elementor.js';

	wp_register_script(
		'carousel-3d-elementor',
		CAROUSEL3D_URL . 'assets/js/elementor.js',
		// The carousel's own script handle, which is not the text domain.
		array( 'elementor-frontend', 'carousel-3d' ),
		file_exists( $js ) ? filemtime( $js ) : CAROUSEL3D_VERSION,
		true
	);
}
