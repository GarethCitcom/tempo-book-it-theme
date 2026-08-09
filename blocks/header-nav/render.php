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
 * taken over that role's header links, so the pinned link steps aside.
 *
 * The basket pill does not: it carries the checkout countdown and is the
 * only way back to a held basket once the customer navigates away, so it
 * survives an assigned menu and no static menu item can replace it.
 *
 * The basket pill markup comes from the plugin (dsb_basket_pill()); its
 * dsb-booking.js keeps the count/countdown live via [data-dsb-basket] hooks.
 * The plugin prints nothing when there's no basket to show, so the pill is
 * buffered here — with the link gone there may be nothing left to wrap.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

$tempo_is_teacher = tempo_is_teacher();
$tempo_has_menu   = has_nav_menu( tempo_nav_menu_location() );
$tempo_pill       = '';

if ( ! $tempo_is_teacher && function_exists( 'dsb_basket_pill' ) ) {
	ob_start();
	dsb_basket_pill();
	$tempo_pill = trim( (string) ob_get_clean() );
}

if ( $tempo_has_menu && '' === $tempo_pill ) {
	return;
}
?>
<nav class="tempo-header-nav" aria-label="<?php esc_attr_e( 'Portal', 'tempo-book-it-theme' ); ?>">
	<?php
	// Plugin-generated markup, escaped at its source.
	echo $tempo_pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<?php if ( ! $tempo_has_menu ) : ?>
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
	<?php endif; ?>
</nav>
