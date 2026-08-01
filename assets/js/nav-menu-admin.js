/**
 * Appearance → Menus — "Link icon" field behaviour: live preview of the
 * chosen built-in icon, and the media-library picker for custom images.
 * Delegated handlers so items added to the menu via AJAX work too.
 */
( function ( $ ) {
	'use strict';

	var config = window.tempoNavMenuIcons || { icons: {} };

	function sync( $select ) {
		var $field = $select.closest( '.tempo-menu-icon-field' );
		var $preview = $field.find( '.tempo-menu-icon-preview' );
		var $custom = $field.find( '.tempo-menu-icon-custom' );
		var value = $select.val();

		$custom.prop( 'hidden', 'custom' !== value );

		if ( 'custom' === value ) {
			// Keep the current <img> preview if one is already set.
			if ( ! $preview.find( 'img' ).length ) {
				$preview.empty();
			}
		} else {
			$preview.html( config.icons[ value ] || '' );
		}
	}

	$( document ).on( 'change', '.tempo-menu-icon-select', function () {
		sync( $( this ) );
	} );

	$( document ).on( 'click', '.tempo-menu-icon-choose', function ( event ) {
		event.preventDefault();

		var $field = $( this ).closest( '.tempo-menu-icon-field' );
		var frame = wp.media( {
			title: config.chooseTitle,
			library: { type: 'image' },
			button: { text: config.chooseText },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var thumb = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;
			$field.find( '.tempo-menu-icon-id' ).val( attachment.id );
			$field.find( '.tempo-menu-icon-preview' ).html(
				$( '<img>', { src: thumb, alt: '' } )
			);
		} );

		frame.open();
	} );
} )( window.jQuery );
