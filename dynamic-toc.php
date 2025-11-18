<?php
/** 
 * Plugin Name: Dynamic Table of Contents Generator
 * Version: 1.0.1
 * Description: Automatically generates a dynamic table of contents for posts and pages based on headings.
*/

declare( strict_types=1 );
namespace TTM\Dynamic_TOC;

add_action( 'the_content', function( $content ) {
    if( ! get_field( 'enable_dynamic_toc' ) ) {
        return $content;
    };
    return automatic_toc( $content );
});

function automatic_toc( string $content ): string {

    // Identify <h2> tags inside divs with class "custom_block" (handles multiple classes)
    $exclude_pattern = '/<div[^>]*\bclass\b[^>]*=\s*"[^"]*\bcustom_block\b[^"]*"[^>]*>(.*?)<\/div>/is';
    preg_match_all($exclude_pattern, $content, $excluded_blocks);
    $excluded_h2s = [];

    foreach ($excluded_blocks[1] as $block_content) {
        preg_match_all('/<h2([^>]*)>(.*?)<\/h2>/i', $block_content, $h2_matches, PREG_SET_ORDER);
        foreach ($h2_matches as $h2) {
            $excluded_h2s[] = $h2[0];
        }
    }

    // Pattern to match <h2> tags
    $pattern = '/<h2([^>]*)>(.*?)<\/h2>/i'; // Match <h2> tags and capture attributes and content inside

    // Process <h2> tags to add unique IDs and populate TOC list
    $content = preg_replace_callback($pattern, function ($matches) use (&$toc_list, $excluded_h2s) {
        if (in_array($matches[0], $excluded_h2s)) {
            // Skip modifying this <h2>
            return $matches[0];
        }

        $attributes = $matches[1];
        $heading_html = $matches[2]; // Retain the full inner HTML

        // Remove any existing ID from the <h2> tag
        $attributes = preg_replace('/\s*id\s*=\s*"[^"]*"/i', '', $attributes); // Remove all ID attributes, even if spaced or formatted differently

        // Generate a slug from the heading text (strip HTML tags before processing for the slug)
        $heading_text = strip_tags($heading_html); // Extract text without HTML
        $slug = strtolower($heading_text); // Convert to lowercase
        $slug = preg_replace('/[^a-z0-9\s]+/', '', $slug); // Remove non-alphanumeric characters except spaces
        $slug = preg_replace('/\s+/', '-', $slug); // Replace spaces with dashes
        $slug = trim($slug, '-'); // Trim leading/trailing dashes

        // Add the slug to the TOC list
        $toc_list[] = [
            'slug' => $slug,
            'text' => $heading_text
        ];

        // Return the modified <h2> with the new ID, preserving the nested elements
        return '<h2 id="' . esc_attr($slug) . '"' . trim($attributes) . '>' . $heading_html . '</h2>';
    }, $content);

    // Return the modified content
    return toc( $toc_list ) . $content;
}

function toc( array $toc_list ) : string {

    ob_start();
    ?>
    <div class="toc">
        <div class="tocaccordion">
            <div class="tocaccordion-item">
                <button class="tocaccordion-title">
                    <div class="tocaccordion-backdrop"></div>
                    <h3 class="tocaccordion-header">Table of Contents</h3>
                    <div class="tocaccordion-icon">+</div>
                </button>
                <div class="tocaccordion-body"><ul>
                    <?php foreach ( $toc_list as $item ) : ?>
                        <li><a href="#<?php echo esc_attr( $item['slug'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
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


add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'js/dynamic-toc.js', array(), '1.0.1', true );
    wp_enqueue_style( 'ttm-dynamic-toc', plugin_dir_url( __FILE__ ) . 'css/dynamic-toc.css', array(), '1.0.1' );
} );