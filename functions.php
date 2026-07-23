<?php
/**
 * Tempo Studio Manager theme — setup, plugin bridge, members-only gate.
 *
 * The theme owns the chrome (header, portal strip, footer, shell, base type).
 * All booking UI belongs to the Tempo Studio Manager plugin — every plugin
 * call below is function_exists-guarded so the theme works standalone.
 *
 * @package tempo-studio-manager
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */

function tempo_studio_manager_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/chrome.css' );

	// Capability handshake, not a slug check: the plugin exposes its
	// "Header background colour" setting only to a theme that declares
	// this — never hardcodes our theme's name — so a fork or rebrand of
	// this theme keeps the setting simply by keeping this line.
	add_theme_support( 'dsb-header-background' );
	add_theme_support( 'dsb-login-panel-image' );
}
add_action( 'after_setup_theme', 'tempo_studio_manager_setup' );

/**
 * Seed a starter header navigation menu so the editable Navigation block
 * (parts/header.html) isn't empty on a fresh install, and — more
 * importantly — doesn't fall back to WordPress's own default of "every
 * top-level page", which would duplicate the pinned Book classes/My
 * classes link (those pages are top-level too). Never runs again once any
 * navigation menu exists, so it can't clobber a school's own editing.
 */
function tempo_studio_manager_seed_navigation() {
	if ( ! post_type_exists( 'wp_navigation' ) ) {
		return;
	}

	$existing = new WP_Query(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	if ( $existing->have_posts() ) {
		return;
	}

	wp_insert_post(
		array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => __( 'Header navigation', 'tempo-studio-manager' ),
			'post_content' => '<!-- wp:navigation-link ' . wp_json_encode(
				array(
					'label' => __( 'My account', 'tempo-studio-manager' ),
					'url'   => tempo_account_url(),
					'kind'  => 'custom',
				)
			) . ' /-->',
		)
	);
}
add_action( 'after_switch_theme', 'tempo_studio_manager_seed_navigation' );

function tempo_studio_manager_register_blocks() {
	register_block_type( get_theme_file_path( 'blocks/logo' ) );
	register_block_type( get_theme_file_path( 'blocks/header-nav' ) );
	register_block_type( get_theme_file_path( 'blocks/portal-strip' ) );
}
add_action( 'init', 'tempo_studio_manager_register_blocks' );

function tempo_studio_manager_enqueue() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'tempo-studio-manager-chrome',
		get_theme_file_uri( 'assets/css/chrome.css' ),
		array(),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'tempo_studio_manager_enqueue' );

/**
 * Site Editor stubs for the theme's PHP-rendered blocks, so they show a
 * labelled placeholder instead of a "missing block" warning. Plain JS, no build.
 */
