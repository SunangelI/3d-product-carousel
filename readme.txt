=== 3D Product Carousel ===
Contributors: SunAngel_I
Tags: carousel, 3d, gallery, showcase, shortcode
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive 3D ring showcase for a handful of products. Drag, swipe or use the arrow keys.

== Description ==

Cards are arranged on a ring in 3D space. The visitor can drag or swipe it around,
the rotation carries momentum and settles on the nearest card, and clicking a card
opens it full size with an optional link through to the product.

**Features:**

* Gutenberg block with a live editor preview, an Elementor widget, and the
  [carousel_3d] shortcode for every other builder
* Different images per carousel, or one set shared across the site
* Works with 2 to 12 cards — the geometry adapts, nothing overlaps or gets cut off
* Drag with a mouse, swipe on touch, or step through with the arrow keys
* Images and video (mp4 / webm / ogv)
* Front card is emphasised; cards on the far side recede with blur and shading
* Soft card shadow plus a contact shadow on the ground, both adjustable
* Lightbox with keyboard support, focus trapping and scroll locking
* Honours `prefers-reduced-motion`; animation pauses off screen and in background tabs
* Settings stored by the plugin, so they survive a theme switch
* Assets load only on pages that actually use the carousel
* Translation ready, with a Ukrainian translation included
* Works on multisite; uninstalling cleans every site in the network

== Installation ==

1. Upload the `3d-product-carousel` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Add the **3D Product Carousel** block to a page, or use the `[carousel_3d]`
   shortcode
4. Configure under **Appearance → Customize → 3D Product Carousel**

== Block ==

Insert **3D Product Carousel** from the Media category. It previews live in the
editor and supports wide and full alignment.

Product images, captions and links live in the Customizer because they are
shared site-wide; the block's sidebar covers the heading and the layout and
motion settings. Every one of those starts at *Use Customizer setting*, so a
block dropped in with no configuration matches a bare shortcode — and changing
a Customizer value still updates every carousel that has not overridden it.

== Elementor ==

Search the Elementor panel for **3D Product Carousel** and drag it in. Every
setting has a control, including the images for that particular carousel — no
shortcode attributes to remember.

The heading, layout and motion controls start at your Customizer settings, so a
widget dropped in without touching anything matches the rest of the site. Leave
the **Cards** list empty and it shows the site-wide images.

Needs Elementor 3.5 or newer.

== Shortcode ==

`[carousel_3d]` renders the carousel using the Customizer settings.

Every setting can be overridden per instance, so several different carousels can
share one page:

`[carousel_3d title="Our range" tilt="-14" wobble="0" autoplay="false" hideback="true"]`

Accepted attributes: `title`, `subtitle`, `hint`, `tilt`, `lean`, `wobble`, `speed`,
`spread`, `aspect`, `autoplay`, `snap`, `fill`, `hideback`, `floating`, `shadow`.

`speed` is degrees of rotation per frame at 60 fps, so `0.12` is roughly one turn
per 50 seconds. `spread` is a multiplier — `1.14` leaves a small gap between cards.

Boolean attributes accept `true` / `false` / `1` / `0` / `yes` / `no`.

== Customizer ==

**Appearance → Customize → 3D Product Carousel**

* **Content** — title, subtitle, interaction hint, number of card slots
* **Layout & motion** — aspect ratio, tilt, lean, spacing, idle sway, auto-rotation,
  snapping, hiding the far side, floating-objects mode, shadow strength
* **Colours** — background gradient, card, accent, title, caption and blob colours
* **Cards** — image or video, caption, link and alt text for each slot

Leaving a slot's image empty skips that slot entirely, so a showcase of three
products shows exactly three products.

== Screenshots ==

1. Eight products on a desktop screen. The front card is emphasised; the far side recedes with blur and shading.
2. A showcase of three products. Empty card slots are skipped, so three products show exactly three products.
3. On a phone the front card stays large and the neighbours peek in from the sides.
4. The block previews live in the editor, with the layout and motion settings in the sidebar.

== Frequently Asked Questions ==

= Why do I see each product twice? =

With fewer than five cards a full ring would put the neighbours directly behind the
viewer, leaving one card facing front and nothing else visible. The set is repeated
around the ring so it stays populated. Add more products and the repeats stop.

= Can I use it with a page builder? =

Yes. Elementor has a proper widget with all the settings in its panel. Every
other builder — Divi, Beaver Builder, Bricks, Oxygen, WPBakery — renders the
shortcode from any text, HTML or shortcode element, and the settings come from
the Customizer or from shortcode attributes.

= Does it work on multisite? =

Yes, per site or network-activated. Settings are per site, and deleting the
plugin cleans every site in the network.

= The carousel does not rotate on its own. =

Auto-rotation is skipped for visitors whose system asks for reduced motion, and it
pauses while the section is off screen, while the tab is in the background, and for
a few seconds after someone interacts with it.

