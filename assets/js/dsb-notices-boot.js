/**
 * Suppress marked notices before the browser paints them.
 *
 * A notice destined for a toast or a dialog is rendered inline by the server,
 * so without this it paints in the page and is then yanked out by the footer
 * controller — a visible flash on every page load that queues one.
 *
 * Setting the class here, in the head, lets CSS hide those notices before first
 * paint. It doubles as the JS-available signal: with JavaScript off the class
 * never appears and every notice renders inline, which is the whole fallback.
 *
 * The timeout is a progressive-enhancement fail-safe, the same shape as
 * woocommerce-checkout-boot.js: a notice must never stay hidden because the
 * controller failed to load or threw. The controller clears it on startup, so
 * in the normal case the class persists and notices injected later by the
 * plugin's REST steps are pre-hidden too.
 *
 * @package tempo-studio-manager
 */

( function () {
	'use strict';

	const root = document.documentElement;

	root.classList.add( 'tempo-notices-js' );

	window.tempoNoticesFailsafe = window.setTimeout( function () {
		root.classList.remove( 'tempo-notices-js' );
	}, 3000 );
}() );
