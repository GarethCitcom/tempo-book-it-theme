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
 * Is WooCommerce rendering anything on this request?
 *
 * Broader than the other Woo conditionals in this theme on purpose — a notice
 * can be queued on one request and printed on the next, wherever the customer
 * lands.
 */
function tempo_studio_manager_is_woocommerce_screen(): bool {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}

	return is_woocommerce()
		|| is_cart()
		|| is_checkout()
		|| is_account_page();
}

/**
 * Load the notice presentation after the theme chrome.
 */
function tempo_studio_manager_enqueue_notice_assets(): void {
	if ( ! tempo_studio_manager_is_woocommerce_screen() ) {
		return;
	}

	$style_path    = 'assets/css/dsb-notices.css';
	$style_file    = get_theme_file_path( $style_path );
	$style_version = file_exists( $style_file ) ? (string) filemtime( $style_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'tempo-studio-manager-dsb-notices',
		get_theme_file_uri( $style_path ),
		array( 'tempo-studio-manager-chrome' ),
		$style_version
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
