/**
 * Dish Events — Calendar initialisation
 *
 * Initialises a FullCalendar v6 instance on the #dish-calendar element.
 * Fetches events from /wp-json/dish/v1/classes via FullCalendar's built-in
 * JSON feed support so date navigation fires new requests automatically.
 *
 * Features:
 *  - Month / Week / List view toggle (FullCalendar headerToolbar)
 *  - Format filter bar: clicking a format button refetches with ?format_id=N
 *  - Private events: non-clickable, title suppressed, CSS class applied
 *  - Low-spots / sold-out CSS classes based on extendedProps.spots_remaining
 *
 * No jQuery. No ES modules. Plain ES5-compatible vanilla JS so no transpiler
 * is needed. FullCalendar v6 global build exposes window.FullCalendar.
 *
 * Config is passed from PHP via wp_localize_script as window.dishCalendar:
 *   restUrl  string   Full URL to /wp-json/dish/v1/classes
 *   locale   string   BCP 47 locale code, e.g. "en-AU"
 *   i18n     object   { allFormats, noEvents, privateEvent }
 *
 * @package Dish\Events
 */

/* global FullCalendar, dishCalendar */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		var el = document.getElementById( 'dish-calendar' );
		if ( ! el ) { return; }

		if ( typeof FullCalendar === 'undefined' ) {
			console.warn( 'Dish Events: FullCalendar not loaded.' );
			return;
		}

		var config  = window.dishCalendar || {};
		var restUrl = config.restUrl || '';
		var locale  = config.locale  || 'en';
		var i18n    = config.i18n    || {};

		/** Currently active format filter (0 = all). */
		var activeFormatId = 0;

		// ── Detail modal (replaces hover popover) ────────────────────────────
		var modal = ( function () {
			var overlay  = document.createElement( 'div' );
			var currency = config.currencySymbol || '$';

			overlay.id = 'dish-cal-modal';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );
			overlay.setAttribute( 'aria-labelledby', 'dish-cal-modal-title' );
			overlay.hidden = true;
			document.body.appendChild( overlay );

			function esc( str ) {
				return String( str )
					.replace( /&/g,  '&amp;' )
					.replace( /</g,  '&lt;' )
					.replace( />/g,  '&gt;' )
					.replace( /"/g, '&quot;' );
			}

			function fmt_time( date ) {
				if ( ! date ) { return ''; }
				return date.toLocaleTimeString( locale, { hour: 'numeric', minute: '2-digit' } );
			}

			function fmt_date( date ) {
				if ( ! date ) { return ''; }
				return date.toLocaleDateString( locale, { weekday: 'long', month: 'long', day: 'numeric' } );
			}

			function hide() {
				overlay.hidden = true;
				document.documentElement.style.overflow = '';
			}

			function show( event ) {
				var props = event.extendedProps || {};
				var fmt   = props.format;
				var html  = '';

				// Format badge.
				if ( fmt && fmt.title ) {
					var dot = fmt.color
						? '<span class="dish-modal__dot" style="background:' + esc( fmt.color ) + '"></span>'
						: '';
					html += '<div class="dish-modal__format">' + dot + esc( fmt.title ) + '</div>';
				}

				// Title.
				html += '<h3 class="dish-modal__title" id="dish-cal-modal-title">' + esc( event.title ) + '</h3>';

				// Meta rows.
				html += '<div class="dish-modal__meta">';

				if ( event.start ) {
					html += '<div class="dish-modal__row">'
						+ '<span class="dish-modal__icon" aria-hidden="true">📅</span>'
						+ '<span>' + esc( fmt_date( event.start ) ) + '</span>'
						+ '</div>';
				}

				if ( event.start ) {
					var time_str = fmt_time( event.start );
					if ( event.end ) { time_str += ' \u2013 ' + fmt_time( event.end ); }
					html += '<div class="dish-modal__row">'
						+ '<span class="dish-modal__icon" aria-hidden="true">🕐</span>'
						+ '<span>' + esc( time_str ) + '</span>'
						+ '</div>';
				}

				if ( props.price_cents ) {
					var price_str = currency + ( props.price_cents / 100 ).toFixed( 2 ).replace( /\.00$/, '' );
					html += '<div class="dish-modal__row">'
						+ '<span class="dish-modal__icon" aria-hidden="true">💳</span>'
						+ '<span>' + esc( price_str ) + ' per ticket</span>'
						+ '</div>';
				}

				var spots = props.spots_remaining;
				if ( spots !== null && spots !== undefined ) {
					var spots_cls = spots <= 0 ? 'dish-modal__spots--sold-out' : ( spots <= 3 ? 'dish-modal__spots--low' : '' );
					var spots_txt = spots <= 0
						? 'Sold out'
						: ( spots <= 3
							? 'Only ' + spots + ' spot' + ( spots === 1 ? '' : 's' ) + ' left'
							: spots + ' spots available' );
					html += '<div class="dish-modal__row ' + spots_cls + '">'
						+ '<span class="dish-modal__icon" aria-hidden="true">🎟</span>'
						+ '<span>' + esc( spots_txt ) + '</span>'
						+ '</div>';
				}

				html += '</div>'; // .dish-modal__meta

				// Action buttons.
				var soldOut     = spots !== null && spots !== undefined && spots <= 0;
				var bookUrl     = props.booking_url;
				var detailUrl   = event.url;
				var isEnquiry   = props.booking_type === 'enquiry';
				var enquiryUrl  = props.enquiry_url || '';

				if ( isEnquiry || bookUrl || detailUrl ) {
					html += '<div class="dish-modal__actions">';
					if ( isEnquiry && enquiryUrl ) {
						html += '<a href="' + esc( enquiryUrl ) + '" class="dish-modal__book-btn dish-modal__book-btn--enquiry">'
							+ esc( i18n.enquire || 'Enquire to Book' ) + ' \u2192'
							+ '</a>';
					} else if ( bookUrl && ! soldOut ) {
						html += '<a href="' + esc( bookUrl ) + '" class="dish-modal__book-btn">'
							+ esc( i18n.bookIt || 'Book It' ) + ' \u2192'
							+ '</a>';
					}
					if ( detailUrl ) {
						html += '<a href="' + esc( detailUrl ) + '" class="dish-modal__detail-link">'
							+ esc( i18n.viewClass || 'View class details' ) + ' \u2192'
							+ '</a>';
					}
					html += '</div>';
				}

				// Assemble overlay: backdrop + card with close button.
				overlay.innerHTML = '<div class="dish-modal__backdrop"></div>'
					+ '<div class="dish-modal__card">'
					+ '<button class="dish-modal__close" aria-label="' + esc( i18n.close || 'Close' ) + '">&times;</button>'
					+ html
					+ '</div>';

				overlay.hidden = false;
				document.documentElement.style.overflow = 'hidden';

				var closeBtn = overlay.querySelector( '.dish-modal__close' );
				var backdrop = overlay.querySelector( '.dish-modal__backdrop' );
				if ( closeBtn ) {
					closeBtn.focus();
					closeBtn.addEventListener( 'click', hide );
				}
				if ( backdrop ) {
					backdrop.addEventListener( 'click', hide );
				}
			}

			// Close on Escape.
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && ! overlay.hidden ) { hide(); }
			} );

			return { show: show, hide: hide };
		}() );

		// ── FullCalendar init ─────────────────────────────────────────────────
		var calendar = new FullCalendar.Calendar( el, {

			initialView : 'dayGridMonth',
			locale      : locale,
			height      : 'auto',
			slotMinTime : '14:00:00', // 2 pm — hide earlier slots in week view
			slotMaxTime : '23:00:00', // 11 pm — hide later slots in week view
			scrollTime  : '16:00:00', // scroll week view to 4 pm on open

			headerToolbar : {
				left   : 'prev,next today',
				center : 'title',
				right  : 'dayGridMonth,timeGridWeek,listWeek',
			},

			noEventsContent : i18n.noEvents || 'No classes this period.',

			// JSON feed — FullCalendar automatically appends ?start=&end= on
			// every navigation so each range fires a fresh request.
			eventSources : [
				{
					url    : restUrl,
					method : 'GET',
					extraParams : function () {
						var params = {};
						if ( activeFormatId ) {
							params.format_id = activeFormatId;
						}
						return params;
					},
					failure : function () {
						console.warn( 'Dish Events: could not load calendar events.' );
					},
				},
			],

			// Click to open detail modal — works on desktop and mobile alike.
			eventClick : function ( info ) {
				info.jsEvent.preventDefault();
				var isPrivate = info.event.extendedProps && info.event.extendedProps.is_private;
				if ( ! isPrivate ) {
					modal.show( info.event );
				}
			},

			// Apply CSS state classes after each event is mounted.
			eventDidMount : function ( info ) {
				var props    = info.event.extendedProps || {};
				var spots    = props.spots_remaining;
				var isPrivate = props.is_private;

				if ( isPrivate ) {
					info.el.classList.add( 'dish-event--private' );
					// Remove the anchor so keyboard users can't focus it either.
					var anchor = info.el.tagName === 'A' ? info.el : info.el.querySelector( 'a' );
					if ( anchor ) {
						anchor.removeAttribute( 'href' );
						anchor.setAttribute( 'role', 'presentation' );
					}
				}

				if ( spots !== null && spots !== undefined ) {
					if ( spots <= 0 ) {
						info.el.classList.add( 'dish-event--sold-out' );
					} else if ( spots <= 3 ) {
						info.el.classList.add( 'dish-event--low-spots' );
					}

					// "N spots left!" label — injected when within the admin-configured threshold.
					var threshold = parseInt( ( window.dishCalendar || {} ).spotsThreshold, 10 ) || 0;
					if ( threshold > 0 && spots > 0 && spots <= threshold ) {
						var label = document.createElement( 'span' );
						label.className = 'dish-event__spots-label';
						label.textContent = spots + ( spots === 1 ? ' spot left!' : ' spots left!' );
						var target = info.el.querySelector( '.fc-event-main' ) || info.el;
						target.appendChild( label );
					}
				}
			},

		} );

		calendar.render();

		// ── Format filter bar ─────────────────────────────────────────────────
		var filterBtns = document.querySelectorAll( '.dish-calendar-filter[data-format-id]' );

		filterBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				// Update active state.
				filterBtns.forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );

				// Update filter and reload events.
				activeFormatId = parseInt( btn.dataset.formatId, 10 ) || 0;
				calendar.refetchEvents();
			} );
		} );

	} );

}() );
