<?php
/**
 * Template: Google Reviews
 *
 * Displays up to 5 Google reviews fetched server-side and cached as a transient.
 * Zero Google scripts are loaded — all data comes from PHP.
 *
 * Override in your theme: {theme}/dish-events/reviews.php
 *
 * Variables available:
 *   $reviews  array  Normalised review objects from GoogleReviews::get().
 *
 * Each review:
 *   $review['author_name']   string
 *   $review['rating']        int     1–5
 *   $review['text']          string
 *   $review['time']          int     Unix timestamp
 *   $review['relative_time'] string  e.g. "3 months ago"
 *   $review['photo_url']     string  Reviewer avatar URL (from Google CDN)
 *
 * @package Dish\Events\Templates
 */

if ( empty( $reviews ) || ! is_array( $reviews ) ) {
	return;
}
?>
<section class="reviews" aria-label="<?php esc_attr_e( 'Customer Reviews', 'dish-events' ); ?>">

	<ul class="reviews__list" role="list">

		<?php foreach ( $reviews as $review ) : ?>

			<li class="reviews__item review-card">

				<div class="review-card__header">

					<?php if ( ! empty( $review['photo_url'] ) ) : ?>
						<img
							class="review-card__avatar"
							src="<?php echo esc_url( $review['photo_url'] ); ?>"
							alt="<?php echo esc_attr( $review['author_name'] ); ?>"
							width="40"
							height="40"
							loading="lazy"
							decoding="async"
						>
					<?php else : ?>
						<span class="review-card__avatar review-card__avatar--placeholder" aria-hidden="true">
							<?php echo esc_html( mb_substr( $review['author_name'], 0, 1 ) ); ?>
						</span>
					<?php endif; ?>

					<div class="review-card__meta">
						<span class="review-card__author"><?php echo esc_html( $review['author_name'] ); ?></span>
						<span class="review-card__date"><?php echo esc_html( $review['relative_time'] ); ?></span>
					</div>

				</div><!-- .review-card__header -->

				<div class="review-card__stars" aria-label="<?php echo esc_attr( sprintf( __( '%d out of 5 stars', 'dish-events' ), $review['rating'] ) ); ?>" role="img">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<span class="review-card__star<?php echo $i <= $review['rating'] ? ' review-card__star--filled' : ''; ?>" aria-hidden="true">★</span>
					<?php endfor; ?>
				</div>

				<?php if ( ! empty( $review['text'] ) ) : ?>
					<p class="review-card__text"><?php echo esc_html( $review['text'] ); ?></p>
				<?php endif; ?>

			</li><!-- .reviews__item -->

		<?php endforeach; ?>

	</ul><!-- .reviews__list -->

</section><!-- .reviews -->
