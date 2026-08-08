<?php
/**
 * Plugin Name: 3D Product Carousel
 * Description: An interactive 3D ring showcase for a handful of products. Drag, swipe or use the arrow keys. Configure via the Customizer, place with the [carousel_3d] shortcode.
 * Version:     2.3.0
 * Author:      Web Squad
 * Author URI:  https://web-squad.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 3d-product-carousel
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package Carousel3D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAROUSEL3D_VERSION', '2.3.0' );
define( 'CAROUSEL3D_URL', plugin_dir_url( __FILE__ ) );
define( 'CAROUSEL3D_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAROUSEL3D_OPTION', 'c3d_settings' );
define( 'CAROUSEL3D_MAX_CARDS', 12 );

require_once CAROUSEL3D_PATH . 'includes/customizer.php';
require_once CAROUSEL3D_PATH . 'includes/block.php';
require_once CAROUSEL3D_PATH . 'includes/elementor.php';

/**
 * Settings
 */

/**
 * Allowed card aspect ratios. Also used to validate before the value ever
 * reaches the CSS, so a stray setting cannot break out of the rule.
 */
function carousel3d_aspect_choices() {
	return array(
		'225/260' => __( 'Default (225:260)', '3d-product-carousel' ),
		'1/1'     => __( 'Square (1:1)', '3d-product-carousel' ),
		'4/5'     => __( 'Portrait (4:5)', '3d-product-carousel' ),
		'9/16'    => __( 'Tall (9:16)', '3d-product-carousel' ),
		'4/3'     => __( 'Landscape (4:3)', '3d-product-carousel' ),
		'16/9'    => __( 'Wide (16:9)', '3d-product-carousel' ),
	);
}

/**
 * Every setting the plugin owns, with its default.
 *
 * @return array Setting key to default value.
 */
function carousel3d_defaults() {
	$d = array(
		'title'                 => __( 'Real Food, Real Results', '3d-product-carousel' ),
		'subtitle'              => __( 'Taste the Difference', '3d-product-carousel' ),
		'hint'                  => __( 'Drag to explore', '3d-product-carousel' ),
		'card_count'            => 8,
		'card_aspect'           => '225/260',
		'ring_tilt'             => -10,
		'ring_lean'             => 0,
		'wobble'                => 1.2,
		'spin_speed'            => 12,   // Stored as degrees per frame ×100.
		'autoplay'              => true,
		'snap'                  => true,
		'fill_ring'             => true,
		'hide_back'             => false,
		'spread'                => 114,  // Stored as a percentage.
		'floating_objects'      => false,
		'shadow'                => 35,   // Percent.
		'color_bg_start'        => '#f0f7f0',
		'color_bg_mid'          => '#e8f5e8',
		'color_bg_end'          => '#dcefd4',
		'color_card_bg'         => '#ffffff',
		'color_card_border'     => '#0b1a12',
		'opacity_card_border'   => 8,
		'color_accent'          => '#008000',
		'color_title'           => '#1b261b',
		'color_label_text'      => '#ffffff',
		'color_circle_top'      => '#008000',
		'opacity_circle_top'    => 6,
		'color_circle_bottom'   => '#008000',
		'opacity_circle_bottom' => 4,
	);

	for ( $i = 1; $i <= CAROUSEL3D_MAX_CARDS; $i++ ) {
		$d[ "card_{$i}_image" ] = '';
		$d[ "card_{$i}_label" ] = '';
		$d[ "card_{$i}_url" ]   = '';
		$d[ "card_{$i}_alt" ]   = '';
	}

	return $d;
}

/**
 * All settings, merged over defaults. Cached per request.
 */
function carousel3d_settings() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$saved = get_option( CAROUSEL3D_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$cache = array_merge( carousel3d_defaults(), $saved );
	return $cache;
}

/**
 * Reads a single setting.
 *
 * @param string $key Setting key.
 * @return mixed The stored value, the default, or null for an unknown key.
 */
function carousel3d_get( $key ) {
	$s = carousel3d_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : null;
}

