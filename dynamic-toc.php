<?php
/**
 * Dynamic Table of Contents Generator
 *
 * @package           Dynamic_TOC
 * @author            Nathan Johnson
 * @copyright         2025 Nathan Johnson
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Dynamic Table of Contents Generator
 * Plugin URI:        https://github.com/NathanDozen3/dynamic-toc/
 * Description:       Automatically generates a dynamic table of contents for posts and pages based on headings.
 * Version:           1.6.0
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Nathan Johnson
 * Author URI:        https://github.com/NathanDozen3
 * Text Domain:       ttm-dynamic-toc
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

declare( strict_types=1 );
namespace TTM\Dynamic_TOC;

use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin version constant.
 */
add_action(
	'init',
	function () {
		$plugin_version = get_plugin_data( __FILE__ )['Version'];
		define( 'TTM_DYNAMIC_TOC_VERSION', $plugin_version );
	}
);

/**
 * Load textdomain for translations
 */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'dynamic-toc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * Get the list of post types that support the TOC meta box.
 *
 * @since 1.5
 * @return string[] Array of post type slugs.
 */
function ttm_get_meta_post_types(): array {
	/**
	 * Filter the list of post types which should display the Dynamic TOC meta box.
	 *
	 * Allows themes and plugins to add support for additional post types.
	 *
	 * @since 1.1
	 *
	 * @param string[] $post_types Array of post type slugs. Default: array( 'post', 'page' ).
	 * @return string[]
	 */
	$post_types = apply_filters( 'ttm_dynamic_toc_meta_post_types', array( 'post', 'page' ) );
	return (array) $post_types;
}

/**
 * Get the post meta key used for per-post TOC enable flag.
 *
 * @since 1.5
 * @return string Meta key name.
 */
function ttm_get_dynamic_toc_meta_key(): string {
	/**
	 * Filter the post meta key used to determine whether the TOC is enabled per-post.
	 *
	 * Allows changing the meta key name that the plugin looks up on the post.
	 *
	 * @since 1.1
	 *
	 * @param string $meta_key Meta key to check for per-post TOC enable. Default 'ttm_dynamic_toc_enabled'.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	$post_id = get_the_ID();
	$meta_key = apply_filters( 'ttm_dynamic_toc_meta_key', 'ttm_dynamic_toc_enabled', $post_id );

	if ( $post_id ) {

		/**
		 * Filters the post meta key used to determine whether the TOC is enabled for a specific post.
		 *
		 * This is a dynamic hook, the actual hook name includes the post ID. Example:
		 * `ttm_dynamic_toc_meta_key_123` for post ID 123.
		 *
		 * @since 1.5
		 *
		 * @param string $meta_key Meta key to check for per-post TOC enable. Default: 'ttm_dynamic_toc_enabled'.
		 * @param int    $post_id  Post ID.
		 * @return string Filtered meta key to use for this post.
		 */
		$meta_key = apply_filters( "ttm_dynamic_toc_meta_key_$post_id", $meta_key, $post_id );
	}
	return $meta_key;
}

/**
 * Register plugin assets (scripts & styles).
 *
 * Registers the script and style handles so they may be enqueued when a TOC is rendered.
 *
 * @since 1.1
 * @return void
 */
