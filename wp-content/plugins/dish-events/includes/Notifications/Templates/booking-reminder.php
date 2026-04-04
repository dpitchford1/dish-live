<?php
/**
 * Default body: Booking Reminder (customer).
 *
 * Dispatched by a cron job (Phase 13). Wired here as a template only.
 *
 * @package Dish\Events\Notifications\Templates
 */

return <<<EOT
Hi {{customer_name}},

Just a friendly reminder that your class is coming up soon!

  Class:     {{class_title}}
  Date:      {{class_date}}
  Time:      {{class_time}}
  Location:  {{class_location}}

View your booking details:
{{booking_details_url}}

See you soon,
{{studio_name}}
EOT;
