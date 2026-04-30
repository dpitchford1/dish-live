/**
 * Dish Recipes — Term image uploader 
 *
 * Media uploader for the category image field on taxonomy add/edit screens.
 */
( function ( $ ) {
	'use strict';

	var frame;
	var $idField   = $( '#dish-term-image-id' );
	var $preview   = $( '#dish-term-image-preview' );
	var $btnSelect = $( '#dish-term-image-select' );
	var $btnRemove = $( '#dish-term-image-remove' );

	$btnSelect.on( 'click', function () {
		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title:    'Select Category Image',
			button:   { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url        = attachment.sizes && attachment.sizes.medium
				? attachment.sizes.medium.url
				: attachment.url;

			$idField.val( attachment.id );
			$preview.html( '<img src="' + url + '" style="max-width:300px;height:auto;display:block;margin-bottom:8px;" alt="">' );

			if ( ! $btnRemove.length ) {
				$btnSelect.after( ' <button type="button" class="button" id="dish-term-image-remove">Remove</button>' );
				$btnRemove = $( '#dish-term-image-remove' );
				bindRemove();
			}
		} );

		frame.open();
	} );

	function bindRemove() {
		$( document ).on( 'click', '#dish-term-image-remove', function () {
			$idField.val( '' );
			$preview.empty();
			$( this ).remove();
		} );
	}

	bindRemove();

} )( jQuery );
