<?php
/**
 * Portal header nav — the one role-aware link that a static WordPress menu
 * item can't express, because both its label and target change per viewer:
 *
 * Customer: [basket pill] Book classes
 * Teacher:  My classes
 *
 * Everything else (My account, and whatever else a school wants — Contact,
 * Prices, About…) belongs in the editable Navigation block right next to
 * this one (parts/header.html) — a normal WordPress menu, freely editable
 * in the Site Editor, not hardcoded here.
 *
 * The basket pill markup comes from the plugin (dsb_basket_pill()); its
 * dsb-booking.js keeps the count/countdown live via [data-dsb-basket] hooks.
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

$tempo_is_teacher = tempo_is_teacher();
?>
<nav class="tempo-header-nav" aria-label="<?php esc_attr_e( 'Portal', 'tempo-studio-manager' ); ?>">
	<?php
	if ( ! $tempo_is_teacher && function_exists( 'dsb_basket_pill' ) ) {
		dsb_basket_pill();
	}
	?>
	<?php if ( $tempo_is_teacher ) : ?>
		<a class="tempo-header-nav__link" href="<?php echo esc_url( tempo_my_classes_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'My %s', 'tempo-studio-manager' ), tempo_vocab( 'classes' ) ) );
			?>
		</a>
	<?php else : ?>
		<a class="tempo-header-nav__link" href="<?php echo esc_url( tempo_book_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'Book %s', 'tempo-studio-manager' ), tempo_vocab( 'classes' ) ) );
			?>
		</a>
	<?php endif; ?>
</nav>
