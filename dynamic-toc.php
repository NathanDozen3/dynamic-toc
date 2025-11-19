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
add_action( 'wp_enqueue_scripts', function() {
    wp_register_script( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'js/dynamic-toc.js', array(), '1.1', true );
    wp_register_style( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'css/dynamic-toc.css', array(), '1.1' );
} );

add_filter( 'the_content', __NAMESPACE__ . '\\ttm_dynamic_toc_the_content' );

/**
 * Main content filter wrapper. Checks context and ACF field safely, then generates TOC when appropriate.
 */
function ttm_dynamic_toc_the_content( $content ) {
    // Only run on front-end singular pages in the main query
    if ( is_admin() || ! is_singular() ) {
        return $content;
    }

    global $post;

    // Safe ACF check (ACF may not be active)
    $enabled = false;
    if ( function_exists( 'get_field' ) ) {
        $field = get_field( 'enable_dynamic_toc' );
        $enabled = is_array( $field ) ? in_array( 'enable_dynamic_toc', $field, true ) : (bool) $field;
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

    // Identify all <div> elements that include the class "custom_block" and mark descendant H2s to ignore
    $ignore_map = array();
    $divs = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " custom_block ")]' );
    foreach ( $divs as $div ) {
        $h2s_in_div = $div->getElementsByTagName( 'h2' );
        foreach ( $h2s_in_div as $h2 ) {
            $ignore_map[spl_object_hash( $h2 )] = true;
        }
    }

    // Gather all H2 elements
    $h2s = $doc->getElementsByTagName( 'h2' );
    $used_slugs = array();
    foreach ( $h2s as $h2 ) {
        // Skip any H2s inside ignored containers
        if ( isset( $ignore_map[spl_object_hash( $h2 )] ) ) {
            continue;
        }

        $heading_text = trim( $h2->textContent );
        if ( $heading_text === '' ) {
            continue;
        }

        // Use existing ID if present; otherwise generate a safe WP slug and ensure uniqueness
        if ( $h2->hasAttribute( 'id' ) ) {
            $slug = $h2->getAttribute( 'id' );
        } else {
            $slug = \sanitize_title( wp_strip_all_tags( $heading_text ) );
            $original = $slug;
            $i = 2;
            while ( in_array( $slug, $used_slugs, true ) ) {
                $slug = $original . '-' . $i;
                $i++;
            }
            $h2->setAttribute( 'id', $slug );
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

    return $toc_html . $new_content;
}

function toc( array $toc_list ) : string {

    ob_start();
    ?>
    <div class="toc">
        <div class="tocaccordion">
            <div class="tocaccordion-item">
                <button class="tocaccordion-title">
                    <span class="tocaccordion-backdrop"></span>
                    <span class="tocaccordion-header">Table of Contents</span>
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

add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
        'key' => 'group_67853d2c2552d',
        'title' => 'Dynamic TOC',
        'fields' => array(
            array(
                'key' => 'field_67853d2c60e58',
                'label' => 'Enable Dynamic TOC',
                'name' => 'enable_dynamic_toc',
                'aria-label' => '',
                'type' => 'checkbox',
                'instructions' => 'This is a dynamic Table of Contents function that will display an accordion TOC at the top of the page based of the H2s on the page.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'choices' => array(
                    'enable_dynamic_toc' => 'Yes',
                ),
                'default_value' => array(
                ),
                'return_format' => 'value',
                'allow_custom' => 0,
                'allow_in_bindings' => 0,
                'layout' => 'vertical',
                'toggle' => 0,
                'save_custom' => 0,
                'custom_choice_button_text' => 'Add new choice',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );
} );
