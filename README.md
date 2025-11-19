# Dynamic Table of Contents Generator

A small, accessible WordPress plugin that automatically generates a table of contents (TOC) for posts and pages based on heading tags. It builds a nested, semantic TOC, provides a collapsible panel with ARIA attributes, and supports per-page enable/disable and server-side caching.

- Plugin slug: `dynamic-toc`
- Version: 1.4.0
- Requires: WordPress 5.2+
- Requires PHP: 8.1+
- License: GPL-2.0-or-later

---

## Features

- Generates a hierarchical TOC from headings (configurable levels).
- Produces accessible markup (button with `aria-expanded`/`aria-controls`, `role="region"`, etc.).
- Collapsible panel toggle (progressive enhancement with JS).
- Per-page enable/disable meta box in the editor.
- Server-side per-post transient caching to avoid repeated work.
- Filters to adjust behavior and markup for advanced integrations.

## Installation

1. Upload the `dynamic-toc` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin from the WordPress Plugins screen.
3. Optionally enable the TOC per page using the _Dynamic TOC_ meta box on the post/page edit screen, or set the default via filter.

## Quick Usage

- The TOC will be inserted into the post content automatically when enabled.
- The plugin detects headings and builds a nested ordered list reflecting the document structure.
- Assets (JS/CSS) are enqueued only when a TOC will be output.

## Filters & Hooks

Below are the available filters and short usage examples showing how to modify plugin behavior from a theme or custom plugin.

- `ttm_dynamic_toc_enabled` (bool $enabled, int $post_id)
  - Filter whether the TOC is enabled for a post.

```php
// Disable TOC for a specific post ID.
add_filter( 'ttm_dynamic_toc_enabled', function( $enabled, $post_id ) {
    if ( 42 === (int) $post_id ) {
        return false;
    }
    return $enabled;
}, 10, 2 );
```

- `ttm_dynamic_toc_heading_levels` (int[] $levels)
  - Filter the heading levels the TOC should consider. Defaults to `[2,3,4]`.

```php
// Include h2-h5 in the TOC.
add_filter( 'ttm_dynamic_toc_heading_levels', function() {
    return array( 2, 3, 4, 5 );
} );
```

- `ttm_dynamic_toc_meta_key` (string $meta_key)
  - Filter the post meta key used for the per-page enable checkbox. Default: `ttm_dynamic_toc_enabled`.

```php
// Use a custom meta key if your site already uses a different one.
add_filter( 'ttm_dynamic_toc_meta_key', function( $key ) {
    return 'my_custom_toc_flag';
} );
```

- `ttm_dynamic_toc_cache_ttl` (int $seconds)
  - Filter the transient TTL (default 12 hours).

```php
// Reduce cache TTL to 1 hour.
add_filter( 'ttm_dynamic_toc_cache_ttl', function( $ttl ) {
    return HOUR_IN_SECONDS;
} );
```

- `ttm_dynamic_toc_html` (string $html, array $toc_list, int $post_id)
  - Filter the rendered TOC HTML before it is prepended to content.

```php
// Wrap the TOC in a custom container or modify markup.
add_filter( 'ttm_dynamic_toc_html', function( $html, $toc_list, $post_id ) {
    return '<div class="my-toc-wrap">' . $html . '</div>';
}, 10, 3 );
```

- `ttm_dynamic_toc_meta_post_types` (string[] $post_types)
  - Filter the post types that show the per-page TOC meta box in the editor.

```php
// Enable the meta box for a custom post type 'book'.
add_filter( 'ttm_dynamic_toc_meta_post_types', function( $types ) {
    $types[] = 'book';
    return $types;
} );
```

Notes:
- The plugin registers assets via `ttm_dynamic_toc_register_assets()`; you can dequeue or override the handles `ttm-dynamic-toc` in your theme if you need custom styling or behavior.
- Use the `ttm_dynamic_toc_html` filter to fully replace the output if you need a completely different markup structure.

## Development

- The plugin ships with PHP_CodeSniffer (WPCS) configured for local development. Use the composer-supplied tools (if present) to lint and autofix:

```bash
# from plugin root (if composer/dev dependencies are installed)
./vendor/bin/phpcs --standard=WordPress --extensions=php -n --ignore=vendor -p .
./vendor/bin/phpcbf --standard=WordPress --extensions=php -n --ignore=vendor -p .
```

- JavaScript is lightweight and responsible for accessibility toggles and reduced-motion respects.

## Contributing

Contributions are welcome. Please open issues or pull requests on the GitHub repository:
https://github.com/NathanDozen3/dynamic-toc

When contributing:
- Follow WordPress PHP coding standards.
- Keep accessibility (keyboard interaction and ARIA) intact.

## Changelog

### 1.2.0
- Added nested TOC rendering by heading level.
- Improved accessible collapse toggles and ARIA attributes.
- Reworked DOM handling to avoid deprecated mbstring usage.

### 1.1.0
- Initial refactor: removed ACF, added per-page meta box and caching.

## License

This plugin is licensed under the GPL-2.0-or-later. See the `LICENSE` file for details.
