<?php

declare( strict_types=1 );

/**
 * Default body: Booking Cancelled (customer).
 *
 * @package Dish\Events\Notifications\Templates
 */

return <<<EOT
Hi {{customer_name}},

Your booking for {{class_title}} on {{class_date}} has been cancelled.

  Booking #: {{booking_id}}

If you believe this is an error, or would like to rebook, please contact us at {{studio_email}}.

{{studio_name}}
EOT;
