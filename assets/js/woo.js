/**
 * Tempo Studio Manager — WooCommerce account enhancements.
 *
 * Progressive enhancement only: adds Show/Hide toggles to the password fields
 * on the Account details form. WooCommerce owns the form and its submission;
 * this never changes field names, values or behaviour.
 *
 * Vanilla JS, no build step (mirrors assets/js/login.js).
 */
(function () {
	'use strict';

	var STRINGS = window.tempoWooStrings || {};
	var SHOW = STRINGS.show || 'Show';
	var HIDE = STRINGS.hide || 'Hide';

	function addToggle(input) {
		if (!input || input.dataset.tempoToggle) {
			return;
		}
		input.dataset.tempoToggle = '1';

		// Wrap the input so the button can sit inside the field on the right.
		var wrap = document.createElement('span');
		wrap.className = 'tempo-woo-pw';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'tempo-woo-pw__toggle';
		btn.textContent = SHOW;
		btn.setAttribute('aria-label', SHOW);
		wrap.appendChild(btn);

		btn.addEventListener('click', function () {
			var showing = input.type === 'text';
			input.type = showing ? 'password' : 'text';
			btn.textContent = showing ? SHOW : HIDE;
			btn.setAttribute('aria-label', showing ? SHOW : HIDE);
		});
	}

	function init() {
		var form = document.querySelector('.woocommerce-EditAccountForm');
		if (!form) {
			return;
		}
		['#password_current', '#password_1', '#password_2'].forEach(function (sel) {
			addToggle(form.querySelector(sel));
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
