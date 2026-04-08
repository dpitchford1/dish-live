<?php

declare(strict_types=1);
/**
 * Core theme bootstrap.
 *
 * Registers theme supports, menus, image sizes, and body-class filters.
 * Instantiated at the bottom of this file via `return new Basecamp()`.
 *
 * @since   2.0.0
 * @package basecamp
 */

namespace Basecamp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Theme' ) ) :

	final class Theme {

		/**
		 * Setup class.
		 *
		 * @since 1.0
		 */
		public function __construct() {
			add_action( 'after_setup_theme', array( $this, 'setup' ) );
            add_filter( 'body_class', array( $this, 'body_classes' ) );

			add_filter( 'wp_page_menu_args', array( $this, 'page_menu_args' ) );
		}

		/**
		 * Sets up theme defaults and registers support for various WordPress features.
		 *
		 * Note that this function is hooked into the after_setup_theme hook, which
		 * runs before the init hook. The init hook is too late for some features, such
		 * as indicating support for post thumbnails.
		 */
		public function setup() {
			/*
			 * Load Localisation files.
			 *
			 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
			 */

			// Loads wp-content/languages/themes/basecamp-it_IT.mo.
			load_theme_textdomain( 'basecamp', trailingslashit( WP_LANG_DIR ) . 'themes' );

			// Loads wp-content/themes/child-theme-name/languages/it_IT.mo.
			load_theme_textdomain( 'basecamp', get_stylesheet_directory() . '/languages' );

			// Loads wp-content/themes/basecamp/languages/it_IT.mo.
			load_theme_textdomain( 'basecamp', get_template_directory() . '/languages' );

            /**
			 * Register menu locations.
			 */
			register_nav_menus(
				apply_filters(
					'basecamp_register_nav_menus',
					array(
						'primary'   => __( 'Primary Menu', 'basecamp' ),
						'utility' => __( 'Secondary Menu', 'basecamp' ),
						'footer 1'  => __( 'Footer Menu 1', 'basecamp' ),
                        'footer 2'  => __( 'Footer Menu 2', 'basecamp' ),
                        'footer 3'  => __( 'Footer Menu 3', 'basecamp' ),
                        'social'  => __( 'Social Menu', 'basecamp' )
					)
				)
			);

            /**
			 * Declare support for title theme feature.
			 */
			add_theme_support( 'title-tag' );

            /** 
             * Add support for page excerpts.
             */
            add_post_type_support( 'page', 'excerpt' );

            /*
			 * Switch default core markup for search form, galleries, captions and widgets
			 * to output valid HTML5.
			 */
			add_theme_support(
				'html5',
				apply_filters(
					'basecamp_html5_args',
					array(
						'search-form',
						'gallery',
						'caption',
						'widgets',
						'style',
						'script',
					)
				)
			);

			/*
			 * Enable support for Post Thumbnails on posts and pages.
			 *
			 * @link https://developer.wordpress.org/reference/functions/add_theme_support/#Post_Thumbnails
			 */
			//add_theme_support( 'post-thumbnails' );
            add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );
            set_post_thumbnail_size( 600, 9999 );

            add_image_size( 'basecamp-img-xl', 1400, 800, false );
            add_image_size( 'basecamp-img-lg', 1280, 720, false );
            add_image_size( 'basecamp-img-m', 980, 560, false );
            add_image_size( 'basecamp-img-sm', 600, 343, false );
            add_image_size( 'basecamp-img-s', 400, 229, false );
            add_image_size( 'basecamp-img-sq-sm', 150, 150, true );
            add_image_size( 'basecamp-img-sq-md', 300, 300, true );
            add_image_size( 'basecamp-img-sq-lg', 600, 600, true );

            // Portrait sizes — 3:4 hard crop. Used with srcset/<picture> for mobile portrait contexts.
            add_image_size( 'portait-sm', 300, 400, true );
            add_image_size( 'portait-m', 640, 853, true );
            add_image_size( 'portait-lg', 960, 1280, true );

add_filter( 'image_size_names_choose', function( $sizes ) {
				return array_merge( $sizes, array(
					'basecamp-img-xl'    => __( '1400 × 800', 'basecamp' ),
					'basecamp-img-lg'    => __( '1280 × 720', 'basecamp' ),
					'basecamp-img-m'     => __( '980 × 560', 'basecamp' ),
					'basecamp-img-sm'    => __( '600 × 343', 'basecamp' ),
					'basecamp-img-s'     => __( '400 × 229', 'basecamp' ),
					'basecamp-img-sq-sm' => __( 'Square 150 × 150', 'basecamp' ),
					'basecamp-img-sq-md' => __( 'Square 300 × 300', 'basecamp' ),
					'basecamp-img-sq-lg' => __( 'Square 600 × 600', 'basecamp' ),
					'portait-sm'         => __( 'Portrait 300 × 400', 'basecamp' ),
					'portait-m'          => __( 'Portrait 640 × 853', 'basecamp' ),
					'portait-lg'         => __( 'Portrait 960 × 1280', 'basecamp' ),
				) );
			} );

			/**
			 * Add support for editor styles.
			 */
			//add_theme_support( 'editor-styles' );

			/**
			 * Enqueue editor styles.
			 */
			//add_editor_style( array( 'assets/css/base/gutenberg-editor.css', $this->google_fonts() ) );

			/**
			 * Add support for responsive embedded content.
			 */
			add_theme_support( 'responsive-embeds' );

            remove_theme_support( 'widgets-block-editor' );

		}

		/**
		 * Enqueue scripts and styles.
		 *
		 * Stub — add wp_enqueue_script() / wp_enqueue_style() calls here.
		 * Hooked to wp_enqueue_scripts if you uncomment the add_action in
		 * __construct(). Alternatively enqueue in header.php / footer.php directly.
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function scripts(): void {
		}

        /**
		 * Adds custom classes to the array of body classes.
		 *
		 * Page-specific classes can be declared via the basecamp_body_page_classes
		 * filter, which receives an associative array of page-slug => class-name pairs.
		 *
		 * Example (in a child theme or project functions file):
		 *   add_filter( 'basecamp_body_page_classes', function( $map ) {
		 *       $map['contact'] = 'is--contact';
		 *       $map['shop']    = 'has--breadcrumb';
		 *       return $map;
		 *   } );
		 *
		 * @param array $classes Classes for the body element.
		 * @return array
		 */
		public function body_classes( $classes ) {
			// Adds a class to blogs with more than 1 published author.
			if ( is_multi_author() ) {
				$classes[] = 'group-blog';
			}

			/**
			 * Page-slug → body-class map.
			 * Populated via filter so project-specific classes stay out of the starter.
			 *
			 * @param array<string, string> $map Page slug => class name.
			 */
			$page_class_map = apply_filters( 'basecamp_body_page_classes', [] );

			foreach ( $page_class_map as $slug => $class ) {
				if ( is_page( $slug ) ) {
					$classes[] = sanitize_html_class( $class );
				}
			}

			return $classes;
		}

		/**
		 * Get our wp_nav_menu() fallback, wp_page_menu(), to show a home link.
		 *
		 * @param array $args Configuration arguments.
		 * @return array
		 */
		public function page_menu_args( $args ) {
			$args['show_home'] = true;
			return $args;
		}

	}
endif;

return new Theme();
