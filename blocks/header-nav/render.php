<?php
/**
 * Portal header nav — the one role-aware link that a static WordPress menu
 * item can't express, because both its label and target change per viewer:
 *
 * Customer: [basket pill] Book classes
 * Teacher:  My classes
 *
 * Everything else (My account, and whatever else a school wants — Contact,
 * Prices, About…) belongs in the registered header menus rendered by the
 * tempo/classic-nav block right next to this one (parts/header.html) —
 * normal WordPress menus, edited under Appearance → Menus.
 *
 * Once a menu is assigned to the viewer's own location, the school has
 * taken over that role's header links entirely, so this pinned block
 * (basket pill included) steps aside and renders nothing.
 *
 * The basket pill markup comes from the plugin (dsb_basket_pill()); its
 * dsb-booking.js keeps the count/countdown live via [data-dsb-basket] hooks.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

if ( has_nav_menu( tempo_nav_menu_location() ) ) {
	return;
}

$tempo_is_teacher = tempo_is_teacher();
?>
<nav class="tempo-header-nav" aria-label="<?php esc_attr_e( 'Portal', 'tempo-book-it-theme' ); ?>">
	<?php
	if ( ! $tempo_is_teacher && function_exists( 'dsb_basket_pill' ) ) {
		dsb_basket_pill();
	}
	?>
	<?php if ( $tempo_is_teacher ) : ?>
		<a class="tempo-header-nav__link" href="<?php echo esc_url( tempo_my_classes_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'My %s', 'tempo-book-it-theme' ), tempo_vocab( 'classes' ) ) );
			?>
		</a>
	<?php else : ?>
		<a class="tempo-header-nav__link" href="<?php echo esc_url( tempo_book_url() ); ?>">
			<?php
			/* translators: %s: tenant word for "classes". */
			echo esc_html( sprintf( __( 'Book %s', 'tempo-book-it-theme' ), tempo_vocab( 'classes' ) ) );
			?>
		</a>
	<?php endif; ?>
</nav>
