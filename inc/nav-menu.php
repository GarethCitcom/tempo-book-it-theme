<?php
/**
 * Classic header navigation.
 *
 * The editable part of the header nav is a classic registered menu
 * (Appearance → Menus), not the block Navigation — schools asked for the
 * familiar admin screen. The theme adds two layers on top of the plain
 * menu:
 *
 * - Per-link icons: each menu item can pick one of the theme's built-in
 *   icons or upload its own image (fields on the Menus screen). Icons only
 *   render when the site enables them in Customize → Header navigation.
 * - Mobile behaviour: below the desktop breakpoint the menu either
 *   collapses behind a toggle button or stays inline showing icons only
 *   (labels stay available to screen readers).
 *
 * The role-aware "Book classes / My classes" link and the basket pill stay
 * in the tempo/header-nav block — they can't live in a static menu. An
 * assigned menu takes over the link; the basket pill stays regardless.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Menu locations + WooCommerce nav-icon removal
 * ---------------------------------------------------------------------- */

/**
 * The three header menu locations, one per audience. Each viewer sees only
 * the menu for their own role: students/parents get `header`, teachers get
 * `header-teacher`, school admins get `header-admin` (see
 * tempo_nav_menu_location() for the role mapping).
 */
function tempo_book_it_nav_locations() {
	return array(
		'header'         => __( 'Header (students & parents)', 'tempo-book-it-theme' ),
		'header-teacher' => __( 'Header (teachers)', 'tempo-book-it-theme' ),
		'header-admin'   => __( 'Header (school admins)', 'tempo-book-it-theme' ),
	);
}

function tempo_book_it_register_menus() {
	register_nav_menus( tempo_book_it_nav_locations() );
}
add_action( 'after_setup_theme', 'tempo_book_it_register_menus' );

/**
 * The menu location for the current viewer's role. Admin wins over
 * teacher (a school admin who also teaches sees the admin menu).
 */
function tempo_nav_menu_location() {
	if ( tempo_is_school_admin() ) {
		return 'header-admin';
	}
	if ( tempo_is_teacher() ) {
		return 'header-teacher';
	}
	return 'header';
}

/**
 * WooCommerce auto-inserts its Customer Account and Mini-Cart icon blocks
 * into every core Navigation block via the Block Hooks API. The header no
 * longer uses that block, but keep any Navigation block a school adds
 * elsewhere clean too — the theme's own account link and the plugin's
 * basket pill already cover both jobs.
 */
function tempo_book_it_unhook_woo_nav_icons( $hooked_block_types, $relative_position, $anchor_block_type ) {
	if ( 'core/navigation' === $anchor_block_type ) {
		$hooked_block_types = array_values(
			array_diff(
				(array) $hooked_block_types,
				array( 'woocommerce/customer-account', 'woocommerce/mini-cart' )
			)
		);
	}
	return $hooked_block_types;
}
add_filter( 'hooked_block_types', 'tempo_book_it_unhook_woo_nav_icons', 10, 3 );

/**
 * Menu output while the viewer's location has no menu assigned. No menu is
 * seeded on activation on purpose: assigning a menu to a location replaces
 * that role's pinned tempo/header-nav links, so taking over the header must
 * be a school's own deliberate step. The basket pill is never replaced —
 * it holds the checkout countdown and the only route back to a held basket.
 */
function tempo_book_it_nav_fallback() {
	echo '<ul id="tempo-classic-nav-list" class="tempo-classic-nav__list"><li class="menu-item"><a href="'
		. esc_url( tempo_account_url() ) . '">'
		. esc_html__( 'My account', 'tempo-book-it-theme' )
		. '</a></li></ul>';
}

/* -------------------------------------------------------------------------
 * Settings (Customize → Header navigation)
 * ---------------------------------------------------------------------- */

/** Whether menu links show an icon next to their text. */
function tempo_nav_show_icons() {
	return (bool) get_theme_mod( 'tempo_nav_icons', false );
}

/**
 * Mobile behaviour: 'collapse' (menu button) or 'icons' (links stay
 * inline, icons only). Icons-only needs icons enabled to mean anything,
 * so it degrades to collapse while they're off.
 */
function tempo_nav_mobile_style() {
	$style = get_theme_mod( 'tempo_nav_mobile', 'collapse' );
	if ( 'icons' === $style && tempo_nav_show_icons() ) {
		return 'icons';
	}
	return 'collapse';
}

function tempo_book_it_sanitize_nav_mobile( $value ) {
	return in_array( $value, array( 'collapse', 'icons' ), true ) ? $value : 'collapse';
}

