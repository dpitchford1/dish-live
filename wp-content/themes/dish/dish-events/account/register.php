<?php
/**
 * Template: [dish_register] — registration form.
 *
 * Shows a registration form for guests when WP user registration is enabled.
 * Logged-in users and sites with registration disabled see appropriate notices.
 *
 * Phase 9 note: once bookings are live, registration will also capture
 * guest customer data. The form here uses the native WP registration flow
 * as a placeholder that can be replaced by a custom form.
 *
 * Theme override: {theme}/dish-events/account/register.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dish-account dish-account--register">

	<?php if ( is_user_logged_in() ) : ?>

		<p class="dish-account__notice">
			<?php
			printf(
				/* translators: %s: profile URL */
				wp_kses(
					__( 'You already have an account. <a href="%s">View your profile &rarr;</a>', 'dish-events' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( \Dish\Events\Admin\Settings::get( 'profile_page' )
					? get_permalink( (int) \Dish\Events\Admin\Settings::get( 'profile_page' ) )
					: home_url() )
			);
			?>
		</p>

	<?php elseif ( ! get_option( 'users_can_register' ) ) : ?>

		<p class="dish-account__notice">
			<?php esc_html_e( 'User registration is not currently available.', 'dish-events' ); ?>
		</p>

	<?php else : ?>

		<h2 class="dish-account__title"><?php esc_html_e( 'Create an account', 'dish-events' ); ?></h2>

		<p class="dish-account__intro">
			<?php esc_html_e( 'Create a free account to manage your bookings and track upcoming classes.', 'dish-events' ); ?>
		</p>

		<?php
		// Display any registration errors stored in a transient (set by WP after redirect).
		$registration_errors = get_transient( 'dish_register_errors_' . session_id() );
		if ( $registration_errors && is_wp_error( $registration_errors ) ) :
		?>
			<div class="dish-account__errors" role="alert">
				<ul>
					<?php foreach ( $registration_errors->get_error_messages() as $message ) : ?>
						<li><?php echo esc_html( $message ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php?action=register' ) ); ?>" class="dish-account__form dish-register-form" novalidate>

			<div class="dish-form-field">
				<label for="dish-reg-username"><?php esc_html_e( 'Username', 'dish-events' ); ?></label>
				<input type="text" id="dish-reg-username" name="user_login" autocomplete="username" required>
			</div>

			<div class="dish-form-field">
				<label for="dish-reg-email"><?php esc_html_e( 'Email address', 'dish-events' ); ?></label>
				<input type="email" id="dish-reg-email" name="user_email" autocomplete="email" required>
			</div>

			<?php do_action( 'register_form' ); ?>

			<?php wp_nonce_field( 'dish-register' ); ?>

			<p class="dish-form-field">
				<button type="submit" class="dish-button dish-button--primary">
					<?php esc_html_e( 'Create account', 'dish-events' ); ?>
				</button>
			</p>

			<p class="dish-account__legal">
				<?php esc_html_e( 'A password will be emailed to you.', 'dish-events' ); ?>
			</p>

		</form>

		<p class="dish-account__switch">
			<?php
			$login_page = (int) \Dish\Events\Admin\Settings::get( 'login_page' );
			$login_url  = $login_page ? get_permalink( $login_page ) : wp_login_url();
			printf(
				wp_kses(
					/* translators: %s: login URL */
					__( 'Already have an account? <a href="%s">Sign in</a>.', 'dish-events' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( $login_url )
			);
			?>
		</p>

	<?php endif; ?>

</div>
