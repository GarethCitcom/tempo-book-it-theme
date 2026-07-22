<?php
/**
 * Portal header nav — right-hand side of the white header bar.
 *
 * Customer: [basket pill] Book classes · My account
 * Teacher:  My classes · My account
 *
 * The basket pill markup comes from the plugin (dsb_basket_pill()); its
 * dsb-booking.js keeps the count/countdown live via [data-dsb-basket] hooks.
 *
 * @package sjp-theatre-arts
 */

defined( 'ABSPATH' ) || exit;

$sjp_is_teacher = sjp_is_teacher();
?>
<nav class="sjp-header-nav" aria-label="<?php esc_attr_e( 'Portal', 'sjp-theatre-arts' ); ?>">
	<?php
	if ( ! $sjp_is_teacher && function_exists( 'dsb_basket_pill' ) ) {
		dsb_basket_pill();
	}
	?>
	<?php if ( $sjp_is_teacher ) : ?>
		<a class="sjp-header-nav__link" href="<?php echo esc_url( sjp_my_classes_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'My %s', 'sjp-theatre-arts' ), sjp_vocab( 'classes' ) ) );
			?>
		</a>
	<?php else : ?>
		<a class="sjp-header-nav__link" href="<?php echo esc_url( sjp_book_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'Book %s', 'sjp-theatre-arts' ), sjp_vocab( 'classes' ) ) );
			?>
		</a>
	<?php endif; ?>
	<a class="sjp-header-nav__link" href="<?php echo esc_url( sjp_account_url() ); ?>">
		<?php esc_html_e( 'My account', 'sjp-theatre-arts' ); ?>
	</a>
</nav>
