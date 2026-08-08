<?php
/**
 * Gutenberg block.
 *
 * Server-rendered through the same code path as the shortcode, so there is one
 * renderer to maintain. The editor script is written against the wp.* globals
 * rather than JSX, which keeps the plugin build-step free.
 *
 * @package Carousel3D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attributes the block exposes. Anything left at its default falls through to
 * the Customizer value, so a block placed with no configuration behaves exactly
 * like a bare [carousel_3d].
 */
function carousel3d_block_attributes() {
	$attributes = array(
		'title'         => array(
			'type'    => 'string',
			'default' => '',
		),
		'subtitle'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'hint'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'useCustomText' => array(
			'type'    => 'boolean',
			'default' => false,
		),
		// Cards chosen for this block specifically. Empty means fall through to
		// the site-wide Customizer cards, which is what most blocks will do.
		'cards'         => array(
			'type'    => 'array',
			'default' => array(),
			'items'   => array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'url'   => array( 'type' => 'string' ),
					'alt'   => array( 'type' => 'string' ),
					'label' => array( 'type' => 'string' ),
					'link'  => array( 'type' => 'string' ),
				),
			),
		),
		// Strings, not numbers, even though these are numeric values.
		// ServerSideRender passes attributes through the query string, so an
		// unset value arrives as "" — which satisfies neither 'number' nor
		// 'null' and makes the block-renderer route reject the whole request.
		// An empty string is the "inherit the Customizer value" signal.
		'tilt'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'wobble'        => array(
			'type'    => 'string',
			'default' => '',
		),
		'spread'        => array(
			'type'    => 'string',
			'default' => '',
		),
		'shadow'        => array(
			'type'    => 'string',
			'default' => '',
		),
		'aspect'        => array(
			'type'    => 'string',
			'default' => '',
		),
		'autoplay'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'snap'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'fill'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'hideback'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'floating'      => array(
			'type'    => 'string',
			'default' => '',
		),
	);

	/**
	 * Filters the block's attributes.
	 *
	 * An add-on that adds sidebar controls has to register the matching
	 * attributes here as well as through carousel3d_shortcode_defaults —
	 * ServerSideRender validates the block's registered schema, and rejects the
	 * whole preview request over one unknown attribute.
	 *
	 * @param array $attributes Attribute name to schema.
	 */
	return apply_filters( 'carousel3d_block_attributes', $attributes );
}

add_action( 'init', 'carousel3d_register_block' );

/**
 * Registers the block and its editor script.
 *
 * @return void
 */
function carousel3d_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return; // WordPress older than 5.0.
	}

	wp_register_script(
		'carousel-3d-block',
		CAROUSEL3D_URL . 'assets/js/block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		file_exists( CAROUSEL3D_PATH . 'assets/js/block.js' ) ? filemtime( CAROUSEL3D_PATH . 'assets/js/block.js' ) : CAROUSEL3D_VERSION,
		true
	);

	// Without this the editor sidebar stays English even when a translation
	// exists: wp.i18n needs the strings as a JSON file, not the .mo.
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations(
			'carousel-3d-block',
			'3d-product-carousel',
			CAROUSEL3D_PATH . 'languages'
		);
	}

	wp_localize_script(
		'carousel-3d-block',
		'C3D_BLOCK',
		array(
			'aspects'   => carousel3d_aspect_choices(),
			'maxCards'  => CAROUSEL3D_MAX_CARDS,
			'customize' => admin_url( 'customize.php?autofocus[panel]=c3d_panel' ),
		)
	);

	// The block name is written into every page that uses it, as
	// <!-- wp:carousel-3d/carousel -->. It stays as it is even though the
	// plugin's slug and text domain are now 3d-product-carousel: renaming it
	// would turn every existing block into "this block contains unexpected or
	// invalid content". Block names do not have to match the slug.
	register_block_type(
		'carousel-3d/carousel',
		array(
			'api_version'     => 3,
			'title'           => __( '3D Product Carousel', '3d-product-carousel' ),
			'description'     => __( 'An interactive 3D ring showcase for a handful of products.', '3d-product-carousel' ),
			'category'        => 'media',
			'icon'            => 'images-alt2',
			'keywords'        => array( 'carousel', 'slider', '3d', 'gallery', 'products' ),
			'supports'        => array(
				'html'  => false,
				'align' => array( 'wide', 'full' ),
			),
			'attributes'      => array_merge(
				carousel3d_block_attributes(),
				array( 'align' => array( 'type' => 'string' ) )
			),
			'editor_script'   => 'carousel-3d-block',
			// Deliberately no 'style' or 'script' here. When a theme has not opted
			// into per-block asset loading — which classic themes generally have
			// not — WordPress enqueues every registered block's style and script on
			// every page of the site, carousel or not. The front-end assets are
			// enqueued from carousel3d_maybe_enqueue() instead, and pulled into the editor
			// by enqueue_block_editor_assets.
			'render_callback' => 'carousel3d_render_block',
		)
	);
}

