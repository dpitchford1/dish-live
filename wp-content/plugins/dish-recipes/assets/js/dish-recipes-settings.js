/**
 * Dish Recipes — Settings page JS 
 *
 * Handles the WP media uploader for the archive image field.
 */
( function ( $ ) {
	'use strict';

	var frame;
	var $idField    = $( '#dish-archive-image-id' );
	var $preview    = $( '#dish-archive-image-preview' );
	var $btnSelect  = $( '#dish-archive-image-select' );
	var $btnRemove  = $( '#dish-archive-image-remove' );

	$btnSelect.on( 'click', function () {
		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title:    'Select Archive Image',
			button:   { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url        = attachment.sizes && attachment.sizes.large
				? attachment.sizes.large.url
				: attachment.url;

			$idField.val( attachment.id );
			$preview.html( '<img src="' + url + '" style="max-width:400px;height:auto;display:block;" alt="">' );

			if ( ! $btnRemove.length ) {
				$btnSelect.after( '<button type="button" class="button" id="dish-archive-image-remove"> Remove</button>' );
				$btnRemove = $( '#dish-archive-image-remove' );
				bindRemove();
			}
		} );

		frame.open();
	} );

	function bindRemove() {
		$btnRemove.on( 'click', function () {
			$idField.val( '' );
			$preview.empty();
			$( this ).remove();
		} );
	}

	bindRemove();

} )( jQuery );
