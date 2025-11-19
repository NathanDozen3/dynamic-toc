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
 * Version:           1.2.0
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
	$meta_key = apply_filters( 'ttm_dynamic_toc_meta_key', 'ttm_dynamic_toc_enabled' );
	$meta_val = get_post_meta( isset( $post->ID ) ? (int) $post->ID : 0, $meta_key, true );

	if ( '' !== $meta_val && null !== $meta_val ) {
		$enabled = (bool) $meta_val;
	} else {
		// Read enable flag from an option (remove ACF dependency). Site owners can set this option.
		// Or filter it programmatically. Default: false.
		$enabled = (bool) get_option( 'ttm_dynamic_toc_enabled', false );
	}

	// Allow programmatic overrides.
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
		$toc_list[] = array(
			'slug' => $slug,
			'text' => $heading_text,
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
	$post_types = apply_filters( 'ttm_dynamic_toc_meta_post_types', array( 'page' ) );
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
 * Meta box display callback.
 *
 * @since 1.1
 * @param WP_Post $post Current post object.
 * @return void Outputs the meta box HTML.
 */
function ttm_dynamic_toc_meta_box_callback( WP_Post $post ): void {
	$meta_key = apply_filters( 'ttm_dynamic_toc_meta_key', 'ttm_dynamic_toc_enabled' );
	$value = get_post_meta( $post->ID, $meta_key, true );
	wp_nonce_field( 'ttm_dynamic_toc_meta_box', 'ttm_dynamic_toc_meta_box_nonce' );
	?>
	<p>
		<label for="ttm_dynamic_toc_enabled">
			<input type="checkbox" name="ttm_dynamic_toc_enabled" id="ttm_dynamic_toc_enabled" value="1" <?php checked( $value, '1' ); ?> />
			<?php esc_html_e( 'Enable Dynamic Table of Contents for this page', 'dynamic-toc' ); ?>
		</label>
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

	$meta_key = apply_filters( 'ttm_dynamic_toc_meta_key', 'ttm_dynamic_toc_enabled' );
	$value = isset( $_POST['ttm_dynamic_toc_enabled'] ) ? '1' : '0';
	update_post_meta( $post_id, $meta_key, $value );

	// Clear cached TOC transient for this post so enabling/disabling or content changes take effect.
	$cache_key = 'ttm_toc_' . (int) $post_id;
	delete_transient( $cache_key );
}
add_action( 'save_post', __NAMESPACE__ . '\\ttm_dynamic_toc_save_meta' );

/**
 * Render the TOC markup from a list of items.
 *
 * @since 1.1
 * @param array<int,array{slug:string,text:string}> $toc_list Array of TOC items. Each item is an
 *                                                               associative array with keys
 *                                                               'slug' => string and
 *                                                               'text' => string.
 * @return string TOC HTML markup.
 */
function toc( array $toc_list ): string {

	ob_start();
	?>
	<div class="toc">
		<div class="tocaccordion">
			<div class="tocaccordion-item">
				<button class="tocaccordion-title" aria-expanded="false" type="button">
					<span class="tocaccordion-backdrop"></span>
					<span class="tocaccordion-header"><?php echo esc_html__( 'Table of Contents', 'dynamic-toc' ); ?></span>
					<span class="tocaccordion-icon">+</span>
				</button>
				<div class="tocaccordion-body">
					<ul>
						<?php foreach ( $toc_list as $item ) : ?>
							<li><a href="#<?php echo esc_attr( $item['slug'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
	
	<?php
	return ob_get_clean();
}