/**
 * The editor renders the block server-side and injects the markup, so it needs
 * the front-end CSS and JS — the cards are sized by script, and without them the
 * preview collapses into a stack of full-size images.
 *
 * This has to be enqueue_block_assets rather than enqueue_block_editor_assets:
 * with a block theme the editor canvas is an iframe, and only styles enqueued on
 * this hook are carried into it. The is_admin() guard is what keeps the assets
 * off the front end, where they are enqueued per page instead.
 */
add_action( 'enqueue_block_assets', 'carousel3d_enqueue_editor_assets' );

/**
 * Pulls the front-end assets into the editor, and only into the editor.
 *
 * @return void
 */
function carousel3d_enqueue_editor_assets() {
	if ( ! is_admin() ) {
		return;
	}
	carousel3d_enqueue_assets();
}

/**
 * Bridge from block attributes to the shortcode renderer.
 *
 * Only attributes the editor actually set are passed through; the rest are left
 * out so shortcode_atts() falls back to the Customizer defaults.
 *
 * @param array $attributes Block attributes.
 * @return string Markup, or an empty string when there is nothing to show.
 */
function carousel3d_render_block( $attributes ) {
	$atts = array();

	if ( ! empty( $attributes['useCustomText'] ) ) {
		foreach ( array( 'title', 'subtitle', 'hint' ) as $key ) {
			if ( isset( $attributes[ $key ] ) ) {
				$atts[ $key ] = $attributes[ $key ];
			}
		}
	}

	// Cards picked in this block become the shortcode's ids/labels/links/alts.
	// An attachment id is preferred over the url so the carousel keeps working
	// if the site moves domain; the url is the fallback for anything without one.
	if ( ! empty( $attributes['cards'] ) && is_array( $attributes['cards'] ) ) {
		$ids    = array();
		$labels = array();
		$links  = array();
		$alts   = array();

		foreach ( $attributes['cards'] as $card ) {
			$id  = isset( $card['id'] ) ? (int) $card['id'] : 0;
			$url = isset( $card['url'] ) ? (string) $card['url'] : '';
			if ( ! $id && '' === $url ) {
				continue;
			}
			$ids[]    = $id ? (string) $id : $url;
			$labels[] = isset( $card['label'] ) ? (string) $card['label'] : '';
			$links[]  = isset( $card['link'] ) ? (string) $card['link'] : '';
			$alts[]   = isset( $card['alt'] ) ? (string) $card['alt'] : '';
		}

		if ( $ids ) {
			$atts['ids']    = implode( ',', $ids );
			$atts['labels'] = implode( '|', $labels );
			$atts['links']  = implode( '|', $links );
			$atts['alts']   = implode( '|', $alts );
		}
	}

	// Everything else is a string whose empty value means "inherit". Numeric
	// ones are cast here; the shortcode treats them the same as typed input.
	$numeric     = array( 'tilt', 'wobble', 'spread', 'shadow' );
	$passthrough = array( 'aspect', 'autoplay', 'snap', 'fill', 'hideback', 'floating' );

	foreach ( array_merge( $numeric, $passthrough ) as $key ) {
		if ( ! isset( $attributes[ $key ] ) || '' === $attributes[ $key ] ) {
			continue;
		}
		$atts[ $key ] = in_array( $key, $numeric, true )
			? (float) $attributes[ $key ]
			: $attributes[ $key ];
	}

	// Anything an add-on registered through carousel3d_block_attributes is
	// passed straight through. Without this its sidebar controls would save
	// correctly and then be dropped on the way to the renderer, which looks
	// exactly like the add-on not working at all.
	$handled = array_merge(
		$numeric,
		$passthrough,
		array( 'title', 'subtitle', 'hint', 'useCustomText', 'cards', 'align' )
	);

	foreach ( $attributes as $key => $value ) {
		if ( in_array( $key, $handled, true ) || is_array( $value ) || '' === $value || null === $value ) {
			continue;
		}
		$atts[ $key ] = $value;
	}

	$html = carousel3d_render_shortcode( $atts );
	if ( '' === $html ) {
		return '';
	}

	$classes = array( 'wp-block-carousel-3d-carousel' );
	if ( ! empty( $attributes['align'] ) ) {
		$classes[] = 'align' . $attributes['align'];
	}

	return sprintf(
		'<div class="%s">%s</div>',
		esc_attr( implode( ' ', $classes ) ),
		$html
	);
}
