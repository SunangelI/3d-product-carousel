<?php
/**
 * Minimal WordPress stubs — enough to actually execute the plugin's front-end
 * path and the customize_register callback, so runtime errors surface without
 * a full WordPress install. Every notice/warning is promoted to an exception.
 */

error_reporting( E_ALL );
set_error_handler( function ( $no, $str, $file, $line ) {
	throw new ErrorException( $str, 0, $no, $file, $line );
} );

define( 'ABSPATH', __DIR__ . '/fake-wp/' );

$GLOBALS['__actions']    = array();
$GLOBALS['__shortcodes'] = array();
$GLOBALS['__options']    = array();
$GLOBALS['__enqueued']   = array();
$GLOBALS['__registered'] = array();

function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__actions'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__filters'][ $hook ][] = $cb; }

/** Runs registered filters in order, threading the value through each. */
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['__filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}

function has_filter( $hook ) { return ! empty( $GLOBALS['__filters'][ $hook ] ); }

function wp_kses_post( $html ) { return $html; }

function wp_get_attachment_url( $id ) {
	return $GLOBALS['__attachments'][ $id ]['url'] ?? false;
}

function get_post_meta( $id, $key = '', $single = false ) {
	// Attachments first, then ordinary post meta — page builders keep their
	// layout here, which is where the enqueue check has to look.
	$v = $GLOBALS['__attachments'][ $id ][ $key ]
		?? ( $GLOBALS['__postmeta'][ $id ][ $key ] ?? '' );
	return $single ? $v : array( $v );
}
function do_action( $hook, ...$a ) {
	foreach ( $GLOBALS['__actions'][ $hook ] ?? array() as $cb ) { $cb( ...$a ); }
}
function add_shortcode( $tag, $cb ) { $GLOBALS['__shortcodes'][ $tag ] = $cb; }

function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }
function get_theme_mod( $k, $d = false ) { return $GLOBALS['__mods'][ $k ] ?? $d; }
function wp_get_themes() { return array( 'twentytwentyfour' => null ); }

/* Multisite. Single-site by default; set $GLOBALS['__sites'] to a list of ids
   to exercise the network path in uninstall.php. Each site gets its own option
   table, swapped in and out by switch_to_blog(). */
function is_multisite() { return ! empty( $GLOBALS['__sites'] ); }

function get_sites( $args = array() ) {
	$ids    = $GLOBALS['__sites'] ?? array();
	$offset = $args['offset'] ?? 0;
	$number = $args['number'] ?? count( $ids );
	return array_slice( $ids, $offset, $number );
}

function switch_to_blog( $site_id ) {
	$GLOBALS['__blog_stack'][]      = $GLOBALS['__current_blog'] ?? 1;
	$GLOBALS['__site_options'][ $GLOBALS['__current_blog'] ?? 1 ] = $GLOBALS['__options'];
	$GLOBALS['__current_blog']      = $site_id;
	$GLOBALS['__options']           = $GLOBALS['__site_options'][ $site_id ] ?? array();
	return true;
}

function restore_current_blog() {
	$prev = array_pop( $GLOBALS['__blog_stack'] );
	$GLOBALS['__site_options'][ $GLOBALS['__current_blog'] ] = $GLOBALS['__options'];
	$GLOBALS['__current_blog'] = $prev;
	$GLOBALS['__options']      = $GLOBALS['__site_options'][ $prev ] ?? array();
	return true;
}

function __( $t, $d = null ) { return $t; }
function _e( $t, $d = null ) { echo $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u ) { return (string) $u; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function absint( $v ) { return abs( (int) $v ); }

function sanitize_hex_color( $color ) {
	if ( '' === $color || null === $color ) { return ''; }
	return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : null;
}
function rest_sanitize_boolean( $v ) {
	if ( is_string( $v ) ) {
		$v = strtolower( $v );
		if ( in_array( $v, array( 'false', '0', 'no', '' ), true ) ) { return false; }
	}
	return (bool) $v;
}
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function shortcode_atts( $pairs, $atts, $tag = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function has_shortcode( $content, $tag ) { return false !== strpos( (string) $content, '[' . $tag ); }

function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/3d-product-carousel/'; }
function plugin_dir_path( $f ) { return rtrim( str_replace( '\\', '/', dirname( $f ) ), '/' ) . '/'; }
function plugin_basename( $f ) { return '3d-product-carousel/3d-product-carousel.php'; }
function load_plugin_textdomain( $d, $x = false, $p = '' ) { return true; }

function wp_register_style( $h, $s = '', $d = array(), $v = false, $m = 'all' ) { $GLOBALS['__registered'][] = "style:$h"; }
function wp_register_script( $h, $s = '', $d = array(), $v = false, $f = false ) { $GLOBALS['__registered'][] = "script:$h"; }
function wp_enqueue_style( $h, $s = '', $d = array(), $v = false, $m = 'all' ) { $GLOBALS['__enqueued'][] = "style:$h"; }
function wp_enqueue_script( $h, $s = '', $d = array(), $v = false, $f = false ) { $GLOBALS['__enqueued'][] = "script:$h"; }
function wp_localize_script( $h, $n, $data ) { return true; }

function is_singular( $t = '' ) { return true; }

function get_post( $p = null ) {
	return (object) array(
		'ID'           => $GLOBALS['__post_id'] ?? 7,
		'post_content' => $GLOBALS['__post_content'] ?? 'Hello [carousel_3d] world',
	);
}

/** Stand-in for the Customizer manager that records what the plugin registers. */
class WP_Customize_Manager {
	public $panels = array(), $sections = array(), $settings = array(), $controls = array();
	public function add_panel( $id, $a = array() ) { $this->panels[] = $id; }
	public function add_section( $id, $a = array() ) { $this->sections[] = $id; }
	public function add_setting( $id, $a = array() ) {
		if ( isset( $a['sanitize_callback'] ) && ! is_callable( $a['sanitize_callback'] ) ) {
			throw new Exception( "setting '$id': sanitize_callback '{$a['sanitize_callback']}' is not callable" );
		}
		$this->settings[ $id ] = $a;
	}
	public function add_control( $id, $a = array() ) {
		$key = is_object( $id ) ? $id->id : $id;
		if ( ! isset( $this->settings[ $key ] ) ) {
			throw new Exception( "control '$key' has no matching setting" );
		}
		$this->controls[] = $key;
	}
}
class WP_Customize_Control {
	public $id;
	public function __construct( $m, $id, $a = array() ) { $this->id = $id; }
}
class WP_Customize_Color_Control extends WP_Customize_Control {}
class WP_Customize_Image_Control extends WP_Customize_Control {}
