<?php
/** 
 * Plugin Name: Dynamic Table of Contents Generator
 * Version: 1.1
 * Description: Automatically generates a dynamic table of contents for posts and pages based on headings.
*/

declare( strict_types=1 );
namespace TTM\Dynamic_TOC;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load textdomain for translations
 */
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'dynamic-toc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Register front-end assets so they can be enqueued only when needed
 */
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\ttm_dynamic_toc_register_assets' );

/**
 * Register plugin assets (scripts & styles).
 */
function ttm_dynamic_toc_register_assets() {
    wp_register_script( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'js/dynamic-toc.js', array(), '1.1', true );
    wp_register_style( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'css/dynamic-toc.css', array(), '1.1' );
}

add_filter( 'the_content', __NAMESPACE__ . '\ttm_dynamic_toc_the_content' );

/**
 * Main content filter wrapper. Checks context and option/filters, then generates TOC when appropriate.
 */
function ttm_dynamic_toc_the_content( $content ) {
    // Only run on front-end singular pages in the main query
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
        // Read enable flag from an option (remove ACF dependency). Site owners can set this option
        // or filter it programmatically. Default: false.
        $enabled = (bool) get_option( 'ttm_dynamic_toc_enabled', false );
    }

    // Allow programmatic overrides
    $enabled = apply_filters( 'ttm_dynamic_toc_enabled', $enabled, isset( $post->ID ) ? $post->ID : 0 );
    if ( ! $enabled ) {
        return $content;
    }

    return automatic_toc( $content, isset( $post->ID ) ? (int) $post->ID : 0 );
}

function automatic_toc( string $content, int $post_id = 0 ): string {
    // Build TOC using DOMDocument to be more reliable than regex
    $toc_list = array();

    // Suppress libxml warnings for malformed HTML fragments
    $internal_errors = libxml_use_internal_errors( true );

    $doc = new \DOMDocument();
    // Wrap content in a container so we can extract modified inner HTML later
    $wrapped = '<div class="ttm-toc-wrapper">' . $content . '</div>';
    $doc->loadHTML( mb_convert_encoding( $wrapped, 'HTML-ENTITIES', 'UTF-8' ) );
    $xpath = new \DOMXPath( $doc );

    // Allow configurable heading levels (defaults to h2-h4)
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
        // fallback to h2 if configuration is empty/invalid
        $tags = array( 'h2' );
    }

    // Build xpath query to select all requested heading tags in document order
    $xpath_query_parts = array();
    foreach ( $tags as $tag ) {
        $xpath_query_parts[] = '//' . $tag;
    }
    $xpath_query = implode( '|', $xpath_query_parts );

    // Identify any heading nodes inside custom_block containers and mark them to ignore
    $ignore_map = array();
    $custom_divs = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " custom_block ")]' );
    foreach ( $custom_divs as $div ) {
        $inner_nodes = $xpath->query( '.' . str_replace('//', '/', $xpath_query), $div );
        foreach ( $inner_nodes as $n ) {
            $ignore_map[spl_object_hash( $n )] = true;
        }
    }

    // Gather all heading nodes
    $nodes = $xpath->query( $xpath_query );
    $used_slugs = array();
    foreach ( $nodes as $node ) {
        // Skip any headings inside ignored containers
        if ( isset( $ignore_map[spl_object_hash( $node )] ) ) {
            continue;
        }

        $heading_text = trim( $node->textContent );
        if ( $heading_text === '' ) {
            continue;
        }

        // Use existing ID if present; otherwise generate a safe WP slug and ensure uniqueness
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
        $toc_list[] = array( 'slug' => $slug, 'text' => $heading_text );
    }

    // Restore libxml state
    libxml_clear_errors();
    libxml_use_internal_errors( $internal_errors );

    // Nothing to do if no headings found
    if ( empty( $toc_list ) ) {
        return $content;
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

    // Enqueue assets only when we will output the TOC
    if ( ! is_admin() ) {
        wp_enqueue_script( 'ttm-dynamic-toc' );
        wp_enqueue_style( 'ttm-dynamic-toc' );
    }

    // Extract the modified inner HTML of our wrapper container
    $wrapper = $doc->getElementsByTagName( 'div' )->item( 0 );
    $new_content = '';
    if ( $wrapper ) {
        foreach ( $wrapper->childNodes as $child ) {
            $new_content .= $doc->saveHTML( $child );
        }
    } else {
        // Fallback
        $new_content = $content;
    }

    // Build TOC HTML and allow developers to filter it
    $toc_html = toc( $toc_list );
    $toc_html = apply_filters( 'ttm_dynamic_toc_html', $toc_html, $toc_list, $post_id );

    $result = $toc_html . $new_content;
    // Store in transient with hash for validation
    set_transient( $cache_key, array( 'hash' => $content_hash, 'html' => $result ), $cache_ttl );

    return $result;
}

/**
 * Add per-page meta box for enabling the TOC.
 */
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\ttm_dynamic_toc_add_meta_box' );
function ttm_dynamic_toc_add_meta_box() {
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

function ttm_dynamic_toc_meta_box_callback( $post ) {
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
 * Save meta and clear cached transient for this post so changes take immediate effect.
 */
add_action( 'save_post', __NAMESPACE__ . '\\ttm_dynamic_toc_save_meta' );
function ttm_dynamic_toc_save_meta( $post_id ) {
    // security checks
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( ! isset( $_POST['ttm_dynamic_toc_meta_box_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ttm_dynamic_toc_meta_box_nonce'] ), 'ttm_dynamic_toc_meta_box' ) ) {
        return;
    }

    $meta_key = apply_filters( 'ttm_dynamic_toc_meta_key', 'ttm_dynamic_toc_enabled' );
    $value = isset( $_POST['ttm_dynamic_toc_enabled'] ) ? '1' : '0';
    update_post_meta( $post_id, $meta_key, $value );

    // Clear cached TOC transient for this post so enabling/disabling or content changes take effect
    $cache_key = 'ttm_toc_' . (int) $post_id;
    delete_transient( $cache_key );
}

function toc( array $toc_list ) : string {

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
