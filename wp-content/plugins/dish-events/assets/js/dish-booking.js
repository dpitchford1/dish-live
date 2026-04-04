/**
 * Dish Booking — frontend JS.
 *
 * Responsibilities:
 *   1. Countdown timer: reads dishBooking.expiresAt (Unix timestamp), ticks
 *      every second, shows MM:SS, redirects to class page when it hits 0.
 *   2. Quantity selector: fires dish_update_qty AJAX on change — cancels the
 *      current hold and starts a fresh one server-side, then updates the timer,
 *      pricing, and hidden form fields in-place. No page reload.
 *   3. Form submission: posts to admin-ajax.php via fetch(), shows inline
 *      errors, redirects to confirmation page on success.
 *   4. Release hold on page unload via navigator.sendBeacon so spots are freed
 *      immediately when the user navigates away without completing checkout.
 *
 * Depends on:
 *   - dishBooking  global injected by checkout.php via wp_add_inline_script()
 *     {
 *       ajaxUrl:      string,
 *       nonce:        string,
 *       sessionKey:   string,
 *       expiresAt:    number,   ← Unix timestamp (seconds)
 *       classId:      number,
 *       ticketTypeId: number,
 *       priceCents:   number,
 *       feeLines:     Array<{label: string, amountCents: number, perTicket: boolean}>,
 *       classUrl:     string,
 *       currency:     string,   ← currency symbol prefix
 *       i18n:         { expired, submitting, error, updating }
 *     }
 *
 * No build step required — vanilla ES2020 compatible with all modern browsers.
 */

