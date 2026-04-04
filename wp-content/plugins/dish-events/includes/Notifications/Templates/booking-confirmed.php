<?php

declare( strict_types=1 );

/**
 * Default body: Booking Confirmation (customer).
 *
 * Returned as a string; {{tokens}} are replaced by NotificationService before sending.
 * The admin can override this entirely in Settings → Emails → Booking confirmation → Body.
 *
 * @package Dish\Events\Notifications\Templates
 */

return <<<EOT
Hi {{customer_name}},

Your booking is confirmed — we can't wait to see you!

  Class:     {{class_title}}
  Date:      {{class_date}}
  Time:      {{class_time}}
  Location:  {{class_location}}
  Tickets:   {{quantity}} × {{ticket_type}}
  Total:     {{amount}}
  Booking #: {{booking_id}}

View your booking details:
{{booking_details_url}}

Questions? Reply to this email or call us at {{studio_phone}}.

See you soon,
{{studio_name}}
EOT;
