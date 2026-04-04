<?php

declare(strict_types=1);
/**
 * Video Carousel Meta Box
 * Adds a repeater-style meta box for managing video carousel slides.
 */

namespace Basecamp\Frontend;

final class VideoCarouselMetabox {
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ] );
	}

	public function add_metabox() {
		add_meta_box(
			'basecamp_video_carousel',
			'Video Carousel Slides',
			[ $this, 'render_metabox' ],
			'page',
			'normal',
			'default'
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( 'basecamp_video_carousel_nonce', 'basecamp_video_carousel_nonce_field' );
		$slides = get_post_meta( $post->ID, '_basecamp_video_carousel_slides', true );
		if ( ! is_array( $slides ) ) $slides = [];

		?>
		<div id="basecamp-video-carousel-metabox">
			<table class="widefat">
				<thead>
					<tr>
						<th>Desktop Video URL</th>
						<th>Mobile Video URL</th>
						<th>Desktop Poster Image URL</th>
						<th>Mobile Poster Image URL</th>
						<th>Overlay Text</th>
						<th>Audio File URL</th>
						<th>Remove</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $slides as $i => $slide ) : ?>
						<tr>
							<td><input type="text" name="basecamp_video_carousel_slides[<?php echo $i; ?>][desktop_video]" value="<?php echo esc_attr( $slide['desktop_video'] ?? '' ); ?>" style="width:100%;" /></td>
							<td><input type="text" name="basecamp_video_carousel_slides[<?php echo $i; ?>][mobile_video]" value="<?php echo esc_attr( $slide['mobile_video'] ?? '' ); ?>" style="width:100%;" /></td>
							<td><input type="text" name="basecamp_video_carousel_slides[<?php echo $i; ?>][poster]" value="<?php echo esc_attr( $slide['poster'] ?? '' ); ?>" style="width:100%;" /></td>
							<td><input type="text" name="basecamp_video_carousel_slides[<?php echo $i; ?>][mobile_poster]" value="<?php echo esc_attr( $slide['mobile_poster'] ?? '' ); ?>" style="width:100%;" /></td>
							<td><textarea name="basecamp_video_carousel_slides[<?php echo $i; ?>][overlay]" style="width:100%;"><?php echo esc_textarea( $slide['overlay'] ?? '' ); ?></textarea></td>
							<td><input type="text" name="basecamp_video_carousel_slides[<?php echo $i; ?>][audio]" value="<?php echo esc_attr( $slide['audio'] ?? '' ); ?>" style="width:100%;" /></td>
							<td><button type="button" class="remove-slide button">Remove</button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="add-video-carousel-slide">Add Slide</button>
			</p>
		</div>
		<script>
		(function($){
			$('#add-video-carousel-slide').on('click', function(){
				var $tbody = $('#basecamp-video-carousel-metabox tbody');
				var i = $tbody.find('tr').length;
				var row = `<tr>
					<td><input type="text" name="basecamp_video_carousel_slides[${i}][desktop_video]" style="width:100%;" /></td>
					<td><input type="text" name="basecamp_video_carousel_slides[${i}][mobile_video]" style="width:100%;" /></td>
					<td><input type="text" name="basecamp_video_carousel_slides[${i}][poster]" style="width:100%;" /></td>
					<td><input type="text" name="basecamp_video_carousel_slides[${i}][mobile_poster]" style="width:100%;" /></td>
					<td><textarea name="basecamp_video_carousel_slides[${i}][overlay]" style="width:100%;"></textarea></td>
					<td><input type="text" name="basecamp_video_carousel_slides[${i}][audio]" style="width:100%;" /></td>
					<td><button type="button" class="remove-slide button">Remove</button></td>
				</tr>`;
				$tbody.append(row);
			});
			$(document).on('click', '.remove-slide', function(){
				$(this).closest('tr').remove();
			});
		})(jQuery);
		</script>
		<style>
			#basecamp-video-carousel-metabox textarea { min-height: 40px; }
		</style>
		<?php
	}

	public function save_metabox( $post_id ) {
		if ( ! isset( $_POST['basecamp_video_carousel_nonce_field'] ) ||
		     ! wp_verify_nonce( $_POST['basecamp_video_carousel_nonce_field'], 'basecamp_video_carousel_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_page', $post_id ) ) return;

		$slides = $_POST['basecamp_video_carousel_slides'] ?? [];
		// Sanitize
		$clean = [];
		foreach ( $slides as $slide ) {
			if ( empty( $slide['desktop_video'] ) && empty( $slide['mobile_video'] ) ) continue;
			$clean[] = [
				'desktop_video'  => esc_url_raw( $slide['desktop_video'] ?? '' ),
				'mobile_video'   => esc_url_raw( $slide['mobile_video'] ?? '' ),
				'poster'         => esc_url_raw( $slide['poster'] ?? '' ),
				'mobile_poster'  => esc_url_raw( $slide['mobile_poster'] ?? '' ),
				'overlay'        => sanitize_text_field( $slide['overlay'] ?? '' ),
				'audio'          => esc_url_raw( $slide['audio'] ?? '' ),
			];
		}
		update_post_meta( $post_id, '_basecamp_video_carousel_slides', $clean );
	}
}

new VideoCarouselMetabox();
