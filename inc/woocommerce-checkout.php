<?php
/**
 * Theme-owned presentation for WooCommerce checkout screens.
 *
 * WooCommerce retains ownership of checkout fields, validation, coupons,
 * payment gateways and order submission. The companion plugin retains
 * ownership of booking context and its configured layout. This file only
 * exposes layout scopes and loads the theme's checkout stylesheet.
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request is a checkout screen styled by this theme.
 *
 * The received endpoint has its own receipt stylesheet.
 *
 * @return bool
 */
function tempo_studio_manager_is_checkout_screen() {
	return function_exists( 'is_checkout' )
		&& is_checkout()
		&& ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() );
}

/**
 * Load the checkout presentation after theme chrome and Woo block styles.
 */
function tempo_studio_manager_enqueue_checkout_styles() {
	if ( ! tempo_studio_manager_is_checkout_screen() ) {
		return;
	}

	$boot_path    = 'assets/js/woocommerce-checkout-boot.js';
	$boot_file    = get_theme_file_path( $boot_path );
	$boot_version = file_exists( $boot_file ) ? (string) filemtime( $boot_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'tempo-studio-manager-woocommerce-checkout-boot',
		get_theme_file_uri( $boot_path ),
		array(),
		$boot_version,
		false
	);

	$style_path    = 'assets/css/woocommerce-checkout.css';
	$style_file    = get_theme_file_path( $style_path );
	$style_version = file_exists( $style_file ) ? (string) filemtime( $style_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'tempo-studio-manager-woocommerce-checkout',
		get_theme_file_uri( $style_path ),
		array( 'tempo-studio-manager-chrome' ),
		$style_version
	);

	$script_path    = 'assets/js/woocommerce-checkout.js';
	$script_file    = get_theme_file_path( $script_path );
	$script_version = file_exists( $script_file ) ? (string) filemtime( $script_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'tempo-studio-manager-woocommerce-checkout',
		get_theme_file_uri( $script_path ),
		array( 'wp-data' ),
		$script_version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'tempo_studio_manager_enqueue_checkout_styles', 40 );

/**
 * Add a theme-owned loading frame around Woo's rendered Checkout Block.
 *
 * This filter runs after the companion plugin's checkout wrapper. Public
 * modifier classes in that markup select the matching skeleton arrangement;
 * no plugin settings or checkout data are read by the theme.
 *
 * @param string $block_content Rendered Checkout Block and plugin wrapper.
 * @return string
 */
function tempo_studio_manager_wrap_checkout_loading_frame( $block_content ) {
	if ( ! tempo_studio_manager_is_checkout_screen() ) {
		return $block_content;
	}

	$modifier = '';
	$inline   = false;

	if ( false !== strpos( $block_content, 'dsb-checkout--swap' ) ) {
		$modifier = ' tempo-checkout-stage--swap';
	} elseif ( false !== strpos( $block_content, 'dsb-checkout--inline' ) ) {
		$modifier = ' tempo-checkout-stage--inline';
		$inline   = true;
	}

	$has_booking_shell = false !== strpos( $block_content, 'data-dsb-checkout' );
	$has_coupon_block  = false !== strpos(
		$block_content,
		'wp-block-woocommerce-checkout-order-summary-coupon-form-block'
	);

	if ( ! $has_coupon_block && function_exists( 'has_block' ) ) {
		$has_coupon_block = has_block(
			'woocommerce/checkout-order-summary-coupon-form-block',
			get_the_ID()
		);
	}

	$await_coupon_move = ! $inline
		&& $has_booking_shell
		&& $has_coupon_block
		&& function_exists( 'wc_coupons_enabled' )
		&& wc_coupons_enabled();
	$coupon_attribute = $await_coupon_move
		? ' data-tempo-await-coupon="true"'
		: '';

	$skeleton  = '<div class="tempo-checkout-skeleton" data-tempo-checkout-skeleton role="status" aria-live="polite">';
	$skeleton .= '<span class="screen-reader-text">' . esc_html__( 'Loading secure checkout…', 'tempo-studio-manager' ) . '</span>';
	$skeleton .= '<div class="tempo-checkout-skeleton__grid" aria-hidden="true">';
	$skeleton .= '<div class="tempo-checkout-skeleton__column tempo-checkout-skeleton__column--main">';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--short"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--heading"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--banner"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--card"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--form"></span>';
	$skeleton .= '</div>';
	$skeleton .= '<div class="tempo-checkout-skeleton__column tempo-checkout-skeleton__column--rail">';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--rail-heading"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--express"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--payment"></span>';
	$skeleton .= '<span class="tempo-checkout-skeleton__bar tempo-checkout-skeleton__bar--action"></span>';
	$skeleton .= '</div>';
	$skeleton .= '</div>';
	$skeleton .= '</div>';

	return '<div class="tempo-checkout-stage' . esc_attr( $modifier ) . '" data-tempo-checkout-stage aria-busy="true"' . $coupon_attribute . '>'
		. $skeleton
		. '<div class="tempo-checkout-stage__content" data-tempo-checkout-content>'
		. $block_content
		. '</div>'
		. '</div>';
}
add_filter( 'render_block_woocommerce/checkout', 'tempo_studio_manager_wrap_checkout_loading_frame', 30, 1 );

/**
 * Add a stable scope without coupling the theme to plugin internals.
 *
 * Layout-specific CSS reads the public checkout shell modifiers already
 * present in the rendered markup.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function tempo_studio_manager_checkout_body_classes( $classes ) {
	if ( tempo_studio_manager_is_checkout_screen() ) {
		$classes[] = 'tempo-woo-checkout';
	}

	return $classes;
}
add_filter( 'body_class', 'tempo_studio_manager_checkout_body_classes' );
