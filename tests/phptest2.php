<?php
/**
 * Scenario tests that must seed state before the plugin loads, because
 * carousel3d_settings() caches per request. Run one scenario per process.
 *   php phptest2.php products
 *   php phptest2.php migration
 *   php phptest2.php empty
 */

require __DIR__ . '/wpstub.php';
$scenario = $argv[1] ?? 'products';
require __DIR__ . '/pluginpath.php';
$plugin = carousel3d_test_dir() . '/3d-product-carousel.php';

$pass = 0; $fail = 0;
function check( $label, $cond, $detail = '' ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  OK    $label\n"; }
	else { $fail++; echo "  FAIL  $label" . ( $detail ? " — $detail" : '' ) . "\n"; }
}

if ( 'products' === $scenario ) {
	echo "== three products in slots 1, 3, 5 (others empty) ==\n";
	$GLOBALS['__options']['c3d_settings'] = array(
		'card_count'   => 8,
		'card_1_image' => 'https://x.test/med.jpg',   'card_1_label' => 'Мед',
		'card_3_image' => 'https://x.test/vosk.png',  'card_3_label' => 'Віск', 'card_3_url' => 'https://x.test/p/2',
		'card_5_image' => 'https://x.test/prop.mp4',  'card_5_label' => 'Прополіс',
	);
	$GLOBALS['__options']['c3d_migrated_from_theme_mods'] = 1;
	require $plugin;

	$cards = carousel3d_collect_cards();
	check( 'exactly three cards', 3 === count( $cards ), count( $cards ) . ' cards' );
	check( 'no demo image slipped in', ! preg_grep( '/collage_/', array_column( $cards, 'img' ) ) );
	check( 'labels preserved', array( 'Мед', 'Віск', 'Прополіс' ) === array_column( $cards, 'label' ) );
	check( 'mp4 detected as video', true === $cards[2]['is_video'] );
	check( 'jpg not treated as video', false === $cards[0]['is_video'] );
	check( 'alt falls back to label', 'Мед' === $cards[0]['alt'] );

	$html = $GLOBALS['__shortcodes']['carousel_3d']( array() );
	check( 'html has three cards', 3 === substr_count( $html, 'class="c3d-card"' ), substr_count( $html, 'class="c3d-card"' ) . '' );
	check( 'video element rendered', false !== strpos( $html, '<video' ) );
	check( 'product link rendered', false !== strpos( $html, 'https://x.test/p/2' ) );
	check( 'empty link becomes #', false !== strpos( $html, 'href="#"' ) );
}

if ( 'migration' === $scenario ) {
	echo "== migration from 1.x theme mods ==\n";
	$GLOBALS['__mods'] = array(
		'c3d_title'            => 'Стара назва',
		'c3d_color_accent'     => '#ff0000',
		'c3d_card_1_image'     => 'https://x.test/old1.jpg',
		'c3d_card_1_label'     => 'Старий товар',
		'c3d_card_2_url'       => '#',
		'c3d_floating_objects' => true,
	);
	require $plugin;
	do_action( 'init' );

	$saved = get_option( 'c3d_settings' );
	check( 'option created', is_array( $saved ) );
	check( 'title migrated', 'Стара назва' === ( $saved['title'] ?? null ) );
	check( 'accent colour migrated', '#ff0000' === ( $saved['color_accent'] ?? null ) );
	check( 'card image migrated', 'https://x.test/old1.jpg' === ( $saved['card_1_image'] ?? null ) );
	check( 'card label migrated', 'Старий товар' === ( $saved['card_1_label'] ?? null ) );
	check( 'placeholder "#" url not migrated', ! isset( $saved['card_2_url'] ) );
	check( 'flag set so it runs once', 1 === get_option( 'c3d_migrated_from_theme_mods' ) );

	$before = $GLOBALS['__options']['c3d_settings'];
	do_action( 'init' );
	check( 'second run is a no-op', $before === $GLOBALS['__options']['c3d_settings'] );
}

if ( 'multisite-uninstall' === $scenario ) {
	echo "== uninstall across a network ==\n";
	// Three sites, each with its own settings and a stray theme mod.
	$GLOBALS['__sites']        = array( 1, 2, 3 );
	$GLOBALS['__current_blog'] = 1;
	$GLOBALS['__site_options'] = array();
	foreach ( array( 1, 2, 3 ) as $id ) {
		$GLOBALS['__site_options'][ $id ] = array(
			'c3d_settings'                 => array( 'title' => "site {$id}" ),
			'c3d_migrated_from_theme_mods' => 1,
			'theme_mods_twentytwentyfour'  => array( 'c3d_title' => 'old', 'custom_logo' => $id ),
		);
	}
	$GLOBALS['__options'] = $GLOBALS['__site_options'][1];

	define( 'WP_UNINSTALL_PLUGIN', true );
	require carousel3d_test_dir() . '/uninstall.php';

	// Fold the current site's state back so every site can be inspected.
	$GLOBALS['__site_options'][ $GLOBALS['__current_blog'] ] = $GLOBALS['__options'];

	foreach ( array( 1, 2, 3 ) as $id ) {
		$o = $GLOBALS['__site_options'][ $id ];
		check( "site {$id}: settings removed", ! isset( $o['c3d_settings'] ) );
		check( "site {$id}: migration flag removed", ! isset( $o['c3d_migrated_from_theme_mods'] ) );
		check( "site {$id}: c3d theme mod purged", ! isset( $o['theme_mods_twentytwentyfour']['c3d_title'] ) );
		check( "site {$id}: other theme mods kept", isset( $o['theme_mods_twentytwentyfour']['custom_logo'] ) );
	}
}

