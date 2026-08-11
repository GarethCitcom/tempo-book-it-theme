<?php
/**
 * Theme-owned presentation for WooCommerce customer account and receipt screens.
 *
 * WooCommerce continues to own account forms, endpoints, saved payment
 * methods, order actions and confirmation data. This file only adds
 * presentational markup through WooCommerce's public hooks and loads the
 * scoped account and order-received stylesheets.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load account presentation after the theme chrome and WooCommerce styles.
 */
function tempo_book_it_enqueue_account_styles() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	$relative_path = 'assets/css/woocommerce-account.css';
	$file_path     = get_theme_file_path( $relative_path );
	$version       = file_exists( $file_path ) ? (string) filemtime( $file_path ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'tempo-book-it-theme-woocommerce-account',
		get_theme_file_uri( $relative_path ),
		array( 'tempo-book-it-theme-chrome' ),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'tempo_book_it_enqueue_account_styles', 30 );

/**
 * Load the theme's receipt presentation on WooCommerce's order-received page.
 */
function tempo_book_it_enqueue_order_received_styles() {
	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return;
	}

	$relative_path = 'assets/css/woocommerce-order-received.css';
	$file_path     = get_theme_file_path( $relative_path );
	$version       = file_exists( $file_path ) ? (string) filemtime( $file_path ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'tempo-book-it-theme-woocommerce-order-received',
		get_theme_file_uri( $relative_path ),
		array( 'tempo-book-it-theme-chrome' ),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'tempo_book_it_enqueue_order_received_styles', 30 );

/**
 * Add the theme scope used by every selector in the account stylesheet.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function tempo_book_it_account_body_class( $classes ) {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$classes[] = 'tempo-woo-account';
	}
	return $classes;
}
add_filter( 'body_class', 'tempo_book_it_account_body_class' );

/**
 * Add a separate scope for the block and classic order-confirmation templates.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function tempo_book_it_order_received_body_class( $classes ) {
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$classes[] = 'tempo-woo-order-received';
	}
	return $classes;
}
add_filter( 'body_class', 'tempo_book_it_order_received_body_class' );

/**
 * Shape the native orders table into the approved compact card fields.
 *
 * @param array<string,string> $columns Native WooCommerce columns.
 * @return array<string,string>
 */
function tempo_book_it_account_order_columns( $columns ) {
	return array(
		'order-number'  => __( 'Order', 'woocommerce' ),
		'order-summary' => __( 'Order summary', 'tempo-book-it-theme' ),
		'order-status'  => __( 'Status', 'woocommerce' ),
		'order-total'   => __( 'Total', 'woocommerce' ),
		'order-actions' => __( 'Actions', 'woocommerce' ),
	);
}
add_filter( 'woocommerce_account_orders_columns', 'tempo_book_it_account_order_columns' );

/**
 * Render the linked order number with the approved label.
 *
 * @param WC_Order $order Order represented by the current row.
 */
function tempo_book_it_account_order_number_column( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$label = sprintf(
		/* translators: %s: customer-facing order number. */
		__( 'Order #%s', 'tempo-book-it-theme' ),
		$order->get_order_number()
	);

	echo '<a href="' . esc_url( $order->get_view_order_url() ) . '" aria-label="'
		. esc_attr( sprintf( __( 'View order number %s', 'woocommerce' ), $order->get_order_number() ) )
		. '">' . esc_html( $label ) . '</a>';
}
add_action( 'woocommerce_my_account_my_orders_column_order-number', 'tempo_book_it_account_order_number_column' );

/**
 * Print a product-name summary without changing the underlying order query.
 *
 * @param WC_Order $order Order represented by the current row.
 */
function tempo_book_it_account_order_summary_column( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$names = array();
	foreach ( $order->get_items( 'line_item' ) as $item ) {
		$name = trim( wp_strip_all_tags( $item->get_name() ) );
		if ( '' !== $name && ! in_array( $name, $names, true ) ) {
			$names[] = $name;
		}
	}

	$visible_names = array_slice( $names, 0, 2 );
	$summary       = implode( ' · ', $visible_names );
	if ( count( $names ) > 2 ) {
		$summary .= sprintf(
			/* translators: %d: number of additional products in an order. */
			_n( ' + %d more', ' + %d more', count( $names ) - 2, 'tempo-book-it-theme' ),
			count( $names ) - 2
		);
	}

	echo '<time datetime="' . esc_attr( $order->get_date_created()->date( 'c' ) ) . '">'
		. esc_html( wc_format_datetime( $order->get_date_created() ) )
		. '</time>';

	if ( '' !== $summary ) {
		echo '<span aria-hidden="true"> · </span><span>' . esc_html( $summary ) . '</span>';
	}
}
add_action( 'woocommerce_my_account_my_orders_column_order-summary', 'tempo_book_it_account_order_summary_column' );

/**
 * Print the order status as a token-coloured badge.
 *
 * @param WC_Order $order Order represented by the current row.
 */
