<?php

declare(strict_types=1);
/**
 * Admin customisations for the Basecamp theme.
 *
 * Encapsulates all backend tweaks: login branding, dashboard widget removal,
 * TinyMCE cleanup, admin bar modifications, and menu visibility.
 * All hooks are registered in the constructor so the class self-wires on
 * instantiation — no separate init() call is needed.
 *
 * @package basecamp
 */

namespace Basecamp\Admin;

// Suppress Email Address Encoder admin notices (filter retained for child-theme overrides).
define( 'EAE_DISABLE_NOTICES', apply_filters( 'air_helper_remove_eae_admin_bar', true ) );

/**
 * Encapsulates all admin/backend customizations for the Basecamp theme.
 */
final class Admin {

		/**
		 * Register all admin hooks.
		 */
		public function __construct() {
			// Dashboard and login tweaks
			add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widgets' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'login_css' ], 10 );
			add_action( 'login_enqueue_scripts', [ $this, 'wpb_login_logo' ] );
			add_action( 'admin_menu', [ $this, 'air_helper_wphidenag' ] );
			add_action( 'admin_menu', [ $this, 'hide_unnecessary_wordpress_menus' ], 999 );

			// Admin UI tweaks
			add_filter( 'login_headerurl', [ $this, 'login_url' ] );
			add_filter( 'login_headertitle', [ $this, 'login_title' ] );
			add_action( 'admin_bar_menu', [ $this, 'replace_howdy' ] );
			add_action( 'wp_before_admin_bar_render', [ $this, 'remove_comments_from_admin_bar' ] );
			add_filter( 'admin_footer_text', [ $this, 'custom_admin_footer' ] );
			add_filter( 'update_footer', '__return_empty_string', 11 );

			// Editor and autosave
			add_action( 'tiny_mce_before_init', [ $this, 'cleanup_mce' ] );
			add_action( 'wp_print_scripts', [ $this, 'disable_autosave' ] );
			add_filter('use_block_editor_for_post_type', [ $this, 'disable_block_editor_everywhere' ], 10, 2);

			// Post status and updates
			add_action( 'transition_post_status', [ $this, 'remove_transient_on_publish' ], 10, 3 );
			add_filter( 'auto_update_plugin', '__return_false' );
			add_filter( 'auto_update_theme', '__return_false' );

			// Admin CSS
			add_action( 'login_enqueue_scripts', [ $this, 'admin_css' ], 10 );
		}

		/**
		 * Remove unwanted dashboard widgets.
		 */
		public function remove_dashboard_widgets() {
			remove_meta_box('dashboard_quick_press','dashboard','side');
			remove_meta_box('dashboard_recent_drafts','dashboard','side');
			remove_meta_box('dashboard_primary','dashboard','side');
			remove_meta_box('dashboard_secondary','dashboard','side');
			remove_meta_box('dashboard_incoming_links','dashboard','normal');
			remove_meta_box('dashboard_plugins','dashboard','normal');
			remove_meta_box('dashboard_right_now','dashboard', 'normal');
			remove_meta_box('dashboard_recent_comments','dashboard','normal');
			remove_meta_box('icl_dashboard_widget','dashboard','normal');
			// remove_meta_box('dashboard_activity','dashboard', 'normal');
			// remove_action('welcome_panel','wp_welcome_panel');
		}

		/**
		 * Enqueue custom login page CSS.
		 *
		 * @return void
		 */
		public function login_css(): void {
			wp_enqueue_style( 'basecamp_login_css', get_template_directory_uri() . '/inc/admin/assets/css/login.css', array() );
		}

		/**
		 * Return the site home URL for the login logo link.
		 *
		 * @return string
		 */
		public function login_url(): string {
			return home_url();
		}

		/**
		 * Return the site name for the login logo title attribute.
		 *
		 * @return string
		 */
		public function login_title(): string {
			return (string) get_option( 'blogname' );
		}

		/**
		 * Enqueue admin area CSS.
		 *
		 * @return void
		 */
		public function admin_css(): void {
			wp_enqueue_style( 'basecamp_admin_css', get_template_directory_uri() . '/inc/admin/assets/css/admin.css', array() );
		}