( function () {
	'use strict';

	if ( typeof dishBooking === 'undefined' ) {
		return;
	}

	const cfg = dishBooking;

	// Flag set to true on successful submission so the pagehide beacon
	// doesn't fire a spurious hold-release after a completed checkout.
	let bookingCompleted = false;

	// ── DOM refs ──────────────────────────────────────────────────────────────
	const timerEl   = document.getElementById( 'dish-timer-countdown' );
	const timerWrap = document.getElementById( 'dish-timer' );
	const form      = document.getElementById( 'dish-checkout-form' );
	const submitBtn = document.getElementById( 'dish-submit-btn' );
	const errorBox  = document.getElementById( 'dish-form-error' );
	const qtySelect = document.getElementById( 'dish-qty' );
	const totalEl   = document.getElementById( 'dish-total-display' );
	const labelEl   = document.getElementById( 'dish-ticket-label' );

	// ── Timer ─────────────────────────────────────────────────────────────────
	function formatSeconds( secs ) {
		const m = Math.floor( secs / 60 );
		const s = secs % 60;
		return String( m ).padStart( 2, '0' ) + ':' + String( s ).padStart( 2, '0' );
	}

	function onTimerExpired() {
		if ( timerEl ) timerEl.textContent = '00:00';
		if ( timerWrap ) timerWrap.classList.add( 'dish-checkout__timer--expired' );
		if ( form ) form.inert = true;
		if ( submitBtn ) submitBtn.disabled = true;

		// Show expired message and redirect after a short pause.
		showError( cfg.i18n.expired );
		setTimeout( function () {
			window.location.href = cfg.classUrl || window.location.origin + '/';
		}, 3000 );
	}

	let timerInterval = null;

	function startTimer() {
		if ( ! timerEl || ! cfg.expiresAt ) return;

		function tick() {
			const remaining = Math.max( 0, cfg.expiresAt - Math.floor( Date.now() / 1000 ) );
			timerEl.textContent = formatSeconds( remaining );

			if ( timerWrap ) {
				if ( remaining <= 60 ) {
					timerWrap.classList.add( 'dish-checkout__timer--urgent' );
				}
				if ( remaining <= 0 ) {
					clearInterval( timerInterval );
					onTimerExpired();
				}
			}
		}

		tick(); // Render immediately.
		timerInterval = setInterval( tick, 1000 );
	}

	function restartTimer() {
		if ( timerInterval ) {
			clearInterval( timerInterval );
			timerInterval = null;
		}
		// Reset visual state from a previous expiry or urgency.
		if ( timerWrap ) {
			timerWrap.classList.remove( 'dish-checkout__timer--urgent' );
			timerWrap.classList.remove( 'dish-checkout__timer--expired' );
		}
		if ( form )      form.inert        = false;
		if ( submitBtn ) submitBtn.disabled = false;
		startTimer();
	}

	// ── Quantity selector ─────────────────────────────────────────────────────
	function centsToCurrency( cents ) {
		const amount = ( cents / 100 ).toFixed( 2 );
		// Strip trailing .00 for round dollar amounts.
		const display = amount.endsWith( '.00' ) ? String( Math.round( cents / 100 ) ) : amount;
		return cfg.currency + display;
	}

	function updateTotal() {
		if ( ! qtySelect ) return;
		const qty      = parseInt( qtySelect.value, 10 ) || 1;
		const feeLines = cfg.feeLines || [];

		let perTicketFees  = 0;
		let perBookingFees = 0;

		feeLines.forEach( function ( fee ) {
			if ( fee.perTicket ) {
				perTicketFees += fee.amountCents;
			} else {
				perBookingFees += fee.amountCents;
			}
		} );

		const subtotal   = cfg.priceCents * qty;
		const total      = subtotal + perTicketFees * qty + perBookingFees;

		// Update the ticket subtotal row (only present when fees exist).
		const subtotalEl = document.getElementById( 'dish-ticket-subtotal' );
		if ( subtotalEl ) {
			subtotalEl.textContent = centsToCurrency( subtotal );
		}

		// Update individual fee line amounts (per-ticket lines scale with qty).
		feeLines.forEach( function ( fee, i ) {
			const feeAmountEl = document.querySelector( '[data-fee-index="' + i + '"] .dish-checkout__fee-amount' );
			if ( feeAmountEl ) {
				const feeAmt = fee.perTicket ? fee.amountCents * qty : fee.amountCents;
				feeAmountEl.textContent = centsToCurrency( feeAmt );
			}
		} );

		if ( totalEl && ( cfg.priceCents > 0 || total > 0 ) ) {
			totalEl.textContent = centsToCurrency( total );
		}

		if ( labelEl ) {
			labelEl.textContent = qty === 1 ? '1 ticket' : qty + ' tickets';
		}

		// Also update the submit button label if it includes the price.
		if ( submitBtn && total > 0 ) {
			submitBtn.dataset.baseLabel = submitBtn.dataset.baseLabel || submitBtn.textContent.replace( /—.*$/, '' ).trim();
			submitBtn.textContent = submitBtn.dataset.baseLabel + ' — ' + centsToCurrency( total );
		}
	}

	if ( qtySelect ) {
		let prevQtyValue      = qtySelect.value;
		let qtyXhrController  = null;  // AbortController for any in-flight request.

		qtySelect.addEventListener( 'change', async function () {
			const newQty = parseInt( qtySelect.value, 10 ) || 1;

			// Cancel any previous in-flight request.
			if ( qtyXhrController ) {
				qtyXhrController.abort();
			}

			// Disable while the AJAX call is in flight.
			qtySelect.disabled = true;
			clearError();

			const controller   = new AbortController();
			qtyXhrController   = controller;

			try {
				const body = new FormData();
				body.append( 'action',      'dish_update_qty' );
				body.append( 'nonce',       cfg.nonce );
				body.append( 'session_key', cfg.sessionKey );
				body.append( 'class_id',    cfg.classId );
				body.append( 'qty',         newQty );

				const response = await fetch( cfg.ajaxUrl, {
					method: 'POST',
					body,
					signal: controller.signal,
				} );

				const json = await response.json();

				if ( json && json.success && json.data ) {
					const d = json.data;

					// Update config in-place — pagehide beacon + form submit read from cfg.
					cfg.sessionKey   = d.session_key;
					cfg.expiresAt    = d.expires_at;
					cfg.priceCents   = d.price_cents;
					cfg.ticketTypeId = d.ticket_type_id;

					// Sync hidden form fields the server reads on submit.
					if ( form ) {
						const skInput = form.elements['session_key'];
						const ttInput = form.elements['ticket_type_id'];
						if ( skInput ) skInput.value = d.session_key;
						if ( ttInput ) ttInput.value = d.ticket_type_id;
					}

					// Restart countdown with the new session’s expiry.
					restartTimer();

					// Update pricing display and submit button.
					updateTotal();

					prevQtyValue = String( newQty );
				} else {
					// Roll back selector to the last known-good qty.
					qtySelect.value = prevQtyValue;
					const msg = ( json && json.data && json.data.message ) ? json.data.message : cfg.i18n.error;
					showError( msg );
				}
			} catch ( err ) {
				if ( err.name !== 'AbortError' ) {
					qtySelect.value = prevQtyValue;
					showError( cfg.i18n.error );
				}
			} finally {
				qtySelect.disabled = false;
				qtyXhrController   = null;
			}
		} );
	}

	// ── Account creation toggle ───────────────────────────────────────────────
	const createAccountCb  = document.getElementById( 'dish-create-account' );
	const accountFieldset  = document.getElementById( 'dish-account-fields' );
	const accountUsername  = document.getElementById( 'dish-account-username' );
	const accountPassword  = document.getElementById( 'dish-account-password' );

	if ( createAccountCb && accountFieldset ) {
		createAccountCb.addEventListener( 'change', function () {
			const checked = this.checked;
			accountFieldset.hidden = ! checked;

			// Only enforce required when the section is visible.
			if ( accountUsername ) accountUsername.required = checked;
			if ( accountPassword ) accountPassword.required = checked;
		} );
	}

	// ── Error display ─────────────────────────────────────────────────────────
	function showError( msg ) {
		if ( ! errorBox ) return;
		errorBox.textContent = msg;
		errorBox.hidden = false;
		errorBox.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function clearError() {
		if ( ! errorBox ) return;
		errorBox.textContent = '';
		errorBox.hidden = true;
	}

	function highlightField( fieldName ) {
		if ( ! fieldName ) return;
		const el = form && form.elements[ fieldName ];
		if ( el ) {
			el.classList.add( 'dish-form-input--error' );
			el.focus();
		}
	}

	function clearFieldErrors() {
		if ( ! form ) return;
		form.querySelectorAll( '.dish-form-input--error' ).forEach( function ( el ) {
			el.classList.remove( 'dish-form-input--error' );
		} );
	}

	// ── Form submission ───────────────────────────────────────────────────────
	function setSubmitting( isSubmitting ) {
		if ( ! submitBtn ) return;
		submitBtn.disabled = isSubmitting;
		if ( isSubmitting ) {
			submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
			submitBtn.textContent = cfg.i18n.submitting;
		} else {
			submitBtn.textContent = submitBtn.dataset.originalText || submitBtn.textContent;
		}
	}

	if ( form ) {
		form.addEventListener( 'submit', async function ( e ) {
			e.preventDefault();
			clearError();
			clearFieldErrors();

			if ( ! form.checkValidity() ) {
				form.reportValidity();
				return;
			}

			setSubmitting( true );

			try {
				const body = new FormData( form );
				// Make sure qty is reflected from the select element.
				if ( qtySelect ) {
					body.set( 'qty', qtySelect.value );
				}

				const response = await fetch( cfg.ajaxUrl, {
					method: 'POST',
					body:   body,
				} );

				const json = await response.json();

				if ( json && json.success && json.data && json.data.redirect_url ) {
					// Success — mark completed so pagehide beacon doesn't fire.
					bookingCompleted = true;
					window.location.href = json.data.redirect_url;
				} else {
					const msg   = ( json && json.data && json.data.message ) ? json.data.message : cfg.i18n.error;
					const field = ( json && json.data && json.data.field )   ? json.data.field   : null;
					showError( msg );
					highlightField( field );
					setSubmitting( false );
				}
			} catch ( err ) {
				showError( cfg.i18n.error );
				setSubmitting( false );
			}
		} );
	}

	// ── Release hold on page unload ───────────────────────────────────────────
	// navigator.sendBeacon is fire-and-forget, safe on page navigation.
	window.addEventListener( 'pagehide', function ( e ) {
		// Skip if already completed (booking form submitted successfully).
		if ( ! cfg.sessionKey || bookingCompleted ) return;
		// Skip if the browser is putting the page into the BFCache (back/forward
		// cache). event.persisted = true means the page is NOT truly unloading —
		// firing the beacon here would delete the live session so that when the
		// user returns via the back button the timer shows time remaining but the
		// server considers the reservation expired.
		if ( e.persisted ) return;
		const body = new FormData();
		body.append( 'action',      'dish_release_hold' );
		body.append( 'session_key', cfg.sessionKey );
		navigator.sendBeacon( cfg.ajaxUrl, body );
	} );

	// ── BFCache restore ───────────────────────────────────────────────────────
	// When the browser restores the page from BFCache, the setInterval may have
	// paused. Restart the countdown so the display is immediately correct and
	// onTimerExpired() fires if the session genuinely ran out while away.
	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			restartTimer();
		}
	} );

	// ── Init ──────────────────────────────────────────────────────────────────
	startTimer();

} )();
