<?php
/**
 * Title: Footer copyright
 * Slug: tempo-studio-manager/footer-copyright
 * Inserter: no
 * Description: Copyright line built from the site name, so every tenant gets their own.
 *
 * @package tempo-studio-manager
 */

?>
<!-- wp:paragraph -->
<p>© <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?>. <?php echo esc_html__( 'All rights reserved.', 'tempo-studio-manager' ); ?></p>
<!-- /wp:paragraph -->