		/**
		 * Custom admin footer text.
		 *
		 * @return void
		 */
		public function custom_admin_footer(): void {
			_e( '<span id="footer-thankyou">Built with <a href="https://kaneism.com" target="_blank">Basecamp</a>.', 'basecamp' );
		}

		/**
		 * Inject inline CSS to replace the default WordPress login logo.
		 *
		 * Override the logo URL via the basecamp_login_logo_url filter:
		 *   add_filter( 'basecamp_login_logo_url', fn() => get_stylesheet_directory_uri() . '/img/logo.png' );
		 *
		 * @return void
		 */
		public function wpb_login_logo(): void {
			$logo_url = apply_filters( 'basecamp_login_logo_url', get_template_directory_uri() . '/assets/img/logo.png' );
			?>
			<style type="text/css">
				#login h1 a, .login h1 a {
					background-image: url(<?php echo esc_url( $logo_url ); ?>);
					height: 150px;
					width: 300px;
					background-size: 300px auto;
					background-repeat: no-repeat;
				}
			</style>
		<?php }

		/**
		 * Remove the WordPress core update nag from the admin.
		 *
		 * @return void
		 */
		public function air_helper_wphidenag(): void {
			remove_action( 'admin_notices', 'update_nag' );
		}

		/**
		 * Replace "Howdy" in the admin bar with "Logged in as".
		 *
		 * @param WP_Admin_Bar $wp_admin_bar
		 * @return void
		 */
		public function replace_howdy( \WP_Admin_Bar $wp_admin_bar ): void {
			$my_account = $wp_admin_bar->get_node( 'my-account' );
			if ( isset( $my_account->title ) ) {
				$wp_admin_bar->add_node( [
					'id'    => 'my-account',
					'title' => str_replace( 'Howdy, ', __( 'Logged in as,', 'basecamp' ), $my_account->title ),
				] );
			}
		}

		/**
		 * Remove H1 from the TinyMCE block formats dropdown.
		 *
		 * @param array $args TinyMCE init args.
		 * @return array
		 */
		public function cleanup_mce( array $args ): array {
			$args['block_formats'] = 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4; Heading 5=h5; Heading 6=h6';
			return $args;
		}

		/**
		 * Deregister the autosave script in the admin to reduce XHR noise.
		 *
		 * @return void
		 */
		public function disable_autosave(): void {
			wp_deregister_script( 'autosave' );
		}

		/**
		 * Remove the comments shortcut from the admin bar.
		 *
		 * @return void
		 */
		public function remove_comments_from_admin_bar(): void {
			global $wp_admin_bar;
			$wp_admin_bar->remove_menu( 'comments' );
		}

		/**
		 * Delete the recent-posts transient when a post is published.
		 *
		 * @param string  $new  New post status.
		 * @param string  $old  Previous post status.
		 * @param WP_Post $post The post object.
		 * @return void
		 */
		public function remove_transient_on_publish( string $new, string $old, \WP_Post $post ): void {
			if ( 'publish' === $new ) {
				delete_transient( 'recent_posts_query_results' );
			}
		}

		/**
		 * Hide Appearance sub-menus and other unnecessary admin menu entries.
		 *
		 * Removes: Header, Background, Customize, Theme File Editor, Patterns,
		 * the Comments top-level menu, and the Discussion settings page.
		 *
		 * @return void
		 */
		public function hide_unnecessary_wordpress_menus(): void {
			global $submenu;

			$hidden_appearance_items = [
				'Header', 'Background', 'Customize',
				'Theme File Editor', 'Patterns', 'Marketing', 'basecamp',
			];

			if ( isset( $submenu['themes.php'] ) ) {
				foreach ( $submenu['themes.php'] as $index => $item ) {
					if ( in_array( $item[0], $hidden_appearance_items, true ) ) {
						unset( $submenu['themes.php'][ $index ] );
					}
				}
			}

			remove_menu_page( 'edit-comments.php' );
			remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		}

		/**
		 * Disable the block editor for all post types, enforcing classic editor.
		 *
		 * @param bool   $use_block_editor Whether to use the block editor.
		 * @param string $post_type        The post type being edited.
		 * @return bool Always false.
		 */
		public function disable_block_editor_everywhere( bool $use_block_editor, string $post_type ): bool {
			return false;
	}
}

new Admin();