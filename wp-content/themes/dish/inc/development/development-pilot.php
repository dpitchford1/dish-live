<?php
/**
 * Template part for displaying the development pilot helper.
 * Displays a bar below the footer with debug information.
 * Only displays on local development environment.
 *
 * @package Kaneism
 */

// Make sure we have access to the global template data
global $kaneism_template_data;
?>

<?php if(in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) { ?>
    <div id="devMenu" class="devpilot-drawer devpilot-bottom is--static">
        <h2 class="heading-primary">DevPilot Menu</h2>
        <h3 class="title-primary">Page Details</h3>
        <p>Window Width: <span id="width"></span> px</p>
        <div id="debug-features"> for debug output of features </div>
        <div class="template-info">
            <h4 class="title-secondary">Template Information</h4>
            <ul>
                <li>
                    <span class="label">Current Template:</span>
                    <span class="value"><?php echo function_exists('get_current_template') ? get_current_template() : 'Function not available'; ?></span>
                </li>
                <li>
                    <span class="label">Context:</span>
                    <span class="value small-text"><?php echo function_exists('get_template_context') ? get_template_context() : 'Function not available'; ?></span>
                </li>
                <?php if (!empty($kaneism_template_data)): ?>
                <li>
                    <span class="label">Template Source:</span>
                    <span class="value"><?php echo $kaneism_template_data['source']; ?></span>
                </li>
                <li>
                    <span class="label">Template Type:</span>
                    <span class="value"><?php echo $kaneism_template_data['type']; ?></span>
                </li>
                <li>
                    <span class="label">Template Path:</span>
                    <span class="value small-text"><?php echo $kaneism_template_data['path']; ?></span>
                </li>
                <?php endif; ?>
                <?php if (is_singular()): ?>
                <li>
                    <span class="label">Post Type:</span>
                    <span class="value"><?php echo get_post_type(); ?></span>
                </li>
                <?php endif; ?>
                <?php if (is_tax() || is_category() || is_tag()): ?>
                <li>
                    <span class="label">Taxonomy:</span>
                    <span class="value"><?php 
                        $term = get_queried_object();
                        echo $term->taxonomy . ' - ' . $term->name; 
                    ?></span>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="devpilot-tools">
            <button id="html-outline-trigger" class="devpilot-button" aria-controls="html-outline-container">Analyze HTML Outline</button>
        </div>
                
    </div> 
    <div id="html-outline-container" class="html-outline-overlay">
        <div class="html-outline-header">
            <h3 id="outline-title">HTML Outline Analysis</h3>
            <button id="html-outline-close" class="close-button" aria-label="Close HTML outline panel">×</button>
        </div>
        <div class="html-outline-content">
            <div id="html-outline-result"></div>
        </div>
    </div>
    <script>
        function widthSetter() { 
            document.getElementById("width").innerHTML = window.innerWidth; 
            // Adjust HTML outline width for mobile
            if (window.innerWidth < 768) {
                document.getElementById("html-outline-container").style.width = "100%";
            } else {
                document.getElementById("html-outline-container").style.width = "33%";
            }
        }
        widthSetter();
        window.addEventListener("resize", widthSetter);
    </script>
<?php } else { ?>
    <!-- DevPilot is not showing because this is not detected as a local environment -->
<?php } ?>