== Upgrade Notice ==

= 2.3.0 =
The plugin folder and text domain are now 3d-product-carousel, to match its
address in the plugin directory. If you installed an earlier version by hand,
WordPress will see this as a different plugin: delete the old carousel-3d folder
and activate this one. Your settings, pages and carousels are untouched.

= 2.2.0 =
Adds an Elementor widget. Pages already built with the WooCommerce add-on's
widget keep working untouched — it is the same widget, moved into this plugin,
and the add-on now adds its shop settings to it.

= 2.1.0 =
Carousels can now have their own images, set per block or per shortcode. Existing
carousels are unaffected: leave the new field empty and the site-wide cards are
used exactly as before.

= 2.0.0 =
Settings move from theme mods into the plugin's own storage and are migrated
automatically on first load. Card sizing is now derived from the available space,
so existing carousels may look larger than before.

== Changelog ==

= 2.3.0 =
* The plugin folder, main file and text domain are now 3d-product-carousel,
  matching the slug this plugin has in the WordPress directory. They have to
  match, or the community translations WordPress downloads by slug are loaded
  under a name nothing in the plugin uses, and quietly do nothing.
* Nothing that a site stores has changed: the [carousel_3d] shortcode, the
  carousel-3d/carousel block name and the saved settings all stay as they were,
  so existing pages and carousels are untouched.

= 2.2.0 =
* Added an Elementor widget. Elementor runs on a large share of WordPress
  sites, and there the only way to configure a carousel used to be writing
  shortcode attributes by hand.
* The widget picks the images for that carousel too, with a caption, link and
  alt text for each — the per-carousel images added in 2.1.0 finally have a
  place to be set outside the block editor.
* Leave the card list empty and the site-wide images are used, exactly as
  before.
* Added extension points so an add-on can put its own section in the widget's
  panel: the carousel3d_elementor_controls action and the
  carousel3d_elementor_attributes and carousel3d_elementor_cards_condition
  filters.
* Translated the block sidebar strings that had been added since 2.0.0 and
  never picked up by the Ukrainian translation.

= 2.1.3 =
* A carousel can now be shut down and restarted, which page builders need: they
  redraw a widget on every change to its settings, and each discarded copy used
  to leave behind an animation loop and a window listener.

= 2.1.2 =
* The stylesheet now reaches the head on pages built with Elementor, Divi,
  Beaver Builder, Bricks, SiteOrigin or WPBakery. They keep the layout in
  postmeta and leave post_content empty, so the old check found nothing and the
  CSS only arrived in the footer — the carousel worked, but the page could
  flash unstyled first.

= 2.1.1 =
* Added the carousel3d_block_attributes filter, so an add-on can register block
  attributes of its own. Without it ServerSideRender rejects the whole preview
  request over one unknown attribute.

= 2.1.0 =
* Each carousel can have its own cards. Pick images in the block sidebar, or
  pass them to the shortcode with ids="12,34" labels="Honey|Wax"
* Attachment ids or plain URLs, mixed freely; ids keep working if the site
  moves domain
* Leaving the new field empty keeps the previous behaviour — the site-wide
  Customizer cards
* Cards can carry a corner badge and a second line under the caption, so an
  extension can show a price or a discount
* Added filters for extensions: carousel3d_pre_cards, carousel3d_cards and
  carousel3d_shortcode_defaults

= 2.0.0 =
* Ukrainian translation plus a .pot template; the block editor sidebar is
  translated too, not just the front end
* Uninstall now cleans every site on a multisite network, not only the one it
  happened to run on
* Images carry lazy-load opt-out markers, so image optimisation plugins cannot
  replace them with blank placeholders
* Added a Gutenberg block, server-rendered through the same code path as the
  shortcode, with a live preview in the editor
* Demo images converted to WebP: the bundled placeholders went from 2.7 MB to 250 KB
* Card size is derived from the scene box, accounting for perspective magnification
  and ring tilt — cards no longer overflow their container or get clipped by themes
* Works correctly with any card count from 2 to 12; sizing no longer assumes exactly 8
* Empty card slots are skipped instead of being filled with bundled demo photos
* Cards on the far side are dimmed and blurred rather than showing mirrored faces
* Idle sway reduced from ±6° to a gentle default, and made configurable
* Added soft card shadows and an adjustable contact shadow
* Added snapping, keyboard control, and a proper accessible lightbox
* Settings moved from theme mods to a plugin option, migrated automatically
* CSS and JS are enqueued only on pages that use the shortcode
* Respects prefers-reduced-motion; pauses when off screen or in a background tab
* Several carousels can now coexist on one page, each with its own settings

= 1.1.0 =
* Customizer colours, card labels, floating objects mode

= 1.0.0 =
* Initial release
