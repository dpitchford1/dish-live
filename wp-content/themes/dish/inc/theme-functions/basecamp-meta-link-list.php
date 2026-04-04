<?php

declare(strict_types=1);
/**
 * Meta box for a customizable repeater link list.
 *
 * Use the 'basecamp_link_list_meta_box_args' filter to control post-type / template scope.
 * The global wrapper basecamp_get_link_list() is kept for template back-compat.
 *
 * @package basecamp
 */

namespace Basecamp\ThemeFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the repeater link-list meta box and its data.
 */
final class MetaLinkList {

	/**
	 * Register hooks (admin-only).
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes',        [ __CLASS__, 'register_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
		add_action( 'save_post',             [ __CLASS__, 'save' ] );
	}

	/**
	 * Add a meta box for a customizable link list.
	 * Use the 'basecamp_link_list_meta_box_args' filter to control where it appears.
	 */
	public static function register_meta_box(): void {
    $args = apply_filters('basecamp_link_list_meta_box_args', [
        'id'       => 'basecamp_link_list',
        'title'    => 'Link List',
        'post_type'=> 'page',
        'context'  => 'normal',
        'priority' => 'default',
        'templates'=> ['page-about.php'], // Default: only About page template
    ]);
    add_meta_box(
			$args['id'],
			$args['title'],
			function( $post ) use ( $args ) {
				self::render_meta_box( $post, $args );
			},
			$args['post_type'],
			$args['context'],
			$args['priority']
		);
	}

	/**
	 * Enqueue jQuery and jQuery UI Sortable for the link list meta box.
	 */
	public static function admin_scripts( $hook ): void {
		global $post;
		if ( ! $post ) {
			return;
		}
		$args = apply_filters( 'basecamp_link_list_meta_box_args', [
			'post_type' => 'page',
			'templates' => [ 'page-about.php' ],
		] );
		$post_types = (array) ( $args['post_type'] ?? 'page' );
		$templates  = (array) ( $args['templates']  ?? [] );
		$show = in_array( $post->post_type, $post_types, true )
			&& ( empty( $templates ) || in_array( get_page_template_slug( $post->ID ), $templates, true ) );
		if ( ( 'post-new.php' === $hook || 'post.php' === $hook ) && $show ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}

	/**
	 * Render the link list meta box.
	 *
	 * @param \WP_Post $post
	 * @param array    $args
	 */
	public static function render_meta_box( $post, $args = [] ): void {
		$links = get_post_meta( $post->ID, '_basecamp_link_list', true );
		// Always show at least one row (empty if no links).
		if ( ! is_array( $links ) || count( $links ) === 0 ) {
			$links = [ [ 'label' => '', 'url' => '', 'new_tab' => 0 ] ];
		}
		wp_nonce_field( 'basecamp_link_list_nonce', 'basecamp_link_list_nonce_field' );
		?>
    <style>
        .link-list-row { display: flex; align-items: center; gap: 8px; }
        .link-list-row .drag-handle { cursor: move; font-size: 18px; color: #888; padding: 0 8px; }
    </style>
    <div id="link-list-rows">
        <?php foreach ($links as $i => $link): ?>
            <div class="link-list-row" style="margin-bottom:10px;">
                <span class="drag-handle" title="Drag to reorder" aria-label="Drag to reorder">&#9776;</span>
                <input type="text" name="basecamp_link_list[<?php echo $i; ?>][label]" placeholder="Label" value="<?php echo esc_attr($link['label'] ?? ''); ?>" style="width:20%;" aria-label="Link label" />
                <input type="url" name="basecamp_link_list[<?php echo $i; ?>][url]" placeholder="URL" value="<?php echo esc_url($link['url'] ?? ''); ?>" style="width:40%;" aria-label="Link URL" />
                <label>
                    <input type="checkbox" name="basecamp_link_list[<?php echo $i; ?>][new_tab]" value="1" <?php checked(!empty($link['new_tab'])); ?> aria-label="Open in new tab" />
                    Open in new tab
                </label>
                <button class="remove-link button" type="button" aria-label="Remove link">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="add-link-list-row" aria-label="Add link">Add link</button>
    <script>
    (function($){
        function updateLinkIndexes() {
            $('#link-list-rows .link-list-row').each(function(i, row){
                $(row).find('input, label input').each(function(){
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/basecamp_link_list\[\d+\]/, 'basecamp_link_list['+i+']');
                        $(this).attr('name', name);
                    }
                });
            });
        }
        $(document).ready(function(){
            $('#link-list-rows').sortable({
                handle: '.drag-handle',
                items: '.link-list-row',
                update: function() { updateLinkIndexes(); }
            });
            $('#add-link-list-row').on('click', function(e){
                e.preventDefault();
                var i = $('#link-list-rows .link-list-row').length;
                var row = `<div class="link-list-row" style="margin-bottom:10px;">
                    <span class="drag-handle" title="Drag to reorder" aria-label="Drag to reorder">&#9776;</span>
                    <input type="text" name="basecamp_link_list[`+i+`][label]" placeholder="Label" style="width:20%;" aria-label="Link label" />
                    <input type="url" name="basecamp_link_list[`+i+`][url]" placeholder="URL" style="width:40%;" aria-label="Link URL" />
                    <label>
                        <input type="checkbox" name="basecamp_link_list[`+i+`][new_tab]" value="1" aria-label="Open in new tab" />
                        Open in new tab
                    </label>
                    <button class="remove-link button" type="button" aria-label="Remove link">Remove</button>
                </div>`;
                $('#link-list-rows').append(row);
            });
            $(document).on('click', '.remove-link', function(e){
                e.preventDefault();
                $(this).closest('.link-list-row').remove();
                updateLinkIndexes();
            });
        });
    })(jQuery);
    </script>
		<?php
	}

	/**
	 * Save the link list meta box data.
	 *
	 * @param int $post_id
	 */
	public static function save( int $post_id ): void {
		$nonce = isset( $_POST['basecamp_link_list_nonce_field'] ) ? $_POST['basecamp_link_list_nonce_field'] : '';
		if ( ! wp_verify_nonce( $nonce, 'basecamp_link_list_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['basecamp_link_list'] ) && is_array( $_POST['basecamp_link_list'] ) ) {
			$links = [];
			foreach ( $_POST['basecamp_link_list'] as $link ) {
				if ( empty( $link['url'] ) ) {
					continue;
				}
				$links[] = [
					'label'   => sanitize_text_field( $link['label'] ?? '' ),
					'url'     => esc_url_raw( $link['url'] ),
					'new_tab' => ! empty( $link['new_tab'] ) ? 1 : 0,
				];
			}
			update_post_meta( $post_id, '_basecamp_link_list', $links );
		} else {
			delete_post_meta( $post_id, '_basecamp_link_list' );
		}
	}

	/**
	 * Helper to get the link list array (no HTML).
	 *
	 * @param int|null $post_id Post ID; falls back to get_the_ID().
	 * @return array
	 */
	public static function get( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		$links = get_post_meta( $post_id, '_basecamp_link_list', true );
		if ( ! is_array( $links ) || empty( $links ) ) {
			return [];
		}
		return $links;
	}
}

MetaLinkList::init();