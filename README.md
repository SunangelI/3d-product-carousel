# 3D Product Carousel

An interactive 3D ring showcase for a handful of products. Drag it, swipe it, or
step through with the arrow keys. Click a card and it opens full size.

Built for the case a grid handles badly: **two to twelve products** that deserve
to be looked at rather than scrolled past.

- Gutenberg block with a live editor preview
- Elementor widget, with every setting in the panel
- `[carousel_3d]` shortcode for every other builder
- Images or video, per carousel or shared across the site
- Works on any card count from 2 to 12 — the geometry is measured, not assumed

## Install

From the WordPress plugin directory, or download a zip from
[Releases](../../releases) and upload it under **Plugins → Add New → Upload**.

Then configure under **Appearance → Customize → 3D Product Carousel**, and place
a carousel with the block, the Elementor widget, or the shortcode.

## The part that was actually hard

The cards are not sized by a formula. A carousel in 3D has three quantities that
each depend on the other two: the card size, the ring radius, and how much
perspective magnifies whatever is nearest the viewer. Pick any two and the third
is already decided — usually badly, with the front card's bottom corners sliced
off by whatever the theme wraps its content in.

So the layout runs every card corner through the same matrix chain the browser
will apply, samples it across a full sway cycle, measures the real projected
bounding box, and solves for a card size that fits by fixed-point iteration.

`tests/overflowtest.js` checks exactly that, for every card count at three
viewport widths, and fails if any corner leaves the scene box by more than two
pixels.

## Development

```bash
php tests/phptest.php                 # against WordPress stubs, no install needed
node tests/gen.js .                   # build the preview fixtures
node tests/server.js                  # serve them on :8777
node tests/overflowtest.js            # geometry, needs Chrome on :9222
node tests/interactiontest.js         # drag, inertia, snapping, lightbox
```

The browser suites drive headless Chrome over the DevTools protocol:

```bash
chrome --headless=new --disable-gpu --remote-debugging-port=9222 about:blank
```

Coding standards are WordPress Coding Standards plus a PHP 7.4 compatibility
check. `composer install && php vendor/bin/phpcs` if you have the dev
dependencies; the plugin is kept at zero errors and zero warnings.

## Extending it

Filters let an add-on supply cards from anywhere — a shop, a custom post type,
an API:

| Filter | Purpose |
|---|---|
| `carousel3d_shortcode_defaults` | Register your own shortcode attributes |
| `carousel3d_block_attributes` | Register block attributes to match |
| `carousel3d_pre_cards` | Supply the cards yourself |
| `carousel3d_cards` | Adjust the finished list |
| `carousel3d_builder_meta_keys` | Add a postmeta key for a builder that keeps its layout outside `post_content` |

For the Elementor widget:

| Hook | Purpose |
|---|---|
| `carousel3d_elementor_controls` | Add your own section to the panel |
| `carousel3d_elementor_attributes` | Turn it into shortcode attributes |
| `carousel3d_elementor_cards_condition` | Hide the image picker while your source is active |

In the browser, `window.C3D.init(root)` starts any carousel inside `root` and
`window.C3D.destroy(root)` shuts it down — what a page builder needs when it
replaces markup after load.

## WooCommerce

A separate add-on fills a carousel from a shop — new arrivals, best sellers, on
sale, featured, top rated, related, hand-picked — with prices and discount
badges on the cards. It uses only the filters above and is sold separately.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
