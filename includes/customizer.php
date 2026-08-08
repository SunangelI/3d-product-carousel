<?php
/**
 * Customizer registration.
 *
 * Everything is stored in a single option (CAROUSEL3D_OPTION) rather than theme mods,
 * so the carousel survives a theme switch.
 *
 * @package Carousel3D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clamps an angle to the range the ring can actually take.
 *
 * @param mixed $value Raw value from the Customizer.
 * @return int Degrees between -45 and 45.
 */
function carousel3d_sanitize_angle( $value ) {
	return max( -45, min( 45, (int) $value ) );
}

/**
 * Clamps a percentage to 0-100.
 *
 * @param mixed $value Raw value from the Customizer.
 * @return int Percentage.
 */
function carousel3d_sanitize_percent( $value ) {
	return max( 0, min( 100, (int) $value ) );
}

/**
 * Validates an aspect ratio against the allowed list.
 *
 * This runs before the value ever reaches the stylesheet, so an unexpected
 * string cannot break out of the CSS rule it is written into.
 *
 * @param mixed $value Raw value from the Customizer.
 * @return string One of the ratios offered in the select, or the default.
 */
function carousel3d_sanitize_aspect( $value ) {
	$choices = carousel3d_aspect_choices();
	return isset( $choices[ $value ] ) ? $value : '225/260';
}

/**
 * Clamps the number of card slots to what the geometry supports.
 *
 * @param mixed $value Raw value from the Customizer.
 * @return int Between 2 and CAROUSEL3D_MAX_CARDS.
 */
function carousel3d_sanitize_card_count( $value ) {
	return max( 2, min( CAROUSEL3D_MAX_CARDS, (int) $value ) );
}

/**
 * Clamps the idle sway amplitude.
 *
 * @param mixed $value Raw value from the Customizer.
 * @return float Degrees between 0 and 8.
 */
function carousel3d_sanitize_wobble( $value ) {
	return max( 0, min( 8, (float) $value ) );
}

/**
 * Registers one setting and its control.
 *
 * Every setting is a key inside the plugin's single option array, rather than a
 * theme mod, so the configuration survives a theme switch.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @param string               $key          Key within the option array.
 * @param array                $args         Overrides for add_setting().
 * @param array                $control      Control arguments. A '_type' of
 *                                           'color' or 'image' picks the
 *                                           matching control class.
 * @return void
 */
function carousel3d_add( $wp_customize, $key, $args, $control ) {
	$defaults = carousel3d_defaults();

	$wp_customize->add_setting(
		CAROUSEL3D_OPTION . '[' . $key . ']',
		wp_parse_args(
			$args,
			array(
				'type'              => 'option',
				'default'           => isset( $defaults[ $key ] ) ? $defaults[ $key ] : '',
				'capability'        => 'edit_theme_options',
				'transport'         => 'refresh',
				'sanitize_callback' => 'sanitize_text_field',
			)
		)
	);

	$id = CAROUSEL3D_OPTION . '[' . $key . ']';

	if ( isset( $control['_type'] ) && 'color' === $control['_type'] ) {
		unset( $control['_type'] );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, $control ) );
	} elseif ( isset( $control['_type'] ) && 'image' === $control['_type'] ) {
		unset( $control['_type'] );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, $control ) );
	} else {
		$wp_customize->add_control( $id, $control );
	}
}

