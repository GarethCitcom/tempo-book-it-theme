<?php
/**
 * Portal logo — colour-primary logo on the white header bar, linked home.
 * 46px tall on desktop, 34px on mobile (see chrome.css).
 *
 * @package sjp-theatre-arts
 */

defined( 'ABSPATH' ) || exit;
?>
<a class="sjp-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
	<img src="<?php echo esc_url( sjp_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
</a>