/**
 * One-time migration from the 1.x theme-mod storage.
 *
 * 1.x kept everything in theme mods, so switching themes silently wiped the
 * carousel. Copy anything we find into the plugin's own option.
 */
function carousel3d_maybe_migrate() {
	if ( get_option( 'c3d_migrated_from_theme_mods' ) ) {
		return;
	}

	$map = array(
		'c3d_title'                 => 'title',
		'c3d_subtitle'              => 'subtitle',
		'c3d_ring_tilt'             => 'ring_tilt',
		'c3d_ring_lean'             => 'ring_lean',
		'c3d_floating_objects'      => 'floating_objects',
		'c3d_card_aspect_ratio'     => 'card_aspect',
		'c3d_color_bg_start'        => 'color_bg_start',
		'c3d_color_bg_mid'          => 'color_bg_mid',
		'c3d_color_bg_end'          => 'color_bg_end',
		'c3d_color_accent'          => 'color_accent',
		'c3d_color_title'           => 'color_title',
		'c3d_color_label_text'      => 'color_label_text',
		'c3d_color_circle_top'      => 'color_circle_top',
		'c3d_opacity_circle_top'    => 'opacity_circle_top',
		'c3d_color_circle_bottom'   => 'color_circle_bottom',
		'c3d_opacity_circle_bottom' => 'opacity_circle_bottom',
	);

	$found = array();
	foreach ( $map as $mod => $key ) {
		$v = get_theme_mod( $mod, null );
		if ( null !== $v && '' !== $v ) {
			$found[ $key ] = $v;
		}
	}
	for ( $i = 1; $i <= 8; $i++ ) {
		foreach ( array( 'image', 'label', 'url', 'alt' ) as $part ) {
			$v = get_theme_mod( "c3d_card_{$i}_{$part}", null );
			if ( null !== $v && '' !== $v && '#' !== $v ) {
				$found[ "card_{$i}_{$part}" ] = $v;
			}
		}
	}

	if ( $found ) {
		update_option( CAROUSEL3D_OPTION, array_merge( get_option( CAROUSEL3D_OPTION, array() ), $found ) );
	}
	update_option( 'c3d_migrated_from_theme_mods', 1 );
}
add_action( 'init', 'carousel3d_maybe_migrate' );

/*
 * Plugin Check flags this call as discouraged since WordPress 4.6, on the
 * grounds that translations hosted on translate.wordpress.org load by
 * themselves. This plugin ships its own translations inside languages/, and
 * that is a different case: WordPress only started finding a plugin's bundled
 * translation directory without being told in 6.7.
 *
 * Verified on this plugin's own files — same .mo, same locale, same settings:
 *
 *   WordPress 6.8.3, no call: "Потягніть, щоб роздивитися"  (loads)
 *   WordPress 6.2.6, no call: "Drag to explore"             (does not load)
 *
 * The plugin supports 6.2, so removing the call would silently drop the bundled
 * translation for every release from 6.2 to 6.6. Revisit when the minimum
 * reaches 6.7.
 */
