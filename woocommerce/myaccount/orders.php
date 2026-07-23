<?php
/**
 * Orders — Tempo Studio Manager theme override.
 *
 * Re-skins WooCommerce's account Orders list as design-system cards instead
 * of a table. All order data, per-order actions, pagination and the
 * surrounding woocommerce_*_account_orders hooks are preserved — only the
 * markup around them changes. Consumes the plugin's --dsb-* tokens (with the
 * theme's own fallbacks) via assets/css/woo.css.
 *
 * Based on WooCommerce core templates/myaccount/orders.php @version 9.5.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempo Studio Manager
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : ?>

	<div class="tempo-woo tempo-woo-orders">
		<?php
		foreach ( $customer_orders->orders as $customer_order ) {
			$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$item_count = $order->get_item_count() - $order->get_item_count_refunded();
			$status     = $order->get_status();

			// Item names for the card subtitle, e.g. "Junior Ballet · Tap".
			$item_names = array();
			foreach ( $order->get_items() as $line_item ) {
				$item_names[] = $line_item->get_name();
			}
			$items_summary = implode( ' · ', array_slice( $item_names, 0, 3 ) );
			if ( count( $item_names ) > 3 ) {
				$items_summary .= ' …';
			}

			$actions = wc_get_account_orders_actions( $order );
			?>
			<div class="tempo-woo-order-card tempo-woo-order-card--status-<?php echo esc_attr( $status ); ?>">
				<span class="tempo-woo-order-card__id">
					<?php echo esc_html( _x( 'Order #', 'hash before order number', 'tempo-studio-manager' ) . $order->get_order_number() ); ?>
				</span>
				<span class="tempo-woo-order-card__meta">
					<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>
					<?php if ( '' !== $items_summary ) : ?>
						· <?php echo esc_html( $items_summary ); ?>
					<?php endif; ?>
				</span>

				<span class="tempo-woo-badge tempo-woo-badge--<?php echo esc_attr( tempo_studio_manager_order_status_severity( $status ) ); ?>">
					<span aria-hidden="true"><?php echo esc_html( tempo_studio_manager_order_status_glyph( $status ) ); ?></span>
					<?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
				</span>

				<span class="tempo-woo-order-card__total">
					<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
				</span>

				<span class="tempo-woo-order-card__actions">
					<a class="tempo-woo-view" href="<?php echo esc_url( $order->get_view_order_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: order number */ __( 'View order number %s', 'tempo-studio-manager' ), $order->get_order_number() ) ); ?>"><?php esc_html_e( 'View', 'tempo-studio-manager' ); ?></a>
					<?php
					// Preserve any non-view actions (pay, cancel…) as plain links.
					foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						if ( 'view' === $key ) {
							continue;
						}
						echo '<a href="' . esc_url( $action['url'] ) . '" class="tempo-woo-order-card__action ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
					}
					?>
				</span>
			</div>
			<?php
		}
		?>
	</div>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<div class="tempo-woo tempo-woo-pagination">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="tempo-woo-btn tempo-woo-btn--outline" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'tempo-studio-manager' ); ?></a>
			<?php endif; ?>
			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="tempo-woo-btn tempo-woo-btn--outline" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'tempo-studio-manager' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else : ?>

	<div class="tempo-woo tempo-woo-empty">
		<?php esc_html_e( 'No order has been made yet.', 'tempo-studio-manager' ); ?>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
