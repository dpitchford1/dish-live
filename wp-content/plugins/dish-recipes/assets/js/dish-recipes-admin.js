/**
 * Dish Recipes — Admin Meta Box JS
 *
 * Handles:
 *   - Tab switching
 *   - Ingredient sectioned repeater (add/remove/reorder sections + rows)
 *   - Method sectioned repeater (add/remove/reorder sections + steps)
 *   - Media upload for legacy PDF
 *
 * Follows the dish JS conventions from base.js:
 *   - IIFE-based namespace, no ES modules
 *   - "use strict"
 *   - DOM elements cached once
 *   - Guard clauses before every DOM interaction
 *   - No jQuery
 */

/* global wp */ 

var dishRecipesAdmin = dishRecipesAdmin || {};

window.dishRecipesAdmin = ( function ( window, document ) {
	'use strict';

	// -------------------------------------------------------------------------
	// DOM cache
	// -------------------------------------------------------------------------

	var els = {};

	/**
	 * Cache all required DOM references.
	 * @returns {boolean} False if a critical element is missing.
	 */
	function cacheElements() {
		els.tabBtns        = document.querySelectorAll( '.dish-meta-tab-btn' );
		els.tabPanels      = document.querySelectorAll( '.dish-meta-tab-panel' );
		els.ingSections    = document.getElementById( 'dish-ingredient-sections' );
		els.addIngSection  = document.getElementById( 'dish-add-ingredient-section' );
		els.methodSections = document.getElementById( 'dish-method-sections' );
		els.addMethodSect  = document.getElementById( 'dish-add-method-section' );
		els.pdfUploadBtn   = document.getElementById( 'dish-pdf-upload' );
		els.pdfRemoveBtn   = document.getElementById( 'dish-pdf-remove' );
		els.pdfIdInput     = document.getElementById( 'dish_recipe_pdf_id' );
		els.pdfFilename    = document.getElementById( 'dish-pdf-filename' );

		return !!(
			els.tabBtns.length &&
			els.tabPanels.length &&
			els.ingSections &&
			els.methodSections
		);
	}

	// -------------------------------------------------------------------------
	// Tabs
	// -------------------------------------------------------------------------

	/**
	 * Activate a tab by its data-tab value.
	 * When the ingredients or method tab is activated for the first time,
	 * initialize jQuery UI Sortable (it cannot be initialized while the panel
	 * has display:none — dimensions are unavailable).
	 * @param {string} tab
	 */
	function activateTab( tab ) {
		els.tabBtns.forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', btn.dataset.tab === tab );
		} );
		els.tabPanels.forEach( function ( panel ) {
			panel.classList.toggle( 'is-active', panel.id === 'dish-tab-' + tab );
		} );

		// Lazy-init sortable on first reveal so jQuery UI can read dimensions.
		if ( tab === 'ingredients' && ! els.ingSortableReady ) {
			els.ingSortableReady = true;
			initIngredientsSortable();
		}
		if ( tab === 'method' && ! els.methodSortableReady ) {
			els.methodSortableReady = true;
			initMethodSortable();
		}
	}

	/**
	 * Wire tab button click events.
	 */
	function initTabs() {
		els.tabBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activateTab( btn.dataset.tab );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Index rebinding — updates all name attributes after add/remove/reorder
	// -------------------------------------------------------------------------

	/**
	 * Rebind all input/select/textarea name attributes in an ingredient section
	 * container to reflect the current DOM order.
	 *
	 * @param {HTMLElement} sectionsContainer
	 */
	function rebindIngredientNames( sectionsContainer ) {
		var sections = sectionsContainer.querySelectorAll( '.dish-section-block' );

		sections.forEach( function ( section, sIdx ) {
			// Section heading
			var heading = section.querySelector( '.dish-section-heading' );
			if ( heading ) {
				heading.name = 'dish_recipe_ingredients[' + sIdx + '][heading]';
			}

			// Ingredient rows
			var rows = section.querySelectorAll( '.dish-ingredient-row' );
			rows.forEach( function ( row, rIdx ) {
				var qty    = row.querySelector( '.dish-ing-qty' );
				var unit   = row.querySelector( '.dish-ing-unit' );
				var item   = row.querySelector( '.dish-ing-item' );
				var note   = row.querySelector( '.dish-ing-note' );
				var base   = 'dish_recipe_ingredients[' + sIdx + '][items][' + rIdx + ']';

				if ( qty )  qty.name  = base + '[qty]';
				if ( unit ) unit.name = base + '[unit]';
				if ( item ) item.name = base + '[item]';
				if ( note ) note.name = base + '[note]';
			} );
		} );
	}

	/**
	 * Rebind all textarea name attributes in a method section container.
	 *
	 * @param {HTMLElement} sectionsContainer
	 */
	function rebindMethodNames( sectionsContainer ) {
		var sections = sectionsContainer.querySelectorAll( '.dish-section-block' );

		sections.forEach( function ( section, sIdx ) {
			var heading = section.querySelector( '.dish-section-heading' );
			if ( heading ) {
				heading.name = 'dish_recipe_method[' + sIdx + '][heading]';
			}

			var steps = section.querySelectorAll( '.dish-method-step textarea' );
			steps.forEach( function ( textarea, stepIdx ) {
				textarea.name = 'dish_recipe_method[' + sIdx + '][steps][' + stepIdx + '][text]';
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// jQuery UI Sortable helper
	// -------------------------------------------------------------------------

	/**
	 * Apply jQuery UI Sortable to container so direct children matching
	 * itemSelector can be reordered by dragging handleSelector.
	 * onSort is called after every completed drag.
	 *
	 * jQuery UI Sortable is used here (not a custom HTML5 implementation)
	 * because WP admin's postbox.js binds competing mousedown / dragstart
	 * handlers that prevent native drag-and-drop from working reliably inside
	 * meta boxes. jQuery UI Sortable is already loaded by WordPress.
	 *
	 * @param {HTMLElement} container
	 * @param {string}      itemSelector
	 * @param {string}      handleSelector
	 * @param {Function}    onSort
	 */
	function makeSortable( container, itemSelector, handleSelector, onSort ) {
		if ( ! container || typeof jQuery === 'undefined' ) { return; }

		jQuery( container ).sortable( {
			items:  itemSelector,
			handle: handleSelector,
			axis:   'y',
			tolerance: 'pointer',
			placeholder: 'dish-sort-placeholder',
			start: function ( event, ui ) {
				ui.item.addClass( 'dish-dragging' );
				// Match placeholder height to the dragged item.
				ui.placeholder.height( ui.item.outerHeight() );
			},
			stop: function ( event, ui ) {
				ui.item.removeClass( 'dish-dragging' );
				if ( onSort ) { onSort(); }
			},
		} );
	}

	// -------------------------------------------------------------------------
	// Ingredient repeater
	// -------------------------------------------------------------------------

	/**
	 * Clone a new ingredient row from the template and append it.
	 * @param {HTMLElement} rowsContainer
	 * @param {number} sIdx Section index for name binding.
	 */
	function addIngredientRow( rowsContainer, sIdx ) {
		var tmpl = document.getElementById( 'dish-ingredient-row-template' );
		if ( ! tmpl ) { return; }

		var clone = tmpl.content.cloneNode( true );
		var rIdx  = rowsContainer.querySelectorAll( '.dish-ingredient-row' ).length;
		var base  = 'dish_recipe_ingredients[' + sIdx + '][items][' + rIdx + ']';

		var qty  = clone.querySelector( '.dish-ing-qty' );
		var unit = clone.querySelector( '.dish-ing-unit' );
		var item = clone.querySelector( '.dish-ing-item' );
		var note = clone.querySelector( '.dish-ing-note' );

		if ( qty )  qty.name  = base + '[qty]';
		if ( unit ) unit.name = base + '[unit]';
		if ( item ) item.name = base + '[item]';
		if ( note ) note.name = base + '[note]';

		rowsContainer.appendChild( clone );
	}

	/**
	 * Add a new ingredient section from the template.
	 */
	function addIngredientSection() {
		var tmpl = document.getElementById( 'dish-ingredient-section-template' );
		if ( ! tmpl ) { return; }

		var clone   = tmpl.content.cloneNode( true );
		var section = clone.querySelector( '.dish-section-block' );

		els.ingSections.appendChild( clone );

		// Add one blank row to the new section immediately.
		var newSection  = els.ingSections.lastElementChild;
		var rowsContainer = newSection.querySelector( '.dish-ingredient-rows' );
		var sIdx        = els.ingSections.querySelectorAll( '.dish-section-block' ).length - 1;

		addIngredientRow( rowsContainer, sIdx );
		rebindIngredientNames( els.ingSections );

		// If sortable is already initialised, refresh so the new section and
		// its rows container are picked up without re-running makeSortable.
		if ( els.ingSortableReady ) {
			jQuery( els.ingSections ).sortable( 'refresh' );
			makeSortable(
				rowsContainer,
				'.dish-ingredient-row',
				'.dish-row-drag',
				function () { rebindIngredientNames( els.ingSections ); }
			);
		}
	}

	/**
	 * Wire all ingredient section and row interactions.
	 * Uses event delegation on the sections container.
	 */
	function initIngredients() {
		if ( ! els.ingSections ) { return; }

		// Delegate clicks within the sections container.
		els.ingSections.addEventListener( 'click', function ( e ) {
			var target = e.target;

			// Add ingredient row.
			if ( target.classList.contains( 'dish-add-ingredient' ) ) {
				var section = target.closest( '.dish-section-block' );
				if ( ! section ) { return; }
				var sIdx = Array.from( els.ingSections.querySelectorAll( '.dish-section-block' ) ).indexOf( section );
				var rowsContainer = section.querySelector( '.dish-ingredient-rows' );
				addIngredientRow( rowsContainer, sIdx );
				rebindIngredientNames( els.ingSections );
				return;
			}

			// Remove ingredient row.
			if ( target.classList.contains( 'dish-row-remove' ) ) {
				var row = target.closest( '.dish-ingredient-row' );
				if ( ! row ) { return; }
				row.remove();
				rebindIngredientNames( els.ingSections );
				return;
			}

			// Remove section.
			if ( target.classList.contains( 'dish-section-remove' ) ) {
				var section = target.closest( '.dish-section-block' );
				if ( ! section ) { return; }
				// Don't remove the last section.
				if ( els.ingSections.querySelectorAll( '.dish-section-block' ).length <= 1 ) {
					return;
				}
				section.remove();
				rebindIngredientNames( els.ingSections );
				return;
			}
		} );

		// Add section button.
		if ( els.addIngSection ) {
			els.addIngSection.addEventListener( 'click', addIngredientSection );
		}
	}

	/**
	 * Initialise jQuery UI Sortable for ingredient sections and rows.
	 * Called lazily on first activation of the Ingredients tab so that
	 * jQuery UI can read element dimensions (display:none prevents this).
	 */
	function initIngredientsSortable() {
		if ( ! els.ingSections ) { return; }

		// Reorder sections.
		makeSortable(
			els.ingSections,
			'.dish-section-block',
			'.dish-section-drag',
			function () { rebindIngredientNames( els.ingSections ); }
		);

		// Reorder rows within each section.
		els.ingSections.querySelectorAll( '.dish-ingredient-rows' ).forEach( function ( rowsContainer ) {
			makeSortable(
				rowsContainer,
				'.dish-ingredient-row',
				'.dish-row-drag',
				function () { rebindIngredientNames( els.ingSections ); }
			);
		} );
	}

	// -------------------------------------------------------------------------
	// Method repeater
	// -------------------------------------------------------------------------

	/**
	 * Clone a new method step from the template and append it.
	 * @param {HTMLElement} stepsList  The <ol> element for steps.
	 * @param {number} sIdx            Section index.
	 */
	function addMethodStep( stepsList, sIdx ) {
		var tmpl = document.getElementById( 'dish-method-step-template' );
		if ( ! tmpl ) { return; }

		var clone   = tmpl.content.cloneNode( true );
		var stepIdx = stepsList.querySelectorAll( '.dish-method-step' ).length;

		var textarea = clone.querySelector( 'textarea' );
		if ( textarea ) {
			textarea.name = 'dish_recipe_method[' + sIdx + '][steps][' + stepIdx + '][text]';
		}

		stepsList.appendChild( clone );
	}

	/**
	 * Add a new method section.
	 */
	function addMethodSection() {
		var tmpl = document.getElementById( 'dish-method-section-template' );
		if ( ! tmpl ) { return; }

		var clone = tmpl.content.cloneNode( true );
		els.methodSections.appendChild( clone );

		var newSection = els.methodSections.lastElementChild;
		var stepsList  = newSection.querySelector( '.dish-method-steps' );
		var sIdx       = els.methodSections.querySelectorAll( '.dish-section-block' ).length - 1;

		addMethodStep( stepsList, sIdx );
		rebindMethodNames( els.methodSections );

		// If sortable is already initialised, refresh so the new section and
		// its steps list are picked up without re-running makeSortable.
		if ( els.methodSortableReady ) {
			jQuery( els.methodSections ).sortable( 'refresh' );
			makeSortable(
				stepsList,
				'.dish-method-step',
				'.dish-row-drag',
				function () { rebindMethodNames( els.methodSections ); }
			);
		}
	}

	/**
	 * Wire all method section and step interactions.
	 */
	function initMethod() {
		if ( ! els.methodSections ) { return; }

		els.methodSections.addEventListener( 'click', function ( e ) {
			var target = e.target;

			// Add step.
			if ( target.classList.contains( 'dish-add-step' ) ) {
				var section = target.closest( '.dish-section-block' );
				if ( ! section ) { return; }
				var sIdx = Array.from( els.methodSections.querySelectorAll( '.dish-section-block' ) ).indexOf( section );
				var stepsList = section.querySelector( '.dish-method-steps' );
				addMethodStep( stepsList, sIdx );
				rebindMethodNames( els.methodSections );
				return;
			}

			// Remove step.
			if ( target.classList.contains( 'dish-row-remove' ) ) {
				var step = target.closest( '.dish-method-step' );
				if ( ! step ) { return; }
				step.remove();
				rebindMethodNames( els.methodSections );
				return;
			}

			// Remove section.
			if ( target.classList.contains( 'dish-section-remove' ) ) {
				var section = target.closest( '.dish-section-block' );
				if ( ! section ) { return; }
				if ( els.methodSections.querySelectorAll( '.dish-section-block' ).length <= 1 ) {
					return;
				}
				section.remove();
				rebindMethodNames( els.methodSections );
				return;
			}
		} );

		if ( els.addMethodSect ) {
			els.addMethodSect.addEventListener( 'click', addMethodSection );
		}
	}

	/**
	 * Initialise jQuery UI Sortable for method sections and steps.
	 * Called lazily on first activation of the Method tab.
	 */
	function initMethodSortable() {
		if ( ! els.methodSections ) { return; }

		// Reorder sections.
		makeSortable(
			els.methodSections,
			'.dish-section-block',
			'.dish-section-drag',
			function () { rebindMethodNames( els.methodSections ); }
		);

		// Reorder steps within each section.
		els.methodSections.querySelectorAll( '.dish-method-steps' ).forEach( function ( stepsList ) {
			makeSortable(
				stepsList,
				'.dish-method-step',
				'.dish-row-drag',
				function () { rebindMethodNames( els.methodSections ); }
			);
		} );
	}

	// -------------------------------------------------------------------------
	// PDF media upload
	// -------------------------------------------------------------------------

	/**
	 * Open the WP media frame to select a PDF attachment.
	 */
	function initPdfUpload() {
		if ( ! els.pdfUploadBtn || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		var mediaFrame;

		els.pdfUploadBtn.addEventListener( 'click', function () {
			if ( mediaFrame ) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media( {
				title:    'Select Recipe PDF',
				button:   { text: 'Use this PDF' },
				library:  { type: 'application/pdf' },
				multiple: false,
			} );

			mediaFrame.on( 'select', function () {
				var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				els.pdfIdInput.value   = attachment.id;
				els.pdfFilename.textContent = attachment.filename || attachment.url.split( '/' ).pop();
			} );

			mediaFrame.open();
		} );

		if ( els.pdfRemoveBtn ) {
			els.pdfRemoveBtn.addEventListener( 'click', function () {
				els.pdfIdInput.value       = '';
				els.pdfFilename.textContent = '';
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	/**
	 * Bootstrap all components.
	 */
	function init() {
		if ( ! cacheElements() ) {
			return;
		}

		initTabs();
		initIngredients();
		initMethod();
		initPdfUpload();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	return {};

} )( window, document );
