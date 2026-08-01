<?php
/**
 * Header menu — renders the classic `header` menu location (Appearance →
 * Menus). Display options live in Customize → Header navigation:
 *
 * - icons & text on each link (per-item icon chosen on the Menus screen),
 * - mobile behaviour: collapse behind a toggle button, or stay inline
 *   showing icons only.
 *
 * The role-aware Book classes / My classes link and the basket pill are
 * the tempo/header-nav block, not menu items.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

$tempo_nav_mobile  = tempo_nav_mobile_style();
$tempo_nav_classes = 'tempo-classic-nav tempo-classic-nav--m-' . $tempo_nav_mobile;
if ( tempo_nav_show_icons() ) {
	$tempo_nav_classes .= ' tempo-classic-nav--has-icons';
}

if ( 'collapse' === $tempo_nav_mobile ) {
	wp_enqueue_script(
		'tempo-book-it-theme-nav',
		get_theme_file_uri( 'assets/js/nav.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
?>
<nav class="<?php echo esc_attr( $tempo_nav_classes ); ?>" aria-label="<?php esc_attr_e( 'Menu', 'tempo-book-it-theme' ); ?>">
	<?php if ( 'collapse' === $tempo_nav_mobile ) : ?>
		<button type="button" class="tempo-classic-nav__toggle" aria-expanded="false" aria-controls="tempo-classic-nav-list">
			<svg class="tempo-classic-nav__toggle-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
			<svg class="tempo-classic-nav__toggle-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'tempo-book-it-theme' ); ?></span>
		</button>
	<?php endif; ?>
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'header',
			'container'      => false,
			'menu_id'        => 'tempo-classic-nav-list',
			'menu_class'     => 'tempo-classic-nav__list',
			'depth'          => 2,
			'fallback_cb'    => 'tempo_book_it_nav_fallback',
		)
	);
	?>
</nav>
