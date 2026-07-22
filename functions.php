<?php
/**
 * SJP Theatre Arts — theme setup, Tempo Studio Manager bridge, members-only gate.
 *
 * The theme owns the chrome (header, portal strip, footer, shell, base type).
 * All booking UI belongs to the Tempo Studio Manager plugin — every plugin
 * call below is function_exists-guarded so the theme works standalone.
 *
 * @package sjp-theatre-arts
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */

function sjp_theatre_arts_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/chrome.css' );
}
add_action( 'after_setup_theme', 'sjp_theatre_arts_setup' );

function sjp_theatre_arts_register_blocks() {
	register_block_type( get_theme_file_path( 'blocks/logo' ) );
	register_block_type( get_theme_file_path( 'blocks/header-nav' ) );
	register_block_type( get_theme_file_path( 'blocks/portal-strip' ) );
}
add_action( 'init', 'sjp_theatre_arts_register_blocks' );

function sjp_theatre_arts_enqueue() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'sjp-theatre-arts-chrome',
		get_theme_file_uri( 'assets/css/chrome.css' ),
		array(),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'sjp_theatre_arts_enqueue' );

/**
 * Site Editor stubs for the theme's PHP-rendered blocks, so they show a
 * labelled placeholder instead of a "missing block" warning. Plain JS, no build.
 */
function sjp_theatre_arts_editor_assets() {
	wp_enqueue_script(
		'sjp-theatre-arts-editor-stub',
		get_theme_file_uri( 'assets/js/editor-stub.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'sjp_theatre_arts_editor_assets' );

/* -------------------------------------------------------------------------
 * Tempo Studio Manager bridge (all guarded — theme must work without plugin)
 * ---------------------------------------------------------------------- */

/**
 * Tenant logo URL, falling back to the bundled SJP asset.
 *
 * @param string $variant 'default' (colour, for white surfaces) or 'white'.
 */
function sjp_logo_url( $variant = 'default' ) {
	if ( function_exists( 'dsb_logo_url' ) ) {
		$url = dsb_logo_url();
		if ( '' !== $url ) {
			return $url;
		}
	}
	$file = 'white' === $variant ? 'logo-white.svg' : 'logo.svg';
	return get_theme_file_uri( 'assets/images/' . $file );
}

/**
 * Tenant vocabulary word (lowercase), falling back to the defaults.
 *
 * @param string $word 'class'|'classes'|'student'|'students'|'teacher'|'teachers'.
 */
function sjp_vocab( $word ) {
	if ( function_exists( 'dsb_vocab' ) ) {
		return dsb_vocab( $word );
	}
	return $word;
}

/**
 * Whether a user gets the teacher chrome ("Teacher portal", My classes nav).
 * The plugin registers the `teacher` role; the filter exists so a site can
 * map additional roles without touching the theme.
 *
 * @param WP_User|null $user Defaults to the current user.
 */
function sjp_is_teacher( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( ! $user->exists() ) {
		return false;
	}
	$teacher_roles = apply_filters( 'sjp_theatre_arts_teacher_roles', array( 'teacher' ) );
	return (bool) array_intersect( $teacher_roles, (array) $user->roles );
}

/** URL of the page hosting [dsb_booking]. */
function sjp_book_url() {
	return apply_filters( 'sjp_theatre_arts_book_url', home_url( '/book-classes/' ) );
}

/** URL of the page hosting [dsb_register] (teacher day view). */
function sjp_my_classes_url() {
	return apply_filters( 'sjp_theatre_arts_my_classes_url', home_url( '/my-classes/' ) );
}

/** WooCommerce My Account URL, with a sane fallback when Woo is absent. */
function sjp_account_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'myaccount' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/my-account/' );
}

/* -------------------------------------------------------------------------
 * Members-only gate — the whole front end requires login
 * ---------------------------------------------------------------------- */

function sjp_theatre_arts_require_login() {
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
	$public_paths = apply_filters( 'sjp_theatre_arts_public_paths', array() );
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
add_action( 'template_redirect', 'sjp_theatre_arts_require_login' );

/* -------------------------------------------------------------------------
 * Login page branding (wp-login.php styled with the design-system tokens)
 * ---------------------------------------------------------------------- */

function sjp_theatre_arts_login_styles() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'sjp-theatre-arts-login',
		get_theme_file_uri( 'assets/css/login.css' ),
		array(),
		$version
	);
	$logo = sjp_logo_url();
	wp_add_inline_style(
		'sjp-theatre-arts-login',
		'#login h1 a { background-image: url(' . esc_url( $logo ) . '); }'
	);
}
add_action( 'login_enqueue_scripts', 'sjp_theatre_arts_login_styles' );

function sjp_theatre_arts_login_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'sjp_theatre_arts_login_headerurl' );

function sjp_theatre_arts_login_headertext() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'sjp_theatre_arts_login_headertext' );
