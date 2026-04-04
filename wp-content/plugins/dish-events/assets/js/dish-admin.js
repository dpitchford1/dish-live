/**
 * dish-admin.js
 *
 * Admin-only JavaScript for the Dish Events plugin.
 * No jQuery. No build step required — plain ES2020.
 *
 * Responsibilities
 * ----------------
 *  1. Meta box tab switching (dish_class edit screen)
 *  2. Recurrence UI show/hide + reactive label/row updates
 *  3. WP Media Library picker for share image
 */

( function () {
	'use strict';

	// =========================================================================
	// 1. Meta box tab switching
	// =========================================================================

	/**
	 * Wire up the tab nav inside #dish-class-metabox.
	 * Active tab is tracked in sessionStorage so a page reload (after save)
	 * returns to the same tab.
	 */
	function initMetaboxTabs() {
		const metabox = document.getElementById( 'dish-class-metabox' );
		if ( ! metabox ) return;

		const tabs   = metabox.querySelectorAll( '.dish-metabox__tab' );
		const panels = metabox.querySelectorAll( '.dish-metabox__panel' );
		const SK     = 'dish_active_tab';

		function activateTab( slug ) {
			tabs.forEach( function ( btn ) {
				const isActive = btn.dataset.tab === slug;
				btn.classList.toggle( 'is-active', isActive );
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			panels.forEach( function ( panel ) {
				if ( panel.dataset.panel === slug ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			} );
			try { sessionStorage.setItem( SK, slug ); } catch ( e ) { /* ignore */ }
		}

		// Restore last active tab.
		const stored = ( function () {
			try { return sessionStorage.getItem( SK ); } catch ( e ) { return null; }
		}() );

		const validSlugs = Array.from( tabs ).map( function ( t ) { return t.dataset.tab; } );
		const initial    = validSlugs.includes( stored ) ? stored : ( validSlugs[ 0 ] || 'datetime' );
		activateTab( initial );

		tabs.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activateTab( btn.dataset.tab );
			} );
		} );
	}

	// =========================================================================
	// 2. Recurrence UI
	// =========================================================================

	function initRecurrenceUI() {
		const typeSelect   = document.getElementById( 'dish_recurrence_type' );
		if ( ! typeSelect ) return;

		const optionsBlock      = document.getElementById( 'dish-recurrence-options' );
		const daysRow           = document.getElementById( 'dish-recurrence-days-row' );
		const unitLabel         = document.getElementById( 'dish-recurrence-unit' );
		const endsSelect        = document.getElementById( 'dish_recurrence_ends' );
		const countRow          = document.getElementById( 'dish-recurrence-count-row' );
		const endDateRow        = document.getElementById( 'dish-recurrence-end-date-row' );
		const monthlyByRow      = document.getElementById( 'dish-recurrence-monthly-by-row' );
		const monthlyWeekRow    = document.getElementById( 'dish-recurrence-monthly-week-row' );
		const monthlyBySelect   = document.getElementById( 'dish_recurrence_monthly_by' );

		const unitLabels = {
			daily:   'day(s)',
			weekly:  'week(s)',
			monthly: 'month(s)',
		};

		function updateRecurrenceUI() {
			const type     = typeSelect.value;
			const isRepeat = type !== 'none';

			// Show / hide the whole recurrence options block.
			if ( optionsBlock ) {
				optionsBlock.hidden = ! isRepeat;
			}

			// Swap interval unit label.
			if ( unitLabel ) {
				unitLabel.textContent = unitLabels[ type ] || '';
			}

			// Weekly-only days row.
			if ( daysRow ) {
				daysRow.hidden = ( type !== 'weekly' );
			}

			// Monthly-only rows.
			if ( monthlyByRow ) {
				monthlyByRow.hidden = ( type !== 'monthly' );
			}
			updateMonthlyWeekRow();
		}

		function updateMonthlyWeekRow() {
			if ( ! monthlyWeekRow ) return;
			const isMonthly  = typeSelect.value === 'monthly';
			const isWeekday  = monthlyBySelect && monthlyBySelect.value === 'weekday';
			monthlyWeekRow.hidden = ! ( isMonthly && isWeekday );
		}

		function updateEndsUI() {
			if ( ! endsSelect ) return;
			const endsBy = endsSelect.value;
			if ( countRow )   countRow.hidden   = ( endsBy !== 'count' );
			if ( endDateRow ) endDateRow.hidden  = ( endsBy !== 'date' );
		}

		typeSelect.addEventListener( 'change', updateRecurrenceUI );
		if ( endsSelect )      endsSelect.addEventListener( 'change', updateEndsUI );
		if ( monthlyBySelect ) monthlyBySelect.addEventListener( 'change', updateMonthlyWeekRow );

		// Run once on load to sync with saved values.
		updateRecurrenceUI();
		updateEndsUI();
	}

	// =========================================================================
	// 3. Class Details — checkbox lists (toggle all, add, remove)
	// =========================================================================

	/**
	 * Wire up all .dish-detail-section elements:
	 *  - Toggle All checkbox
	 *  - Add item (button + Enter key)
	 *  - Remove item (delegated click)
	 *  - Initial toggle-all indeterminate state
	 */
	function initClassDetails() {
		document.querySelectorAll( '.dish-detail-section' ).forEach( function ( section ) {
			const toggleAll = section.querySelector( '.dish-toggle-all' );
			const list      = section.querySelector( '.dish-detail-list' );
			const addInput  = section.querySelector( '.dish-add-item-input' );
			const addBtn    = section.querySelector( '.dish-add-item-btn' );
			const prefix    = section.dataset.prefix || '';

			// ── Toggle All ────────────────────────────────────────────────
			if ( toggleAll ) {
				toggleAll.addEventListener( 'change', function () {
					list.querySelectorAll( '.dish-item-check' ).forEach( function ( cb ) {
						cb.checked = toggleAll.checked;
					} );
				} );
			}

			// ── Sync toggle-all state when individual boxes change ────────
			if ( list ) {
				list.addEventListener( 'change', function ( e ) {
					if ( e.target.classList.contains( 'dish-item-check' ) && toggleAll ) {
						syncToggleAll( section, toggleAll );
					}
				} );
			}

			// ── Add item ──────────────────────────────────────────────────
			function doAdd() {
				if ( ! addInput ) return;
				const label = addInput.value.trim();
				if ( ! label ) return;
				addDetailItem( list, prefix, label );
				addInput.value = '';
				addInput.focus();
				if ( toggleAll ) syncToggleAll( section, toggleAll );
			}

			if ( addBtn )   addBtn.addEventListener( 'click', doAdd );
			if ( addInput ) addInput.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) { e.preventDefault(); doAdd(); }
			} );

			// ── Remove item (delegated) ───────────────────────────────────
			if ( list ) {
				list.addEventListener( 'click', function ( e ) {
					const btn = e.target.closest( '.dish-remove-item' );
					if ( ! btn ) return;
					btn.closest( '.dish-detail-item' ).remove();
					if ( toggleAll ) syncToggleAll( section, toggleAll );
				} );
			}

			// ── Initial state ─────────────────────────────────────────────
			if ( toggleAll ) syncToggleAll( section, toggleAll );
		} );
	}

	/**
	 * Update a Toggle All checkbox to reflect the current checked/indeterminate state.
	 *
	 * @param {Element} section
	 * @param {HTMLInputElement} toggleAll
	 */
	function syncToggleAll( section, toggleAll ) {
		const boxes   = Array.from( section.querySelectorAll( '.dish-detail-list .dish-item-check' ) );
		const total   = boxes.length;
		const checked = boxes.filter( function ( cb ) { return cb.checked; } ).length;

		toggleAll.checked       = total > 0 && checked === total;
		toggleAll.indeterminate = checked > 0 && checked < total;
	}

	/**
	 * Create and append a new list item to a detail section list.
	 *
	 * @param {Element} list    The <ul> element.
	 * @param {string}  prefix  Field name prefix (e.g. 'dish_wtb').
	 * @param {string}  label   The item label text.
	 */
	function addDetailItem( list, prefix, label ) {
		const li = document.createElement( 'li' );
		li.className = 'dish-detail-item';

		const safeLabel = escHtml( label );
		const safeAttr  = escAttr( label );
		const safePfx   = escAttr( prefix );

		li.innerHTML =
			'<label>' +
				'<input type="checkbox" class="dish-item-check"' +
					' name="' + safePfx + '_checked[]"' +
					' value="' + safeAttr + '" checked>' +
				' ' + safeLabel +
			'</label>' +
			'<input type="hidden" name="' + safePfx + '_label[]" value="' + safeAttr + '">' +
			'<button type="button" class="dish-remove-item button-link" title="Remove">' +
			'<span class="dashicons dashicons-trash"></span>' +		'</button>';
		list.appendChild( li );
	}

	// -------------------------------------------------------------------------
	// String escaping utilities
	// -------------------------------------------------------------------------

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#x27;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// =========================================================================
	// 4. Checkout fields repeater
	// =========================================================================

	function initCheckoutFields() {
		const body   = document.getElementById( 'dish-cf-body' );
		const addBtn = document.getElementById( 'dish-cf-add' );
		if ( ! body || ! addBtn ) return;

		// Remove row (delegated).
		body.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.dish-cf-remove' );
			if ( ! btn ) return;
			btn.closest( 'tr' ).remove();
			reindexCfRows();
		} );

		// Add new blank row.
		addBtn.addEventListener( 'click', function () {
			const idx = body.querySelectorAll( 'tr.dish-cf-row' ).length;
			body.insertAdjacentHTML( 'beforeend', buildCfRow( idx ) );
		} );
	}

	function reindexCfRows() {
		document.querySelectorAll( '#dish-cf-body tr.dish-cf-row' ).forEach( function ( row, idx ) {
			row.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.name = el.name.replace( /dish_cf\[\d+\]/, 'dish_cf[' + idx + ']' );
			} );
		} );
	}

	function buildCfRow( idx ) {
		return '<tr class="dish-cf-row">' +
			'<td><span class="dashicons dashicons-move dish-cf-handle" style="cursor:move;color:#aaa;"></span></td>' +
			'<td><input type="text" class="widefat" name="dish_cf[' + idx + '][label]" value="" placeholder="Field label"></td>' +
			'<td><select name="dish_cf[' + idx + '][type]" class="widefat">' +
				'<option value="text">Text</option>' +
				'<option value="textarea">Textarea</option>' +
				'<option value="select">Select</option>' +
				'<option value="checkbox">Checkbox</option>' +
				'<option value="radio">Radio</option>' +
			'</select></td>' +
			'<td style="text-align:center;"><input type="checkbox" name="dish_cf[' + idx + '][required]" value="1"></td>' +
			'<td style="text-align:center;"><input type="checkbox" name="dish_cf[' + idx + '][per_attendee]" value="1"></td>' +
			'<td><button type="button" class="button-link dish-cf-remove" title="Remove"><span class="dashicons dashicons-trash" style="color:#b32d2e;"></span></button></td>' +
		'</tr>';
	}

	// =========================================================================
	// Boot
	// =========================================================================

	function boot() {
		initMetaboxTabs();
		initRecurrenceUI();
		initClassDetails();
		initCheckoutFields();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

}() );
