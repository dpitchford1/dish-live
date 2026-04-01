<?php
/**
 * Template: [dish_login] — login form.
 *
 * Shows the WordPress login form for guests. Logged-in users see a
 * brief "already logged in" notice with a link to their profile.
 *
 * Theme override: {theme}/dish-events/account/login.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dish-account dish-account--login">

	<?php if ( is_user_logged_in() ) : ?>

		<p class="dish-account__notice">
			<?php
			printf(
				/* translators: 1: display name, 2: profile URL */
				wp_kses(
					__( 'You are logged in as <strong>%1$s</strong>. <a href="%2$s">View your profile &rarr;</a>', 'dish-events' ),
					[ 'strong' => [], 'a' => [ 'href' => [] ] ]
				),
				esc_html( wp_get_current_user()->display_name ),
				esc_url( \Dish\Events\Admin\Settings::get( 'profile_page' )
					? get_permalink( (int) \Dish\Events\Admin\Settings::get( 'profile_page' ) )
					: home_url() )
			);
			?>
		</p>

	<?php else : ?>

		<h2 class="dish-account__title"><?php esc_html_e( 'Sign in', 'dish-events' ); ?></h2>

		<?php
		wp_login_form( [
			'echo'           => true,
			'redirect'       => get_permalink(),
			'form_id'        => 'dish-login-form',
			'label_username' => __( 'Email address or username', 'dish-events' ),
			'label_password' => __( 'Password', 'dish-events' ),
			'label_remember' => __( 'Remember me', 'dish-events' ),
			'label_log_in'   => __( 'Sign in', 'dish-events' ),
			'remember'       => true,
		] );
		?>

		<?php if ( get_option( 'users_can_register' ) ) : ?>
			<p class="dish-account__switch">
				<?php
				$register_page = (int) \Dish\Events\Admin\Settings::get( 'register_page' );
				$register_url  = $register_page ? get_permalink( $register_page ) : wp_registration_url();
				printf(
					wp_kses(
						/* translators: %s: registration URL */
						__( 'Don\'t have an account? <a href="%s">Create one</a>.', 'dish-events' ),
						[ 'a' => [ 'href' => [] ] ]
					),
					esc_url( $register_url )
				);
				?>
			</p>
		<?php endif; ?>

		<p class="dish-account__forgot">
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
				<?php esc_html_e( 'Forgot your password?', 'dish-events' ); ?>
			</a>
		</p>

	<?php endif; ?>

</div>
