<?php
/**
 * View order — Tempo Studio Manager theme override.
 *
 * Re-skins the single-order view: a back link and a status banner replace
 * WooCommerce's plain status sentence, then the standard order details and
 * customer details render through do_action( 'woocommerce_view_order' ) —
 * unchanged, so plugin booking meta and every order hook still fire. The
 * order-details table and billing address are styled by assets/css/woo.css.
 *
 * Based on WooCommerce core templates/myaccount/view-order.php @version 10.6.0.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempo Studio Manager
 */

defined( 'ABSPATH' ) || exit;

$notes    = $order->get_customer_order_notes();
$status   = $order->get_status();
$severity = tempo_studio_manager_order_status_severity( $status );
$glyph    = tempo_studio_manager_order_status_glyph( $status );
?>
<div class="tempo-woo tempo-woo-order-view">
	<a class="tempo-woo-back" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">‹ <?php esc_html_e( 'Back to orders', 'tempo-studio-manager' ); ?></a>

	<div class="tempo-woo-order-banner tempo-woo-order-banner--<?php echo esc_attr( $severity ); ?>">
		<span class="tempo-woo-badge tempo-woo-badge--<?php echo esc_attr( $severity ); ?>">
			<span aria-hidden="true"><?php echo esc_html( $glyph ); ?></span>
			<?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
		</span>
		<span class="tempo-woo-order-banner__text">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: 1: order number 2: order date */
					esc_html__( 'Order %1$s was placed on %2$s.', 'tempo-studio-manager' ),
					'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>',
					'<strong>' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</strong>'
				)
			);
			?>
		</span>
	</div>

	<?php if ( $notes ) : ?>
		<div class="tempo-woo-order-updates">
			<h2><?php esc_html_e( 'Order updates', 'tempo-studio-manager' ); ?></h2>
			<ol class="woocommerce-OrderUpdates commentlist notes">
				<?php foreach ( $notes as $note ) : ?>
					<li class="woocommerce-OrderUpdate comment note">
						<div class="woocommerce-OrderUpdate-inner comment_container">
							<div class="woocommerce-OrderUpdate-text comment-text">
								<p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( date_i18n( __( 'l jS \o\f F Y, h:ia', 'tempo-studio-manager' ), strtotime( $note->comment_date ) ) ); ?></p>
								<div class="woocommerce-OrderUpdate-description description">
									<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_view_order', $order_id ); ?>
</div>
