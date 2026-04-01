<?php // WordPress custom title script

if ( function_exists('is_tag') && is_tag() || is_category() || is_tax() ) { ?>

	<h2 class=""><?php _e( 'Posts Categorized:', 'basecamp' ); ?><?php single_cat_title(); ?></h2>

<?php } elseif ( is_archive() ) { ?>

	<h3 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>

<?php } elseif ( is_search() ) { ?>

	<h3 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>

<?php } elseif ( !(is_404() ) && ( is_single() ) || ( is_page() )) { ?>

	<h2 class=""><?php the_title(); ?></h2>

<?php } elseif ( is_404() ) { ?>

	<h2><?php _e( '404', 'basecamp' ); ?></h2>

<?php } elseif ( is_home() ) { ?>

	<h2 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h2>

<?php } else { ?>

	<h2 class=""><?php the_title(); ?></h2>

<?php }

?>