/**
 * Header menu mobile toggle (collapse mode only — enqueued by
 * blocks/classic-nav/render.php when Customize → Header navigation is set
 * to collapse). Opens/closes the menu panel; closes on outside click and
 * Escape.
 */
( function () {
	'use strict';

	function close( nav ) {
		nav.classList.remove( 'is-open' );
		var button = nav.querySelector( '.tempo-classic-nav__toggle' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
	}

	document.querySelectorAll( '.tempo-classic-nav--m-collapse' ).forEach( function ( nav ) {
		var button = nav.querySelector( '.tempo-classic-nav__toggle' );
		if ( ! button ) {
			return;
		}
		button.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		document.querySelectorAll( '.tempo-classic-nav--m-collapse.is-open' ).forEach( function ( nav ) {
			if ( ! nav.contains( event.target ) ) {
				close( nav );
			}
		} );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		document.querySelectorAll( '.tempo-classic-nav--m-collapse.is-open' ).forEach( function ( nav ) {
			close( nav );
			var button = nav.querySelector( '.tempo-classic-nav__toggle' );
			if ( button ) {
				button.focus();
			}
		} );
	} );
} )();