add_action(
	'customize_register',
	function ( $wp_customize ) {

		$wp_customize->add_panel(
			'c3d_panel',
			array(
				'title'       => __( '3D Product Carousel', '3d-product-carousel' ),
				'description' => __( 'Place the carousel with the [carousel_3d] shortcode.', '3d-product-carousel' ),
				'priority'    => 120,
			)
		);

		/* ── Content ─────────────────────────────────────────── */
		$wp_customize->add_section(
			'c3d_content',
			array(
				'title' => __( 'Content', '3d-product-carousel' ),
				'panel' => 'c3d_panel',
			)
		);

		carousel3d_add(
			$wp_customize,
			'title',
			array(),
			array(
				'label'   => __( 'Section title', '3d-product-carousel' ),
				'section' => 'c3d_content',
			)
		);

		carousel3d_add(
			$wp_customize,
			'subtitle',
			array(),
			array(
				'label'   => __( 'Section subtitle', '3d-product-carousel' ),
				'section' => 'c3d_content',
			)
		);

		carousel3d_add(
			$wp_customize,
			'hint',
			array(),
			array(
				'label'       => __( 'Interaction hint', '3d-product-carousel' ),
				'description' => __( 'Shown under the carousel. Leave empty to hide.', '3d-product-carousel' ),
				'section'     => 'c3d_content',
			)
		);

		carousel3d_add(
			$wp_customize,
			'card_count',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_card_count',
			),
			array(
				'label'       => __( 'Number of card slots', '3d-product-carousel' ),
				'description' => __( 'Slots without an image are skipped, so you can show as few as two products.', '3d-product-carousel' ),
				'section'     => 'c3d_content',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 2,
					'max'  => CAROUSEL3D_MAX_CARDS,
					'step' => 1,
				),
			)
		);

		/* ── Layout ──────────────────────────────────────────── */
		$wp_customize->add_section(
			'c3d_layout',
			array(
				'title' => __( 'Layout & motion', '3d-product-carousel' ),
				'panel' => 'c3d_panel',
			)
		);

		carousel3d_add(
			$wp_customize,
			'card_aspect',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_aspect',
			),
			array(
				'label'   => __( 'Photo aspect ratio', '3d-product-carousel' ),
				'section' => 'c3d_layout',
				'type'    => 'select',
				'choices' => carousel3d_aspect_choices(),
			)
		);

		carousel3d_add(
			$wp_customize,
			'ring_tilt',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_angle',
			),
			array(
				'label'       => __( 'Ring tilt (°)', '3d-product-carousel' ),
				'description' => __( 'Negative looks down on the ring, positive looks up.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => -45,
					'max'  => 45,
					'step' => 1,
				),
			)
		);

		carousel3d_add(
			$wp_customize,
			'ring_lean',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_angle',
			),
			array(
				'label'       => __( 'Ring lean (°)', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => -45,
					'max'  => 45,
					'step' => 1,
				),
			)
		);

		carousel3d_add(
			$wp_customize,
			'spread',
			array(
				'sanitize_callback' => 'absint',
			),
			array(
				'label'       => __( 'Spacing between cards (%)', '3d-product-carousel' ),
				'description' => __( '100 = cards touch edge to edge. Higher pushes them apart.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => 100,
					'max'  => 200,
					'step' => 2,
				),
			)
		);

		carousel3d_add(
			$wp_customize,
			'wobble',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_wobble',
			),
			array(
				'label'       => __( 'Idle sway (°)', '3d-product-carousel' ),
				'description' => __( 'A gentle breathing motion. 0 turns it off — recommended above 2.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 8,
					'step' => 0.2,
				),
			)
		);

		carousel3d_add(
			$wp_customize,
			'spin_speed',
			array(
				'sanitize_callback' => 'absint',
			),
			array(
				'label'       => __( 'Auto-rotation speed', '3d-product-carousel' ),
				'description' => __( 'Degrees per frame ×100. 12 ≈ one turn per 50 seconds.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 60,
					'step' => 1,
				),
			)
		);

		carousel3d_add(
			$wp_customize,
			'autoplay',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'label'       => __( 'Rotate automatically', '3d-product-carousel' ),
				'description' => __( 'Always disabled for visitors who ask for reduced motion.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'checkbox',
			)
		);

		carousel3d_add(
			$wp_customize,
			'snap',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'label'   => __( 'Snap to the nearest card', '3d-product-carousel' ),
				'section' => 'c3d_layout',
				'type'    => 'checkbox',
			)
		);

		carousel3d_add(
			$wp_customize,
			'fill_ring',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'label'       => __( 'Fill the ring with repeats', '3d-product-carousel' ),
				'description' => __( 'With fewer than five cards the ring is padded by repeating them, so it does not look half empty. Turn off to show each product exactly once.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'checkbox',
			)
		);

		carousel3d_add(
			$wp_customize,
			'hide_back',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'label'       => __( 'Hide cards on the far side', '3d-product-carousel' ),
				'description' => __( 'Off: the back cards stay visible, dimmed and blurred. On: they disappear entirely.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'checkbox',
			)
		);

		carousel3d_add(
			$wp_customize,
			'floating_objects',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'label'       => __( 'Floating objects mode', '3d-product-carousel' ),
				'description' => __( 'Drops the card frame and fits the whole image. For cut-out PNGs.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'checkbox',
			)
		);

		carousel3d_add(
			$wp_customize,
			'shadow',
			array(
				'sanitize_callback' => 'carousel3d_sanitize_percent',
			),
			array(
				'label'       => __( 'Shadow strength (%)', '3d-product-carousel' ),
				'description' => __( 'Applies to both the card shadow and the contact shadow on the ground.', '3d-product-carousel' ),
				'section'     => 'c3d_layout',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 100,
					'step' => 5,
				),
			)
		);

		/* ── Colours ─────────────────────────────────────────── */
		$wp_customize->add_section(
			'c3d_colors',
			array(
				'title' => __( 'Colours', '3d-product-carousel' ),
				'panel' => 'c3d_panel',
			)
		);

		$colors = array(
			'color_bg_start'      => __( 'Background — top', '3d-product-carousel' ),
			'color_bg_mid'        => __( 'Background — middle', '3d-product-carousel' ),
			'color_bg_end'        => __( 'Background — bottom', '3d-product-carousel' ),
			'color_card_bg'       => __( 'Card background', '3d-product-carousel' ),
			'color_card_border'   => __( 'Card border', '3d-product-carousel' ),
			'color_accent'        => __( 'Accent', '3d-product-carousel' ),
			'color_title'         => __( 'Title', '3d-product-carousel' ),
			'color_label_text'    => __( 'Label text', '3d-product-carousel' ),
			'color_circle_top'    => __( 'Top blob', '3d-product-carousel' ),
			'color_circle_bottom' => __( 'Bottom blob', '3d-product-carousel' ),
		);
		foreach ( $colors as $key => $label ) {
			carousel3d_add(
				$wp_customize,
				$key,
				array(
					'sanitize_callback' => 'sanitize_hex_color',
				),
				array(
					'_type'   => 'color',
					'label'   => $label,
					'section' => 'c3d_colors',
				)
			);
		}

		$opacities = array(
			'opacity_card_border'   => __( 'Card border opacity (%)', '3d-product-carousel' ),
			'opacity_circle_top'    => __( 'Top blob opacity (%)', '3d-product-carousel' ),
			'opacity_circle_bottom' => __( 'Bottom blob opacity (%)', '3d-product-carousel' ),
		);
		foreach ( $opacities as $key => $label ) {
			carousel3d_add(
				$wp_customize,
				$key,
				array(
					'sanitize_callback' => 'carousel3d_sanitize_percent',
				),
				array(
					'label'       => $label,
					'section'     => 'c3d_colors',
					'type'        => 'range',
					'input_attrs' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				)
			);
		}

		/* ── Cards ───────────────────────────────────────────── */
		$wp_customize->add_section(
			'c3d_cards',
			array(
				'title'       => __( 'Cards', '3d-product-carousel' ),
				'description' => __( 'Leave the image empty to skip a slot entirely.', '3d-product-carousel' ),
				'panel'       => 'c3d_panel',
			)
		);

		for ( $i = 1; $i <= CAROUSEL3D_MAX_CARDS; $i++ ) {
			carousel3d_add(
				$wp_customize,
				"card_{$i}_image",
				array(
					'sanitize_callback' => 'esc_url_raw',
				),
				array(
					'_type'   => 'image',
					/* translators: %d: card number. */
					'label'   => sprintf( __( 'Card %d — image or video', '3d-product-carousel' ), $i ),
					'section' => 'c3d_cards',
				)
			);

			carousel3d_add(
				$wp_customize,
				"card_{$i}_label",
				array(),
				array(
					/* translators: %d: card number. */
					'label'   => sprintf( __( 'Card %d — caption', '3d-product-carousel' ), $i ),
					'section' => 'c3d_cards',
				)
			);

			carousel3d_add(
				$wp_customize,
				"card_{$i}_url",
				array(
					'sanitize_callback' => 'esc_url_raw',
				),
				array(
					/* translators: %d: card number. */
					'label'   => sprintf( __( 'Card %d — link', '3d-product-carousel' ), $i ),
					'section' => 'c3d_cards',
					'type'    => 'url',
				)
			);

			carousel3d_add(
				$wp_customize,
				"card_{$i}_alt",
				array(),
				array(
					/* translators: %d: card number. */
					'label'       => sprintf( __( 'Card %d — alt text', '3d-product-carousel' ), $i ),
					'description' => __( 'Falls back to the caption when empty.', '3d-product-carousel' ),
					'section'     => 'c3d_cards',
				)
			);
		}
	}
);
