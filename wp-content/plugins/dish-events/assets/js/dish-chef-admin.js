/**
 * dish-chef-admin.js
 *
 * WP Media Library gallery picker — shared by the Chef and Class Template edit screens.
 *
 * Usage: add class "dish-gallery-add" to any "Add Images" button and give it these
 * data attributes:
 *   data-input      — id of the hidden JSON input
 *   data-preview    — id of the preview <div>
 *   data-clear      — id of the "Clear Gallery" button
 *   data-frame-title  — translated modal title string
 *   data-frame-button — translated modal button label
 *
 * Enqueued in the footer (in_footer: true) so wp.media is guaranteed available.
 * No build step required — plain ES5-compatible vanilla JS.
 *
 * @package Dish\Events
 */

( function () {
	'use strict';

	/**
	 * Initialise a single gallery widget.
	 *
	 * @param {HTMLButtonElement} addBtn
	 */
	function initGallery( addBtn ) {
		var input   = document.getElementById( addBtn.dataset.input );
		var preview = document.getElementById( addBtn.dataset.preview );
		var clrBtn  = document.getElementById( addBtn.dataset.clear );

		if ( ! input || ! preview || ! clrBtn ) {
			return;
		}

		addBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			var frame = wp.media( {
				title:    addBtn.dataset.frameTitle  || 'Select Gallery Images',
				button:   { text: addBtn.dataset.frameButton || 'Add to Gallery' },
				multiple: true,
			} );

			frame.on( 'select', function () {
				var existing = JSON.parse( input.value || '[]' );

				frame.state().get( 'selection' ).each( function ( att ) {
					if ( existing.indexOf( att.id ) === -1 ) {
						existing.push( att.id );

						var sizes = att.attributes.sizes;
						var src   = ( sizes && sizes.thumbnail ) ? sizes.thumbnail.url : att.attributes.url;
						var img   = document.createElement( 'img' );

						img.src             = src;
						img.width           = 80;
						img.height          = 80;
						img.style.objectFit = 'cover';

						preview.appendChild( img );
					}
				} );

				input.value          = JSON.stringify( existing );
				clrBtn.style.display = existing.length ? '' : 'none';
			} );

			frame.open();
		} );

		clrBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			input.value          = '[]';
			preview.innerHTML    = '';
			clrBtn.style.display = 'none';
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		document.querySelectorAll( '.dish-gallery-add' ).forEach( initGallery );
	} );

	// =========================================================================
	// Custom flag / friendly-for rows (dish_class_template menu meta box)
	// =========================================================================

	/**
	 * Wire add-row and remove-row behaviour for a custom flag list.
	 *
	 * @param {HTMLUListElement} list
	 */
	function initCustomFlagList( list ) {
		/**
		 * Build a new custom-flag <li> with a text input and remove button.
		 *
		 * @param {string} fieldName  The input name attribute (e.g. "dish_menu_custom_dietary[]").
		 * @param {string} placeholder
		 * @returns {HTMLLIElement}
		 */
		function buildRow( fieldName, placeholder ) {
			var li     = document.createElement( 'li' );
			li.className = 'dish-custom-flag';

			var input         = document.createElement( 'input' );
			input.type        = 'text';
			input.name        = fieldName;
			input.className   = 'regular-text dish-custom-flag__input';
			input.placeholder = placeholder;

			var btn           = document.createElement( 'button' );
			btn.type          = 'button';
			btn.className     = 'dish-custom-flag__remove button-link';
			btn.setAttribute( 'aria-label', 'Remove' );
			btn.innerHTML     = '&#x2715;';

			li.appendChild( input );
			li.appendChild( btn );
			return li;
		}

		// Add button — insert a new row before the add-row <li>.
		var addBtn = list.querySelector( '.dish-custom-flag__add' );
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				var addRow   = list.querySelector( '.dish-custom-flag__add-row' );
				var newRow   = buildRow( addBtn.dataset.fieldName, addBtn.dataset.placeholder || '' );
				list.insertBefore( newRow, addRow );
				newRow.querySelector( 'input' ).focus();
			} );
		}

		// Remove buttons — delegated so newly added rows are covered.
		list.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'dish-custom-flag__remove' ) ) {
				var li = e.target.closest( 'li.dish-custom-flag' );
				if ( li ) {
					li.remove();
				}
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.dish-flag-list[id]' ).forEach( initCustomFlagList );
	} );
}() );
