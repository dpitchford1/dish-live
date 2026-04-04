<?php

declare( strict_types=1 );

/**
 * Default body: Admin — New Booking notification (studio copy).
 *
 * Sent to email_admin_to when a booking is first created (dish_pending, pre-payment).
 *
 * @package Dish\Events\Notifications\Templates
 */

return <<<EOT
A new booking has been received.

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
