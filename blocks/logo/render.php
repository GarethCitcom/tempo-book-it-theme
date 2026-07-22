<?php
/**
 * Portal logo — colour-primary logo on the white header bar, linked home.
 * 46px tall on desktop, 34px on mobile (see chrome.css).
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;
?>
<a class="tempo-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
	<img src="<?php echo esc_url( tempo_logo_url( tempo_header_logo_variant() ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
</a>