function tempo_studio_manager_editor_assets() {
	wp_enqueue_script(
		'tempo-studio-manager-editor-stub',
		get_theme_file_uri( 'assets/js/editor-stub.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'tempo_studio_manager_editor_assets' );

/* -------------------------------------------------------------------------
 * Tempo Studio Manager bridge (all guarded — theme must work without plugin)
 * ---------------------------------------------------------------------- */

/**
 * Tenant logo URL, falling back to the theme's bundled default logo.
 *
 * @param string $variant 'default' (colour, for light surfaces) or 'white'
 *                        (light/inverse variant, for dark or brand-coloured
 *                        surfaces — e.g. a header background matching the
 *                        tenant's brand colour).
 */
function tempo_logo_url( $variant = 'default' ) {
	if ( 'white' === $variant ) {
		if ( function_exists( 'dsb_logo_url_inverse' ) ) {
			$url = dsb_logo_url_inverse();
			if ( '' !== $url ) {
				return $url;
			}
		}
		return get_theme_file_uri( 'assets/images/logo-white.svg' );
	}

	if ( function_exists( 'dsb_logo_url' ) ) {
		$url = dsb_logo_url();
		if ( '' !== $url ) {
			return $url;
		}
	}
	return get_theme_file_uri( 'assets/images/logo.svg' );
}

/** Optional full-bleed login-panel image supplied by the companion plugin. */
function tempo_login_panel_image_url() {
	if ( function_exists( 'dsb_login_panel_image_url' ) ) {
		return esc_url_raw( dsb_login_panel_image_url( '2048x2048' ) );
	}
	return '';
}

/**
 * Tenant vocabulary word (lowercase), falling back to the defaults.
 *
 * @param string $word 'class'|'classes'|'student'|'students'|'teacher'|'teachers'.
 */
function tempo_vocab( $word ) {
	if ( function_exists( 'dsb_vocab' ) ) {
		return dsb_vocab( $word );
	}
	return $word;
}

/**
 * Readable text colour (white or the design system's dark ink) for placing
 * on top of a brand colour used as a background. Mirrors the plugin's
 * dsb_brand_colour_contrast() so the theme's own defaults (the fallback
 * when the plugin is absent) behave the same way: white is kept unless it
 * would be genuinely hard to read, so today's orange/purple stay white.
 *
 * @param string $which 'primary' or 'secondary'.
 */
function tempo_brand_colour_contrast( $which = 'primary' ) {
	if ( function_exists( 'dsb_brand_colour_contrast' ) ) {
		return dsb_brand_colour_contrast( $which );
	}

	$hex = tempo_studio_manager_hex( tempo_studio_manager_brand_colour_fallback( $which ) );
	return tempo_studio_manager_contrast_text( $hex );
}

/** Theme's own default brand colour, used only when the plugin is absent. */
function tempo_studio_manager_brand_colour_fallback( $which ) {
	return 'secondary' === $which ? '#330164' : '#FF7300';
}

/**
 * WCAG relative luminance (0-1) of a hex colour — theme's local copy of the
 * plugin's Assets::relative_luminance(), for standalone use.
 */
function tempo_studio_manager_relative_luminance( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		$hex = '000000';
	}

	$linear = array();
	foreach ( str_split( $hex, 2 ) as $channel ) {
		$c        = hexdec( $channel ) / 255;
		$linear[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

/**
 * Readable text colour for a background — theme's local copy of the
 * plugin's Assets::contrast_text(), kept in sync deliberately. White is
 * kept unless its own contrast ratio drops below 1.5:1 (near-white/pale
 * backgrounds); see the plugin's Assets::contrast_text() for the full
 * rationale — this is a safety net for pale colours, not a WCAG repaint
 * of the theme's default orange/purple, which stay white-on-brand.
 */
function tempo_studio_manager_contrast_text( $hex ) {
	$bg_luminance = tempo_studio_manager_relative_luminance( $hex );
	$white_ratio  = ( max( $bg_luminance, 1.0 ) + 0.05 ) / ( min( $bg_luminance, 1.0 ) + 0.05 );
	return $white_ratio >= 1.5 ? '#FFFFFF' : '#3F3F46';
}

/** Normalise a hex colour, falling back to black for anything unusable. */
function tempo_studio_manager_hex( $hex ) {
	return sanitize_hex_color( (string) $hex ) ?: '#000000';
}

/**
 * Whether a user gets the teacher chrome ("Teacher portal", My classes nav).
 * The plugin registers the `teacher` role; the filter exists so a site can
 * map additional roles without touching the theme.
 *
 * @param WP_User|null $user Defaults to the current user.
 */
function tempo_is_teacher( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( ! $user->exists() ) {
		return false;
	}
	$teacher_roles = apply_filters( 'tempo_studio_manager_teacher_roles', array( 'teacher' ) );
	return (bool) array_intersect( $teacher_roles, (array) $user->roles );
}

/**
 * Permalink of the first published page containing a shortcode, cached for a
 * day. Falls back when the plugin (and so the shortcode) isn't registered.
 *
 * @param string $shortcode Shortcode tag, e.g. 'dsb_booking'.
 * @param string $fallback  URL to use when no page is found.
 */
function tempo_studio_manager_shortcode_page_url( $shortcode, $fallback ) {
	$cache = get_transient( 'tempo_studio_manager_shortcode_pages' );
	if ( ! is_array( $cache ) ) {
		$cache = array();
	}
	if ( ! array_key_exists( $shortcode, $cache ) ) {
		$cache[ $shortcode ] = 0;
		foreach ( get_pages( array( 'number' => 200 ) ) as $page ) {
			if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
				$cache[ $shortcode ] = $page->ID;
				break;
			}
		}
		set_transient( 'tempo_studio_manager_shortcode_pages', $cache, DAY_IN_SECONDS );
	}
	$url = $cache[ $shortcode ] ? get_permalink( $cache[ $shortcode ] ) : '';
	return $url ? $url : $fallback;
}

function tempo_studio_manager_flush_shortcode_pages() {
	delete_transient( 'tempo_studio_manager_shortcode_pages' );
}
add_action( 'save_post_page', 'tempo_studio_manager_flush_shortcode_pages' );

/**
 * URL of the page hosting [dsb_booking]. The plugin creates this page on
 * activation and lets a school reassign it (Settings → General), which is
 * now authoritative; the shortcode scan is only a fallback for an older
 * plugin version that predates dsb_booking_page_url().
 */
function tempo_book_url() {
	if ( function_exists( 'dsb_booking_page_url' ) ) {
		return apply_filters( 'tempo_studio_manager_book_url', dsb_booking_page_url() );
	}
	return apply_filters(
		'tempo_studio_manager_book_url',
		tempo_studio_manager_shortcode_page_url( 'dsb_booking', home_url( '/book-classes/' ) )
	);
}

/** URL of the page hosting [dsb_register] (teacher day view). See tempo_book_url(). */
function tempo_my_classes_url() {
	if ( function_exists( 'dsb_register_page_url' ) ) {
		return apply_filters( 'tempo_studio_manager_my_classes_url', dsb_register_page_url() );
	}
	return apply_filters(
		'tempo_studio_manager_my_classes_url',
		tempo_studio_manager_shortcode_page_url( 'dsb_register', home_url( '/my-classes/' ) )
	);
}

/** WooCommerce My Account URL, with a sane fallback when Woo is absent. */
function tempo_account_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'myaccount' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/my-account/' );
}

/**
 * Tenant's custom header background colour, or the theme's own default
 * white — the approved chrome — when nothing has been set. Declared via
 * Settings → Branding → "Header background colour" (Tempo Studio Manager
 * plugin), unlocked by this theme's `add_theme_support( 'dsb-header-background' )`.
 */
function tempo_header_background_colour() {
	if ( function_exists( 'dsb_header_background_colour' ) ) {
		$hex = dsb_header_background_colour();
		if ( '' !== $hex ) {
			return $hex;
		}
	}
	return '#FFFFFF';
}

/** Whether the header has been branded away from the default white. */
function tempo_header_is_custom() {
	return '#FFFFFF' !== strtoupper( tempo_header_background_colour() );
}

/**
 * Readable colour for header nav-link text and the logo choice, given
 * whatever the header background is. On the default white header this
 * deliberately returns '' (not a computed value) so the CSS falls back
 * to the brand-secondary purple used throughout the approved design —
 * only a genuinely custom header background needs a contrast calculation.
 */
function tempo_header_foreground_colour() {
	if ( ! tempo_header_is_custom() ) {
		return '';
	}
	$hex = tempo_header_background_colour();
	if ( function_exists( 'dsb_contrast_text' ) ) {
		return dsb_contrast_text( $hex );
	}
	return tempo_studio_manager_contrast_text( $hex );
}

/** 'white' when the header background needs the light/inverse logo, else 'default'. */
function tempo_header_logo_variant() {
	return '#FFFFFF' === strtoupper( tempo_header_foreground_colour() ) ? 'white' : 'default';
}

/**
 * Mix a hex colour towards white — mirrors the plugin's tinted "brand subtle"
 * backgrounds (colour-mix at a small percentage over white).
 *
 * @param string $hex    '#rrggbb' (or '#rgb') colour.
 * @param float  $amount Portion of the colour to keep (0.09 = 9% colour, 91% white).
 */
function tempo_studio_manager_tint( $hex, $amount ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '';
	}
	$out = '#';
	foreach ( str_split( $hex, 2 ) as $channel ) {
		$mixed = (int) round( hexdec( $channel ) * $amount + 255 * ( 1 - $amount ) );
		$out  .= str_pad( dechex( $mixed ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/**
 * Feed the tenant brand colours from Tempo Studio Manager settings into the
 * theme.json palette, so the chrome, buttons, headings and links follow the
 * plugin's branding without any theme edits. The theme.json values remain
 * the defaults when the plugin is absent or a colour is unset.
 */
function tempo_studio_manager_brand_palette( $theme_json ) {
	if ( ! function_exists( 'dsb_brand_colour' ) ) {
		return $theme_json;
	}

	$overrides = array();
	$primary   = sanitize_hex_color( dsb_brand_colour( 'primary' ) );
	$secondary = sanitize_hex_color( dsb_brand_colour( 'secondary' ) );
	if ( $primary ) {
		$overrides['brand-primary']          = $primary;
		$overrides['brand-tint-primary']     = tempo_studio_manager_tint( $primary, 0.09 );
		$overrides['brand-primary-contrast'] = tempo_brand_colour_contrast( 'primary' );
	}
	if ( $secondary ) {
		$overrides['brand-secondary']          = $secondary;
		$overrides['brand-tint-secondary']     = tempo_studio_manager_tint( $secondary, 0.05 );
		$overrides['brand-secondary-contrast'] = tempo_brand_colour_contrast( 'secondary' );
	}
	if ( ! $overrides ) {
		return $theme_json;
	}

	// get_data() may key presets by origin ('theme' => [...]); unwrap to the flat authored shape.
	$data    = $theme_json->get_data();
	$palette = isset( $data['settings']['color']['palette'] ) ? $data['settings']['color']['palette'] : array();
	if ( isset( $palette['theme'] ) && is_array( $palette['theme'] ) ) {
		$palette = $palette['theme'];
	}
	foreach ( $palette as &$colour ) {
		if ( isset( $colour['slug'], $overrides[ $colour['slug'] ] ) && $overrides[ $colour['slug'] ] ) {
			$colour['color'] = $overrides[ $colour['slug'] ];
		}
	}
	unset( $colour );

	return $theme_json->update_with(
		array(
			'version'  => 3,
			'settings' => array( 'color' => array( 'palette' => $palette ) ),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'tempo_studio_manager_brand_palette' );

/**
 * Feed a custom theme-header background into theme.json when the tenant
 * has set one (Settings → Branding → "Header background colour"). Left
 * untouched on the default white header — chrome.css's own CSS-variable
 * fallback chain already gives the approved white-header look, and
 * `settings.custom.header` is a keyed object so a partial override here
 * doesn't disturb the untouched borderWidth/logoHeight siblings.
 */
function tempo_studio_manager_header_background( $theme_json ) {
	if ( ! tempo_header_is_custom() ) {
		return $theme_json;
	}

	return $theme_json->update_with(
		array(
			'version'  => 3,
			'settings' => array(
				'custom' => array(
					'header' => array(
						'background' => tempo_header_background_colour(),
						'foreground' => tempo_header_foreground_colour(),
					),
				),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'tempo_studio_manager_header_background' );

/* -------------------------------------------------------------------------
 * Members-only gate — the whole front end requires login
 * ---------------------------------------------------------------------- */

function tempo_studio_manager_require_login() {
	if ( is_user_logged_in() ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}
	// The login page links to the privacy policy — keep it reachable.
	if ( is_privacy_policy() ) {
		return;
	}

	$request_path = isset( $_SERVER['REQUEST_URI'] )
		? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		: '/';

	/**
	 * Paths (prefix match) that stay public, e.g. array( '/privacy-policy/' ).
	 *
	 * @param string[] $paths
	 */
	$public_paths = apply_filters( 'tempo_studio_manager_public_paths', array() );
	foreach ( $public_paths as $public_path ) {
		if ( 0 === strpos( $request_path, $public_path ) ) {
			return;
		}
	}

	$requested = isset( $_SERVER['REQUEST_URI'] )
		? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		: home_url( '/' );

	wp_safe_redirect( wp_login_url( $requested ) );
	exit;
}
add_action( 'template_redirect', 'tempo_studio_manager_require_login' );

/* -------------------------------------------------------------------------
 * Theme-owned sign-in experience.
 * ---------------------------------------------------------------------- */

require_once get_theme_file_path( 'inc/custom-login.php' );

/* -------------------------------------------------------------------------
 * WooCommerce My Account + Checkout re-skin (theme side).
 * ---------------------------------------------------------------------- */

require_once get_theme_file_path( 'inc/woocommerce.php' );
