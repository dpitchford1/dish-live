<?php
/**
 * Partial: upcoming class instances for a template.
 *
 * Queries up to 4 upcoming instances for the current template. If fewer than
 * 4 exist the remaining slots are back-filled with the soonest upcoming
 * instances from *other* templates (one per template, ordered by start date).
 * Format pill is suppressed for own-template cards and shown for back-fill
 * cards so visitors can identify the different class at a glance.
 *
 * Theme override: {theme}/dish-events/class-templates/upcoming.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\ClassRepository;
use Dish\Events\Frontend\Frontend;

$_tpl_id  = (int) get_the_ID();
$_limit   = 4;

// --- Own template's upcoming instances -----------------------------------
$_own = ClassRepository::query( [
	'template_id' => $_tpl_id,
	'start_after' => time(),
	'is_private'  => false,
	'limit'       => $_limit,
	'order'       => 'ASC',
] );

// --- Back-fill from other templates if needed ----------------------------
$_backfill = [];
$_need     = $_limit - count( $_own );

if ( $_need > 0 ) {
	$_own_ids   = array_map( fn( $c ) => $c->ID, $_own );
	$_seen_tpls = [];
	$_candidates = ClassRepository::query( [
		'start_after' => time(),
		'is_private'  => false,
		'limit'       => 40, // fetch enough to find one-per-template variety
		'order'       => 'ASC',
	] );

	foreach ( $_candidates as $_c ) {
		if ( in_array( $_c->ID, $_own_ids, true ) ) {
			continue; // already in own list
		}
		$_c_tpl = (int) get_post_meta( $_c->ID, 'dish_template_id', true );
		if ( $_c_tpl === $_tpl_id || isset( $_seen_tpls[ $_c_tpl ] ) ) {
			continue; // skip current template's instances and duplicates
		}
		$_seen_tpls[ $_c_tpl ] = true;
		$_backfill[]           = $_c;
		if ( count( $_backfill ) >= $_need ) {
			break;
		}
	}
}

$_all_classes = array_merge( $_own, $_backfill );

if ( empty( $_all_classes ) ) {
	return;
}
?>
<section class="content-region fluid-content">
	<?php if ( count( $_own ) < $_limit && ! empty( $_backfill ) ) : ?>
		<h2 class="section-heading"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h2>
	<?php else : ?>
		<h2 class="section-heading"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h2>
	<?php endif; ?>
	<div class="grid-general grid--4col">
		<?php foreach ( $_all_classes as $class ) :
			$suppress_format_pill = ( (int) get_post_meta( $class->ID, 'dish_template_id', true ) === $_tpl_id );
		?>
			<?php include Frontend::locate( 'classes/card.php' ); ?>
		<?php endforeach; ?>
	</div>
</section>
