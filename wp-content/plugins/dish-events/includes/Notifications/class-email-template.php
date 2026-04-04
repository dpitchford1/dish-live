<?php
/**
 * Email template utilities for dish-events notifications.
 *
 * Responsibilities
 * ----------------
 *  replace_tokens()    — Replace {{token}} placeholders in a string.
 *  wrap()              — Wrap a plain-text body in the studio HTML email layout.
 *  get_default_body()  — Return the built-in body for a given settings key
 *                        (used when the admin has not customised the body in Settings → Emails).
 *
 * @package Dish\Events\Notifications
 */

declare( strict_types=1 );

namespace Dish\Events\Notifications;

/**
 * Class EmailTemplate
 */
final class EmailTemplate {

	// -------------------------------------------------------------------------
	// Token replacement
	// -------------------------------------------------------------------------

	/**
	 * Replace {{token_name}} placeholders in a string.
	 *
	 * @param  string               $text   Raw text containing {{tokens}}.
	 * @param  array<string,string> $tokens Map of {{token_name}} → resolved value.
	 * @return string
	 */
	public static function replace_tokens( string $text, array $tokens ): string {
		return str_replace( array_keys( $tokens ), array_values( $tokens ), $text );
	}

	// -------------------------------------------------------------------------
	// HTML wrapper
	// -------------------------------------------------------------------------

	/**
	 * Wrap a plain-text email body in a simple studio-branded HTML layout.
	 *
	 * The body is treated as plain text: newlines are converted to <br>,
	 * and all content is HTML-escaped before insertion. This is intentional —
	 * Settings → Emails stores body content via sanitize_textarea_field,
	 * which strips all HTML tags.
	 *
	 * @param  string $body         Plain-text body (tokens already replaced).
	 * @param  string $studio_name  Displayed in the email header and footer.
	 * @param  string $studio_email Displayed in the email footer.
	 * @return string               Complete HTML document ready for wp_mail().
	 */
	public static function wrap( string $body, string $studio_name, string $studio_email ): string {
		$name    = esc_html( $studio_name );
		$contact = esc_html( $studio_email );
		$content = nl2br( esc_html( $body ) );

		return <<<HTML
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width,initial-scale=1">
		</head>
		<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 16px;">
				<tr>
					<td align="center">
						<table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;background:#ffffff;border-radius:4px;overflow:hidden;">

							<!-- Header -->
							<tr>
								<td style="background:#1c1c1c;padding:24px 32px;">
									<span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:0.5px;">{$name}</span>
								</td>
							</tr>

							<!-- Body -->
							<tr>
								<td style="padding:32px;color:#333333;font-size:15px;line-height:1.7;">
									{$content}
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="padding:16px 32px;background:#f9f9f9;border-top:1px solid #eeeeee;color:#999999;font-size:12px;line-height:1.5;">
									{$name} &nbsp;&middot;&nbsp; {$contact}
								</td>
							</tr>

						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		HTML;
	}

	// -------------------------------------------------------------------------
	// Default body templates
	// -------------------------------------------------------------------------

	/**
	 * Return the built-in default body for a settings key.
	 *
	 * Each Template file returns a plain-text string with {{token}} placeholders.
	 * The admin can override this entirely from Settings → Emails → Body.
	 *
	 * @param  string $key Settings prefix e.g. 'email_booking_confirmation'.
	 * @return string
	 */
	public static function get_default_body( string $key ): string {
		$map = [
			'email_booking_confirmation' => 'booking-confirmed.php',
			'email_booking_cancelled'    => 'booking-cancelled.php',
			'email_booking_reminder'     => 'booking-reminder.php',
			'email_waitlist_available'   => 'waitlist-available.php',
			'email_payment_receipt'      => 'payment-receipt.php',
			'email_admin_new_booking'    => 'admin-new-booking.php',
			'email_admin_cancellation'   => 'admin-cancellation.php',
		];

		$filename = $map[ $key ] ?? '';
		if ( $filename === '' ) {
			return '';
		}

		$file = __DIR__ . '/Templates/' . $filename;
		if ( ! file_exists( $file ) ) {
			return '';
		}

		$result = include $file;
		return is_string( $result ) ? $result : '';
	}
}