function ttm_dynamic_toc_register_assets(): void {
	wp_register_script( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'js/dynamic-toc.js', array(), TTM_DYNAMIC_TOC_VERSION, true );
	wp_register_style( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'css/dynamic-toc.css', array(), TTM_DYNAMIC_TOC_VERSION );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\ttm_dynamic_toc_register_assets' );

/**
 * Register the Gutenberg block for inserting the Dynamic TOC.
 *
 * Registers the dynamic TOC block using the block.json metadata file, allowing
 * editors to insert a [dynamic_toc] shortcode via the block editor UI.
 *
 * @since 1.5.0
 * @return void
 */
function ttm_dynamic_toc_register_block(): void {
	// Ensure block.json exists before attempting to register.
	$block_json_file = plugin_dir_path( __FILE__ ) . 'blocks/dynamic-toc-block/block.json';
	if ( ! file_exists( $block_json_file ) ) {
		return;
	}

	// Register the block via block.json metadata.
	register_block_type( $block_json_file );

	// Enqueue the compiled editor script from the block's build/ directory.
	$build_file = plugin_dir_path( __FILE__ ) . 'blocks/dynamic-toc-block/build/index.js';
	if ( file_exists( $build_file ) ) {
		wp_enqueue_script(
			'ttm-dynamic-toc-block-editor',
			plugin_dir_url( __FILE__ ) . 'blocks/dynamic-toc-block/build/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-i18n' ),
			filemtime( $build_file ),
			true
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\ttm_dynamic_toc_register_block' );

/**
 * Filter the post content and inject the dynamic TOC when enabled.
 *
 * Determines whether the TOC is enabled for the current post (per-post meta takes precedence
 * over the global option) and delegates to {@see automatic_toc()} when enabled.
 *
 * @since 1.1
 * @param string $content Post content.
 * @return string Modified content with TOC when applicable.
 */
function ttm_dynamic_toc_the_content( string $content ): string {
	// Only run on front-end singular pages in the main query.
	if ( is_admin() || ! is_singular() ) {
		return $content;
	}

	global $post;

	// Per-page meta takes precedence. If post meta is explicitly set (0/1), use it.
	// Fallback to global option and then filter.
	$meta_key = ttm_get_dynamic_toc_meta_key();
	$meta_val = get_post_meta( isset( $post->ID ) ? (int) $post->ID : 0, $meta_key, true );

	if ( '' !== $meta_val && null !== $meta_val ) {
		$enabled = (bool) $meta_val;
	} else {
		// Read enable flag from an option (remove ACF dependency). Site owners can set this option.
		// Or filter it programmatically. Default: false.
		$enabled = (bool) get_option( 'ttm_dynamic_toc_enabled', false );
	}

	// Allow programmatic overrides.
	/**
	 * Filter whether the dynamic TOC is enabled for a given post.
	 *
	 * This filter receives the current enabled state and the post ID so callers
	 * can enable or disable the TOC programmatically.
	 *
	 * @since 1.1
	 *
	 * @param bool $enabled Current enabled state.
	 * @param int  $post_id Post ID being checked.
	 * @return bool
	 */
	$enabled = apply_filters( 'ttm_dynamic_toc_enabled', $enabled, isset( $post->ID ) ? $post->ID : 0 );
	if ( ! $enabled ) {
		return $content;
	}

	return automatic_toc( $content, isset( $post->ID ) ? (int) $post->ID : 0 );
}
add_filter( 'the_content', __NAMESPACE__ . '\ttm_dynamic_toc_the_content' );

/**
 * Build and inject the TOC into post content.
 *
 * Parses configured heading levels, generates/ensures unique IDs, builds the TOC HTML,
 * enqueues required assets, and caches the final output in a per-post transient.
 *
 * @since 1.1
 * @param string $content Post content HTML.
 * @param int    $post_id Post ID.
 * @return string Content prefixed with TOC HTML when headings found; original content otherwise.
 */
function automatic_toc( string $content, int $post_id = 0 ): string {
	// Build TOC using DOMDocument to be more reliable than regex.
	$toc_list = array();

	// Suppress libxml warnings for malformed HTML fragments.
	$internal_errors = libxml_use_internal_errors( true );

	$doc = new \DOMDocument();
	// Wrap content in a container so we can extract modified inner HTML later.
	$wrapped = '<div class="ttm-toc-wrapper">' . $content . '</div>';

	/*
	 * Avoid deprecated mb_convert_encoding() handling of HTML entities. Prepending an
	 * XML encoding declaration and using LIBXML flags produces consistent results
	 * with DOMDocument::loadHTML without triggering mbstring deprecation notices.
	 */
	$html_for_dom = '<?xml encoding="utf-8" ?>' . $wrapped;
	// LIBXML_HTML_NOIMPLIED and LIBXML_HTML_NODEFDTD keep DOMDocument from adding
	// extra html/body tags around fragments on newer PHP versions.
	$doc->loadHTML( $html_for_dom, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$xpath = new \DOMXPath( $doc );

	// Allow configurable heading levels (defaults to h2-h4).
	$default_levels = array( 2, 3, 4 );
	/**
	 * Filter the heading levels that the TOC should include.
	 *
	 * Receives an array of integers representing heading levels (1-6). Default is h2-h4.
	 *
	 * @since 1.1
	 *
	 * @param int[] $levels Array of heading levels to include. Default: array(2,3,4).
	 * @return int[]
	 */
	$levels = (array) apply_filters( 'ttm_dynamic_toc_heading_levels', $default_levels );
	$tags = array();
	foreach ( $levels as $lvl ) {
		$lvl = (int) $lvl;
		if ( $lvl >= 1 && $lvl <= 6 ) {
			$tags[] = 'h' . $lvl;
		}
	}

	if ( empty( $tags ) ) {
		// Fallback to h2 if configuration is empty/invalid.
		$tags = array( 'h2' );
	}

	// Build xpath query to select all requested heading tags in document order.
	$xpath_query_parts = array();
	foreach ( $tags as $tag ) {
		$xpath_query_parts[] = '//' . $tag;
	}
	$xpath_query = implode( '|', $xpath_query_parts );

	// Identify any heading nodes inside custom_block containers and mark them to ignore.
	$ignore_map = array();
	$custom_divs = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " custom_block ")]' );
	foreach ( $custom_divs as $div ) {
		$inner_nodes = $xpath->query( '.' . str_replace( '//', '/', $xpath_query ), $div );
		foreach ( $inner_nodes as $n ) {
			$ignore_map[ spl_object_hash( $n ) ] = true;
		}
	}

	// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotSnakeCase
	// Gather all heading nodes.
	$nodes = $xpath->query( $xpath_query );
	$used_slugs = array();
	foreach ( $nodes as $node ) {
		// Skip any headings inside ignored containers.
		if ( isset( $ignore_map[ spl_object_hash( $node ) ] ) ) {
			continue;
		}
		$heading_text = trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( '' === $heading_text ) {
			continue;
		}

		// Use existing ID if present; otherwise generate a safe WP slug and ensure uniqueness.
		if ( $node->hasAttribute( 'id' ) ) {
			$slug = $node->getAttribute( 'id' );
		} else {
			$slug = \sanitize_title( wp_strip_all_tags( $heading_text ) );
			$original = $slug;
			$i = 2;
			while ( in_array( $slug, $used_slugs, true ) ) {
				$slug = $original . '-' . $i;
				$i++;
			}
			$node->setAttribute( 'id', $slug );
		}

		$used_slugs[] = $slug;
		// Determine heading level (e.g., h2 -> 2). Default to 2 if not parsable.
		$tag_name = strtolower( $node->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$level = 2;
		if ( 0 === strpos( $tag_name, 'h' ) && isset( $tag_name[1] ) ) {
			$maybe = (int) substr( $tag_name, 1 );
			if ( $maybe >= 1 && $maybe <= 6 ) {
				$level = $maybe;
			}
		}

		$toc_list[] = array(
			'slug'  => $slug,
			'text'  => $heading_text,
			'level' => $level,
		);
	}

	// Restore libxml state.
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	// Nothing to do if no headings found.
	if ( empty( $toc_list ) ) {
		return $content;
	}

	// Enqueue assets only when we will output the TOC.
	if ( ! is_admin() ) {
		wp_enqueue_script( 'ttm-dynamic-toc' );
		wp_enqueue_style( 'ttm-dynamic-toc' );
	}

	// Build output and support server-side caching. Use a single per-post transient that
	// stores both the content hash and the HTML so we can invalidate easily on save.
	/**
	 * Filter the per-post TOC cache TTL (seconds).
	 *
	 * Allows adjustment of how long the server-side per-post TOC transient is cached.
	 *
	 * @since 1.1
	 *
	 * @param int $ttl Time to live in seconds. Default: 12 * HOUR_IN_SECONDS.
	 * @return int
	 */
	$cache_ttl = (int) apply_filters( 'ttm_dynamic_toc_cache_ttl', 12 * HOUR_IN_SECONDS );
	$cache_key = 'ttm_toc_' . (int) $post_id;
	$cached = get_transient( $cache_key );
	$content_hash = md5( $content );
	if ( is_array( $cached ) && isset( $cached['hash'], $cached['html'] ) && $cached['hash'] === $content_hash ) {
		return $cached['html'];
	}

	// Extract the modified inner HTML of our wrapper container.
	$wrapper = $doc->getElementsByTagName( 'div' )->item( 0 );
	$new_content = '';
	if ( $wrapper ) {
		foreach ( $wrapper->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$new_content .= $doc->saveHTML( $child );
		}
	} else {
		// Fallback.
		$new_content = $content;
	}

	// phpcs:enable Squiz.NamingConventions.ValidVariableName.NotSnakeCase

	// Build TOC HTML and allow developers to filter it.
	$toc_html = toc( $toc_list );
	/**
	 * Filter the generated TOC HTML before it is inserted into the post content.
	 *
	 * This filter allows plugins or themes to modify the rendered TOC markup, or
	 * replace it entirely.
	 *
	 * @since 1.1
	 *
	 * @param string $toc_html Rendered TOC HTML.
	 * @param array<int,array{slug:string,text:string,level:int}> $toc_list Array of TOC items.
	 * @param int $post_id Post ID the TOC is being generated for.
	 * @return string Filtered TOC HTML.
	 */
	$toc_html = apply_filters( 'ttm_dynamic_toc_html', $toc_html, $toc_list, $post_id );

	$result = $toc_html . $new_content;
	// Store in transient with hash for validation.
	set_transient(
		$cache_key,
		array(
			'hash' => $content_hash,
			'html' => $result,
		),
		$cache_ttl
	);

	return $result;
}

/**
 * Add the per-page TOC meta box to supported post types.
 *
 * The list of post types may be filtered via {@see 'ttm_dynamic_toc_meta_post_types'}.
 *
 * @since 1.1
 * @return void
 */
function ttm_dynamic_toc_add_meta_box(): void {

	$post_types = ttm_get_meta_post_types();
	foreach ( (array) $post_types as $pt ) {
		add_meta_box(
			'ttm_dynamic_toc_meta',
			__( 'Dynamic TOC', 'dynamic-toc' ),
			__NAMESPACE__ . '\\ttm_dynamic_toc_meta_box_callback',
			$pt,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\ttm_dynamic_toc_add_meta_box' );

/**
 * Register post meta for the per-page TOC flag so it is available via REST/Gutenberg.
 *
 * Uses the same meta key filtered by `ttm_dynamic_toc_meta_key` and allows themes
 * and plugins to show the flag in the REST API and block editor.
 *
 * @since 1.4.0
 * @return void
 */
function ttm_dynamic_toc_register_post_meta(): void {
	$meta_key = ttm_get_dynamic_toc_meta_key();
	$post_types = ttm_get_meta_post_types();

	// Register the post meta per post type to ensure compatibility with
	// environments where passing an array to `register_post_meta` may cause
	// unexpected runtime behavior. Iterate explicitly to avoid type issues.
	$meta_args = array(
		'type'              => 'boolean',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => function ( $value ) {
			return (bool) $value;
		},
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	);

	foreach ( (array) $post_types as $pt ) {
		register_post_meta( $pt, $meta_key, $meta_args );
	}
}
add_action( 'init', __NAMESPACE__ . '\\ttm_dynamic_toc_register_post_meta' );

/**
 * Meta box display callback.
 *
 * @since 1.1
 * @param WP_Post $post Current post object.
 * @return void Outputs the meta box HTML.
 */
function ttm_dynamic_toc_meta_box_callback( WP_Post $post ): void {
	$meta_key = ttm_get_dynamic_toc_meta_key();
	$value = get_post_meta( $post->ID, $meta_key, true );
	wp_nonce_field( 'ttm_dynamic_toc_meta_box', 'ttm_dynamic_toc_meta_box_nonce' );
	?>
	<p>
		<label for="ttm_dynamic_toc_enabled">
			<input type="checkbox" name="ttm_dynamic_toc_enabled" id="ttm_dynamic_toc_enabled" value="1" <?php checked( $value, '1' ); ?> />
			<?php esc_html_e( 'Show Table of Contents on this page', 'dynamic-toc' ); ?>
		</label>
		<br />
		<span class="description"><?php esc_html_e( 'When checked, a collapsible Table of Contents will appear at the top of this page.', 'dynamic-toc' ); ?></span>
	</p>
	<?php
}

/**
 * Save the per-page TOC meta and clear TOC transient.
 *
 * Performs nonce & capability checks, updates post meta, and deletes the per-post
 * TOC transient so changes take effect immediately.
 *
 * @since 1.1
 * @param int $post_id Post ID being saved.
 * @return void
 */
function ttm_dynamic_toc_save_meta( int $post_id ): void {
	// security checks.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$nonce = isset( $_POST['ttm_dynamic_toc_meta_box_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ttm_dynamic_toc_meta_box_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ttm_dynamic_toc_meta_box' ) ) {
		return;
	}

	$meta_key = ttm_get_dynamic_toc_meta_key();
	$value = isset( $_POST['ttm_dynamic_toc_enabled'] ) ? '1' : '0';
	update_post_meta( $post_id, $meta_key, $value );

	// Clear cached TOC transient for this post so enabling/disabling or content changes take effect.
	$cache_key = 'ttm_toc_' . (int) $post_id;
	delete_transient( $cache_key );
}
add_action( 'save_post', __NAMESPACE__ . '\\ttm_dynamic_toc_save_meta' );

/**
 * Shortcode handler to render the TOC in-place.
 *
 * Usage: [dynamic_toc]
 *
 * When used as a shortcode, bypasses the per-post enable check since the user
 * has explicitly inserted the block/shortcode. The filter ttm_dynamic_toc_enabled
 * can still be used to prevent rendering if needed.
 *
 * @since 1.4.0
 * @param array $atts Shortcode attributes (reserved for future use).
 * @return string HTML markup for the TOC.
 */
function ttm_dynamic_toc_shortcode( $atts = array() ) {
	global $post;

	if ( empty( $post ) ) {
		return '';
	}

	// When called via shortcode, the user explicitly wants the TOC,
	// so we skip the per-post meta/option check and go straight to rendering.
	// However, we still respect the global filter for override purposes.
	$enabled = apply_filters( 'ttm_dynamic_toc_enabled', true, $post->ID );

	if ( ! $enabled ) {
		return '';
	}

	$content = get_post_field( 'post_content', $post->ID );
	return automatic_toc( $content, (int) $post->ID );
}
add_shortcode( 'dynamic_toc', __NAMESPACE__ . '\ttm_dynamic_toc_shortcode' );

/**
 * Render the TOC markup from a list of items.
 *
 * @since 1.1
 * @param array<int,array{slug:string,text:string,level:int}> $toc_list Array of TOC items. Each
 *                                                                       item is an associative
 *                                                                       array with keys
 *                                                                       'slug' => string,
 *                                                                       'text' => string and
 *                                                                       'level' => int.
 * @return string TOC HTML markup.
 */
function toc( array $toc_list ): string {

	ob_start();

	/* Generate unique IDs so multiple TOCs on a page won't conflict. */
	$uniq = wp_unique_id( 'ttm-toc-' );
	$nav_label_id = 'ttm-toc-heading-' . $uniq;
	$toggle_id = 'ttm-toc-toggle-' . $uniq;
	$panel_id = 'ttm-toc-panel-' . $uniq;
	$count = count( $toc_list );

	?>
	<nav class="ttm-toc" aria-labelledby="<?php echo esc_attr( $nav_label_id ); ?>">
		<h2 id="<?php echo esc_attr( $nav_label_id ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Table of contents', 'dynamic-toc' ); ?></h2>

		<div class="ttm-toc__toggle">
			<button id="<?php echo esc_attr( $toggle_id ); ?>" class="ttm-toc__button" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="ttm-toc__title"><?php echo esc_html__( 'Table of contents', 'dynamic-toc' ); ?></span>
				<span class="ttm-toc__info">
					<span class="ttm-toc__count">(<?php echo esc_html( (string) $count ); ?>)</span>
					<span class="ttm-toc__icon" aria-hidden="true">+</span>
				</span>
			</button>
		</div>

		<div id="<?php echo esc_attr( $panel_id ); ?>" class="ttm-toc__panel" role="region" aria-labelledby="<?php echo esc_attr( $toggle_id ); ?>" hidden>
			<?php
			// Render a nested ordered list based on heading levels. Compute the minimum
			// heading level present so we render lists starting from that base.
			$levels = array_map(
				static function ( $it ) {
					return isset( $it['level'] ) ? (int) $it['level'] : 2;
				},
				$toc_list
			);
			$min_level = ! empty( $levels ) ? min( $levels ) : 2;

			$prev_level = $min_level - 1;

			foreach ( $toc_list as $item ) :
				$level = isset( $item['level'] ) ? (int) $item['level'] : $min_level;
				if ( $level < $min_level ) {
					$level = $min_level;
				}

				if ( $level > $prev_level ) {
					for ( $i = $prev_level + 1; $i <= $level; $i++ ) {
						echo '<ol class="ttm-toc__list ttm-toc__list--level-' . esc_attr( $i ) . '">';
					}
				} elseif ( $level < $prev_level ) {
					for ( $i = $prev_level; $i > $level; $i-- ) {
						echo '</li></ol>';
					}
					echo '</li>';
				} elseif ( $prev_level >= $min_level ) {
					// Same level as previous, close previous li.
					echo '</li>';
				}

				// Output current list item (left open so nested lists can be appended).
				?>
				<li class="ttm-toc__item"><a href="#<?php echo esc_attr( $item['slug'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
				<?php
				$prev_level = $level;
			endforeach;

			// Close any remaining open lists.
			for ( $i = $prev_level; $i >= $min_level; $i-- ) {
				echo '</li></ol>';
			}
			?>
		</div>
	</nav>

	<?php
	return ob_get_clean();
}
