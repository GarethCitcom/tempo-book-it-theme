<?php
/**
 * View order — presentation-only theme override.
 *
 * The status summary follows the approved account design. WooCommerce still
 * renders order notes, details, gateway actions and customer details through
 * its native hooks and templates.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes       = $order->get_customer_order_notes();
$status      = $order->get_status();
$status_name = wc_get_order_status_name( $status );
$tone        = tempo_studio_manager_order_status_tone( $status );
?>

<div class="tempo-woo-order-view">
	<a class="tempo-woo-order-view__back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
		<span aria-hidden="true">‹</span> <?php esc_html_e( 'Back to orders', 'tempo-studio-manager' ); ?>
	</a>

	<div class="tempo-woo-order-view__summary tempo-woo-order-view__summary--<?php echo esc_attr( $tone ); ?>">
		<span class="tempo-woo-status tempo-woo-status--<?php echo esc_attr( $status ); ?>">
			<span class="tempo-woo-status__glyph" aria-hidden="true"><?php echo esc_html( tempo_studio_manager_order_status_glyph( $status ) ); ?></span>
			<?php echo esc_html( $status_name ); ?>
		</span>
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: 1: order number, 2: order date. */
					__( 'Order <strong>#%1$s</strong> was placed on <strong>%2$s</strong>.', 'tempo-studio-manager' ),
					esc_html( $order->get_order_number() ),
					esc_html( wc_format_datetime( $order->get_date_created() ) )
				)
			);
			?>
		</p>
	</div>

	<?php if ( $notes ) : ?>
		<section class="tempo-woo-order-view__updates">
			<h2><?php esc_html_e( 'Order updates', 'woocommerce' ); ?></h2>
			<ol class="woocommerce-OrderUpdates commentlist notes">
				<?php foreach ( $notes as $note ) : ?>
					<li class="woocommerce-OrderUpdate comment note">
						<div class="woocommerce-OrderUpdate-inner comment_container">
							<div class="woocommerce-OrderUpdate-text comment-text">
								<p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( date_i18n( __( 'l jS \o\f F Y, h:ia', 'woocommerce' ), strtotime( $note->comment_date ) ) ); ?></p>
								<div class="woocommerce-OrderUpdate-description description">
									<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_view_order', $order_id ); ?>
</div>
