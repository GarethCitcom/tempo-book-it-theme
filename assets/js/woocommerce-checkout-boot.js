/**
 * Enable the checkout skeleton before the Checkout Block markup is parsed.
 *
 * The footer script removes these classes when Woo's usable controls are
 * ready (page frame) and when the payment store reports gateways that can
 * actually pay (payment area). The timeouts are progressive-enhancement
 * fail-safes: checkout content must never remain hidden if a gateway
 * script fails to initialise.
 */
( function () {
	'use strict';

	const root = document.documentElement;

	root.classList.add( 'tempo-checkout-loading-enabled' );
	root.classList.add( 'tempo-payment-loading-enabled' );

	window.setTimeout( function () {
		root.classList.remove( 'tempo-checkout-loading-enabled' );
	}, 12000 );

	// The payment area keeps its own, longer-lived mask: gateways negotiate
	// with Stripe/PayPal after the rest of the checkout is usable.
	window.setTimeout( function () {
		root.classList.remove( 'tempo-payment-loading-enabled' );
	}, 15000 );
}() );
