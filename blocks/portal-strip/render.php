<?php
/**
 * Portal strip — orange band directly below the header, no gap.
 *
 * Left: "Booking portal" / "Teacher portal". Right: "{name} · Need help? ·
 * Log out". The site is members-only, so this only ever renders for
 * logged-in users; it outputs nothing otherwise (e.g. on the privacy policy
 * page).
 *
 * The links between the name and Log out come from tempo_portal_strip_links()
 * (functions.php): by default just the plugin's "Need help?" page, taken
 * from dsb_help_page_url() and left out when the plugin has no such page,
 * so nothing here is hardcoded to a URL. Filter
 * `tempo_book_it_portal_strip_links` to add or remove entries.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}

$tempo_user  = wp_get_current_user();
$tempo_label = tempo_is_teacher()
	/* translators: %s: tenant word for "teacher", capitalised. */
	? sprintf( __( '%s portal', 'tempo-book-it-theme' ), ucfirst( tempo_vocab( 'teacher' ) ) )
	: __( 'Booking portal', 'tempo-book-it-theme' );
$tempo_links   = tempo_portal_strip_links();
// The page being viewed, for aria-current on whichever link points here.
$tempo_current = is_singular() ? untrailingslashit( strtok( (string) get_permalink(), '?#' ) ) : '';
?>
<div class="tempo-portal-strip">
	<div class="tempo-portal-strip__inner">
		<span class="tempo-portal-strip__label"><?php echo esc_html( $tempo_label ); ?></span>
		<span class="tempo-portal-strip__user">
			<?php echo esc_html( $tempo_user->display_name ); ?>
			<?php foreach ( $tempo_links as $tempo_link ) : ?>
				<?php
				$tempo_url   = isset( $tempo_link['url'] ) ? (string) $tempo_link['url'] : '';
				$tempo_text  = isset( $tempo_link['label'] ) ? (string) $tempo_link['label'] : '';
				if ( '' === $tempo_url || '' === $tempo_text ) {
					continue;
				}
				$tempo_is_here = '' !== $tempo_current && untrailingslashit( strtok( $tempo_url, '?#' ) ) === $tempo_current;
				?>
				<span class="tempo-portal-strip__sep" aria-hidden="true">·</span>
				<a class="tempo-portal-strip__link" href="<?php echo esc_url( $tempo_url ); ?>"<?php echo $tempo_is_here ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $tempo_text ); ?>
				</a>
			<?php endforeach; ?>
			<span class="tempo-portal-strip__sep" aria-hidden="true">·</span>
			<a class="tempo-portal-strip__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<?php esc_html_e( 'Log out', 'tempo-book-it-theme' ); ?>
			</a>
		</span>
	</div>
</div>