function tempo_book_it_account_order_status_column( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$status = $order->get_status();
	echo '<span class="tempo-woo-status tempo-woo-status--' . esc_attr( $status ) . '">'
		. '<span class="tempo-woo-status__glyph" aria-hidden="true">' . esc_html( tempo_book_it_order_status_glyph( $status ) ) . '</span> '
		. esc_html( wc_get_order_status_name( $status ) )
		. '</span>';
}
add_action( 'woocommerce_my_account_my_orders_column_order-status', 'tempo_book_it_account_order_status_column' );

/**
 * Keep the native formatted order amount while omitting Woo's item-count copy.
 *
 * @param WC_Order $order Order represented by the current row.
 */
function tempo_book_it_account_order_total_column( $order ) {
	if ( $order instanceof WC_Order ) {
		echo wp_kses_post( $order->get_formatted_order_total() );
	}
}
add_action( 'woocommerce_my_account_my_orders_column_order-total', 'tempo_book_it_account_order_total_column' );

/**
 * Explain saved payment methods before WooCommerce renders the gateway data.
 *
 * @param bool $has_methods Whether the customer has saved methods.
 */
function tempo_book_it_payment_methods_intro( $has_methods ) {
	unset( $has_methods );
	echo '<p class="tempo-woo-payment-intro">'
		. esc_html__( 'Saved cards let you check out faster next term. Details are held securely by our payment provider — we never store your full card number.', 'tempo-book-it-theme' )
		. '</p>';
}
add_action( 'woocommerce_before_account_payment_methods', 'tempo_book_it_payment_methods_intro', 5 );

/**
 * Present a gateway's method data as a branded, readable row.
 *
 * @param array<string,mixed> $method Saved-method data supplied by WooCommerce.
 */
function tempo_book_it_payment_method_column( $method ) {
	$details = isset( $method['method'] ) && is_array( $method['method'] ) ? $method['method'] : array();
	$brand   = isset( $details['brand'] ) ? (string) $details['brand'] : '';
	$last4   = isset( $details['last4'] ) ? (string) $details['last4'] : '';
	$label   = '' !== $brand ? wc_get_credit_card_type_label( $brand ) : __( 'Payment method', 'woocommerce' );

	echo '<span class="tempo-woo-payment-method">';
	echo '<span class="tempo-woo-payment-method__brand">' . esc_html( strtoupper( $label ) ) . '</span>';
	echo '<span class="tempo-woo-payment-method__number">';
	echo '' !== $last4
		? esc_html( sprintf( '•••• %s', $last4 ) )
		: esc_html( $label );
	echo '</span>';
	if ( ! empty( $method['is_default'] ) ) {
		echo '<span class="tempo-woo-status tempo-woo-status--default"><span aria-hidden="true">✓</span> '
			. esc_html__( 'Default', 'tempo-book-it-theme' )
			. '</span>';
	}
	echo '</span>';
}
add_action( 'woocommerce_account_payment_methods_column_method', 'tempo_book_it_payment_method_column' );

/**
 * Add the human label used by the approved design to the expiry value.
 *
 * @param array<string,mixed> $method Saved-method data supplied by WooCommerce.
 */
function tempo_book_it_payment_expires_column( $method ) {
	if ( ! empty( $method['expires'] ) ) {
		echo '<span class="tempo-woo-payment-method__expires">'
			. esc_html( sprintf( __( 'Expires %s', 'tempo-book-it-theme' ), $method['expires'] ) )
			. '</span>';
	}
}
add_action( 'woocommerce_account_payment_methods_column_expires', 'tempo_book_it_payment_expires_column' );

/**
 * Preserve every action a payment gateway supplies, adding only theme classes.
 *
 * @param array<string,mixed> $method Saved-method data supplied by WooCommerce.
 */
function tempo_book_it_payment_actions_column( $method ) {
	if ( empty( $method['actions'] ) || ! is_array( $method['actions'] ) ) {
		return;
	}

	foreach ( $method['actions'] as $key => $action ) {
		if ( empty( $action['url'] ) || empty( $action['name'] ) ) {
			continue;
		}
		echo '<a href="' . esc_url( $action['url'] ) . '" class="tempo-woo-payment-action tempo-woo-payment-action--'
			. esc_attr( sanitize_html_class( (string) $key ) ) . '">'
			. esc_html( $action['name'] )
			. '</a>';
	}
}
add_action( 'woocommerce_account_payment_methods_column_actions', 'tempo_book_it_payment_actions_column' );

/**
 * Map WooCommerce order statuses to the approved unicode status language.
 *
 * @param string $status WooCommerce order status slug without the wc- prefix.
 * @return string
 */
function tempo_book_it_order_status_glyph( $status ) {
	if ( in_array( $status, array( 'completed', 'refunded' ), true ) ) {
		return '✓';
	}
	if ( in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
		return '×';
	}
	if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
		return '!';
	}
	return '•';
}

/**
 * Map a WooCommerce order status to an approved design-system tone.
 *
 * @param string $status WooCommerce order status slug without the wc- prefix.
 * @return string
 */
function tempo_book_it_order_status_tone( $status ) {
	if ( in_array( $status, array( 'completed', 'refunded' ), true ) ) {
		return 'success';
	}
	if ( in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
		return 'danger';
	}
	if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
		return 'warning';
	}
	return 'info';
}
