/**
 * Site Editor placeholders for the theme's PHP-rendered blocks.
 * Without these, server-only blocks show a "missing block" warning in the
 * editor. Front-end output comes entirely from each block's render.php.
 */
( function ( blocks, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	function registerStub( name, title, hint ) {
		blocks.registerBlockType( name, {
			title: title,
			icon: 'admin-site-alt3',
			category: 'theme',
			edit: function () {
				return el(
					'div',
					{
						style: {
							padding: '10px 14px',
							background: '#FFF4EB',
							border: '1px dashed #FF7300',
							borderRadius: '8px',
							color: '#330164',
							fontSize: '13px'
						}
					},
					title + ' — ' + hint
				);
			},
			save: function () {
				return null;
			}
		} );
	}

	registerStub(
		'tempo/logo',
		__( 'Portal logo', 'tempo-book-it-theme' ),
		__( 'rendered on the front end: tenant logo linked home', 'tempo-book-it-theme' )
	);
	registerStub(
		'tempo/header-nav',
		__( 'Portal header nav', 'tempo-book-it-theme' ),
		__( 'rendered on the front end: basket pill + the role-aware Book classes / My classes link', 'tempo-book-it-theme' )
	);
	registerStub(
		'tempo/portal-strip',
		__( 'Portal strip', 'tempo-book-it-theme' ),
		__( 'rendered on the front end: portal label, signed-in name, log out', 'tempo-book-it-theme' )
	);
} )( window.wp.blocks, window.wp.element, window.wp.i18n );
