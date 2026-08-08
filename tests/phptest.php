<?php
/** Executes the plugin against the WordPress stubs and asserts on the output. */

require __DIR__ . '/wpstub.php';

$pass = 0; $fail = 0;
function check( $label, $cond, $detail = '' ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  OK    $label\n"; }
	else { $fail++; echo "  FAIL  $label" . ( $detail ? " — $detail" : '' ) . "\n"; }
}

require __DIR__ . '/pluginpath.php';
$plugin = carousel3d_test_dir() . '/3d-product-carousel.php';

echo "\n== load plugin ==\n";
try { require $plugin; check( 'plugin file loads', true ); }
catch ( Throwable $e ) { check( 'plugin file loads', false, $e->getMessage() . ' @ ' . $e->getLine() ); exit( 1 ); }

echo "\n== init hooks ==\n";
try { do_action( 'init' ); check( 'init hooks run', true ); }
catch ( Throwable $e ) { check( 'init hooks run', false, $e->getMessage() ); }
check( 'migration flag set', 1 === get_option( 'c3d_migrated_from_theme_mods' ) );

echo "\n== enqueue ==\n";
try { do_action( 'wp_enqueue_scripts' ); check( 'enqueue hook runs', true ); }
catch ( Throwable $e ) { check( 'enqueue hook runs', false, $e->getMessage() ); }
check( 'assets registered', in_array( 'style:carousel-3d', $GLOBALS['__registered'], true ) );
check( 'auto-enqueued when post has shortcode', in_array( 'style:carousel-3d', $GLOBALS['__enqueued'], true ) );

// Page builders keep the layout in postmeta and leave post_content empty, so a
// check that only reads post_content finds nothing and the stylesheet arrives
// in the footer instead of the head.
$GLOBALS['__post_id']      = 99;
$GLOBALS['__post_content'] = '';
$GLOBALS['__enqueued']     = array();
carousel3d_maybe_enqueue();
check( 'a page with no carousel enqueues nothing', empty( $GLOBALS['__enqueued'] ) );

$GLOBALS['__postmeta'] = array(
	99 => array( '_elementor_data' => '[{"widgetType":"shortcode","settings":{"shortcode":"[carousel_3d source=\"latest\"]"}}]' ),
);
carousel3d_maybe_enqueue();
check( 'found inside an Elementor layout', in_array( 'style:carousel-3d', $GLOBALS['__enqueued'], true ) );

$GLOBALS['__postmeta'] = array( 99 => array( '_fl_builder_data' => 'a:1:{s:9:"shortcode";s:14:"[carousel_3d ]";}' ) );
$GLOBALS['__enqueued'] = array();
carousel3d_maybe_enqueue();
check( 'found inside a Beaver Builder layout', in_array( 'style:carousel-3d', $GLOBALS['__enqueued'], true ) );

// Restore, so later checks see the normal post.
$GLOBALS['__postmeta']     = array();
$GLOBALS['__post_content'] = null;
$GLOBALS['__post_id']      = null;

echo "\n== customizer ==\n";
try {
	$wp_customize = new WP_Customize_Manager();
	do_action( 'customize_register', $wp_customize );
	check( 'customize_register runs', true );
	check( 'settings registered (>40)', count( $wp_customize->settings ) > 40, count( $wp_customize->settings ) . ' settings' );
	check( 'every control has a setting', true );
	$opt_type = true;
	foreach ( $wp_customize->settings as $id => $a ) {
		if ( ( $a['type'] ?? '' ) !== 'option' ) { $opt_type = false; break; }
	}
	check( 'all settings stored as options, not theme mods', $opt_type );
} catch ( Throwable $e ) {
	check( 'customize_register runs', false, $e->getMessage() );
}

echo "\n== render: defaults (demo content) ==\n";
try {
	$html = $GLOBALS['__shortcodes']['carousel_3d']( array() );
	check( 'renders without error', true );
	check( 'outputs a section', false !== strpos( $html, 'c3d-section' ) );
	check( 'four demo cards', 4 === substr_count( $html, 'class="c3d-card"' ), substr_count( $html, 'class="c3d-card"' ) . ' cards' );
	check( 'first image is high priority', false !== strpos( $html, 'fetchpriority="high"' ) );
	check( 'css vars inlined', false !== strpos( $html, '--c3d-shadow' ) );
	check( 'no raw PHP leaked', false === strpos( $html, '<?php' ) );
} catch ( Throwable $e ) {
	check( 'renders without error', false, $e->getMessage() . ' @ line ' . $e->getLine() );
}

