( function () {
	'use strict';

	document.querySelectorAll( '.tempo-password-toggle' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var input = document.getElementById( button.getAttribute( 'aria-controls' ) );
			var label = button.querySelector( '.screen-reader-text' );
			if ( ! input ) {
				return;
			}
			var showing = input.type === 'text';
			input.type = showing ? 'password' : 'text';
			button.setAttribute( 'aria-pressed', showing ? 'false' : 'true' );
			if ( label ) {
				label.textContent = showing ? 'Show password' : 'Hide password';
			}
		} );
	} );

	document.querySelectorAll( '[data-registration-form]' ).forEach( function ( form ) {
		var choices = form.querySelectorAll( 'input[name="account_type"]' );
		var panels = form.querySelectorAll( '[data-registration-panel]' );

		function updateRegistrationFields() {
			var selected = form.querySelector( 'input[name="account_type"]:checked' );
			var accountType = selected ? selected.value : 'student';

			panels.forEach( function ( panel ) {
				var active = panel.getAttribute( 'data-registration-panel' ) === accountType;
				panel.hidden = ! active;
				panel.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
				panel.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
					field.disabled = ! active;
					if ( field.hasAttribute( 'data-registration-required' ) ) {
						field.required = active;
					}
				} );
			} );
		}

		choices.forEach( function ( choice ) {
			choice.addEventListener( 'change', updateRegistrationFields );
		} );
		updateRegistrationFields();
	} );
}() );