add_action(
	'init',
	function () {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled translations do not load without this below WordPress 6.7; see the note above.
		load_plugin_textdomain( '3d-product-carousel', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * Assets — registered always, enqueued only where needed
 */

// Registered on init rather than wp_enqueue_scripts, because the block needs
// these handles to exist in the editor too — the editor renders the block
// server-side and injects the markup, so it needs the same CSS and JS.
add_action( 'init', 'carousel3d_register_assets', 5 );
add_action( 'wp_enqueue_scripts', 'carousel3d_maybe_enqueue' );

/**
 * Registers the front-end stylesheet and script.
 *
 * Registration happens on init rather than wp_enqueue_scripts because the block
 * editor needs these handles to exist too.
 *
 * @return void
 */
function carousel3d_register_assets() {
	$css = CAROUSEL3D_PATH . 'assets/css/carousel-3d.css';
	$js  = CAROUSEL3D_PATH . 'assets/js/carousel-3d.js';

	// The handles keep the old name. They are internal identifiers, nothing
	// outside this plugin refers to them by any other name, and renaming them
	// would only churn the Elementor widget's dependencies for no gain.
	wp_register_style(
		'carousel-3d',
		CAROUSEL3D_URL . 'assets/css/carousel-3d.css',
		array(),
		file_exists( $css ) ? filemtime( $css ) : CAROUSEL3D_VERSION
	);
	wp_register_script(
		'carousel-3d',
		CAROUSEL3D_URL . 'assets/js/carousel-3d.js',
		array(),
		file_exists( $js ) ? filemtime( $js ) : CAROUSEL3D_VERSION,
		true
	);
	wp_localize_script(
		'carousel-3d',
		'C3D_I18N',
		array(
			'open'  => __( 'View product', '3d-product-carousel' ),
			'close' => __( 'Close', '3d-product-carousel' ),
		)
	);
}

/**
 * Where page builders keep the page, since it is not in post_content.
 *
 * Elementor, Divi, Beaver Builder, Bricks, SiteOrigin and WPBakery all store
 * the layout in postmeta and leave post_content empty or nearly so. Checking
 * only post_content there finds nothing, and the stylesheet arrives in the
 * footer instead of the head — the carousel still works, but the page can flash
 * unstyled first.
 *
 * @return array Meta keys to scan.
 */
function carousel3d_builder_meta_keys() {
	/**
	 * Filters the postmeta keys scanned for the shortcode.
	 *
	 * @param array $keys Meta keys.
	 */
	return apply_filters(
		'carousel3d_builder_meta_keys',
		array(
			'_elementor_data',       // Elementor.
			'_et_pb_ab_subject',     // Divi.
			'_et_builder_module_features_cache',
			'_fl_builder_data',      // Beaver Builder.
			'_bricks_page_content_2', // Bricks.
			'panels_data',           // SiteOrigin.
			'_wpb_shortcodes_custom_css', // WPBakery.
		)
	);
}

/**
 * Enqueue only where the carousel actually appears, and early enough that the
 * stylesheet lands in <head> rather than the footer.
 *
 * The shortcode and the block also enqueue at render time as a last resort,
 * which covers widgets and template parts; there the CSS ends up in the footer,
 * but it still arrives.
 */
function carousel3d_maybe_enqueue() {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post ) {
		return;
	}
	$content = (string) $post->post_content;

	// Append whatever a page builder stored, so the check works there too.
	foreach ( carousel3d_builder_meta_keys() as $meta_key ) {
		$stored = get_post_meta( $post->ID, $meta_key, true );
		if ( is_string( $stored ) && '' !== $stored ) {
			$content .= ' ' . $stored;
		}
	}

	if ( has_shortcode( $content, 'carousel_3d' )
		|| ( function_exists( 'has_block' ) && has_block( 'carousel-3d/carousel', $post ) ) ) {
		carousel3d_enqueue_assets();
	}
}

/**
 * Enqueues the registered front-end assets.
 *
 * @return void
 */
function carousel3d_enqueue_assets() {
	wp_enqueue_style( 'carousel-3d' );
	wp_enqueue_script( 'carousel-3d' );
}

/**
 * Converts a hex colour and an opacity into an rgba() string.
 *
 * @param string $color   Hex colour, with or without the leading hash.
 * @param float  $opacity Between 0 and 1.
 * @return string An rgba() value; opaque black if the colour cannot be parsed.
 */
function carousel3d_hex2rgba( $color, $opacity = 1 ) {
	$color   = trim( (string) $color );
	$opacity = max( 0, min( 1, (float) $opacity ) );

	if ( 0 === strpos( $color, 'rgb' ) ) {
		return $color;
	}
	$color = ltrim( $color, '#' );

	if ( 3 === strlen( $color ) ) {
		$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
	}
	if ( 6 !== strlen( $color ) || ! ctype_xdigit( $color ) ) {
		return "rgba(0, 0, 0, {$opacity})";
	}

	// Trim trailing zeros so 0.500 reads as 0.5, but keep a bare "0".
	$trimmed = rtrim( rtrim( number_format( $opacity, 3, '.', '' ), '0' ), '.' );

	return sprintf(
		'rgba(%d, %d, %d, %s)',
		hexdec( substr( $color, 0, 2 ) ),
		hexdec( substr( $color, 2, 2 ) ),
		hexdec( substr( $color, 4, 2 ) ),
		'' === $trimmed ? '0' : $trimmed
	);
}

/**
 * Reads a colour setting, falling back to the default if it is not valid hex.
 *
 * @param string $key Setting key.
 * @return string Hex colour.
 */
function carousel3d_hex( $key ) {
	$defaults = carousel3d_defaults();
	$val      = sanitize_hex_color( (string) carousel3d_get( $key ) );
	return $val ? $val : $defaults[ $key ];
}

/**
 * Reads a percentage setting as a 0-1 fraction, ready for rgba().
 *
 * @param string $key Setting key.
 * @return float Between 0 and 1.
 */
function carousel3d_pct( $key ) {
	return max( 0, min( 100, (int) carousel3d_get( $key ) ) ) / 100;
}

/**
 * Shortcode
 */

add_shortcode( 'carousel_3d', 'carousel3d_render_shortcode' );

/**
 * Renders the carousel. Shared by the shortcode and the block.
 *
 * @param array|string $atts Shortcode attributes; any omitted attribute falls
 *                           back to the matching Customizer setting.
 * @return string Markup, or an empty string when there is nothing to show.
 */
function carousel3d_render_shortcode( $atts ) {
	/**
	 * Filters the attributes a carousel accepts, and their defaults.
	 *
	 * Anything not listed here is discarded by shortcode_atts(), so an add-on
	 * that wants its own attribute — a product source, say — has to register it
	 * through this filter or the value never reaches the renderer.
	 *
	 * Applies to the block as well as the shortcode; both end up here.
	 *
	 * @param array $defaults Attribute name to default value.
	 */
	$defaults = apply_filters(
		'carousel3d_shortcode_defaults',
		array(
			'title'    => carousel3d_get( 'title' ),
			'subtitle' => carousel3d_get( 'subtitle' ),
			'hint'     => carousel3d_get( 'hint' ),
			'tilt'     => carousel3d_get( 'ring_tilt' ),
			'lean'     => carousel3d_get( 'ring_lean' ),
			'wobble'   => carousel3d_get( 'wobble' ),
			'speed'    => carousel3d_get( 'spin_speed' ) / 100,
			'spread'   => carousel3d_get( 'spread' ) / 100,
			'aspect'   => carousel3d_get( 'card_aspect' ),
			'autoplay' => carousel3d_get( 'autoplay' ),
			'snap'     => carousel3d_get( 'snap' ),
			'hideback' => carousel3d_get( 'hide_back' ),
			'floating' => carousel3d_get( 'floating_objects' ),
			'shadow'   => carousel3d_get( 'shadow' ),
			'fill'     => carousel3d_get( 'fill_ring' ),
			'ids'      => '',
			'labels'   => '',
			'links'    => '',
			'alts'     => '',
		)
	);

	$atts = shortcode_atts( $defaults, $atts, 'carousel_3d' );

	// rest_sanitize_boolean understands "false", "0", "no" — a plain cast
	// would treat the string "false" as true.
	foreach ( array( 'autoplay', 'snap', 'hideback', 'floating', 'fill' ) as $flag ) {
		$atts[ $flag ] = rest_sanitize_boolean( $atts[ $flag ] );
	}

	$cards = carousel3d_collect_cards( $atts );
	if ( empty( $cards ) ) {
		return '';
	}

	carousel3d_enqueue_assets();

	$aspects = carousel3d_aspect_choices();
	$aspect  = isset( $aspects[ $atts['aspect'] ] ) ? $atts['aspect'] : '225/260';

	$shadow = max( 0, min( 100, (int) $atts['shadow'] ) ) / 100;

	$vars = array(
		'--c3d-bg-start'      => carousel3d_hex( 'color_bg_start' ),
		'--c3d-bg-mid'        => carousel3d_hex( 'color_bg_mid' ),
		'--c3d-bg-end'        => carousel3d_hex( 'color_bg_end' ),
		'--c3d-card-bg'       => carousel3d_hex( 'color_card_bg' ),
		'--c3d-card-border'   => carousel3d_hex2rgba( carousel3d_hex( 'color_card_border' ), carousel3d_pct( 'opacity_card_border' ) ),
		'--c3d-accent'        => carousel3d_hex( 'color_accent' ),
		'--c3d-title-color'   => carousel3d_hex( 'color_title' ),
		'--c3d-label-color'   => carousel3d_hex( 'color_label_text' ),
		'--c3d-circle-top'    => carousel3d_hex2rgba( carousel3d_hex( 'color_circle_top' ), carousel3d_pct( 'opacity_circle_top' ) ),
		'--c3d-circle-bottom' => carousel3d_hex2rgba( carousel3d_hex( 'color_circle_bottom' ), carousel3d_pct( 'opacity_circle_bottom' ) ),
		'--c3d-card-aspect'   => $aspect,
		'--c3d-shadow'        => (string) $shadow,
	);

	$style = '';
	foreach ( $vars as $prop => $value ) {
		$style .= $prop . ':' . $value . ';';
	}

	// Unique per instance, so several carousels can share a page.
	static $instance = 0;
	++$instance;
	$id = 'c3d-' . $instance;

	$scene_classes = array( 'c3d-scene' );
	if ( $atts['floating'] ) {
		$scene_classes[] = 'c3d-scene--floating';
	}

	ob_start();
	?>
	<section class="c3d-section" id="<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $style ); ?>">
		<div class="c3d-container">
			<?php if ( $atts['title'] || $atts['subtitle'] ) : ?>
				<div class="c3d-title-area">
					<?php if ( $atts['subtitle'] ) : ?>
						<span class="c3d-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></span>
					<?php endif; ?>
					<?php if ( $atts['title'] ) : ?>
						<h2 class="c3d-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<?php endif; ?>
					<div class="c3d-accent-line"></div>
				</div>
			<?php endif; ?>

			<div class="<?php echo esc_attr( implode( ' ', $scene_classes ) ); ?>"
				tabindex="0"
				role="group"
				aria-roledescription="<?php esc_attr_e( '3D carousel', '3d-product-carousel' ); ?>"
				aria-label="<?php echo esc_attr( $atts['title'] ? $atts['title'] : __( 'Product carousel', '3d-product-carousel' ) ); ?>"
				data-tilt="<?php echo esc_attr( $atts['tilt'] ); ?>"
				data-lean="<?php echo esc_attr( $atts['lean'] ); ?>"
				data-wobble="<?php echo esc_attr( $atts['wobble'] ); ?>"
				data-speed="<?php echo esc_attr( $atts['speed'] ); ?>"
				data-spread="<?php echo esc_attr( $atts['spread'] ); ?>"
				data-autoplay="<?php echo $atts['autoplay'] ? '1' : '0'; ?>"
				data-snap="<?php echo $atts['snap'] ? '1' : '0'; ?>"
				data-fill="<?php echo $atts['fill'] ? '1' : '0'; ?>"
				data-hideback="<?php echo $atts['hideback'] ? '1' : '0'; ?>">
				<div class="c3d-ring">
					<?php foreach ( $cards as $index => $card ) : ?>
						<div class="c3d-card">
							<a href="<?php echo esc_url( $card['url'] ? $card['url'] : '#' ); ?>" tabindex="-1">
								<?php if ( $card['is_video'] ) : ?>
									<video src="<?php echo esc_url( $card['img'] ); ?>" loop muted playsinline preload="metadata"
										aria-label="<?php echo esc_attr( $card['alt'] ); ?>"></video>
								<?php else : ?>
									<?php
									/*
									 * The opt-out classes and data-no-lazy are for third-party lazy-load
									 * plugins (WP Rocket, Smush, a3 Lazy Load, Jetpack). They rewrite src
									 * to a 1x1 placeholder and move the real URL to data-src; inside a 3D
									 * transform that can leave the ring showing blank cards. Each vendor
									 * honours a different opt-out, hence all of them.
									 *
									 * The first card faces the viewer on load, so it is the likely LCP
									 * element and is fetched eagerly.
									 */
									?>
									<img src="<?php echo esc_url( $card['img'] ); ?>"
										alt="<?php echo esc_attr( $card['alt'] ); ?>"
										class="c3d-card-media skip-lazy no-lazy no-lazyload"
										data-no-lazy="1" data-skip-lazy="1"
										<?php if ( 0 === $index ) : ?>
										fetchpriority="high" decoding="async"
										<?php else : ?>
										loading="lazy" decoding="async"
										<?php endif; ?>>
								<?php endif; ?>
								<?php if ( ! empty( $card['badge'] ) ) : ?>
									<span class="c3d-card-badge"><?php echo esc_html( $card['badge'] ); ?></span>
								<?php endif; ?>
								<?php if ( $card['label'] || ! empty( $card['meta'] ) ) : ?>
									<span class="c3d-card-label">
										<?php if ( $card['label'] ) : ?>
											<span class="c3d-card-name"><?php echo esc_html( $card['label'] ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $card['meta'] ) ) : ?>
											<?php // Limited HTML, so a shop can pass a formatted price. ?>
											<span class="c3d-card-meta"><?php echo wp_kses_post( $card['meta'] ); ?></span>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $atts['hint'] ) : ?>
				<span class="c3d-hint"><?php echo esc_html( $atts['hint'] ); ?></span>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Normalises one card and works out whether its media is a video.
 *
 * A card is an array with these keys. Only 'img' is required; add-ons building
 * cards from elsewhere should produce the same shape.
 *
 *   img      string  Image or video URL.
 *   url      string  Where the card links to. Empty renders a dead link.
 *   alt      string  Alt text. Falls back to the caption.
 *   label    string  Caption shown across the bottom of the card.
 *   badge    string  Optional short flag in the corner, e.g. a discount.
 *   meta     string  Optional line under the caption. Limited HTML allowed, so
 *                    a shop can pass a formatted price.
 *   is_video bool    Worked out from the extension; do not set by hand.
 *
 * @param array $card Partial card.
 * @return array|null Normalised card, or null if it has no media.
 */
function carousel3d_normalise_card( $card ) {
	$img = isset( $card['img'] ) ? trim( (string) $card['img'] ) : '';
	if ( '' === $img ) {
		return null;
	}

	$label = isset( $card['label'] ) ? (string) $card['label'] : '';
	$alt   = isset( $card['alt'] ) ? trim( (string) $card['alt'] ) : '';
	if ( '' === $alt ) {
		$alt = $label; // A real caption beats "Card 3".
	}

	$path = (string) wp_parse_url( $img, PHP_URL_PATH );
	$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

	return array(
		'img'      => $img,
		'url'      => isset( $card['url'] ) ? (string) $card['url'] : '',
		'alt'      => $alt,
		'label'    => $label,
		'badge'    => isset( $card['badge'] ) ? (string) $card['badge'] : '',
		'meta'     => isset( $card['meta'] ) ? (string) $card['meta'] : '',
		'is_video' => in_array( $ext, array( 'mp4', 'webm', 'ogv', 'ogg' ), true ),
	);
}

/**
 * Cards defined on this instance, from the shortcode or the block.
 *
 * `ids` accepts attachment ids or plain URLs, mixed freely. The parallel lists
 * are pipe-separated because captions routinely contain commas.
 *
 * @param array $atts Shortcode attributes.
 * @return array Cards; empty when this instance defines none.
 */
function carousel3d_cards_from_atts( $atts ) {
	$raw = isset( $atts['ids'] ) ? trim( (string) $atts['ids'] ) : '';
	if ( '' === $raw ) {
		return array();
	}

	$items  = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' );
	$labels = carousel3d_split_list( isset( $atts['labels'] ) ? $atts['labels'] : '' );
	$links  = carousel3d_split_list( isset( $atts['links'] ) ? $atts['links'] : '' );
	$alts   = carousel3d_split_list( isset( $atts['alts'] ) ? $atts['alts'] : '' );

	$cards = array();
	foreach ( array_values( $items ) as $i => $item ) {
		if ( ctype_digit( $item ) ) {
			$img = wp_get_attachment_url( (int) $item );
			if ( ! $img ) {
				continue; // Attachment deleted since the shortcode was written.
			}
			$alt = isset( $alts[ $i ] ) ? $alts[ $i ]
				: (string) get_post_meta( (int) $item, '_wp_attachment_image_alt', true );
		} else {
			$img = $item;
			$alt = isset( $alts[ $i ] ) ? $alts[ $i ] : '';
		}

		$card = carousel3d_normalise_card(
			array(
				'img'   => $img,
				'url'   => isset( $links[ $i ] ) ? $links[ $i ] : '',
				'alt'   => $alt,
				'label' => isset( $labels[ $i ] ) ? $labels[ $i ] : '',
			)
		);
		if ( $card ) {
			$cards[] = $card;
		}
	}

	return array_slice( $cards, 0, CAROUSEL3D_MAX_CARDS );
}

/**
 * Splits a pipe-separated attribute into a list.
 *
 * @param string $value Raw attribute.
 * @return array Trimmed parts.
 */
function carousel3d_split_list( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return array();
	}
	return array_map( 'trim', explode( '|', $value ) );
}

