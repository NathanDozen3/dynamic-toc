=== Dynamic Table of Contents Generator ===
Contributors: NathanDozen3
Donate link: https://github.com/sponsors/NathanDozen3
Tags: table-of-contents,toc,accessibility,headings,content
Requires at least: 5.2
Tested up to: 6.4
Stable tag: 1.4.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically generates a dynamic table of contents for posts and pages based on headings.

== Description ==

Dynamic Table of Contents Generator builds a hierarchical table of contents from heading
tags (H2..H6 by default) and prepends it to the post content when enabled. The TOC
is rendered as an accessible, collapsible panel with semantic markup and ARIA attributes.

Features:

* Generates nested ordered lists reflecting the document structure.
* Accessible toggle button with `aria-expanded` and `aria-controls`.
* Per-page enable/disable meta box.
* Server-side caching per post for performance.
* Filters provided for customization.

== Installation ==

1. Upload the `dynamic-toc` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Edit a page or post and use the **Dynamic TOC** meta box to enable or disable the TOC per page.

== Frequently Asked Questions ==

= How do I change which heading levels are included? =
Use the `ttm_dynamic_toc_heading_levels` filter to provide an array of levels. Example in a theme or plugin:

```php
add_filter( 'ttm_dynamic_toc_heading_levels', function() {
    return array( 2, 3, 4 );
} );
```

= How can I disable the TOC globally? =
Either remove the plugin or use the `ttm_dynamic_toc_enabled` filter to return `false` by default.

= Can I customize the output markup? =
Yes — use the `ttm_dynamic_toc_html` filter to modify the generated HTML, or replace the `toc()` renderer via custom filter if you prefer.

== Screenshots ==

1. Collapsible Table of Contents panel (closed and open states).

== Changelog ==

= 1.2.0 =
* Added nested TOC rendering by heading level.
* Improved ARIA attributes and collapse behavior.
* Replaced deprecated mbstring conversion approach.

= 1.1.0 =
* Added per-page meta box and transient caching.

== Upgrade Notice ==

= 1.2.0 =
This update changes the TOC markup to render nested ordered lists. If you rely on previous markup selectors
in your theme or custom CSS, you may need to update them to the new `.ttm-toc__list--level-{n}` classes.

== Arbitrary section ==

If you need premium features or assistance customizing the plugin, open an issue on GitHub.
