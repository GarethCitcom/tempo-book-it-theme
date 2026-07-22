<?php
/**
 * Portal strip — orange band directly below the header, no gap.
 *
 * Left: "Booking portal" / "Teacher portal". Right: "{name} · Log out".
 * The site is members-only, so this only ever renders for logged-in users;
 * it outputs nothing otherwise (e.g. on the privacy policy page).
 *
 * @package sjp-theatre-arts
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}

$sjp_user  = wp_get_current_user();
$sjp_label = sjp_is_teacher()
	/* translators: %s: tenant word for "teacher", capitalised. */
	? sprintf( __( '%s portal', 'sjp-theatre-arts' ), ucfirst( sjp_vocab( 'teacher' ) ) )
	: __( 'Booking portal', 'sjp-theatre-arts' );
?>
<div class="sjp-portal-strip">
	<div class="sjp-portal-strip__inner">
		<span class="sjp-portal-strip__label"><?php echo esc_html( $sjp_label ); ?></span>
		<span class="sjp-portal-strip__user">
			<?php echo esc_html( $sjp_user->display_name ); ?>
			<span class="sjp-portal-strip__sep" aria-hidden="true">·</span>
			<a class="sjp-portal-strip__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<?php esc_html_e( 'Log out', 'sjp-theatre-arts' ); ?>
			</a>
		</span>
	</div>
</div>
