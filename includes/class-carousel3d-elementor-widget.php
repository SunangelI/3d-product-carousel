<?php
/**
 * The Elementor widget.
 *
 * @package Carousel3D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A 3D carousel with native Elementor controls.
 */
class Carousel3D_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug, as stored in the page's Elementor data.
	 *
	 * Do not rename: it is what identifies the widget inside every page already
	 * built with it.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'carousel_3d';
	}

	/**
	 * Name shown in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( '3D Product Carousel', '3d-product-carousel' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-slides';
	}

	/**
	 * Panel categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return apply_filters( 'carousel3d_elementor_categories', array( 'carousel-3d', 'general' ) );
	}

	/**
	 * Extra words the panel search should match.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return apply_filters(
			'carousel3d_elementor_keywords',
			array( 'carousel', '3d', 'gallery', 'slider', 'showcase', 'products' )
		);
	}

	/**
	 * Stylesheets this widget needs.
	 *
	 * Declaring them here is what makes Elementor load the carousel's CSS in the
	 * document head, in the editor as well as on the front end.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'carousel-3d' );
	}

	/**
	 * Scripts this widget needs.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'carousel-3d', 'carousel-3d-elementor' );
	}

	/**
	 * Whether Elementor may reuse one rendered copy across the page.
	 *
	 * It may not: each carousel gets a unique DOM id, and an add-on's sources
	 * can be position-dependent — "related products" resolves against the
	 * product being viewed, which differs per request.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Reads a carousel setting, falling back to the plugin default.
	 *
	 * The widget's controls start at whatever the site is configured to use, so
	 * a freshly dropped widget looks exactly like a bare shortcode.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value if the setting is unknown.
	 * @return mixed
	 */
	private function site_default( $key, $fallback ) {
		$value = carousel3d_get( $key );
		return null === $value ? $fallback : $value;
	}

	/**
	 * Builds the panel.
	 *
	 * @return void
	 */
	protected function register_controls() {
		/**
		 * Fires before the widget's own sections are registered.
		 *
		 * An add-on that supplies cards — from a shop, a post type, an API —
		 * adds its section here, and it lands at the top of the panel where the
		 * most important choice belongs. Register controls on the widget with
		 * Elementor's normal start_controls_section() / add_control() calls, and
		 * turn them into shortcode attributes through
		 * carousel3d_elementor_attributes.
		 *
		 * @since 2.2.0
		 *
		 * @param \Elementor\Widget_Base $widget The widget being built.
		 */
		do_action( 'carousel3d_elementor_controls', $this );

		$this->register_card_controls();
		$this->register_text_controls();
		$this->register_motion_controls();
	}

	/**
	 * The images this carousel shows.
	 *
	 * @return void
	 */
	private function register_card_controls() {
		$this->start_controls_section(
			'section_cards',
			array(
				'label'     => __( 'Cards', '3d-product-carousel' ),
				/**
				 * Filters when the image picker is shown.
				 *
				 * An add-on that can replace the cards wholesale uses this to
				 * hide the picker while its own source is active, so nobody
				 * picks images that will be ignored.
				 *
				 * @since 2.2.0
				 *
				 * @param array $condition An Elementor control condition.
				 */
				'condition' => apply_filters( 'carousel3d_elementor_cards_condition', array() ),
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'image',
			array(
				'label'   => __( 'Image', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array( 'url' => '' ),
			)
		);
		$repeater->add_control(
			'label',
			array(
				'label'       => __( 'Caption', '3d-product-carousel' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);
		$repeater->add_control(
			'url',
			array(
				'label'         => __( 'Links to', '3d-product-carousel' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'label_block'   => true,
				'options'       => false,
				'show_external' => false,
			)
		);
		$repeater->add_control(
			'alt',
			array(
				'label'       => __( 'Alt text', '3d-product-carousel' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'description' => __( 'Describes the image to a screen reader. Leave empty to use the one set in the media library.', '3d-product-carousel' ),
			)
		);

		$this->add_control(
			'cards',
			array(
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ label || "' . esc_js( __( 'Card', '3d-product-carousel' ) ) . '" }}}',
				'description' => __( 'Leave this empty to use the images set for the whole site, under Appearance → Customize → 3D Product Carousel.', '3d-product-carousel' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Headings above the ring.
	 *
	 * @return void
	 */
	private function register_text_controls() {
		$this->start_controls_section(
			'section_text',
			array( 'label' => __( 'Heading', '3d-product-carousel' ) )
		);

		$this->add_control(
			'subtitle',
			array(
				'label'   => __( 'Small line above', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => $this->site_default( 'subtitle', '' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Heading', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => $this->site_default( 'title', '' ),
			)
		);

		$this->add_control(
			'hint',
			array(
				'label'       => __( 'Interaction hint', '3d-product-carousel' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $this->site_default( 'hint', '' ),
				'description' => __( 'Shown under the carousel. Leave empty to hide it.', '3d-product-carousel' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Shape and movement of the ring.
	 *
	 * @return void
	 */
	private function register_motion_controls() {
		$this->start_controls_section(
			'section_motion',
			array( 'label' => __( 'Layout & motion', '3d-product-carousel' ) )
		);

		$this->add_control(
			'motion_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'These start at your site-wide settings, under Appearance → Customize → 3D Product Carousel.', '3d-product-carousel' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'aspect',
			array(
				'label'   => __( 'Card shape', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => carousel3d_aspect_choices(),
				'default' => $this->site_default( 'card_aspect', '225/260' ),
			)
		);

		$this->add_control(
			'tilt',
			array(
				'label'   => __( 'Tilt', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'range'   => array(
					'px' => array(
						'min'  => -45,
						'max'  => 45,
						'step' => 1,
					),
				),
				'default' => array( 'size' => (float) $this->site_default( 'ring_tilt', -10 ) ),
			)
		);

		$this->add_control(
			'lean',
			array(
				'label'   => __( 'Lean', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'range'   => array(
					'px' => array(
						'min'  => -45,
						'max'  => 45,
						'step' => 1,
					),
				),
				'default' => array( 'size' => (float) $this->site_default( 'ring_lean', 0 ) ),
			)
		);

		$this->add_control(
			'spread',
			array(
				'label'       => __( 'Spacing', '3d-product-carousel' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 1,
						'max'  => 2.5,
						'step' => 0.01,
					),
				),
				'default'     => array( 'size' => (float) $this->site_default( 'spread', 114 ) / 100 ),
				'description' => __( 'A multiplier. 1 packs the cards edge to edge; higher values open a gap.', '3d-product-carousel' ),
			)
		);

		$this->add_control(
			'wobble',
			array(
				'label'       => __( 'Idle sway', '3d-product-carousel' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 8,
						'step' => 0.1,
					),
				),
				'default'     => array( 'size' => (float) $this->site_default( 'wobble', 1.2 ) ),
				'description' => __( 'Degrees. 0 holds the ring still.', '3d-product-carousel' ),
			)
		);

		$this->add_control(
			'shadow',
			array(
				'label'   => __( 'Shadow', '3d-product-carousel' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'range'   => array(
					'%' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				),
				'default' => array(
					'unit' => '%',
					'size' => (float) $this->site_default( 'shadow', 35 ),
				),
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Rotate on its own', '3d-product-carousel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $this->site_default( 'autoplay', true ) ? 'true' : '',
				'return_value' => 'true',
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'     => __( 'Rotation speed', '3d-product-carousel' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0.01,
						'max'  => 1,
						'step' => 0.01,
					),
				),
				'default'   => array( 'size' => (float) $this->site_default( 'spin_speed', 12 ) / 100 ),
				'condition' => array( 'autoplay' => 'true' ),
			)
		);

		$this->add_control(
			'snap',
			array(
				'label'        => __( 'Settle on a card', '3d-product-carousel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $this->site_default( 'snap', true ) ? 'true' : '',
				'return_value' => 'true',
			)
		);

		$this->add_control(
			'hideback',
			array(
				'label'        => __( 'Hide the far side', '3d-product-carousel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $this->site_default( 'hide_back', false ) ? 'true' : '',
				'return_value' => 'true',
			)
		);

		$this->add_control(
			'floating',
			array(
				'label'        => __( 'Floating objects', '3d-product-carousel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $this->site_default( 'floating_objects', false ) ? 'true' : '',
				'return_value' => 'true',
				'description'  => __( 'Drops the card frames, so cut-out product photos appear to float.', '3d-product-carousel' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Turns the panel settings into shortcode attributes.
	 *
	 * Going through the shortcode rather than calling the renderer directly is
	 * deliberate: one code path means the widget cannot drift away from what the
	 * shortcode and the block produce.
	 *
	 * @param array $s Widget settings.
	 * @return array Attribute name to string value.
	 */
	private function attributes_from_settings( $s ) {
		$atts = $this->cards_to_attributes( $s['cards'] ?? array() );

		// The heading fields are emitted even when empty — an empty heading is a
		// choice the user made in the panel, not an omission.
		foreach ( array( 'title', 'subtitle', 'hint' ) as $key ) {
			$atts[ $key ] = (string) ( $s[ $key ] ?? '' );
		}

		$atts['tilt']   = (string) $this->slider( $s, 'tilt', -10 );
		$atts['lean']   = (string) $this->slider( $s, 'lean', 0 );
		$atts['spread'] = (string) $this->slider( $s, 'spread', 1.14 );
		$atts['wobble'] = (string) $this->slider( $s, 'wobble', 1.2 );
		$atts['shadow'] = (string) $this->slider( $s, 'shadow', 35 );
		$atts['speed']  = (string) $this->slider( $s, 'speed', 0.12 );

		foreach ( array( 'autoplay', 'snap', 'hideback', 'floating' ) as $flag ) {
			$atts[ $flag ] = empty( $s[ $flag ] ) ? 'false' : 'true';
		}

		// Empty values are kept on purpose. Dropping them would let the
		// shortcode fall back to the Customizer, so clearing the heading in the
		// panel would silently put the site-wide heading back. The card shape is
		// the exception: empty means the control never produced a value, and an
		// empty aspect would be discarded by the renderer anyway.
		$aspect = (string) ( $s['aspect'] ?? '' );
		if ( '' !== $aspect ) {
			$atts['aspect'] = $aspect;
		}

		/**
		 * Filters the shortcode attributes the widget produces.
		 *
		 * The counterpart to carousel3d_elementor_controls: whatever an add-on
		 * added to the panel, it turns into attributes here.
		 *
		 * @since 2.2.0
		 *
		 * @param array $atts Attribute name to value.
		 * @param array $s    The widget's settings.
		 */
		return apply_filters( 'carousel3d_elementor_attributes', $atts, $s );
	}

	/**
	 * Turns the repeater rows into the ids/labels/links/alts attributes.
	 *
	 * The lists are positional — ids are separated by commas, the rest by pipes
	 * — so a row without an image has to be dropped from all four at once or
	 * every caption after it lands on the wrong card.
	 *
	 * @param array $rows Repeater rows.
	 * @return array Attributes, empty when no row has an image.
	 */
	private function cards_to_attributes( $rows ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$ids = array();
		$lab = array();
		$url = array();
		$alt = array();

		foreach ( $rows as $row ) {
			$id  = isset( $row['image']['id'] ) ? (int) $row['image']['id'] : 0;
			$src = isset( $row['image']['url'] ) ? trim( (string) $row['image']['url'] ) : '';

			// An attachment id survives the site moving domain; a bare URL is
			// all there is for an image from outside the media library.
			if ( $id ) {
				$ids[] = (string) $id;
			} elseif ( '' !== $src ) {
				$ids[] = $src;
			} else {
				continue;
			}

			$lab[] = $this->one_line( $row['label'] ?? '' );
			$alt[] = $this->one_line( $row['alt'] ?? '' );
			$url[] = $this->one_line( $row['url']['url'] ?? '' );
		}

		if ( empty( $ids ) ) {
			return array();
		}

		return array(
			'ids'    => implode( ',', $ids ),
			'labels' => implode( '|', $lab ),
			'links'  => implode( '|', $url ),
			'alts'   => implode( '|', $alt ),
		);
	}

	/**
	 * Strips the characters that would break a positional attribute list.
	 *
	 * @param string $value Raw field value.
	 * @return string
	 */
	private function one_line( $value ) {
		return trim( str_replace( array( '|', ',', "\r", "\n" ), ' ', (string) $value ) );
	}

	/**
	 * Reads a slider control, which Elementor stores as an array.
	 *
	 * A slider the user has never touched can arrive with an empty size, so the
	 * fallback matters.
	 *
	 * @param array  $s        Widget settings.
	 * @param string $key      Control name.
	 * @param float  $fallback Value when unset.
	 * @return float
	 */
	private function slider( $s, $key, $fallback ) {
		if ( ! isset( $s[ $key ]['size'] ) || '' === $s[ $key ]['size'] || ! is_numeric( $s[ $key ]['size'] ) ) {
			return $fallback;
		}
		return (float) $s[ $key ]['size'];
	}

	/**
	 * Renders the carousel.
	 *
	 * @return void
	 */
	protected function render() {
		$atts  = $this->attributes_from_settings( $this->get_settings_for_display() );
		$parts = array();

		// Only the characters that would break shortcode parsing are removed.
		// Escaping here instead would double-encode, because the carousel
		// escapes these values again when it prints them.
		foreach ( $atts as $name => $value ) {
			$parts[] = $name . '="' . str_replace( array( '[', ']', '"' ), '', (string) $value ) . '"';
		}

		$shortcode = '[carousel_3d ' . implode( ' ', $parts ) . ']';
		$html      = do_shortcode( $shortcode );

		if ( '' === trim( $html ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div class="elementor-alert elementor-alert-info">%s</div>',
					esc_html__( 'Nothing to show yet — pick some images, or set them for the whole site in the Customizer.', '3d-product-carousel' )
				);
			}
			return;
		}

		// do_shortcode returns the carousel's own escaped markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
