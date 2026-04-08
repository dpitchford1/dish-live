<?php // WordPress custom title script

if ( function_exists('is_tag') && is_tag() || is_category() || is_tax() ) { ?>

	<h1 class=""><?php _e( 'Posts Categorized:', 'basecamp' ); ?><?php single_cat_title(); ?></h1>

<?php } elseif ( is_archive() ) { ?>

	<h3 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>

<?php } elseif ( is_search() ) { ?>

	<h3 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>

<?php } elseif ( !(is_404() ) && ( is_single() ) || ( is_page() )) { ?>

	<h1 class=""><?php the_title(); ?></h1>

<?php } elseif ( is_404() ) { ?>

	<h1><?php _e( '404', 'basecamp' ); ?></h1>
<?php } elseif ( is_home() ) { ?>

	<h1 class=""><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h1>

<?php } else { ?>

	<h1 class=""><?php the_title(); ?></h1>
<?php }

?>