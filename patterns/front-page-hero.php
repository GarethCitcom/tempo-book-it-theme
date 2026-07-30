<?php
/**
 * Title: Front page welcome
 * Slug: tempo-book-it-theme/front-page-hero
 * Categories: featured
 * Description: Welcome heading with a Book classes call to action.
 *
 * @package tempo-book-it-theme
 */

?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|16"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'Welcome to the booking portal', 'tempo-book-it-theme' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'Book classes, manage your bookings and keep your details up to date — all in one place.', 'tempo-book-it-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|24"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--24)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/book-classes/"><?php echo esc_html__( 'Book classes', 'tempo-book-it-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
