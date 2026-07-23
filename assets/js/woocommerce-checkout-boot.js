/**
 * Enable the checkout skeleton before the Checkout Block markup is parsed.
 *
 * The footer script removes this class when Woo's usable controls are ready.
 * The timeout is a progressive-enhancement fail-safe: checkout content must
 * never remain hidden if a gateway script fails to initialise.
 */
( function () {
	'use strict';

	const root = document.documentElement;

	root.classList.add( 'tempo-checkout-loading-enabled' );

	window.setTimeout( function () {
		root.classList.remove( 'tempo-checkout-loading-enabled' );
	}, 12000 );
}() );