function tempo_book_it_customize_nav( $wp_customize ) {
	$wp_customize->add_section(
		'tempo_header_nav',
		array(
			'title'       => __( 'Header navigation', 'tempo-book-it-theme' ),
			'description' => __( 'How the header menu links display. The menu itself is edited under Appearance → Menus.', 'tempo-book-it-theme' ),
			'priority'    => 40,
		)
	);

	$wp_customize->add_setting(
		'tempo_nav_icons',
		array(
			'default'           => false,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'tempo_nav_icons',
		array(
			'section'     => 'tempo_header_nav',
			'type'        => 'checkbox',
			'label'       => __( 'Show icons next to menu links', 'tempo-book-it-theme' ),
			'description' => __( 'Pick each link\'s icon — or upload your own image — on the Appearance → Menus screen.', 'tempo-book-it-theme' ),
		)
	);

	$wp_customize->add_setting(
		'tempo_nav_mobile',
		array(
			'default'           => 'collapse',
			'sanitize_callback' => 'tempo_book_it_sanitize_nav_mobile',
		)
	);
	$wp_customize->add_control(
		'tempo_nav_mobile',
		array(
			'section'     => 'tempo_header_nav',
			'type'        => 'radio',
			'label'       => __( 'On small screens', 'tempo-book-it-theme' ),
			'choices'     => array(
				'collapse' => __( 'Collapse the menu behind a menu button', 'tempo-book-it-theme' ),
				'icons'    => __( 'Keep the links visible, icons only', 'tempo-book-it-theme' ),
			),
			'description' => __( 'Icons-only needs "Show icons" enabled; links without an icon keep their text.', 'tempo-book-it-theme' ),
		)
	);
}
add_action( 'customize_register', 'tempo_book_it_customize_nav' );

/* -------------------------------------------------------------------------
 * Icon set
 * ---------------------------------------------------------------------- */

/**
 * Built-in menu icons: slug => label + SVG paths (24×24 viewBox, drawn in
 * currentColor so they follow the header foreground automatically).
 */
function tempo_book_it_nav_icons() {
	return array(
		'home'     => array(
			'label' => __( 'Home', 'tempo-book-it-theme' ),
			'paths' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
		),
		'calendar' => array(
			'label' => __( 'Calendar', 'tempo-book-it-theme' ),
			'paths' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
		),
		'user'     => array(
			'label' => __( 'Person', 'tempo-book-it-theme' ),
			'paths' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		),
		'users'    => array(
			'label' => __( 'People', 'tempo-book-it-theme' ),
			'paths' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		),
		'bag'      => array(
			'label' => __( 'Bag', 'tempo-book-it-theme' ),
			'paths' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		),
		'star'     => array(
			'label' => __( 'Star', 'tempo-book-it-theme' ),
			'paths' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
		),
		'heart'    => array(
			'label' => __( 'Heart', 'tempo-book-it-theme' ),
			'paths' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
		),
		'music'    => array(
			'label' => __( 'Music', 'tempo-book-it-theme' ),
			'paths' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
		),
		'phone'    => array(
			'label' => __( 'Phone', 'tempo-book-it-theme' ),
			'paths' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		),
		'mail'     => array(
			'label' => __( 'Email', 'tempo-book-it-theme' ),
			'paths' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
		),
		'map-pin'  => array(
			'label' => __( 'Location', 'tempo-book-it-theme' ),
			'paths' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		),
		'info'     => array(
			'label' => __( 'Info', 'tempo-book-it-theme' ),
			'paths' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
		),
	);
}

/** Full SVG element for a built-in icon slug, '' when unknown. */
function tempo_book_it_nav_icon_svg( $slug ) {
	$icons = tempo_book_it_nav_icons();
	if ( ! isset( $icons[ $slug ] ) ) {
		return '';
	}
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
		. ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. $icons[ $slug ]['paths'] . '</svg>';
}

/**
 * Icon markup for one menu item: the chosen built-in SVG, the uploaded
 * image, or '' when the item has no icon.
 */
function tempo_book_it_nav_item_icon_html( $item_id ) {
	$icon = get_post_meta( $item_id, '_tempo_menu_icon', true );
	if ( '' === $icon ) {
		return '';
	}
	if ( 'custom' === $icon ) {
		$attachment_id = (int) get_post_meta( $item_id, '_tempo_menu_icon_id', true );
		if ( ! $attachment_id ) {
			return '';
		}
		return wp_get_attachment_image(
			$attachment_id,
			array( 48, 48 ),
			true,
			array(
				'class' => 'tempo-nav-icon__img',
				'alt'   => '',
			)
		);
	}
	return tempo_book_it_nav_icon_svg( $icon );
}

/**
 * Prepend the item's icon to its label in the header menu. Runs only when
 * icons are enabled; the label keeps its own span either way so the
 * icons-only mobile CSS can hide text without losing the accessible name.
 */
function tempo_book_it_nav_item_title( $title, $item, $args ) {
	if ( empty( $args->theme_location )
		|| ! array_key_exists( $args->theme_location, tempo_book_it_nav_locations() )
		|| ! tempo_nav_show_icons()
	) {
		return $title;
	}
	$label = '<span class="tempo-nav-label">' . $title . '</span>';
	$icon  = tempo_book_it_nav_item_icon_html( $item->ID );
	if ( '' === $icon ) {
		return $label;
	}
	return '<span class="tempo-nav-icon" aria-hidden="true">' . $icon . '</span>' . $label;
}
add_filter( 'nav_menu_item_title', 'tempo_book_it_nav_item_title', 10, 3 );

/* -------------------------------------------------------------------------
 * Appearance → Menus — per-item icon field
 * ---------------------------------------------------------------------- */

function tempo_book_it_nav_item_fields( $item_id ) {
	$icons     = tempo_book_it_nav_icons();
	$current   = get_post_meta( $item_id, '_tempo_menu_icon', true );
	$custom_id = (int) get_post_meta( $item_id, '_tempo_menu_icon_id', true );
	$preview   = tempo_book_it_nav_item_icon_html( $item_id );
	?>
	<p class="description description-wide tempo-menu-icon-field">
		<label for="tempo-menu-icon-<?php echo (int) $item_id; ?>">
			<?php esc_html_e( 'Link icon', 'tempo-book-it-theme' ); ?><br>
			<select id="tempo-menu-icon-<?php echo (int) $item_id; ?>"
				name="tempo-menu-icon[<?php echo (int) $item_id; ?>]"
				class="tempo-menu-icon-select">
				<option value=""><?php esc_html_e( 'No icon', 'tempo-book-it-theme' ); ?></option>
				<?php foreach ( $icons as $slug => $icon ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
						<?php echo esc_html( $icon['label'] ); ?>
					</option>
				<?php endforeach; ?>
				<option value="custom" <?php selected( $current, 'custom' ); ?>>
					<?php esc_html_e( 'Custom image…', 'tempo-book-it-theme' ); ?>
				</option>
			</select>
		</label>
		<span class="tempo-menu-icon-preview"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput -- theme SVG / wp_get_attachment_image(). ?></span>
		<span class="tempo-menu-icon-custom" <?php echo 'custom' === $current ? '' : 'hidden'; ?>>
			<input type="hidden" class="tempo-menu-icon-id"
				name="tempo-menu-icon-id[<?php echo (int) $item_id; ?>]"
				value="<?php echo esc_attr( $custom_id ? $custom_id : '' ); ?>">
			<button type="button" class="button button-small tempo-menu-icon-choose">
				<?php esc_html_e( 'Choose image', 'tempo-book-it-theme' ); ?>
			</button>
		</span>
	</p>
	<?php
}
add_action( 'wp_nav_menu_item_custom_fields', 'tempo_book_it_nav_item_fields' );

function tempo_book_it_nav_item_save( $menu_id, $menu_item_db_id ) {
	// Also fires on programmatic inserts (e.g. the activation seeder),
	// where the Menus-screen nonce is absent — nothing posted, nothing to do.
	if ( ! isset( $_POST['update-nav-menu-nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['update-nav-menu-nonce'] ) ), 'update-nav_menu' )
		|| ! current_user_can( 'edit_theme_options' )
	) {
		return;
	}

	$posted = isset( $_POST['tempo-menu-icon'][ $menu_item_db_id ] )
		? sanitize_key( wp_unslash( $_POST['tempo-menu-icon'][ $menu_item_db_id ] ) )
		: '';
	if ( 'custom' === $posted || array_key_exists( $posted, tempo_book_it_nav_icons() ) ) {
		update_post_meta( $menu_item_db_id, '_tempo_menu_icon', $posted );
	} else {
		delete_post_meta( $menu_item_db_id, '_tempo_menu_icon' );
	}

	$attachment_id = isset( $_POST['tempo-menu-icon-id'][ $menu_item_db_id ] )
		? absint( $_POST['tempo-menu-icon-id'][ $menu_item_db_id ] )
		: 0;
	if ( $attachment_id ) {
		update_post_meta( $menu_item_db_id, '_tempo_menu_icon_id', $attachment_id );
	} else {
		delete_post_meta( $menu_item_db_id, '_tempo_menu_icon_id' );
	}
}
add_action( 'wp_update_nav_menu_item', 'tempo_book_it_nav_item_save', 10, 2 );

function tempo_book_it_nav_admin_assets( $hook ) {
	if ( 'nav-menus.php' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'tempo-book-it-nav-menu-admin',
		get_theme_file_uri( 'assets/js/nav-menu-admin.js' ),
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	$svgs = array();
	foreach ( array_keys( tempo_book_it_nav_icons() ) as $slug ) {
		$svgs[ $slug ] = tempo_book_it_nav_icon_svg( $slug );
	}
	wp_localize_script(
		'tempo-book-it-nav-menu-admin',
		'tempoNavMenuIcons',
		array(
			'icons'       => $svgs,
			'chooseTitle' => __( 'Choose a menu icon', 'tempo-book-it-theme' ),
			'chooseText'  => __( 'Use this icon', 'tempo-book-it-theme' ),
		)
	);

	wp_add_inline_style(
		'wp-admin',
		'.tempo-menu-icon-field{display:flex;flex-wrap:wrap;align-items:flex-end;gap:8px}'
		. '.tempo-menu-icon-preview{line-height:0;padding-bottom:5px}'
		. '.tempo-menu-icon-preview svg,.tempo-menu-icon-preview img{width:18px;height:18px;object-fit:contain}'
		. '.tempo-menu-icon-custom{padding-bottom:2px}'
	);
}
add_action( 'admin_enqueue_scripts', 'tempo_book_it_nav_admin_assets' );
