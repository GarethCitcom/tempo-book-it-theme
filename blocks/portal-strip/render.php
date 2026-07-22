<?php
/**
 * Portal strip — orange band directly below the header, no gap.
 *
 * Left: "Booking portal" / "Teacher portal". Right: "{name} · Log out".
 * The site is members-only, so this only ever renders for logged-in users;
 * it outputs nothing otherwise (e.g. on the privacy policy page).
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}

$tempo_user  = wp_get_current_user();
$tempo_label = tempo_is_teacher()
	/* translators: %s: tenant word for "teacher", capitalised. */
	? sprintf( __( '%s portal', 'tempo-studio-manager' ), ucfirst( tempo_vocab( 'teacher' ) ) )
	: __( 'Booking portal', 'tempo-studio-manager' );
?>
<div class="tempo-portal-strip">
	<div class="tempo-portal-strip__inner">
		<span class="tempo-portal-strip__label"><?php echo esc_html( $tempo_label ); ?></span>
		<span class="tempo-portal-strip__user">
			<?php echo esc_html( $tempo_user->display_name ); ?>
			<span class="tempo-portal-strip__sep" aria-hidden="true">·</span>
			<a class="tempo-portal-strip__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<?php esc_html_e( 'Log out', 'tempo-studio-manager' ); ?>
			</a>
		</span>
	</div>
</div>