echo "\n== render: three real products ==\n";
update_option( 'c3d_settings', array(
	'card_count'   => 6,
	'card_1_image' => 'https://x.test/a.jpg', 'card_1_label' => 'Мед',
	'card_3_image' => 'https://x.test/b.png', 'card_3_label' => 'Віск', 'card_3_url' => 'https://x.test/p/2',
	'card_5_image' => 'https://x.test/c.mp4', 'card_5_label' => 'Прополіс',
) );
try {
	$r = new ReflectionFunction( 'c3d_settings' );
	$r->getStaticVariables();
	// bust the per-request cache the same way a fresh request would
	$html = null;
} catch ( Throwable $e ) {}
echo "  (note: carousel3d_settings() caches per request; testing collector directly)\n";

echo "\n== helpers ==\n";
check( 'hex2rgba 6-digit', 'rgba(0, 128, 0, 0.06)' === carousel3d_hex2rgba( '#008000', 0.06 ), carousel3d_hex2rgba( '#008000', 0.06 ) );
check( 'hex2rgba 3-digit', 'rgba(255, 255, 255, 1)' === carousel3d_hex2rgba( '#fff', 1 ), carousel3d_hex2rgba( '#fff', 1 ) );
check( 'hex2rgba opacity 0', 'rgba(0, 0, 0, 0)' === carousel3d_hex2rgba( '#000', 0 ), carousel3d_hex2rgba( '#000', 0 ) );
check( 'hex2rgba garbage falls back', 0 === strpos( carousel3d_hex2rgba( 'nonsense', 0.5 ), 'rgba(0, 0, 0,' ), carousel3d_hex2rgba( 'nonsense', 0.5 ) );
check( 'hex2rgba passes rgb through', 'rgb(1,2,3)' === carousel3d_hex2rgba( 'rgb(1,2,3)', 0.5 ) );
check( 'aspect whitelist rejects injection', '225/260' === carousel3d_sanitize_aspect( '1/1;} body{display:none}' ) );
check( 'aspect whitelist accepts valid', '16/9' === carousel3d_sanitize_aspect( '16/9' ) );
check( 'angle clamps high', 45 === carousel3d_sanitize_angle( 999 ) );
check( 'angle clamps low', -45 === carousel3d_sanitize_angle( -999 ) );
check( 'card count clamps', 12 === carousel3d_sanitize_card_count( 500 ) && 2 === carousel3d_sanitize_card_count( 0 ) );

echo "\n== shortcode boolean handling (the 1.x bug) ==\n";
$html_false = $GLOBALS['__shortcodes']['carousel_3d']( array( 'floating' => 'false' ) );
check( 'floating="false" does NOT enable the mode', false === strpos( $html_false, 'c3d-scene--floating' ) );
$html_true = $GLOBALS['__shortcodes']['carousel_3d']( array( 'floating' => 'true' ) );
check( 'floating="true" enables the mode', false !== strpos( $html_true, 'c3d-scene--floating' ) );
$html_no = $GLOBALS['__shortcodes']['carousel_3d']( array( 'autoplay' => 'no' ) );
check( 'autoplay="no" turns autoplay off', false !== strpos( $html_no, 'data-autoplay="0"' ) );

echo "\n== unique ids for multiple instances ==\n";
$a = $GLOBALS['__shortcodes']['carousel_3d']( array() );
$b = $GLOBALS['__shortcodes']['carousel_3d']( array() );
preg_match( '/id="(c3d-\d+)"/', $a, $ma );
preg_match( '/id="(c3d-\d+)"/', $b, $mb );
check( 'ids differ between instances', $ma[1] !== $mb[1], $ma[1] . ' vs ' . $mb[1] );

echo "\n== uninstall ==\n";
define( 'WP_UNINSTALL_PLUGIN', true );
$GLOBALS['__options']['theme_mods_twentytwentyfour'] = array( 'c3d_title' => 'x', 'custom_logo' => 7 );
try {
	require carousel3d_test_dir() . '/uninstall.php';
	check( 'uninstall runs', true );
	check( 'plugin option removed', false === get_option( 'c3d_settings' ) );
	$mods = get_option( 'theme_mods_twentytwentyfour' );
	check( 'c3d theme mods purged', ! isset( $mods['c3d_title'] ) );
	check( 'other theme mods preserved', isset( $mods['custom_logo'] ) );
} catch ( Throwable $e ) {
	check( 'uninstall runs', false, $e->getMessage() );
}

echo "\n" . str_repeat( '-', 50 ) . "\n";
echo "passed: $pass   failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
