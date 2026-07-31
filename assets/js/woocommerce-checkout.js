/**
 * Keep the plugin-owned "To pay today" presentation aligned with the
 * authoritative WooCommerce Checkout Block total.
 *
 * WooCommerce still calculates totals and owns coupons, payment and checkout.
 * This script only mirrors the formatted total already rendered by Woo.
 */
( function () {
	'use strict';

	function initialiseCheckoutSkeleton( checkout ) {
		const root = document.documentElement;
		const stage = document.querySelector( '[data-tempo-checkout-stage]' );

		if ( ! stage || ! checkout ) {
			root.classList.remove( 'tempo-checkout-loading-enabled' );
			return;
		}

		let readySince = 0;
		let finished = false;
		let observer = null;
		let interval = 0;
		let timeout = 0;

		function finish() {
			if ( finished ) {
				return;
			}

			finished = true;
			root.classList.remove( 'tempo-checkout-loading-enabled' );
			stage.classList.add( 'is-ready' );
			stage.setAttribute( 'aria-busy', 'false' );

			if ( observer ) {
				observer.disconnect();
			}
			if ( interval ) {
				window.clearInterval( interval );
			}
			if ( timeout ) {
				window.clearTimeout( timeout );
			}

			window.setTimeout( function () {
				const skeleton = stage.querySelector(
					'[data-tempo-checkout-skeleton]'
				);

				if ( skeleton ) {
					skeleton.hidden = true;
				}
			}, 180 );
		}

		function storeTotalIsFree() {
			if ( ! window.wp || ! window.wp.data ) {
				return false;
			}

			const cartStore = window.wp.data.select( 'wc/store/cart' );
			const totals = cartStore && cartStore.getCartTotals
				? cartStore.getCartTotals()
				: null;

			return Boolean(
				totals
				&& Object.prototype.hasOwnProperty.call( totals, 'total_price' )
				&& Number( totals.total_price ) <= 0
			);
		}

		function usableCheckoutIsRendered() {
			const expressBlock = checkout.querySelector(
				'.wp-block-woocommerce-checkout-express-payment-block'
			);
			const couponMoveRequired = 'true' === stage.getAttribute(
				'data-tempo-await-coupon'
			);
			const couponMoveReady = ! couponMoveRequired || Boolean(
				stage.querySelector(
					'.dsb-checkout__grid[data-tempo-coupon-in-totals="true"] '
					+ '.tempo-checkout-coupon-in-totals'
				)
			);
			const contactReady = Boolean(
				checkout.querySelector(
					'.wp-block-woocommerce-checkout-contact-information-block input, '
					+ '.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-address-card'
				)
			);
			const actionReady = Boolean(
				checkout.querySelector(
					'.wc-block-components-checkout-place-order-button'
				)
			);
			const paymentReady = storeTotalIsFree() || Boolean(
				checkout.querySelector(
					'.wp-block-woocommerce-checkout-payment-block '
					+ '.wc-block-components-radio-control__option, '
					+ '.wp-block-woocommerce-checkout-payment-block '
					+ '.wc-block-components-payment-method-content, '
					+ '.wp-block-woocommerce-checkout-payment-block '
					+ '.wc-block-checkout__no-payment-methods-notice, '
					+ '.wp-block-woocommerce-checkout-payment-block '
					+ '.wc-block-checkout__only-express-payments-notice'
				)
			);
			const expressReady = ! expressBlock
				|| ! expressBlock.classList.contains(
					'wp-block-woocommerce-checkout-express-payment-block--has-express-payment-methods'
				)
				|| Boolean(
					expressBlock.querySelector(
						'.wc-block-components-express-payment__event-buttons > *'
					)
				);
			const coreSkeleton = checkout.querySelector(
				'.wc-block-components-skeleton'
			);

			return contactReady
				&& actionReady
				&& paymentReady
				&& expressReady
				&& couponMoveReady
				&& ! coreSkeleton;
		}

		function check() {
			if ( finished ) {
				return;
			}

			if ( usableCheckoutIsRendered() ) {
				if ( ! readySince ) {
					readySince = Date.now();
				}
				if ( Date.now() - readySince >= 250 ) {
					window.requestAnimationFrame( finish );
				}
			} else {
				readySince = 0;
			}
		}

		observer = new MutationObserver( check );
		observer.observe( checkout, {
			childList: true,
			subtree: true,
		} );
		interval = window.setInterval( check, 150 );
		timeout = window.setTimeout( finish, 10000 );
		check();
	}

	function initialiseCheckoutTotalMirror() {
		if ( ! document.body.classList.contains( 'tempo-woo-checkout' ) ) {
			return;
		}

		const checkoutRoot = document.querySelector(
			'[data-tempo-checkout-stage] .wp-block-woocommerce-checkout'
		);

		initialiseCheckoutSkeleton( checkoutRoot );

		const shell = document.querySelector( '.dsb-checkout:not(.dsb-checkout--inline)' );
		if ( ! shell ) {
			return;
		}

		const checkout = shell.querySelector( '.wp-block-woocommerce-checkout' );
		if ( ! checkout ) {
			return;
		}

		const grid = shell.querySelector( '.dsb-checkout__grid' );
		if ( ! grid ) {
			return;
		}

		grid.setAttribute( 'data-tempo-checkout-script', 'coupon-v7' );

		let frame = 0;
		let ready = false;
		let couponResizeObserver = null;

		function flattenWooLayoutWrappers() {
			const pay = shell.querySelector( '.dsb-checkout__pay' );
			const selectors = [
				'.wc-block-components-sidebar-layout',
				'.wp-block-woocommerce-checkout-fields-block',
				'.wp-block-woocommerce-checkout-express-payment-block',
				'.wc-block-checkout__form',
				'.wc-block-checkout__sidebar',
				'.wp-block-woocommerce-checkout-totals-block',
				'.wp-block-woocommerce-checkout-order-summary-block',
				'.wc-block-components-checkout-order-summary__content',
				'.wp-block-woocommerce-checkout-order-summary-totals-block',
			];

			if ( pay ) {
				pay.style.setProperty( 'display', 'contents', 'important' );
			}
			checkout.style.setProperty( 'display', 'contents', 'important' );

			selectors.forEach( function ( selector ) {
				checkout.querySelectorAll( selector ).forEach( function ( wrapper ) {
					wrapper.style.setProperty( 'display', 'contents', 'important' );
				} );
			} );
		}

		function resetPositionedElement( element ) {
			if ( ! element ) {
				return;
			}

			[
				'position',
				'z-index',
				'top',
				'left',
				'right',
				'width',
				'margin',
				'transform',
			].forEach( function ( property ) {
				element.style.removeProperty( property );
			} );
		}

		function findCouponContents() {
			const candidates = Array.from(
				document.querySelectorAll( '.wc-block-components-totals-coupon' )
			);

			document.querySelectorAll( 'button, [role="button"]' ).forEach(
				function ( control ) {
					const label = control.textContent.replace( /\s+/g, ' ' ).trim();

					if ( ! /^(?:\+\s*)?Add coupons?$/i.test( label ) ) {
						return;
					}

					const panel = control.closest(
						'.wc-block-components-totals-coupon, '
						+ '.wp-block-woocommerce-checkout-order-summary-coupon-form-block, '
						+ '.wc-block-components-panel, '
						+ '[class*="coupon"]'
					) || control.parentElement;

					if ( panel && ! candidates.includes( panel ) ) {
						candidates.push( panel );
					}
				}
			);

			return candidates.sort( function ( first, second ) {
				const firstVisible = Boolean(
					first.offsetWidth || first.offsetHeight || first.getClientRects().length
				);
				const secondVisible = Boolean(
					second.offsetWidth || second.offsetHeight || second.getClientRects().length
				);

				return Number( secondVisible ) - Number( firstVisible );
			} );
		}

		function isolateMobileCouponPath( coupon ) {
			document.querySelectorAll( '.tempo-checkout-mobile-summary-redundant' ).forEach(
				function ( element ) {
					element.classList.remove( 'tempo-checkout-mobile-summary-redundant' );
				}
			);

			if ( ! window.matchMedia( '(max-width: 999.98px)' ).matches ) {
				return;
			}

			const summary = coupon.closest(
				'.wp-block-woocommerce-checkout-order-summary-block'
			);
			let current = coupon;

			if ( ! summary ) {
				return;
			}

			while ( current && current !== summary ) {
				const parent = current.parentElement;

				if ( ! parent ) {
					break;
				}

				Array.from( parent.children ).forEach( function ( sibling ) {
					if ( sibling !== current ) {
						sibling.classList.add(
							'tempo-checkout-mobile-summary-redundant'
						);
					}
				} );

				current = parent;
			}
		}

		function restoreMobileCheckoutFlow() {
			const wrappers = [
				shell.querySelector( '.dsb-checkout__pay' ),
				checkout,
				...checkout.querySelectorAll(
					'.wc-block-components-sidebar-layout, '
					+ '.wp-block-woocommerce-checkout-fields-block, '
					+ '.wp-block-woocommerce-checkout-express-payment-block, '
					+ '.wc-block-checkout__form, '
					+ '.wc-block-checkout__sidebar, '
					+ '.wp-block-woocommerce-checkout-totals-block, '
					+ '.wp-block-woocommerce-checkout-order-summary-block, '
					+ '.wc-block-components-checkout-order-summary__content, '
					+ '.wp-block-woocommerce-checkout-order-summary-totals-block'
				),
			];

			wrappers.forEach( function ( wrapper ) {
				if ( wrapper ) {
					wrapper.style.removeProperty( 'display' );
				}
			} );

			[
				'.wc-block-components-express-payment',
				'.wc-block-components-express-payment-continue-rule',
				'.wp-block-woocommerce-checkout-payment-block',
				'.wp-block-woocommerce-checkout-order-note-block',
				'.wp-block-woocommerce-checkout-terms-block',
				'.wp-block-woocommerce-checkout-actions-block',
			].forEach( function ( selector ) {
				resetPositionedElement( checkout.querySelector( selector ) );
			} );
			findCouponContents().forEach(
				function ( coupon, index ) {
					const couponBlock = coupon.closest(
						'.wp-block-woocommerce-checkout-order-summary-coupon-form-block'
					);

					resetPositionedElement( coupon );
					coupon.classList.remove( 'tempo-checkout-coupon-in-totals' );
					if ( couponBlock ) {
						resetPositionedElement( couponBlock );
						couponBlock.style.removeProperty( 'display' );
						couponBlock.classList.remove( 'tempo-checkout-coupon-in-totals' );
					}
					if ( index > 0 ) {
						coupon.style.setProperty( 'display', 'none', 'important' );
					}
				}
			);

			const payment = checkout.querySelector(
				'.wp-block-woocommerce-checkout-payment-block'
			);
			const actions = checkout.querySelector(
				'.wp-block-woocommerce-checkout-actions-block'
			);

			if ( payment ) {
				payment.style.removeProperty( 'margin-top' );
			}
			if ( actions ) {
				actions.style.removeProperty( 'margin-top' );
			}

			grid.style.removeProperty( '--tempo-checkout-rail-height' );
			grid.removeAttribute( 'data-tempo-express-positioned' );
			grid.setAttribute( 'data-tempo-checkout-flow', 'mobile' );
			return positionCouponInTotals();
		}

		function positionRailElement( element, top ) {
			element.style.setProperty( 'position', 'absolute', 'important' );
			element.style.setProperty( 'z-index', '2' );
			element.style.setProperty( 'top', '0' );
			element.style.setProperty( 'right', '0' );
			element.style.setProperty( 'width', 'var(--tempo-checkout-payment-rail)' );
			element.style.setProperty( 'margin', '0', 'important' );
			element.style.setProperty( 'transform', 'none' );

			const height = Math.ceil( element.getBoundingClientRect().height );
			const correction = Math.round(
				grid.getBoundingClientRect().top + top - element.getBoundingClientRect().top
			);

			element.style.setProperty( 'transform', 'translateY(' + correction + 'px)' );
			return height;
		}

		function positionCouponInTotals() {
			const totals = shell.querySelector( '.dsb-totals' );
			const couponClassCount = document.querySelectorAll(
				'.wc-block-components-totals-coupon'
			).length;
			const couponContents = findCouponContents();
			const couponContent = couponContents[ 0 ];
			const coupon = couponContent;

			grid.setAttribute(
				'data-tempo-coupon-status',
				'totals:' + Number( Boolean( totals ) )
					+ ';class:' + couponClassCount
					+ ';candidates:' + couponContents.length
			);

			if ( ! totals || ! coupon ) {
				grid.removeAttribute( 'data-tempo-coupon-in-totals' );
				return false;
			}

			couponContents.forEach( function ( content, index ) {
				const block = content.closest(
					'.wp-block-woocommerce-checkout-order-summary-coupon-form-block'
				);

				if ( block ) {
					resetPositionedElement( block );
					block.classList.remove( 'tempo-checkout-coupon-in-totals' );
					block.style.removeProperty( 'display' );
				}

				if ( index > 0 ) {
					content.style.setProperty( 'display', 'none', 'important' );
				}
			} );

			const couponBlock = coupon.closest(
				'.wp-block-woocommerce-checkout-order-summary-coupon-form-block'
			);
			if ( couponBlock ) {
				couponBlock.style.setProperty( 'display', 'contents', 'important' );
			}

			isolateMobileCouponPath( coupon );

			const actions = checkout.querySelector(
				'.wp-block-woocommerce-checkout-actions-block'
			);
			if ( actions ) {
				actions.style.removeProperty( 'margin-top' );
			}

			totals.style.removeProperty( 'padding-bottom' );
			grid.style.setProperty( 'position', 'relative' );
			coupon.classList.add( 'tempo-checkout-coupon-in-totals' );
			coupon.style.setProperty( 'position', 'absolute', 'important' );
			coupon.style.setProperty( 'z-index', '3' );
			coupon.style.setProperty( 'top', '0' );
			coupon.style.setProperty( 'left', '0' );
			coupon.style.setProperty( 'right', 'auto' );
			coupon.style.setProperty( 'margin', '0', 'important' );
			coupon.style.setProperty( 'transform', 'none' );

			let totalsRect = totals.getBoundingClientRect();
			const totalRow = totals.querySelector( '.dsb-review-row--total' );
			const basePadding = parseFloat(
				window.getComputedStyle( totals ).paddingBottom
			) || 14;
			const contentWidth = Math.max( 0, totalsRect.width - ( basePadding * 2 ) );

			coupon.style.setProperty( 'width', contentWidth + 'px' );

			const couponHeight = Math.ceil( coupon.getBoundingClientRect().height );
			totals.style.setProperty(
				'padding-bottom',
				( basePadding + couponHeight + 8 ) + 'px'
			);

			/*
			 * Reserve the coupon's height before calculating its coordinates.
			 * On the single-column layout that reservation moves Woo's source
			 * wrapper; measuring first placed the panel one coupon-height too
			 * low and allowed an expanded form to overlap Express Checkout.
			 */
			totalsRect = totals.getBoundingClientRect();
			const totalRowRect = totalRow
				? totalRow.getBoundingClientRect()
				: totalsRect;
			const couponRect = coupon.getBoundingClientRect();
			const desiredLeft = totalsRect.left + basePadding;
			const desiredTop = totalRowRect.bottom + 8;

			coupon.style.setProperty(
				'transform',
				'translate('
					+ Math.round( desiredLeft - couponRect.left ) + 'px, '
					+ Math.round( desiredTop - couponRect.top ) + 'px)'
			);
			grid.setAttribute( 'data-tempo-coupon-in-totals', 'true' );
			grid.setAttribute( 'data-tempo-coupon-status', 'positioned' );
			grid.setAttribute( 'data-tempo-coupon-target', 'panel' );

			if ( ! couponResizeObserver && 'ResizeObserver' in window ) {
				couponResizeObserver = new ResizeObserver( scheduleMirror );
				couponResizeObserver.observe( coupon );
			}

			return true;
		}

		function positionExpressCheckout() {
			if ( ! grid ) {
				return;
			}

			if ( shell.classList.contains( 'dsb-checkout--swap' ) ) {
				if ( ! window.matchMedia( '(min-width: 1000px)' ).matches ) {
					ready = restoreMobileCheckoutFlow();
					return;
				}

				grid.setAttribute( 'data-tempo-checkout-flow', 'desktop-swap' );
				ready = positionCouponInTotals();
				return;
			}

			if ( ! window.matchMedia( '(min-width: 1000px)' ).matches ) {
				ready = restoreMobileCheckoutFlow();
				return;
			}

			flattenWooLayoutWrappers();
			grid.setAttribute( 'data-tempo-checkout-flow', 'desktop' );

			/*
			 * Stack the whole payment rail down the right-hand column with the
			 * absolute-position technique. Grid rows are shared between the two
			 * columns, so leaving these blocks as row-placed grid items tied the
			 * left column's flow to the rail's height and opened a rail-sized gap
			 * above "Contact information" whenever the payment side was taller
			 * than the booking panel. Out of flow, each column scrolls its own
			 * natural height; the measured rail height is handed back to the grid
			 * so the page never ends above the rail.
			 */
			const expressContent = checkout.querySelector(
				'.wc-block-components-express-payment'
			);
			const continueRule = checkout.querySelector(
				'.wc-block-components-express-payment-continue-rule'
			);
			const paymentContent = checkout.querySelector(
				'.wc-block-checkout__payment-method'
			);
			const payment = paymentContent && (
				paymentContent.closest( '.wp-block-woocommerce-checkout-payment-block' )
				|| paymentContent
			);

			if ( ! payment ) {
				grid.setAttribute(
					'data-tempo-express-status',
					'express:' + Boolean( expressContent ) + ';payment:false'
				);
				return;
			}

			const railParts = [];

			if ( expressContent ) {
				railParts.push( { element: expressContent, gapBefore: 0 } );

				if ( continueRule ) {
					railParts.push( { element: continueRule, gapBefore: 10 } );
				}
			}

			railParts.push( {
				element: payment,
				gapBefore: expressContent ? 18 : 0,
			} );

			[
				checkout.querySelector( '.wp-block-woocommerce-checkout-order-note-block' ),
				checkout.querySelector( '.wp-block-woocommerce-checkout-terms-block' ),
				checkout.querySelector( '.wp-block-woocommerce-checkout-actions-block' ),
			].forEach( function ( element ) {
				if ( element ) {
					railParts.push( { element: element, gapBefore: 18 } );
				}
			} );

			let railBottom = 0;

			railParts.forEach( function ( part ) {
				railBottom += part.gapBefore;
				railBottom += positionRailElement( part.element, railBottom );
			} );

			grid.style.setProperty(
				'--tempo-checkout-rail-height',
				Math.ceil( railBottom ) + 'px'
			);
			grid.setAttribute( 'data-tempo-express-positioned', 'true' );
			grid.removeAttribute( 'data-tempo-express-status' );
			ready = positionCouponInTotals();
		}

		function updateDesktopPayCard( button, formattedTotal, free ) {
			const actionsBlock = button.closest(
				'.wp-block-woocommerce-checkout-actions-block'
			);
			const buttonText = button.querySelector(
				'.wc-block-components-checkout-place-order-button__text'
			);

			if ( ! actionsBlock || ! buttonText ) {
				return;
			}

			const payLabel = free
				? 'Place order — nothing to pay today'
				: 'Pay ' + formattedTotal + ' securely';

			button.setAttribute( 'data-tempo-total', formattedTotal );
			button.setAttribute( 'aria-label', payLabel );
			buttonText.setAttribute( 'data-tempo-pay-label', payLabel );

			let totalRow = actionsBlock.querySelector(
				':scope > .tempo-checkout-desktop-total-row'
			);

			if ( ! totalRow ) {
				totalRow = document.createElement( 'div' );
				totalRow.className = 'tempo-checkout-desktop-total-row';
				totalRow.setAttribute( 'aria-hidden', 'true' );
				totalRow.innerHTML =
					'<span class="tempo-checkout-desktop-total-label">To pay today</span>'
					+ '<span class="tempo-checkout-desktop-total-value"></span>';
				actionsBlock.insertBefore( totalRow, actionsBlock.firstChild );
			}

			const totalValue = totalRow.querySelector(
				'.tempo-checkout-desktop-total-value'
			);
			if ( totalValue && totalValue.textContent !== formattedTotal ) {
				totalValue.textContent = formattedTotal;
			}

			let note = actionsBlock.querySelector(
				':scope > .tempo-checkout-desktop-pay-note'
			);

			if ( ! note ) {
				note = document.createElement( 'span' );
				note.className = 'tempo-checkout-desktop-pay-note';
				note.textContent =
					'Hold expiry is enforced by the server — the countdown is a courtesy display.';
				actionsBlock.appendChild( note );
			}
		}

		function mirrorTotal() {
			frame = 0;
			positionExpressCheckout();

			const value = checkout.querySelector(
				'.wc-block-components-totals-footer-item .wc-block-components-totals-item__value'
			);
			const formattedTotal = value
				? value.textContent.trim().replace( /([.,])00(?=\s*(?:[^\d\s]+)?\s*$)/, '' )
				: '';

			if ( ! formattedTotal ) {
				return;
			}

			shell.querySelectorAll( '[data-dsb-to-pay]' ).forEach( function ( target ) {
				target.textContent = formattedTotal;
			} );

			const button = checkout.querySelector(
				'.wc-block-components-checkout-place-order-button'
			);
			let free = false;

			if ( window.wp && window.wp.data ) {
				const cartStore = window.wp.data.select( 'wc/store/cart' );
				const totals = cartStore && cartStore.getCartTotals ? cartStore.getCartTotals() : null;

				if ( totals && Object.prototype.hasOwnProperty.call( totals, 'total_price' ) ) {
					free = Number( totals.total_price ) <= 0;

					shell.querySelectorAll( '[data-dsb-pay-full]' ).forEach( function ( target ) {
						target.hidden = free;
					} );
					shell.querySelectorAll( '[data-dsb-pay-free]' ).forEach( function ( target ) {
						target.hidden = ! free;
					} );
				}
			}

			if ( button ) {
				updateDesktopPayCard( button, formattedTotal, free );
			}
		}

		function scheduleMirror() {
			if ( frame ) {
				return;
			}
			frame = window.requestAnimationFrame( mirrorTotal );
		}

		new MutationObserver( scheduleMirror ).observe( checkout, {
			childList: true,
			characterData: true,
			subtree: true,
		} );

		if ( 'ResizeObserver' in window ) {
			/*
			 * Every rail member is height-watched: the stack is positioned, so a
			 * block growing (an expanded payment method, a validation message,
			 * the order-note textarea) must re-run the stacking pass.
			 */
			const railObserver = new ResizeObserver( scheduleMirror );

			[
				'.wp-block-woocommerce-checkout-express-payment-block',
				'.wp-block-woocommerce-checkout-payment-block',
				'.wp-block-woocommerce-checkout-order-note-block',
				'.wp-block-woocommerce-checkout-terms-block',
				'.wp-block-woocommerce-checkout-actions-block',
			].forEach( function ( selector ) {
				const element = checkout.querySelector( selector );

				if ( element ) {
					railObserver.observe( element );
				}
			} );
		}

		window.addEventListener( 'resize', scheduleMirror, { passive: true } );

		let readinessChecks = 0;
		const readinessTimer = window.setInterval( function () {
			readinessChecks += 1;
			scheduleMirror();

			if ( ready || readinessChecks >= 20 ) {
				window.clearInterval( readinessTimer );
			}
		}, 250 );

		scheduleMirror();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialiseCheckoutTotalMirror, { once: true } );
	} else {
		initialiseCheckoutTotalMirror();
	}
}() );
