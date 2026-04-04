<?php

declare(strict_types=1);
/**
 * Development template helpers for Basecamp theme.
 *
 * Captures the active template filename, source, type, and WP query context
 * for display in the DevPilot development panel.
 *
 * @package basecamp
 */

add_filter( 'template_include', 'basecamp_var_template_include', 1000 );

/**
 * Capture the resolved template path and populate $basecamp_template_data.
 *
 * @param string $template Absolute path to the template file WP will load.
 * @return string Unmodified template path.
 */
function basecamp_var_template_include( string $template ): string {
	global $basecamp_template_data;

	$GLOBALS['current_theme_template'] = basename( $template );

	$theme_dir      = get_template_directory();
	$stylesheet_dir = get_stylesheet_directory();

	if ( $stylesheet_dir !== $theme_dir && str_starts_with( $template, $stylesheet_dir ) ) {
		$source = 'Child Theme';
	} elseif ( str_starts_with( $template, $theme_dir ) ) {
		$source = 'Theme';
	} elseif ( str_contains( $template, '/plugins/' ) ) {
		$source = 'Plugin';
	} else {
		$source = 'WordPress Core';
	}

	$basecamp_template_data = [
		'source' => $source,
		'type'   => basecamp_get_template_type(),
		'path'   => $template,
	];

	return $template;
}

/**
 * Return the basename of the currently loaded template file.
 *
 * @param bool $echo Whether to echo the value instead of returning it.
 * @return string|false Template basename, or false if not yet set.
 */
function basecamp_get_current_template( bool $echo = false ): string|false {
	if ( ! isset( $GLOBALS['current_theme_template'] ) ) {
		return false;
	}
	if ( $echo ) {
		echo esc_html( $GLOBALS['current_theme_template'] );
	}
	return $GLOBALS['current_theme_template'];
}

/**
 * Return a short label for the current WP query type.
 *
 * @return string Type label e.g. 'Singular', 'Archive', 'Front Page'.
 */
function basecamp_get_template_type(): string {
	if ( is_front_page() ) return 'Front Page';
	if ( is_home() )       return 'Blog Index';
	if ( is_singular() )   return 'Singular';
	if ( is_tax() || is_category() || is_tag() ) return 'Taxonomy';
	if ( is_post_type_archive() ) return 'Post Type Archive';
	if ( is_date() )   return 'Date Archive';
	if ( is_author() ) return 'Author Archive';
	if ( is_search() ) return 'Search';
	if ( is_404() )    return '404';
	return 'Index';
}

/**
 * Return a detailed, human-readable description of the current WP query context.
 *
 * @return string Context label e.g. 'Singular — dish_class_template: Pasta 101'.
 */
function basecamp_get_template_context(): string {
	if ( is_front_page() && is_home() ) {
		return 'Front Page (Blog Index)';
	}
	if ( is_front_page() ) {
		return 'Front Page (Static)';
	}
	if ( is_home() ) {
		return 'Blog Index';
	}
	if ( is_singular() ) {
		return 'Singular — ' . get_post_type() . ': ' . get_the_title( get_queried_object_id() );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		return 'Taxonomy — ' . $term->taxonomy . ': ' . $term->name;
	}
	if ( is_post_type_archive() ) {
		return 'Post Type Archive — ' . (string) get_query_var( 'post_type' );
	}
	if ( is_date() ) {
		return 'Date Archive';
	}
	if ( is_author() ) {
		$author = get_queried_object();
		return 'Author Archive — ' . $author->display_name;
	}
	if ( is_search() ) {
		return 'Search — "' . get_search_query() . '"';
	}
	if ( is_404() ) {
		return '404 Not Found';
	}
	return 'Index / Fallback';
}