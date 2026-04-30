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
				html += '<h3 class="card-title" id="dish-cal-modal-title">' + esc( event.title ) + '</h3>';

				// Meta rows.
				html += '<ul class="icon-list--default dish-modal__meta">';

				if ( event.start ) {
					html += '<li class="ico--date">' + esc( fmt_date( event.start ) ) + '</li>';
				}

				if ( event.start ) {
					var time_str = fmt_time( event.start );
					if ( event.end ) { time_str += ' \u2013 ' + fmt_time( event.end ); }
					html += '<li class="ico--time">' + esc( time_str ) + '</li>';
				}

				if ( props.price_cents ) {
					var price_str = currency + ( props.price_cents / 100 ).toFixed( 2 ).replace( /\.00$/, '' );
					html += '<li class="ico--price">' + esc( price_str ) + ' per ticket</li>';
				}

				var spots = props.spots_remaining;
				if ( spots !== null && spots !== undefined ) {
					var spots_cls = spots <= 0 ? ' dish-modal__spots--sold-out' : ( spots <= 3 ? ' dish-modal__spots--low' : '' );
					var spots_txt = spots <= 0
						? 'Sold out'
						: ( spots <= 3
							? 'Only ' + spots + ' spot' + ( spots === 1 ? '' : 's' ) + ' left'
							: spots + ' spots available' );
					html += '<li class="ico--ticket' + spots_cls + '">' + esc( spots_txt ) + '</li>';
				}

				html += '</ul>'; // .dish-modal__meta

				// Action buttons.
				var isPast      = !! props.is_past;
				var soldOut     = spots !== null && spots !== undefined && spots <= 0;
				var bookUrl     = props.booking_url;
				var detailUrl   = props.detail_url;
				var isEnquiry   = props.booking_type === 'enquiry';
				var enquiryUrl  = props.enquiry_url || '';

				if ( isPast ) {
					html += '<div class="dish-modal__actions">';
					html += '<span class="dish-modal__past-notice">' + esc( i18n.classPast || 'This class has already taken place.' ) + '</span>';
					if ( detailUrl ) {
						html += '<a href="' + esc( detailUrl ) + '" class="button button--outline">'
							+ esc( i18n.viewClass || 'View class details' ) + ' \u2192'
							+ '</a>';
					}
					html += '</div>';
				} else if ( isEnquiry || bookUrl || detailUrl || soldOut ) {
					html += '<div class="dish-modal__actions">';
					if ( isEnquiry && enquiryUrl ) {
						html += '<a href="' + esc( enquiryUrl ) + '" class="button button--primary">'
							+ esc( i18n.enquire || 'Enquire to Book' ) + ' \u2192'
							+ '</a>';
					} else if ( soldOut ) {
						var waitlistBase   = config.waitlistUrl || '/contact-us/waiting-list/';
						var waitlistParams = 'class-name=' + encodeURIComponent( event.title );
						if ( event.start ) {
							var d = event.start;
							var ymd = d.getFullYear() + '-'
								+ String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-'
								+ String( d.getDate() ).padStart( 2, '0' );
							waitlistParams += '&date-241=' + ymd;
						}
						var waitlistUrl = waitlistBase + ( waitlistBase.indexOf( '?' ) >= 0 ? '&' : '?' ) + waitlistParams;
						html += '<a href="' + esc( waitlistUrl ) + '" class="button button--secondary">'
							+ esc( i18n.waitlist || 'Join the waiting list' ) + ' \u2192'
							+ '</a>';
					} else if ( bookUrl ) {
						html += '<a href="' + esc( bookUrl ) + '" class="button button--primary">'
							+ esc( i18n.bookIt || 'Book It' ) + ' \u2192'
							+ '</a>';
					}
					if ( detailUrl ) {
						html += '<a href="' + esc( detailUrl ) + '" class="button button--outline">'
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
		// First day of the current month — used as the earliest navigable date.
		var now      = new Date();
		var mql      = window.matchMedia( '(max-width: 767px)' );
		var isMobile = mql.matches;

		// Grid view: start of month so the full grid renders without greyed-out days.
		// List view (mobile): today, so past classes in the current month are hidden.
		var validRangeStart = isMobile
			? new Date( now.getFullYear(), now.getMonth(), now.getDate() )
			: new Date( now.getFullYear(), now.getMonth(), 1 );

		// Responsive view: list on mobile, grid on desktop.

		/**
		 * Returns the correct initialView / changeView string for the given state.
		 * @param  {boolean} mobile
		 * @returns {string}
		 */
		function calView( mobile ) {
			return mobile ? 'listMonth' : 'dayGridMonth';
		}

		/**
		 * Returns the correct headerToolbar config for the given state.
		 * Mobile strips the view-switcher buttons to keep the bar uncluttered.
		 * @param  {boolean} mobile
		 * @returns {object}
		 */
		function calToolbar( mobile ) {
			if ( mobile ) {
				return {
					left   : 'prev,next',
					center : 'title',
					right  : '',
				};
			}
			return {
				left   : 'prev,next today',
				center : 'title',
				right  : 'dayGridMonth,timeGridWeek,listWeek',
			};
		}

		var calendar = new FullCalendar.Calendar( el, {

			initialView : calView( isMobile ),
			locale      : locale,
			timeZone    : config.timeZone || 'local',
			height      : 'auto',
			slotMinTime : '14:00:00', // 2 pm — hide earlier slots in week view
			slotMaxTime : '23:00:00', // 11 pm — hide later slots in week view
			scrollTime  : '16:00:00', // scroll week view to 4 pm on open

			// Prevent backwards navigation — no past months.
			validRange : { start: validRangeStart },

			headerToolbar : calToolbar( isMobile ),

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

			// Render each event as a mini-card: format name + time + title.
			eventContent : function ( arg ) {
				var event  = arg.event;
				var props  = event.extendedProps || {};
				var fmt    = props.format || {};
				var isList       = arg.view.type === 'listMonth';
				var isMobileList = isList && isMobile;

				// ── List view (mobile only): single self-contained card ────────────────────
				if ( isMobileList ) {
					var card = document.createElement( 'div' );
					card.className = 'dish-list-card';

					// Thumbnail.
					if ( props.thumbnail_url ) {
						var thumb = document.createElement( 'img' );
						thumb.src       = props.thumbnail_url;
						thumb.alt       = '';
						thumb.className = 'dish-list-card__thumb';
						card.appendChild( thumb );
					}

					// Content block.
					var content = document.createElement( 'div' );
					content.className = 'dish-list-card__content';

					// Format badge + time on one line.
					var meta = document.createElement( 'div' );
					meta.className = 'dish-list-card__meta';

					if ( fmt.color ) {
						var dot = document.createElement( 'span' );
						dot.className  = 'dish-list-card__dot';
						dot.style.background = fmt.color;
						meta.appendChild( dot );
					}
					if ( fmt.title ) {
						var fmtSpan = document.createElement( 'span' );
						fmtSpan.className   = 'dish-list-card__format';
						fmtSpan.textContent = fmt.title;
						meta.appendChild( fmtSpan );
					}
					if ( event.start && ! event.allDay ) {
						var timeSpan = document.createElement( 'span' );
						timeSpan.className   = 'dish-list-card__time';
						timeSpan.textContent = event.start.toLocaleTimeString( locale, {
							hour: 'numeric', minute: '2-digit', hour12: true,
						} ).toLowerCase();
						meta.appendChild( timeSpan );
					}
					content.appendChild( meta );

					// Title.
					var listTitle = document.createElement( 'div' );
					listTitle.className   = 'dish-list-card__title fc-event-title';
					listTitle.textContent = event.title;
					content.appendChild( listTitle );

					card.appendChild( content );
					return { domNodes: [ card ] };
				}

				// ── Grid / week view: format name + time header + title ──────
				var header = document.createElement( 'div' );
				header.className = 'dish-event__header';

				if ( fmt.color ) {
					var eventDot = document.createElement( 'span' );
					eventDot.className = 'dish-event__dot';
					eventDot.style.background = fmt.color;
					header.appendChild( eventDot );
				}

				if ( fmt.title ) {
					var formatName = document.createElement( 'span' );
					formatName.className = 'dish-event__format-name';
					formatName.textContent = fmt.title;
					header.appendChild( formatName );
				}

				if ( event.start && ! event.allDay ) {
					var time = document.createElement( 'span' );
					time.className = 'dish-event__time';
					time.textContent = event.start.toLocaleTimeString( locale, {
						hour   : 'numeric',
						minute : '2-digit',
						hour12 : true,
					} ).toLowerCase();
					header.appendChild( time );
				}

				// Title.
				var title = document.createElement( 'div' );
				title.className = 'dish-event__title fc-event-title';
				title.textContent = event.title;

				var nodes = [];
				if ( header.hasChildNodes() ) { nodes.push( header ); }
				nodes.push( title );

				return { domNodes: nodes };
			},

			// Click to open detail modal — works on desktop and mobile alike.
			eventClick : function ( info ) {
				info.jsEvent.preventDefault();
				var isPrivate = info.event.extendedProps && info.event.extendedProps.is_private;
				if ( ! isPrivate ) {
					modal.show( info.event );
				}
			},

			// Apply CSS state classes and inject list-view thumbnail after each event is mounted.
			eventDidMount : function ( info ) {
				var props     = info.event.extendedProps || {};
				var spots     = props.spots_remaining;
				var isPrivate = props.is_private;
				var isList    = info.view.type === 'listMonth';

				if ( isPrivate ) {
					info.el.classList.add( 'dish-event--private' );
					// Remove the anchor so keyboard users can't focus it either.
					var anchor = info.el.tagName === 'A' ? info.el : info.el.querySelector( 'a' );
					if ( anchor ) {
						anchor.removeAttribute( 'href' );
						anchor.setAttribute( 'role', 'presentation' );
					}
					// Always show "Booked" label — private events have no public spots.
					var privateTarget = info.el.querySelector( '.dish-list-card__title' ) || info.el.querySelector( '.dish-event__title' ) || info.el.querySelector( '.fc-event-main' ) || info.el;
					var bookedLabel = document.createElement( 'span' );
					bookedLabel.className = 'dish-event__status-label';
					bookedLabel.textContent = 'Booked';
					privateTarget.insertAdjacentElement( 'afterend', bookedLabel );
				}

				if ( spots !== null && spots !== undefined ) {
					var target = info.el.querySelector( '.dish-list-card__title' ) || info.el.querySelector( '.dish-event__title' ) || info.el.querySelector( '.fc-event-main' ) || info.el;

					if ( spots <= 0 ) {
						info.el.classList.add( 'dish-event--sold-out' );
						var soldLabel = document.createElement( 'span' );
						soldLabel.className = 'dish-event__status-label';
						soldLabel.textContent = isPrivate ? 'Booked' : 'Sold out!';
						target.insertAdjacentElement( 'afterend', soldLabel );
						if ( ! isPrivate ) {
							var waitLink = document.createElement( 'a' );
							waitLink.className = 'dish-event__waitlist-link';
							waitLink.href = config.waitlistUrl || '/contact-us/waiting-list/';
							waitLink.textContent = i18n.waitlist || 'Join waiting list';
							soldLabel.insertAdjacentElement( 'afterend', waitLink );
						}
					} else if ( spots <= 3 ) {
						info.el.classList.add( 'dish-event--low-spots' );
					}

					// "N spots left!" label — injected when within the admin-configured threshold.
					var threshold = parseInt( ( window.dishCalendar || {} ).spotsThreshold, 10 ) || 0;
					if ( threshold > 0 && spots > 0 && spots <= threshold ) {
						var spotsLabel = document.createElement( 'span' );
						spotsLabel.className = 'dish-event__spots-label';
						spotsLabel.textContent = spots + ( spots === 1 ? ' spot left!' : ' spots left!' );
						target.insertAdjacentElement( 'afterend', spotsLabel );
					}
				}
			},

		} );

		calendar.render();

		// Switch view and toolbar when the mobile breakpoint is crossed.
		mql.addEventListener( 'change', function ( e ) {
			calendar.changeView( calView( e.matches ) );
			calendar.setOption( 'headerToolbar', calToolbar( e.matches ) );
		} );

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
