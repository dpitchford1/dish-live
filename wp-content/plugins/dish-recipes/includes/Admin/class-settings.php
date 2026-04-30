<?php
/**
 * Plugin settings page — Recipes > Settings.
 *
 * Currently exposes one field: the archive featured image.
 * Stored as a single serialised option: dish_recipes_settings.
 *
 * @package Dish\Recipes\Admin
 */

declare( strict_types=1 );

namespace Dish\Recipes\Admin;

/**
 * Class Settings
 *
 * Registers the settings submenu, renders the form, and provides
 * a static getter used anywhere in the plugin or theme templates.
 *
 * Usage:
 *   $image_id = \Dish\Recipes\Admin\Settings::get( 'archive_image_id' );
 */
class Settings {

	private const OPTION_KEY = 'dish_recipes_settings';

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * Register hooks via the plugin Loader.
	 *
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_action( 'admin_menu',    $this, 'add_submenu' );
		$loader->add_action( 'admin_init',    $this, 'register_settings' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_media_uploader' );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Add Settings submenu under the Recipes CPT menu.
	 */
	public function add_submenu(): void {
		add_submenu_page(
			'edit.php?post_type=dish_recipe',
			__( 'Recipe Settings', 'dish-recipes' ),
			__( 'Settings', 'dish-recipes' ),
			'manage_options',
			'dish-recipes-settings',
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	/**
	 * Register the settings group and sanitise callback.
	 */
	public function register_settings(): void {
		register_setting(
			'dish_recipes_settings_group',
			self::OPTION_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);
	}

	/**
	 * Sanitise settings on save.
	 *
	 * @param  mixed $input Raw POST data.
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		$clean = [];

		$clean['archive_image_id'] = isset( $input['archive_image_id'] )
			? absint( $input['archive_image_id'] )
			: 0;

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue WP media uploader on our settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_media_uploader( string $hook ): void {
		if ( 'dish_recipe_page_dish-recipes-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'dish-recipes-settings',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dish-recipes-settings.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the settings page HTML.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$image_id  = self::get( 'archive_image_id' );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Recipe Settings', 'dish-recipes' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'dish_recipes_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="dish-archive-image"><?php esc_html_e( 'Archive Featured Image', 'dish-recipes' ); ?></label>
						</th>
						<td>
							<div id="dish-archive-image-preview" style="margin-bottom:8px;">
								<?php if ( $image_url ) : ?>
									<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:400px;height:auto;display:block;" alt="">
								<?php endif; ?>
							</div>
							<input type="hidden"
								id="dish-archive-image-id"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[archive_image_id]"
								value="<?php echo esc_attr( (string) $image_id ); ?>">
							<button type="button" class="button" id="dish-archive-image-select">
								<?php esc_html_e( 'Select Image', 'dish-recipes' ); ?>
							</button>
							<?php if ( $image_id ) : ?>
								<button type="button" class="button" id="dish-archive-image-remove">
									<?php esc_html_e( 'Remove', 'dish-recipes' ); ?>
								</button>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Shown as the hero/header image on the recipes archive page.', 'dish-recipes' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Static getter
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a single setting value.
	 *
	 * @param  string $key     Setting key (e.g. 'archive_image_id').
	 * @param  mixed  $default Fallback if the key is not set.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$options = get_option( self::OPTION_KEY, [] );
		return $options[ $key ] ?? $default;
	}
}
