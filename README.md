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

- `ttm_dynamic_toc_enabled` (bool $enabled, int $post_id)
  - Filter whether the TOC is enabled for a post.

- `ttm_dynamic_toc_heading_levels` (array $levels)
  - Filter the heading levels the TOC should consider. Defaults to `[2,3,4]`.

- `ttm_dynamic_toc_meta_key` (string $meta_key)
  - Filter the post meta key used for the per-page enable checkbox. Default: `ttm_dynamic_toc_enabled`.

- `ttm_dynamic_toc_cache_ttl` (int $seconds)
  - Filter the transient TTL (default 12 hours).

- `ttm_dynamic_toc_html` (string $html, array $toc_list, int $post_id)
  - Filter the rendered TOC HTML before it is prepended to content.

- `ttm_dynamic_toc_register_assets` is the function that registers the JS and CSS handles. You can dequeue or override behavior as needed.

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
