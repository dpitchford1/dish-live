<?php

declare(strict_types=1);
/**
 * Subnav template tag.
 *
 * Outputs a contextual secondary navigation for pages that have children,
 * or are themselves a child of a parent page.
 *
 * Behaviour:
 *   - Current page has children  → lists those children.
 *   - Current page is a child    → lists its siblings (parent's children).
 *   - Neither                    → outputs nothing.
 *
 * The active page's <li> receives the class `menu--selected` and its <a>
 * receives aria-current="page" for accessibility.
 *
 * Usage: <?php the_subnav(); ?>
 *
 * @package Basecamp\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output a contextual subnav for the current page.
 *
 * @return void
 */
function the_subnav(): void {
	if ( ! is_page() ) {
		return;
	}

	$current_id = (int) get_queried_object_id();
	$post       = get_post( $current_id );

	if ( ! $post ) {
		return;
	}

	$parent_id = (int) $post->post_parent;

	if ( $parent_id ) {
		// Current page is a child — list siblings (parent's children).
		$parent   = get_post( $parent_id );
		$children = get_pages( [
			'parent'      => $parent_id,
			'sort_column' => 'menu_order',
			'sort_order'  => 'ASC',
			'post_status' => 'publish',
		] );
	} else {
		// Current page may be a parent — check for children.
		$parent   = $post;
		$children = get_pages( [
			'parent'      => $current_id,
			'sort_column' => 'menu_order',
			'sort_order'  => 'ASC',
			'post_status' => 'publish',
		] );
	}

	if ( empty( $children ) ) {
		return;
	}

	?>
	<nav class="subnav fluid-content" aria-label="<?php echo esc_attr( get_the_title( $parent ) ); ?> navigation">
		<ul class="subnav-list is--flex-list">
			<?php
			$parent_is_current = ( (int) $parent->ID === $current_id );
			?>
			<li>
				<a href="<?php echo esc_url( get_permalink( $parent ) ); ?>"<?php echo $parent_is_current ? ' class="menu--selected" aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $parent ) ); ?></a>
			</li>
			<?php foreach ( $children as $child ) :
				$is_current = ( (int) $child->ID === $current_id );
			?>
			<li>
				<a href="<?php echo esc_url( get_permalink( $child ) ); ?>"<?php echo $is_current ? ' class="menu--selected" aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $child ) ); ?></a>
			</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}
