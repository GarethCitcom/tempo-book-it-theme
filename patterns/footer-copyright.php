<?php
/**
 * Title: Footer copyright
 * Slug: tempo-book-it-theme/footer-copyright
 * Inserter: no
 * Description: Copyright line built from the site name, so every tenant gets their own.
 *
 * @package tempo-book-it-theme
 */

?>
<!-- wp:paragraph -->
<p>© <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?>. <?php echo esc_html__( 'All rights reserved.', 'tempo-book-it-theme' ); ?></p>
<!-- /wp:paragraph -->