/**
 * Cards from the site-wide Customizer settings.
 *
 * Only slots that actually have an image are returned — an unset slot is
 * skipped, not silently backfilled with a demo photo.
 *
 * @return array Cards; empty when nothing has been configured.
 */
function carousel3d_cards_from_settings() {
	$count = max( 2, min( CAROUSEL3D_MAX_CARDS, (int) carousel3d_get( 'card_count' ) ) );
	$cards = array();

	for ( $i = 1; $i <= $count; $i++ ) {
		$card = carousel3d_normalise_card(
			array(
				'img'   => carousel3d_get( "card_{$i}_image" ),
				'url'   => carousel3d_get( "card_{$i}_url" ),
				'alt'   => carousel3d_get( "card_{$i}_alt" ),
				'label' => carousel3d_get( "card_{$i}_label" ),
			)
		);
		if ( $card ) {
			$cards[] = $card;
		}
	}

	return $cards;
}

/**
 * Builds the card list for one carousel.
 *
 * Order of precedence: whatever an add-on supplies, then cards defined on this
 * instance, then the site-wide Customizer cards, then the bundled demo images.
 *
 * @param array $atts Shortcode attributes for this instance.
 * @return array Cards.
 */
function carousel3d_collect_cards( $atts = array() ) {
	/**
	 * Short-circuits the card list.
	 *
	 * Return an array to supply the cards yourself and skip every built-in
	 * source. This is the hook an add-on uses to pull products from a shop.
	 *
	 * @param array|null $cards Null by default.
	 * @param array      $atts  Attributes of the carousel being rendered.
	 */
	$cards = apply_filters( 'carousel3d_pre_cards', null, $atts );

	if ( ! is_array( $cards ) ) {
		$cards = carousel3d_cards_from_atts( $atts );

		if ( empty( $cards ) ) {
			$cards = carousel3d_cards_from_settings();
		}
		if ( empty( $cards ) ) {
			$cards = carousel3d_demo_cards();
		}
	}

	/**
	 * Filters the finished card list.
	 *
	 * @param array $cards Cards about to be rendered.
	 * @param array $atts  Attributes of the carousel being rendered.
	 */
	return apply_filters( 'carousel3d_cards', $cards, $atts );
}

/** Placeholder content shown on a fresh install so the shortcode is never blank. */
function carousel3d_demo_cards() {
	$demo = array( 'collage_1.webp', 'collage_2.webp', 'collage_3.webp', 'collage_4.webp' );
	$out  = array();
	foreach ( $demo as $n => $file ) {
		$out[] = array(
			'img'      => CAROUSEL3D_URL . 'assets/img/' . $file,
			'url'      => '',
			'alt'      => __( 'Demo image — replace in the Customizer', '3d-product-carousel' ),
			/* translators: %d: sequence number of the bundled demo image. */
			'label'    => sprintf( __( 'Demo %d', '3d-product-carousel' ), $n + 1 ),
			'badge'    => '',
			'meta'     => '',
			'is_video' => false,
		);
	}
	return $out;
}
