/**
 * Cookie Consent — Basecamp Theme
 *
 * GDPR & CCPA compliant. Uses Google Consent Mode v2.
 * Preference is stored in both localStorage AND a browser cookie.
 * The browser cookie lets PHP detect consent server-side so the banner HTML
 * and this script are skipped entirely on subsequent page loads.
 *
 * Possible values:
 *   'accepted' — user accepted analytics cookies
 *   'declined' — user declined analytics cookies
 *   null/absent — no preference stored, show banner
 *
 * Re-open the banner via the [cookie_preferences] shortcode — it resets
 * both localStorage and the cookie then reloads, no JS required.
 */
( function () { 
	'use strict';

	var STORAGE_KEY = 'basecamp_cookie_consent';
	var COOKIE_TTL  = 60 * 60 * 24 * 365; // 1 year in seconds

	// -------------------------------------------------------------------------
	// GA Consent Mode helpers
	// -------------------------------------------------------------------------

	function grantAnalytics() {
		if ( typeof gtag !== 'function' ) return;
		gtag( 'consent', 'update', {
			analytics_storage : 'granted'
		} );
	}

	// -------------------------------------------------------------------------
	// Cookie helpers
	// -------------------------------------------------------------------------

	function setCookie( value ) {
		document.cookie = STORAGE_KEY + '=' + value
			+ '; path=/; max-age=' + COOKIE_TTL + '; SameSite=Lax';
	}

	// -------------------------------------------------------------------------
	// Banner visibility
	// -------------------------------------------------------------------------

	function showBanner() {
		var banner = document.getElementById( 'basecamp-cookie-banner' );
		if ( ! banner ) return;
		banner.removeAttribute( 'hidden' );
		banner.removeAttribute( 'aria-hidden' );
	}

	function hideBanner() {
		var banner = document.getElementById( 'basecamp-cookie-banner' );
		if ( ! banner ) return;
		banner.setAttribute( 'hidden', '' );
		banner.setAttribute( 'aria-hidden', 'true' );
	}

	// -------------------------------------------------------------------------
	// User actions
	// -------------------------------------------------------------------------

	function onAccept() {
		localStorage.setItem( STORAGE_KEY, 'accepted' );
		setCookie( 'accepted' );
		hideBanner();
		grantAnalytics();
	}

	function onDecline() {
		localStorage.setItem( STORAGE_KEY, 'declined' );
		setCookie( 'declined' );
		hideBanner();
	}

	// -------------------------------------------------------------------------
	// Button binding
	// -------------------------------------------------------------------------

	function bindButtons() {
		var acceptBtn  = document.getElementById( 'basecamp-cookie-accept' );
		var declineBtn = document.getElementById( 'basecamp-cookie-decline' );

		// Clone-replace to clear any previously attached listeners.
		if ( acceptBtn ) {
			var newAccept = acceptBtn.cloneNode( true );
			acceptBtn.parentNode.replaceChild( newAccept, acceptBtn );
			newAccept.addEventListener( 'click', onAccept );
		}

		if ( declineBtn ) {
			var newDecline = declineBtn.cloneNode( true );
			declineBtn.parentNode.replaceChild( newDecline, declineBtn );
			newDecline.addEventListener( 'click', onDecline );
		}
	}

	// -------------------------------------------------------------------------
	// Initialise
	// -------------------------------------------------------------------------

	function init() {
		var consent = localStorage.getItem( STORAGE_KEY );

		if ( consent === 'accepted' ) {
			// localStorage set but cookie may be absent (e.g. user cleared cookies).
			// Re-sync the cookie so PHP can skip the banner on the next load.
			setCookie( 'accepted' );
			grantAnalytics();
		} else if ( consent === 'declined' ) {
			// Re-sync declined state to cookie as well.
			setCookie( 'declined' );
		} else {
			// No stored preference — first-time visitor.
			showBanner();
			bindButtons();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
