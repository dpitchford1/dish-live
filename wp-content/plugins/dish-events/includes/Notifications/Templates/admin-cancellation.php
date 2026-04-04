<?php

declare( strict_types=1 );

/**
 * Default body: Admin — Booking Cancellation notification (studio copy).
 *
 * Sent to email_admin_to when a booking is moved to dish_cancelled.
 *
 * @package Dish\Events\Notifications\Templates
 */

return <<<EOT
A booking has been cancelled.

  Booking #:  {{booking_id}}
  Customer:   {{customer_name}} ({{customer_email}})
  Phone:      {{customer_phone}}
  Class:      {{class_title}}
  Date:       {{class_date}} at {{class_time}}
  Tickets:    {{quantity}} × {{ticket_type}}
  Total:      {{amount}}

View booking:
{{booking_details_url}}
EOT;
