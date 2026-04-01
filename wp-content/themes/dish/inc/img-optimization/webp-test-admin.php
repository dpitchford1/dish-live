<?php
/**
 * WebP Testing Admin Page
 *
 * Provides a simple admin page to test WebP support, file existence, and filter output.
 * Useful for debugging and verifying WebP integration.
 *
 * @package basecamp
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add WebP test page to the admin Tools menu.
 */
function basecamp_webp_test_menu() {
    add_management_page(
        'WebP Testing',            // Page title
        'WebP Testing',            // Menu title
        'manage_options',          // Capability
        'basecamp-webp-test',      // Menu slug
        'basecamp_webp_test_page'  // Callback function
    );
}
add_action('admin_menu', 'basecamp_webp_test_menu');

/**
 * Display the WebP test admin page.
 */
function basecamp_webp_test_page() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    echo '<div class="wrap">';
    echo '<h1>WebP Implementation Testing</h1>';
    
    // Test browser support
    echo '<h2>Browser WebP Support</h2>';
    echo '<p>basecamp_webp_is_supported(): ' . (basecamp_webp_is_supported() ? 'YES' : 'NO') . '</p>';
    
    // Check direct headers
    echo '<p>HTTP_ACCEPT contains image/webp: ' . (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false ? 'YES' : 'NO') . '</p>';
    echo '<p>User Agent: ' . esc_html($_SERVER['HTTP_USER_AGENT']) . '</p>';
    
    // Test WebP file finding
    echo '<h2>WebP File Path Testing</h2>';
    
    // Get recent images from the media library
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => 5,
        'post_status' => 'inherit'
    ));
    
    if (empty($attachments)) {
        echo '<p>No image attachments found.</p>';
    } else {
        echo '<table class="widefat">';
        echo '<thead><tr>
            <th>Image ID</th>
            <th>Original URL</th>
            <th>WebP URL</th>
            <th>Original File Exists</th>
            <th>Standard WebP Format Exists</th>
            <th>Appended WebP Format Exists</th>
            <th>Effective WebP Path</th>
        </tr></thead><tbody>';
        
        foreach ($attachments as $attachment) {
            $img_url = wp_get_attachment_url($attachment->ID);
            $webp_url = basecamp_get_webp_image($img_url);
            
            $upload_dir = wp_upload_dir();
            $original_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $img_url);
            
            $original_exists = file_exists($original_path);
            
            // Get paths for both WebP formats
            $path_parts = pathinfo($original_path);
            $standard_webp_path = str_replace('.' . $path_parts['extension'], '.webp', $original_path);
            $appended_webp_path = $original_path . '.webp';
            
            $standard_webp_exists = file_exists($standard_webp_path);
            $appended_webp_exists = file_exists($appended_webp_path);
            
            // Determine which WebP path is being used
            $effective_webp_path = '-';
            if ($webp_url) {
                if ($standard_webp_exists) {
                    $effective_webp_path = $standard_webp_path;
                } else if ($appended_webp_exists) {
                    $effective_webp_path = $appended_webp_path;
                }
            }
            
            echo '<tr>';
            echo '<td>' . $attachment->ID . '</td>';
            echo '<td>' . esc_html($img_url) . '</td>';
            echo '<td>' . ($webp_url ? esc_html($webp_url) : 'Not found') . '</td>';
            echo '<td>' . ($original_exists ? 'YES' : 'NO') . '</td>';
            echo '<td>' . ($standard_webp_exists ? 'YES' : 'NO') . ' <br><small>' . esc_html($standard_webp_path) . '</small></td>';
            echo '<td>' . ($appended_webp_exists ? 'YES' : 'NO') . ' <br><small>' . esc_html($appended_webp_path) . '</small></td>';
            echo '<td><small>' . esc_html($effective_webp_path) . '</small></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Test filter application
    echo '<h2>Filter Testing</h2>';
    
    // Get a test image
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => 1,
        'post_status' => 'inherit'
    ));
    
    if (empty($attachments)) {
        echo '<p>No image attachments found.</p>';
    } else {
        $attachment_id = $attachments[0]->ID;
        
        // Output HTML for image with and without extra class
        echo '<h3>Regular Image Output (without srcset-enabled class)</h3>';
        echo '<pre style="background:#f6f6f6;padding:10px;overflow:auto;">';
        $img_html = wp_get_attachment_image($attachment_id, 'large');
        echo htmlspecialchars($img_html);
        echo '</pre>';
        
        echo '<h3>Image with srcset-enabled class</h3>';
        echo '<pre style="background:#f6f6f6;padding:10px;overflow:auto;">';
        $img_html_with_class = wp_get_attachment_image(
            $attachment_id, 
            'large', 
            false,
            array('class' => 'srcset-enabled')
        );
        echo htmlspecialchars($img_html_with_class);
        echo '</pre>';
        
        echo '<h3>Displayed Images:</h3>';
        echo '<div style="display:flex;gap:20px;margin-bottom:20px;">';
        echo '<div><p>Regular image:</p>' . $img_html . '</div>';
        echo '<div><p>With srcset-enabled class:</p>' . $img_html_with_class . '</div>';
        echo '</div>';

        // Show registered filters for debugging
        echo '<h3>Registered Filters</h3>';
        global $wp_filter;
        $relevant_filters = array(
            'wp_get_attachment_image_src',
            'wp_get_attachment_image_attributes',
            'post_thumbnail_html'
        );
        
        echo '<ul>';
        foreach ($relevant_filters as $filter_name) {
            echo '<li><strong>' . esc_html($filter_name) . ':</strong> ';
            if (isset($wp_filter[$filter_name])) {
                echo 'Active - ' . count($wp_filter[$filter_name]->callbacks) . ' callbacks';
                echo '<ul>';
                foreach ($wp_filter[$filter_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $id => $callback) {
                        if (is_array($callback['function']) && is_object($callback['function'][0])) {
                            echo '<li>Priority ' . $priority . ': ' . get_class($callback['function'][0]) . '::' . $callback['function'][1] . '</li>';
                        } elseif (is_array($callback['function'])) {
                            echo '<li>Priority ' . $priority . ': ' . (is_string($callback['function'][0]) ? $callback['function'][0] : 'Object') . '::' . $callback['function'][1] . '</li>';
                        } else {
                            echo '<li>Priority ' . $priority . ': ' . (is_string($callback['function']) ? $callback['function'] : 'Closure') . '</li>';
                        }
                    }
                }
                echo '</ul>';
            } else {
                echo 'Not active';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
}

// Remove test file after use (cleanup on deactivation)
register_deactivation_hook(__FILE__, function() {
    if (file_exists(get_template_directory() . '/webp-test.php')) {
        @unlink(get_template_directory() . '/webp-test.php');
    }
});