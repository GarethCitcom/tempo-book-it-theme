<?php
/**
 * Theme-owned presentation for WooCommerce notices.
 *
 * WooCommerce and the plugin decide what a notice says and when it appears; this
 * file decides how it looks and, for the ones the plugin marks as urgent, where
 * it appears. The stylesheet alone is enough — the controller only promotes
 * marked notices out of the flow, so with JS off everything stays inline.
 *
 * Unlike the account and checkout stylesheets, these assets are not scoped to a
 * body class: notices also fire on cart, shop and any page a plugin notice
 * reaches, so they load wherever WooCommerce is on screen.
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the scope every notice selector is qualified with.
 *
 * The selectors need this to out-specify WooCommerce: the Blocks notice banner
 * paints its status backgrounds on two-class selectors like
 * `.wc-block-components-notice-banner.is-error`, which would beat a bare
 * `.wc-block-components-notice-banner` rule whatever the load order. Prefixed
 * with `body` as well, the notice rules sit one element above that.
 *
 * Not conditional on WooCommerce: the plugin's hold countdown can lapse on any
 * page that shows the header basket pill, and the expiry dialog has to be
 * presentable there too.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function tempo_studio_manager_notices_body_class( $classes ) {
	$classes[] = 'tempo-woo-notices';

	return $classes;
}
add_filter( 'body_class', 'tempo_studio_manager_notices_body_class' );

/**
 * Load the notice presentation after the theme chrome.
 *
 * Loaded site-wide rather than gated on a WooCommerce conditional. A notice can
 * be queued on one request and printed on the next, wherever the customer lands,
 * and a hold can lapse while they are on a page WooCommerce knows nothing about.
 * The theme is members-only, so every front-end page is a logged-in page.
 */
function tempo_studio_manager_enqueue_notice_assets(): void {
	$style_path    = 'assets/css/dsb-notices.css';
	$style_file    = get_theme_file_path( $style_path );
	$style_version = file_exists( $style_file ) ? (string) filemtime( $style_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'tempo-studio-manager-dsb-notices',
		get_theme_file_uri( $style_path ),
		array( 'tempo-studio-manager-chrome' ),
		$style_version
	);

	// Head, not footer, and deliberately before the controller: it has to run
	// ahead of the first paint to stop a promoted notice flashing inline.
	$boot_path    = 'assets/js/dsb-notices-boot.js';
	$boot_file    = get_theme_file_path( $boot_path );
	$boot_version = file_exists( $boot_file ) ? (string) filemtime( $boot_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'tempo-studio-manager-dsb-notices-boot',
		get_theme_file_uri( $boot_path ),
		array(),
		$boot_version,
		false
	);

	$script_path    = 'assets/js/dsb-notices.js';
	$script_file    = get_theme_file_path( $script_path );
	$script_version = file_exists( $script_file ) ? (string) filemtime( $script_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'tempo-studio-manager-dsb-notices',
		get_theme_file_uri( $script_path ),
		array(),
		$script_version,
		true
	);

	// The controller builds the toast and dialog chrome, so its few labels are
	// the theme's to translate.
	wp_localize_script(
		'tempo-studio-manager-dsb-notices',
		'tempoNoticesL10n',
		array(
			'dismiss' => __( 'Dismiss', 'tempo-studio-manager' ),
			'notNow'  => __( 'Not now', 'tempo-studio-manager' ),
			'close'   => __( 'Close', 'tempo-studio-manager' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tempo_studio_manager_enqueue_notice_assets', 30 );
