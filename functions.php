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
}
add_action( 'after_setup_theme', 'tempo_studio_manager_setup' );

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
 * @param string $variant 'default' (colour, for white surfaces) or 'white'.
 */
function tempo_logo_url( $variant = 'default' ) {
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
function tempo_vocab( $word ) {
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

/** URL of the page hosting [dsb_booking], auto-detected. */
function tempo_book_url() {
	return apply_filters(
		'tempo_studio_manager_book_url',
		tempo_studio_manager_shortcode_page_url( 'dsb_booking', home_url( '/book-classes/' ) )
	);
}

/** URL of the page hosting [dsb_register] (teacher day view), auto-detected. */
function tempo_my_classes_url() {
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
		$overrides['brand-primary']        = $primary;
		$overrides['brand-tint-primary']   = tempo_studio_manager_tint( $primary, 0.09 );
	}
	if ( $secondary ) {
		$overrides['brand-secondary']      = $secondary;
		$overrides['brand-tint-secondary'] = tempo_studio_manager_tint( $secondary, 0.05 );
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
 * Login page branding (wp-login.php styled with the design-system tokens)
 * ---------------------------------------------------------------------- */

function tempo_studio_manager_login_styles() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'tempo-studio-manager-login',
		get_theme_file_uri( 'assets/css/login.css' ),
		array(),
		$version
	);
	$logo   = tempo_logo_url();
	$inline = '#login h1 a { background-image: url(' . esc_url( $logo ) . '); }';

	// Tenant brand colours from plugin settings (login.css falls back to the defaults).
	if ( function_exists( 'dsb_brand_colour' ) ) {
		$primary   = sanitize_hex_color( dsb_brand_colour( 'primary' ) );
		$secondary = sanitize_hex_color( dsb_brand_colour( 'secondary' ) );
		$vars      = '';
		if ( $primary ) {
			$vars .= '--tempo-brand-primary:' . $primary . ';';
		}
		if ( $secondary ) {
			$vars .= '--tempo-brand-secondary:' . $secondary . ';';
		}
		if ( $vars ) {
			$inline .= ' body.login {' . $vars . '}';
		}
	}

	wp_add_inline_style( 'tempo-studio-manager-login', $inline );
}
add_action( 'login_enqueue_scripts', 'tempo_studio_manager_login_styles' );

function tempo_studio_manager_login_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'tempo_studio_manager_login_headerurl' );

function tempo_studio_manager_login_headertext() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'tempo_studio_manager_login_headertext' );
