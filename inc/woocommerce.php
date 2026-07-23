<?php
/**
 * WooCommerce My Account + Checkout re-skin (theme side).
 *
 * The plugin owns all booking-shaped UI and decorates the Checkout Block and
 * its own account tabs. This file styles the WooCommerce-native surfaces the
 * plugin deliberately leaves to the theme — Account details, Payment methods,
 * Orders and the single order/confirmation view — so they match the design
 * system. It never restyles .dsb-* internals; template overrides live in
 * woocommerce/ and their markup uses the tempo-woo prefix.
 *
 * Everything is function_exists / class_exists guarded so the theme keeps
 * working with WooCommerce absent, and the design tokens carry hex fallbacks
 * so the pages look right even when the plugin (which prints the full --dsb-*
 * set) is not active.
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map a WooCommerce order status to a design-system status severity
 * (success | info | warning | danger | neutral) for the badge colours.
 */
function tempo_studio_manager_order_status_severity( $status ) {
	$status = (string) $status;
	$map    = array(
		'completed'  => 'success',
		'processing' => 'info',
		'on-hold'    => 'warning',
		'pending'    => 'warning',
		'failed'     => 'danger',
		'cancelled'  => 'neutral',
		'refunded'   => 'neutral',
	);

	/**
	 * Filter the design-system severity used for an order-status badge.
	 *
	 * @param string $severity One of success|info|warning|danger|neutral.
	 * @param string $status   The WooCommerce order status (no wc- prefix).
	 */
	return apply_filters(
		'tempo_studio_manager_order_status_severity',
		$map[ $status ] ?? 'neutral',
		$status
	);
}

/**
 * The unicode glyph paired with an order-status badge — matching the design
 * system's glyph set (✓ ! × i •), never an emoji or icon font.
 */
function tempo_studio_manager_order_status_glyph( $status ) {
	$glyphs = array(
		'success' => '✓',
		'info'    => 'i',
		'warning' => '!',
		'danger'  => '×',
		'neutral' => '•',
	);
	$severity = tempo_studio_manager_order_status_severity( $status );
	return $glyphs[ $severity ] ?? '•';
}

/**
 * Enqueue the WooCommerce re-skin stylesheet + password-toggle script on the
 * account, checkout and order-received pages only, and inline a small brand
 * shim so the tenant's two brand colours resolve even on the Woo-native pages
 * where the plugin doesn't print its token layer.
 */
function tempo_studio_manager_woo_assets() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	// Account pages + the order-received (thank-you) page. The main checkout
	// form is the plugin's to decorate, so the theme stays out of it.
	$is_woo_surface = ( function_exists( 'is_account_page' ) && is_account_page() )
		|| ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) );
	if ( ! $is_woo_surface ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'tempo-studio-manager-woo',
		get_theme_file_uri( 'assets/css/woo.css' ),
		array(),
		$version
	);
	wp_enqueue_script(
		'tempo-studio-manager-woo',
		get_theme_file_uri( 'assets/js/woo.js' ),
		array(),
		$version,
		true
	);
	wp_localize_script(
		'tempo-studio-manager-woo',
		'tempoWooStrings',
		array(
			'show' => __( 'Show', 'tempo-studio-manager' ),
			'hide' => __( 'Hide', 'tempo-studio-manager' ),
		)
	);

	// Brand shim: the two tenant colours + a derived subtle tint and the
	// brand text colour, so tempo-woo cards pick up the tenant's palette on
	// pages where the plugin's :root token block isn't present. When the
	// plugin IS active these mirror the values it already prints.
	$primary   = function_exists( 'dsb_brand_colour' ) ? sanitize_hex_color( dsb_brand_colour( 'primary' ) ) : '';
	$secondary = function_exists( 'dsb_brand_colour' ) ? sanitize_hex_color( dsb_brand_colour( 'secondary' ) ) : '';
	$primary   = $primary ?: tempo_studio_manager_brand_colour_fallback( 'primary' );
	$secondary = $secondary ?: tempo_studio_manager_brand_colour_fallback( 'secondary' );
	$subtle    = tempo_studio_manager_tint( $primary, 0.09 ); // ~9% primary over white.

	$vars = sprintf(
		':root{--dsb-color-brand-primary:%1$s;--dsb-color-brand-focus:%1$s;--dsb-color-brand-secondary:%2$s;--dsb-color-text-brand:%2$s;--dsb-color-bg-brand-subtle:%3$s;}',
		$primary,
		$secondary,
		$subtle
	);
	wp_add_inline_style( 'tempo-studio-manager-woo', $vars );
}
add_action( 'wp_enqueue_scripts', 'tempo_studio_manager_woo_assets' );