if ( 'per-instance' === $scenario ) {
	echo "== cards defined on the carousel itself ==\n";
	// Site-wide cards exist too, so this also proves per-instance wins.
	$GLOBALS['__options']['c3d_settings'] = array(
		'card_count'   => 4,
		'card_1_image' => 'https://x.test/global1.webp',
		'card_1_label' => 'Глобальна 1',
		'card_2_image' => 'https://x.test/global2.webp',
		'card_2_label' => 'Глобальна 2',
	);
	$GLOBALS['__options']['c3d_migrated_from_theme_mods'] = 1;
	$GLOBALS['__attachments'] = array(
		77 => array( 'url' => 'https://x.test/attach-77.webp', '_wp_attachment_image_alt' => 'alt з медіатеки' ),
		88 => array( 'url' => 'https://x.test/attach-88.webp' ),
	);
	require $plugin;
	$sc = $GLOBALS['__shortcodes']['carousel_3d'];

	$cards = carousel3d_cards_from_atts( array( 'ids' => '77,88', 'labels' => 'Мед|Віск' ) );
	check( 'two cards from attachment ids', 2 === count( $cards ) );
	check( 'id resolved to a url', 'https://x.test/attach-77.webp' === $cards[0]['img'] );
	check( 'alt taken from the media library', 'alt з медіатеки' === $cards[0]['alt'] );
	check( 'label applied', 'Віск' === $cards[1]['label'] );

	$mixed = carousel3d_cards_from_atts( array( 'ids' => '77,https://x.test/plain.webp' ) );
	check(
		'ids and urls can be mixed',
		2 === count( $mixed ) && 'https://x.test/plain.webp' === $mixed[1]['img']
	);

	$gone = carousel3d_cards_from_atts( array( 'ids' => '77,9999' ) );
	check( 'deleted attachment is skipped, not rendered blank', 1 === count( $gone ) );

	$pipes = carousel3d_cards_from_atts(
		array(
			'ids'    => '77,88',
			'labels' => 'Мед, натуральний|Віск',
			'links'  => 'https://x.test/p/1|',
		)
	);
	check( 'commas survive inside a caption', 'Мед, натуральний' === $pipes[0]['label'] );
	check( 'a blank link stays blank', '' === $pipes[1]['url'] );

	$html = $sc( array( 'ids' => '77,88', 'labels' => 'Мед|Віск' ) );
	check( 'shortcode renders the instance cards', 2 === substr_count( $html, 'class="c3d-card"' ) );
	check( 'and not the site-wide ones', false === strpos( $html, 'Глобальна' ) );

	$html2 = $sc( array() );
	check( 'without ids it still uses the site-wide cards', false !== strpos( $html2, 'Глобальна 1' ) );
}

if ( 'extension-point' === $scenario ) {
	echo "== the filters an add-on hooks into ==\n";
	$GLOBALS['__options']['c3d_migrated_from_theme_mods'] = 1;
	require $plugin;
	$sc = $GLOBALS['__shortcodes']['carousel_3d'];

	// An add-on has to register its own attribute first, or shortcode_atts()
	// drops it before the renderer ever sees it.
	add_filter(
		'carousel3d_shortcode_defaults',
		function ( $defaults ) {
			$defaults['source'] = '';
			return $defaults;
		}
	);

	// carousel3d_pre_cards then takes over completely — this is what the shop
	// add-on will do.
	add_filter(
		'carousel3d_pre_cards',
		function ( $cards, $atts ) {
			if ( 'bestsellers' !== ( $atts['source'] ?? '' ) ) {
				return $cards;
			}
			return array(
				carousel3d_normalise_card(
					array(
						'img'   => 'https://x.test/product.webp',
						'label' => 'Товар із крамниці',
						'badge' => '-20%',
						'meta'  => '<del>500 грн</del> <ins>400 грн</ins>',
					)
				),
			);
		},
		10,
		2
	);

	$html = $sc( array( 'source' => 'bestsellers' ) );
	check( 'add-on supplied the cards', 1 === substr_count( $html, 'class="c3d-card"' ) );
	check( 'demo images did not slip in', false === strpos( $html, 'collage_' ) );
	check(
		'badge rendered',
		false !== strpos( $html, 'c3d-card-badge' ) && false !== strpos( $html, '-20%' )
	);
	check( 'price html preserved', false !== strpos( $html, '<del>500 грн</del>' ) );

	// Without the marker the filter passes through and demo content returns.
	$plain = $sc( array() );
	check( 'filter left other carousels alone', false !== strpos( $plain, 'collage_' ) );

	// carousel3d_cards can adjust the finished list.
	add_filter(
		'carousel3d_cards',
		function ( $cards ) {
			return array_slice( $cards, 0, 2 );
		}
	);
	$trimmed = $sc( array() );
	check( 'final filter can trim the list', 2 === substr_count( $trimmed, 'class="c3d-card"' ) );
}

if ( 'empty' === $scenario ) {
	echo "== nothing configured at all ==\n";
	$GLOBALS['__options']['c3d_migrated_from_theme_mods'] = 1;
	require $plugin;
	$html = $GLOBALS['__shortcodes']['carousel_3d']( array() );
	check( 'falls back to demo content', 4 === substr_count( $html, 'class="c3d-card"' ) );
	check( 'demo cards are labelled as demo', false !== strpos( $html, 'Demo 1' ) );
	check( 'demo alt warns the user', false !== strpos( $html, 'replace in the Customizer' ) );
}

echo "  --> passed: $pass  failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
