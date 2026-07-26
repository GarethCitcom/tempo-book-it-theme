/**
 * Notice channel controller.
 *
 * The plugin marks a notice with `.dsb-notice[data-dsb-channel]`; this promotes
 * the marked ones out of the page flow into a toast or a dialog. Anything
 * unmarked is left exactly where WooCommerce put it, styled by
 * assets/css/dsb-notices.css.
 *
 * Nothing here is required for a notice to be readable. If this file never runs,
 * every notice stays inline and legible — that is the whole fallback story, so
 * keep it that way.
 *
 * @package tempo-studio-manager
 */

( function () {
	'use strict';

	var TOAST_LIFETIME = 5000;
	var TOAST_MAX = 4;
	var EXIT_DURATION = 160;

	/* Containers a marked notice can arrive in. */
	var HOSTS = [
		'.woocommerce-message',
		'.woocommerce-error',
		'.woocommerce-info',
		'.wc-block-components-notice-banner',
		'.dsb-flash'
	].join( ',' );

	var FOCUSABLE = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])'
	].join( ',' );

	var toastRegion = null;
	var liveToasts = [];
	var popupQueue = [];
	var openPopup = null;

	/* ── Tone ──────────────────────────────────────────────────────── */

	var TONES = {
		success: { glyph: '✓', italic: false },
		danger: { glyph: '!', italic: false },
		warning: { glyph: '!', italic: false },
		info: { glyph: 'i', italic: true }
	};

	/**
	 * Resolve a notice's tone from its host element, matching the CSS.
	 *
	 * @param {Element} host   The WooCommerce notice container.
	 * @param {Element} marker The plugin's marker span.
	 * @return {string} success | danger | warning | info
	 */
	function toneOf( host, marker ) {
		var declared = marker.getAttribute( 'data-dsb-type' );
		if ( declared && TONES[ declared ] ) {
			return declared;
		}
		if ( host.classList.contains( 'woocommerce-message' ) || host.classList.contains( 'is-success' ) ) {
			return 'success';
		}
		if ( host.classList.contains( 'woocommerce-error' ) || host.classList.contains( 'is-error' ) ) {
			return 'danger';
		}
		if ( host.classList.contains( 'is-warning' ) || host.classList.contains( 'dsb-flash' ) ) {
			return 'warning';
		}
		return 'info';
	}

	/** Apply a tone's tokens to an element the CSS then reads. */
	function applyTone( el, tone ) {
		var spec = TONES[ tone ] || TONES.info;
		el.style.setProperty( '--tempo-notice-bg', 'var(--tempo-notice-' + tone + '-bg)' );
		el.style.setProperty( '--tempo-notice-fg', 'var(--tempo-notice-' + tone + '-fg)' );
		el.style.setProperty( '--tempo-notice-glyph-style', spec.italic ? 'italic' : 'normal' );
		return spec.glyph;
	}

	/** Visible, focusable descendants, in document order. */
	function focusableWithin( root ) {
		return Array.prototype.filter.call(
			root.querySelectorAll( FOCUSABLE ),
			function ( el ) {
				return null !== el.offsetParent;
			}
		);
	}

	/* ── Toast ─────────────────────────────────────────────────────── */

	function region() {
		if ( toastRegion && document.body.contains( toastRegion ) ) {
			return toastRegion;
		}
		toastRegion = document.createElement( 'div' );
		toastRegion.className = 'dsb-toast-region';
		// Polite, non-atomic: a new toast is announced without interrupting, and
		// focus is never moved here.
		toastRegion.setAttribute( 'aria-live', 'polite' );
		toastRegion.setAttribute( 'aria-atomic', 'false' );
		document.body.appendChild( toastRegion );
		return toastRegion;
	}

	function dismissToast( toast ) {
		if ( toast.dsbLeaving ) {
			return;
		}
		toast.dsbLeaving = true;
		toast.classList.add( 'is-leaving' );

		var index = liveToasts.indexOf( toast );
		if ( index > -1 ) {
			liveToasts.splice( index, 1 );
		}

		window.setTimeout( function () {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, EXIT_DURATION );
	}

	function scheduleDismiss( toast ) {
		window.clearTimeout( toast.dsbTimer );
		toast.dsbTimer = window.setTimeout( function () {
			dismissToast( toast );
		}, TOAST_LIFETIME );
	}

	/**
	 * @param {Object} notice { key, title, text, tone }
	 */
	function showToast( notice ) {
		var host = region();

		// Same key still on screen: reset its timer rather than stack a duplicate.
		var existing = notice.key
			? liveToasts.filter( function ( t ) {
				return t.dataset.dsbKey === notice.key;
			} )[ 0 ]
			: null;

		if ( existing ) {
			existing.classList.remove( 'is-leaving' );
			existing.dsbLeaving = false;
			// Restart the progress bar by forcing a reflow.
			var bar = existing.querySelector( '.dsb-toast__bar' );
			if ( bar ) {
				bar.style.animation = 'none';
				void bar.offsetWidth;
				bar.style.animation = '';
			}
			scheduleDismiss( existing );
			return;
		}

		while ( liveToasts.length >= TOAST_MAX ) {
			dismissToast( liveToasts[ 0 ] );
		}

		var toast = document.createElement( 'div' );
		toast.className = 'dsb-toast';
		if ( notice.key ) {
			toast.dataset.dsbKey = notice.key;
		}
		var glyph = applyTone( toast, notice.tone );

		var inner = document.createElement( 'div' );
		inner.className = 'dsb-toast__inner';

		var disc = document.createElement( 'div' );
		disc.className = 'dsb-toast__glyph';
		disc.setAttribute( 'aria-hidden', 'true' );
		disc.textContent = glyph;
		inner.appendChild( disc );

		var body = document.createElement( 'div' );
		body.className = 'dsb-toast__body';

		if ( notice.title ) {
			var title = document.createElement( 'div' );
			title.className = 'dsb-toast__title';
			title.textContent = notice.title;
			body.appendChild( title );
		}

		var text = document.createElement( 'div' );
		text.className = 'dsb-toast__text';
		text.textContent = notice.text;
		body.appendChild( text );
		inner.appendChild( body );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'dsb-toast__close';
		close.setAttribute( 'aria-label', dsbNoticesL10n( 'dismiss' ) );
		close.textContent = '×';
		close.addEventListener( 'click', function () {
			dismissToast( toast );
		} );
		inner.appendChild( close );

		toast.appendChild( inner );

		var bar = document.createElement( 'div' );
		bar.className = 'dsb-toast__bar';
		toast.appendChild( bar );

		// Hover and focus hold it open; leaving restarts the clock.
		toast.addEventListener( 'mouseenter', function () {
			window.clearTimeout( toast.dsbTimer );
		} );
		toast.addEventListener( 'mouseleave', function () {
			scheduleDismiss( toast );
		} );
		toast.addEventListener( 'focusin', function () {
			window.clearTimeout( toast.dsbTimer );
		} );
		toast.addEventListener( 'focusout', function () {
			scheduleDismiss( toast );
		} );

		host.appendChild( toast );
		liveToasts.push( toast );
		scheduleDismiss( toast );
	}

	/* ── Popup ─────────────────────────────────────────────────────── */

	function closePopup() {
		if ( ! openPopup ) {
			return;
		}
		var current = openPopup;
		openPopup = null;

		document.removeEventListener( 'keydown', current.keyHandler, true );
		document.documentElement.classList.remove( 'dsb-notice-locked' );

		if ( current.backdrop.parentNode ) {
			current.backdrop.parentNode.removeChild( current.backdrop );
		}

		// Back to where the parent was, or to the document when the notice
		// arrived on page load with nothing to return to.
		if ( current.returnTo && document.body.contains( current.returnTo ) ) {
			try {
				current.returnTo.focus( { preventScroll: true } );
			} catch ( e ) {
				current.returnTo.focus();
			}
		}

		// One at a time: the next queued notice opens as this one closes.
		if ( popupQueue.length ) {
			showPopup( popupQueue.shift() );
		}
	}

	/**
	 * @param {Object} notice { key, title, text, tone, ctaLabel, ctaUrl }
	 */
	function showPopup( notice ) {
		if ( openPopup ) {
			popupQueue.push( notice );
			return;
		}

		var titleId = 'dsb-notice-title-' + ( notice.key || 'x' ) + '-' + liveToasts.length + popupQueue.length;
		var bodyId = titleId + '-body';

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'dsb-notice-backdrop';

		var dialog = document.createElement( 'div' );
		dialog.className = 'dsb-notice-dialog';
		dialog.setAttribute( 'role', 'alertdialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'tabindex', '-1' );
		dialog.setAttribute( 'aria-describedby', bodyId );
		var glyph = applyTone( dialog, notice.tone );

		var header = document.createElement( 'div' );
		header.className = 'dsb-notice-dialog__header';

		var disc = document.createElement( 'div' );
		disc.className = 'dsb-notice-dialog__glyph';
		disc.setAttribute( 'aria-hidden', 'true' );
		disc.textContent = glyph;
		header.appendChild( disc );

		if ( notice.title ) {
			var heading = document.createElement( 'h2' );
			heading.className = 'dsb-notice-dialog__title';
			heading.id = titleId;
			heading.textContent = notice.title;
			header.appendChild( heading );
			dialog.setAttribute( 'aria-labelledby', titleId );
		}

		dialog.appendChild( header );

		var body = document.createElement( 'div' );
		body.className = 'dsb-notice-dialog__body';
		body.id = bodyId;
		body.textContent = notice.text;
		dialog.appendChild( body );

		var actions = document.createElement( 'div' );
		actions.className = 'dsb-notice-dialog__actions';

		var secondary = document.createElement( 'button' );
		secondary.type = 'button';
		secondary.className = 'dsb-notice-dialog__btn dsb-notice-dialog__btn--secondary';
		secondary.textContent = notice.ctaLabel
			? dsbNoticesL10n( 'notNow' )
			: dsbNoticesL10n( 'close' );
		secondary.addEventListener( 'click', closePopup );
		actions.appendChild( secondary );

		// The recovery path, when the plugin offered one. A URL makes it a link
		// styled as the primary button, not a new notice.
		if ( notice.ctaLabel ) {
			var primary = document.createElement( notice.ctaUrl ? 'a' : 'button' );
			primary.className = 'dsb-notice-dialog__btn dsb-notice-dialog__btn--primary';
			primary.textContent = notice.ctaLabel;
			if ( notice.ctaUrl ) {
				primary.href = notice.ctaUrl;
			} else {
				primary.type = 'button';
				primary.addEventListener( 'click', closePopup );
			}
			actions.appendChild( primary );
		}

		dialog.appendChild( actions );
		backdrop.appendChild( dialog );

		backdrop.addEventListener( 'click', function ( event ) {
			if ( event.target === backdrop ) {
				closePopup();
			}
		} );

		function keyHandler( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closePopup();
				return;
			}
			if ( 'Tab' !== event.key ) {
				return;
			}
			// Trap: cycle within the dialog.
			var items = focusableWithin( dialog );
			if ( ! items.length ) {
				event.preventDefault();
				dialog.focus();
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && ( document.activeElement === first || document.activeElement === dialog ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		openPopup = {
			backdrop: backdrop,
			keyHandler: keyHandler,
			returnTo: document.activeElement instanceof HTMLElement ? document.activeElement : null
		};

		document.addEventListener( 'keydown', keyHandler, true );
		document.documentElement.classList.add( 'dsb-notice-locked' );
		document.body.appendChild( backdrop );

		// Focus moves into the dialog on open, landing on the first control so
		// the parent can act or dismiss without hunting.
		var initial = focusableWithin( dialog );
		if ( initial.length ) {
			initial[ 0 ].focus();
		} else {
			dialog.focus();
		}
	}

	/* ── Scan and promote ──────────────────────────────────────────── */

	/**
	 * A popup that leaves its inline copy on the page should not re-block on
	 * every revisit, so an acknowledgement is remembered for the session.
	 *
	 * Only relevant to the keep-inline case. When the host is removed there is
	 * nothing left on the page to re-block over, and the notice will not be
	 * rendered again unless the server queues it again — which is a genuinely new
	 * event worth showing. Toasts are never suppressed.
	 */
	function alreadyAcknowledged( key ) {
		if ( ! key ) {
			return false;
		}
		try {
			return '1' === window.sessionStorage.getItem( 'dsb-notice-ack:' + key );
		} catch ( e ) {
			return false;
		}
	}

	function acknowledge( key ) {
		if ( ! key ) {
			return;
		}
		try {
			window.sessionStorage.setItem( 'dsb-notice-ack:' + key, '1' );
		} catch ( e ) {
			// Private browsing or a full quota: showing the popup again is a far
			// better failure than losing the message.
		}
	}

	function readNotice( host, marker ) {
		return {
			key: marker.getAttribute( 'data-dsb-key' ) || '',
			title: marker.getAttribute( 'data-dsb-title' ) || '',
			ctaLabel: marker.getAttribute( 'data-dsb-cta-label' ) || '',
			ctaUrl: marker.getAttribute( 'data-dsb-cta-url' ) || '',
			keepInline: marker.hasAttribute( 'data-dsb-keep-inline' ),
			text: ( marker.textContent || '' ).trim(),
			tone: toneOf( host, marker )
		};
	}

	function scan() {
		var markers = document.querySelectorAll( '.dsb-notice[data-dsb-channel]' );

		Array.prototype.forEach.call( markers, function ( marker ) {
			if ( marker.dsbHandled ) {
				return;
			}
			marker.dsbHandled = true;

			var channel = marker.getAttribute( 'data-dsb-channel' );
			var host = marker.closest( HOSTS );
			if ( ! host ) {
				return;
			}

			var notice = readNotice( host, marker );
			if ( ! notice.text ) {
				return;
			}

			if ( 'toast' === channel ) {
				// The page already shows the result, so the host is redundant.
				showToast( notice );
				host.parentNode && host.parentNode.removeChild( host );
				return;
			}

			if ( 'popup' === channel ) {
				// The dialog is showing the same words, so the host goes — unless
				// the plugin marked it to stay. It does that for the
				// paid-but-not-booked notice, which is the order's standing record
				// of a money failure rather than a transient state.
				if ( ! notice.keepInline ) {
					showPopup( notice );
					host.parentNode && host.parentNode.removeChild( host );
					return;
				}

				// Kept inline, so suppress the dialog on a revisit — otherwise it
				// re-blocks every time the parent opens that order.
				if ( alreadyAcknowledged( notice.key ) ) {
					return;
				}
				acknowledge( notice.key );
				showPopup( notice );
			}

			// 'inline', or anything unrecognised: CSS has it.
		} );
	}

	/* ── Strings ───────────────────────────────────────────────────── */

	function dsbNoticesL10n( name ) {
		var strings = window.tempoNoticesL10n || {};
		var fallback = { dismiss: 'Dismiss', notNow: 'Not now', close: 'Close' };
		return strings[ name ] || fallback[ name ];
	}

	/* ── Boot ──────────────────────────────────────────────────────── */

	function init() {
		// The boot script hid promoted notices before first paint and armed a
		// fail-safe to reveal them if this file never arrived. It did arrive, so
		// cancel it — that keeps the pre-paint suppression in force for the rest of
		// the session, including notices the plugin injects later over REST.
		if ( window.tempoNoticesFailsafe ) {
			window.clearTimeout( window.tempoNoticesFailsafe );
			window.tempoNoticesFailsafe = null;
		}

		scan();

		// The Blocks notice banner mounts after first paint, and the plugin
		// re-renders its own flash on every REST step, so a one-off scan misses
		// most of them. Matches the MutationObserver approach the theme already
		// uses for Woo-block readiness in woocommerce-checkout.js.
		if ( 'undefined' !== typeof window.MutationObserver ) {
			new window.MutationObserver( scan ).observe( document.body, {
				childList: true,
				subtree: true
			} );
		}

		// The plugin's basket/step transitions. Fired on document, payload in
		// event.detail; feature-detected because dsbBooking only exists on pages
		// where the plugin enqueued its script.
		document.addEventListener( 'dsb:state', scan );

		// A checkout hold lapsing. This one carries its own payload rather than
		// marking up a notice, because there is no server render to attach a
		// marker to — the countdown simply reaches zero on whatever page the
		// parent is on. Copy still comes from the plugin.
		document.addEventListener( 'dsb:hold-expired', onHoldExpiredEvent );

		// A register state that arrives as the screen is being replaced — the day
		// boundary passing, or the register completed on another device. Same
		// reasoning as the hold above: swapTo() destroys anything inline, so the
		// plugin hands it over as an event instead.
		document.addEventListener( 'dsb:register-notice', onRegisterNotice );

		// WooCommerce Blocks' own cart event.
		document.body.addEventListener( 'wc-blocks_added_to_cart', scan );
	}

	/**
	 * Present an expired hold as a blocking dialog.
	 *
	 * @param {CustomEvent} event detail = { key, title, text, ctaLabel, ctaUrl }.
	 */
	function onHoldExpiredEvent( event ) {
		var detail = event && event.detail ? event.detail : {};
		if ( ! detail.text ) {
			return;
		}

		showPopup( {
			key: detail.key || 'hold_expired',
			title: detail.title || '',
			text: detail.text,
			ctaLabel: detail.ctaLabel || '',
			ctaUrl: detail.ctaUrl || '',
			tone: detail.tone || 'danger'
		} );
	}

	/**
	 * Present a register state the teacher's next tap depends on.
	 *
	 * @param {CustomEvent} event detail = { channel, key, tone, title, text }.
	 */
	function onRegisterNotice( event ) {
		var detail = event && event.detail ? event.detail : {};
		if ( ! detail.text ) {
			return;
		}

		// The plugin decides the channel; a tenant demoting everything to inline
		// through dsb_notice_channel already arrives here as 'inline', and there is
		// nothing for the theme to do with it — the plugin renders those in place.
		if ( 'popup' !== detail.channel && 'toast' !== detail.channel ) {
			return;
		}

		var payload = {
			key: detail.key || '',
			title: detail.title || '',
			text: detail.text,
			ctaLabel: detail.ctaLabel || '',
			ctaUrl: detail.ctaUrl || '',
			tone: detail.tone || 'warning'
		};

		if ( 'toast' === detail.channel ) {
			showToast( payload );
			return;
		}

		showPopup( payload );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
}() );
