/**
 * Editor script for the 3D Product Carousel block.
 *
 * Written against the wp.* globals rather than JSX so the plugin needs no build
 * step. The block is server-rendered, so the canvas just shows ServerSideRender
 * output and the sidebar edits the same attributes the shortcode accepts.
 *
 * Cards themselves live in the Customizer, not here: they are global to the
 * site, and duplicating that UI per block would let the two disagree.
 */
(function (blocks, element, blockEditor, components, i18n, serverSideRender) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var Placeholder = components.Placeholder;
	var Spinner = components.Spinner;
	var ExternalLink = components.ExternalLink;

	var MediaUpload = blockEditor.MediaUpload;
	var MediaUploadCheck = blockEditor.MediaUploadCheck;
	var Button = components.Button;
	var BaseControl = components.BaseControl;

	var CFG = window.C3D_BLOCK || {};
	var SSR = serverSideRender && (serverSideRender.default || serverSideRender);

	/**
	 * ServerSideRender injects markup long after the front-end script has run,
	 * and with a block theme the editor canvas is an iframe with its own
	 * document — so nothing in there ever gets initialised on its own.
	 *
	 * A poll rather than a MutationObserver: the canvas iframe is torn down and
	 * recreated as the editor changes modes, which would strand any observer
	 * bound to its document. C3D.init() skips scenes it has already claimed, so
	 * running it repeatedly is free.
	 */
	function initPreviews() {
		if (!window.C3D || !window.C3D.init) { return; }
		var docs = [document];
		var frames = document.querySelectorAll('iframe[name="editor-canvas"]');
		for (var i = 0; i < frames.length; i++) {
			try {
				if (frames[i].contentDocument) { docs.push(frames[i].contentDocument); }
			} catch { /* cross-origin, not ours */ }
		}
		docs.forEach(function (doc) {
			if (doc.querySelector('.c3d-scene:not([data-c3d-ready])')) {
				window.C3D.init(doc);
			}
		});
	}
	window.setInterval(initPreviews, 500);

	/** Tri-state select: inherit the Customizer value, or force on/off. */
	function inheritSelect(label, value, onChange, help) {
		return el(SelectControl, {
			label: label,
			help: help,
			value: value || '',
			options: [
				{ label: __('Use Customizer setting', '3d-product-carousel'), value: '' },
				{ label: __('On', '3d-product-carousel'), value: 'true' },
				{ label: __('Off', '3d-product-carousel'), value: 'false' }
			],
			onChange: onChange,
			__nextHasNoMarginBottom: true
		});
	}

	/**
	 * Per-block card picker.
	 *
	 * Leaving it empty is the normal case: the carousel then shows the
	 * site-wide cards from the Customizer, exactly as it did before this
	 * existed. Choosing images here overrides them for this block only.
	 */
	function cardPicker(cards, onChange) {
		var chosen = cards || [];

		var rows = chosen.map(function (card, i) {
			return el('div', { key: card.id || card.url || i, className: 'c3d-card-row' },
				el('div', { className: 'c3d-card-row__head' },
					card.url && el('img', {
						src: card.url, alt: '', width: 40, height: 40,
						style: { objectFit: 'cover', borderRadius: '4px', marginRight: '8px' }
					}),
					el('strong', { style: { flex: 1, fontSize: '12px' } },
						card.label || card.alt || __('Untitled', '3d-product-carousel')),
					el(Button, {
						icon: 'arrow-up-alt2', label: __('Move up', '3d-product-carousel'),
						disabled: i === 0, isSmall: true,
						onClick: function () {
							var next = chosen.slice();
							next.splice(i - 1, 0, next.splice(i, 1)[0]);
							onChange(next);
						}
					}),
					el(Button, {
						icon: 'arrow-down-alt2', label: __('Move down', '3d-product-carousel'),
						disabled: i === chosen.length - 1, isSmall: true,
						onClick: function () {
							var next = chosen.slice();
							next.splice(i + 1, 0, next.splice(i, 1)[0]);
							onChange(next);
						}
					}),
					el(Button, {
						icon: 'no-alt', label: __('Remove', '3d-product-carousel'),
						isSmall: true, isDestructive: true,
						onClick: function () {
							onChange(chosen.filter(function (_, j) { return j !== i; }));
						}
					})
				),
				el(TextControl, {
					label: __('Caption', '3d-product-carousel'),
					value: card.label || '',
					onChange: function (v) {
						onChange(chosen.map(function (c, j) {
							return j === i ? Object.assign({}, c, { label: v }) : c;
						}));
					},
					__nextHasNoMarginBottom: true
				}),
				el(TextControl, {
					label: __('Link', '3d-product-carousel'),
					value: card.link || '',
					onChange: function (v) {
						onChange(chosen.map(function (c, j) {
							return j === i ? Object.assign({}, c, { link: v }) : c;
						}));
					},
					__nextHasNoMarginBottom: true
				})
			);
		});

		return el(Fragment, {},
			el(MediaUploadCheck, {},
				el(MediaUpload, {
					allowedTypes: ['image', 'video'],
					multiple: true,
					gallery: false,
					value: chosen.map(function (c) { return c.id; }).filter(Boolean),
					onSelect: function (media) {
						var list = Array.isArray(media) ? media : [media];
						onChange(list.map(function (m) {
							// Keep any caption already typed for this image.
							var was = chosen.filter(function (c) { return c.id === m.id; })[0] || {};
							return {
								id: m.id,
								url: m.url,
								alt: m.alt || '',
								label: was.label !== undefined ? was.label : (m.title || ''),
								link: was.link || ''
							};
						}));
					},
					render: function (open) {
						return el(Button, {
							variant: 'secondary', onClick: open.open,
							style: { width: '100%', justifyContent: 'center' }
						}, chosen.length
							? __('Change images', '3d-product-carousel')
							: __('Choose images for this carousel', '3d-product-carousel'));
					}
				})
			),
			chosen.length
				? el(Fragment, {}, rows,
					el(Button, {
						variant: 'link', isDestructive: true,
						onClick: function () { onChange([]); }
					}, __('Clear and use the site-wide cards', '3d-product-carousel')))
				: el('p', { style: { fontSize: '12px', opacity: 0.7, margin: '8px 0 0' } },
					__('Empty: this block shows the cards set in the Customizer.', '3d-product-carousel'))
		);
	}

	function aspectOptions() {
		var out = [{ label: __('Use Customizer setting', '3d-product-carousel'), value: '' }];
		Object.keys(CFG.aspects || {}).forEach(function (key) {
			out.push({ label: CFG.aspects[key], value: key });
		});
		return out;
	}

	blocks.registerBlockType('carousel-3d/carousel', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;

			var sidebar = el(InspectorControls, {},
				el(PanelBody, { title: __('Cards', '3d-product-carousel'), initialOpen: true },
					el(BaseControl, {
						label: __('Cards in this carousel', '3d-product-carousel'),
						help: __('Choose images here to show different products on this page. Leave it empty and the site-wide cards are used.', '3d-product-carousel'),
						__nextHasNoMarginBottom: true
					}, cardPicker(a.cards, function (v) { set({ cards: v }); })),
					el('p', { style: { fontSize: '12px', margin: '12px 0 0' } },
						el(ExternalLink, { href: CFG.customize || '#' },
							__('Edit the site-wide cards in the Customizer', '3d-product-carousel'))
					)
				),

				el(PanelBody, { title: __('Content', '3d-product-carousel'), initialOpen: false },
					el(ToggleControl, {
						label: __('Override the heading here', '3d-product-carousel'),
						help: __('Off: this block uses the site-wide title and subtitle.', '3d-product-carousel'),
						checked: !!a.useCustomText,
						onChange: function (v) { set({ useCustomText: v }); },
						__nextHasNoMarginBottom: true
					}),
					a.useCustomText && el(Fragment, {},
						el(TextControl, {
							label: __('Title', '3d-product-carousel'),
							value: a.title || '',
							onChange: function (v) { set({ title: v }); },
							__nextHasNoMarginBottom: true
						}),
						el(TextControl, {
							label: __('Subtitle', '3d-product-carousel'),
							value: a.subtitle || '',
							onChange: function (v) { set({ subtitle: v }); },
							__nextHasNoMarginBottom: true
						}),
						el(TextControl, {
							label: __('Interaction hint', '3d-product-carousel'),
							help: __('Leave empty to hide it.', '3d-product-carousel'),
							value: a.hint || '',
							onChange: function (v) { set({ hint: v }); },
							__nextHasNoMarginBottom: true
						})
					)
				),

				el(PanelBody, { title: __('Layout & motion', '3d-product-carousel'), initialOpen: false },
					el(SelectControl, {
						label: __('Photo aspect ratio', '3d-product-carousel'),
						value: a.aspect || '',
						options: aspectOptions(),
						onChange: function (v) { set({ aspect: v }); },
						__nextHasNoMarginBottom: true
					}),
					el(RangeControl, {
						label: __('Ring tilt (°)', '3d-product-carousel'),
						value: a.tilt === '' ? undefined : Number(a.tilt),
						onChange: function (v) { set({ tilt: v === undefined ? '' : String(v) }); },
						min: -45, max: 45, allowReset: true, resetFallbackValue: undefined,
						__nextHasNoMarginBottom: true
					}),
					el(RangeControl, {
						label: __('Idle sway (°)', '3d-product-carousel'),
						value: a.wobble === '' ? undefined : Number(a.wobble),
						onChange: function (v) { set({ wobble: v === undefined ? '' : String(v) }); },
						min: 0, max: 8, step: 0.2, allowReset: true, resetFallbackValue: undefined,
						__nextHasNoMarginBottom: true
					}),
					el(RangeControl, {
						label: __('Spacing between cards (%)', '3d-product-carousel'),
						value: a.spread === '' ? undefined : Number(a.spread),
						onChange: function (v) { set({ spread: v === undefined ? '' : String(v) }); },
						min: 100, max: 200, step: 2, allowReset: true, resetFallbackValue: undefined,
						__nextHasNoMarginBottom: true
					}),
					el(RangeControl, {
						label: __('Shadow strength (%)', '3d-product-carousel'),
						value: a.shadow === '' ? undefined : Number(a.shadow),
						onChange: function (v) { set({ shadow: v === undefined ? '' : String(v) }); },
						min: 0, max: 100, step: 5, allowReset: true, resetFallbackValue: undefined,
						__nextHasNoMarginBottom: true
					}),
					inheritSelect(__('Rotate automatically', '3d-product-carousel'), a.autoplay,
						function (v) { set({ autoplay: v }); },
						__('Always off for visitors who ask for reduced motion.', '3d-product-carousel')),
					inheritSelect(__('Snap to the nearest card', '3d-product-carousel'), a.snap,
						function (v) { set({ snap: v }); }),
					inheritSelect(__('Fill the ring with repeats', '3d-product-carousel'), a.fill,
						function (v) { set({ fill: v }); },
						__('Applies when there are fewer than five cards.', '3d-product-carousel')),
					inheritSelect(__('Hide cards on the far side', '3d-product-carousel'), a.hideback,
						function (v) { set({ hideback: v }); }),
					inheritSelect(__('Floating objects mode', '3d-product-carousel'), a.floating,
						function (v) { set({ floating: v }); },
						__('Drops the card frame. For cut-out PNGs.', '3d-product-carousel'))
				)
			);

			var preview = SSR
				? el(SSR, {
					block: 'carousel-3d/carousel',
					attributes: a,
					EmptyResponsePlaceholder: function () {
						return el(Placeholder, {
							icon: 'images-alt2',
							label: __('3D Product Carousel', '3d-product-carousel'),
							instructions: __('No cards yet. Add at least one product image in the Customizer.', '3d-product-carousel')
						}, el(ExternalLink, { href: CFG.customize || '#' },
							__('Open the Customizer', '3d-product-carousel')));
					},
					LoadingResponsePlaceholder: function () {
						return el(Placeholder, {}, el(Spinner));
					}
				})
				: el(Placeholder, { icon: 'images-alt2', label: __('3D Product Carousel', '3d-product-carousel') });

			// The carousel drives itself with pointer events; swallowing them
			// inside the canvas keeps dragging from fighting block selection.
			var canvas = el('div', {
				className: 'c3d-editor-preview',
				style: { pointerEvents: 'none' }
			}, preview);

			return el(Fragment, {}, sidebar,
				el('div', useBlockProps ? useBlockProps() : {}, canvas));
		},

		// Server-rendered: nothing is stored in post content.
		save: function () { return null; }
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
