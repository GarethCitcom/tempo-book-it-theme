<?php
/**
 * Payment methods — Tempo Studio Manager theme override.
 *
 * Re-skins WooCommerce's saved-cards table as design-system cards. The saved
 * method data and its actions (make default / delete) come straight from
 * WooCommerce, and the before/after hooks and the "Add payment method"
 * gateway check are preserved.
 *
 * Based on WooCommerce core templates/myaccount/payment-methods.php @version 8.9.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempo Studio Manager
 */

defined( 'ABSPATH' ) || exit;

$saved_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
$has_methods   = (bool) $saved_methods;

do_action( 'woocommerce_before_account_payment_methods', $has_methods ); ?>

<div class="tempo-woo tempo-woo-payment-methods">
	<p class="tempo-woo-payment-methods__intro">
		<?php esc_html_e( 'Saved cards let you check out faster next term. Details are held securely by our payment provider — we never store your full card number.', 'tempo-studio-manager' ); ?>
	</p>

	<?php if ( $has_methods ) : ?>

		<?php
		foreach ( $saved_methods as $type => $methods ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			foreach ( $methods as $method ) :
				$brand = ! empty( $method['method']['brand'] ) ? wc_get_credit_card_type_label( $method['method']['brand'] ) : '';
				$last4 = $method['method']['last4'] ?? '';
				// Compact chip label, e.g. VISA / MC, from the brand name.
				$chip = strtoupper( 'mastercard' === strtolower( (string) ( $method['method']['brand'] ?? '' ) ) ? 'MC' : substr( preg_replace( '/[^a-z]/i', '', $brand ), 0, 4 ) );
				?>
				<div class="tempo-woo-card-row<?php echo ! empty( $method['is_default'] ) ? ' is-default' : ''; ?>">
					<span class="tempo-woo-card-row__brand"><?php echo esc_html( $chip ); ?></span>
					<span class="tempo-woo-card-row__detail">
						<span class="tempo-woo-card-row__number">
							<?php
							if ( $last4 ) {
								/* translators: 1: credit card type 2: last 4 digits */
								echo esc_html( sprintf( __( '%1$s ending %2$s', 'tempo-studio-manager' ), $brand, $last4 ) );
							} else {
								echo esc_html( $brand );
							}
							?>
						</span>
						<?php if ( ! empty( $method['expires'] ) ) : ?>
							<span class="tempo-woo-card-row__expires"><?php /* translators: %s: expiry date */ echo esc_html( sprintf( __( 'Expires %s', 'tempo-studio-manager' ), $method['expires'] ) ); ?></span>
						<?php endif; ?>
					</span>

					<?php if ( ! empty( $method['is_default'] ) ) : ?>
						<span class="tempo-woo-badge tempo-woo-badge--success"><span aria-hidden="true">✓</span> <?php esc_html_e( 'Default', 'tempo-studio-manager' ); ?></span>
					<?php endif; ?>

					<span class="tempo-woo-card-row__actions">
						<?php
						foreach ( $method['actions'] as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
							$is_delete = 'delete' === $key;
							echo '<a href="' . esc_url( $action['url'] ) . '" class="tempo-woo-card-row__action ' . sanitize_html_class( $key ) . ( $is_delete ? ' tempo-woo-card-row__action--danger' : '' ) . '">' . esc_html( $action['name'] ) . '</a>';
						}
						?>
					</span>
				</div>
				<?php
			endforeach;
		endforeach;
		?>

	<?php else : ?>

		<div class="tempo-woo-empty"><?php esc_html_e( 'No saved cards yet.', 'tempo-studio-manager' ); ?></div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_account_payment_methods', $has_methods ); ?>

	<?php if ( WC()->payment_gateways->get_available_payment_gateways() ) : ?>
		<a class="tempo-woo-btn tempo-woo-btn--outline" href="<?php echo esc_url( wc_get_endpoint_url( 'add-payment-method' ) ); ?>"><?php esc_html_e( 'Add payment method', 'tempo-studio-manager' ); ?></a>
	<?php endif; ?>
</div>
