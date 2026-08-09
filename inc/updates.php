<?php
/**
 * Self-hosted theme updates.
 *
 * The theme is not on wordpress.org, so core would never offer an update for
 * it — and, worse, could one day offer somebody else's theme that happens to
 * share the slug. The `Update URI` header in style.css closes that door: core
 * skips the wordpress.org check for a theme that declares one and asks a
 * hostname-specific filter instead.
 *
 * The source of truth is a small JSON manifest published as an asset on every
 * GitHub release, reached through the `releases/latest/download/` URL that
 * always resolves to the newest one. Both entry points below read the same
 * cached copy, so a site makes at most one request every twelve hours.
 *
 * Two entry points, deliberately:
 *
 * - `pre_set_site_transient_update_themes` runs on every themes update check,
 *   whatever WordPress makes of the header, and writes the offer straight into
 *   the transient core reads. This is the one that does the work.
 * - `update_themes_github.com` is core's own route for a theme with an
 *   `Update URI`. It costs three lines and keeps the theme correct if core
 *   ever stops populating the transient the older way.
 *
 * Both are silent on failure: an unreachable manifest means no update offer,
 * never a broken admin screen.
 *
 * @package tempo-book-it-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where the release manifest lives.
 *
 * Filterable so a tenant running their own mirror — or the plugin, if it ever
 * takes over the update channel — can repoint it without editing the theme.
 * Note that core's own route is bound to the host in the `Update URI` header,
 * so a manifest moved off github.com is served by the transient filter alone.
 *
 * @return string
 */
function tempo_book_it_update_manifest_url() {
	return apply_filters(
		'tempo_book_it_update_manifest_url',
		'https://github.com/GarethCitcom/tempo-book-it-theme/releases/latest/download/update.json'
	);
}

/**
 * Fetch the release manifest, cached for twelve hours.
 *
 * The cache holds one of three things: the decoded manifest, the string
 * 'unavailable' after a failed attempt (so a site with no outbound access
 * retries hourly rather than on every check), or nothing at all.
 *
 * @param bool $allow_remote Whether an expired cache may be refilled over the
 *                           network. False from any path that runs on ordinary
 *                           page loads.
 * @return array|false Manifest data, or false when none is available.
 */
function tempo_book_it_update_manifest( $allow_remote = false ) {
	$cached = get_site_transient( 'tempo_book_it_update_manifest' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	// A recent failure, or a caller that must not make requests.
	if ( 'unavailable' === $cached || ! $allow_remote ) {
		return false;
	}

	$response = wp_remote_get(
		tempo_book_it_update_manifest_url(),
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		set_site_transient( 'tempo_book_it_update_manifest', 'unavailable', HOUR_IN_SECONDS );

		return false;
	}

	$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] )
		|| ! tempo_book_it_update_package_allowed( $manifest['download_url'] ) ) {
		set_site_transient( 'tempo_book_it_update_manifest', 'unavailable', HOUR_IN_SECONDS );

		return false;
	}

	set_site_transient( 'tempo_book_it_update_manifest', $manifest, 12 * HOUR_IN_SECONDS );

	return $manifest;
}

/**
 * Whether a package URL may be handed to the WordPress upgrader.
 *
 * The manifest names the zip WordPress will download and unpack over the live
 * theme, so it is checked rather than trusted: HTTPS only, and only from the
 * hosts the releases actually come from. A mirror adds its own host through
 * the filter.
 *
 * @param string $url Package URL from the manifest.
 * @return bool
 */
function tempo_book_it_update_package_allowed( $url ) {
	$parts = wp_parse_url( $url );

	if ( empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] || empty( $parts['host'] ) ) {
		return false;
	}

	$hosts = apply_filters(
		'tempo_book_it_update_package_hosts',
		array( 'github.com', 'githubusercontent.com' )
	);

	foreach ( $hosts as $host ) {
		if ( $parts['host'] === $host || substr( $parts['host'], - strlen( '.' . $host ) ) === '.' . $host ) {
			return true;
		}
	}

	return false;
}

/**
 * Build the update record core expects, from the manifest.
 *
 * Version comparison is left to the caller: the transient filter sorts an
 * offer into `response` or `no_update` itself, while core does that for the
 * `Update URI` route.
 *
 * Both `version` and `new_version` carry the same value on purpose. Core's
 * `Update URI` filter documents the first; the update transient is consumed
 * elsewhere under the second, including by the Dashboard → Updates screen.
 *
 * @param WP_Theme $theme        The installed theme.
 * @param bool     $allow_remote Whether the manifest may be fetched now.
 * @return array|false
 */
function tempo_book_it_update_offer( $theme, $allow_remote = false ) {
	$manifest = tempo_book_it_update_manifest( $allow_remote );

	if ( ! $manifest ) {
		return false;
	}

	$version = preg_replace( '/[^0-9A-Za-z.\-+]/', '', (string) $manifest['version'] );

	if ( '' === $version ) {
		return false;
	}

	$offer = array(
		'id'          => $theme->get( 'UpdateURI' ),
		'theme'       => $theme->get_stylesheet(),
		'version'     => $version,
		'new_version' => $version,
		'url'         => isset( $manifest['url'] ) ? esc_url_raw( $manifest['url'] ) : '',
		'package'     => esc_url_raw( $manifest['download_url'] ),
	);

	foreach ( array( 'requires', 'requires_php', 'tested' ) as $key ) {
		if ( ! empty( $manifest[ $key ] ) ) {
			$offer[ $key ] = sanitize_text_field( $manifest[ $key ] );
		}
	}

	return $offer;
}

/**
 * Put the offer into the update transient core reads.
 *
 * Runs on every themes update check — twice-daily cron, and whenever someone
 * hits "Check again" on Dashboard → Updates, which is also the moment to
 * bypass the manifest cache so a just-published release shows up immediately.
 *
 * The theme is looked up by template rather than stylesheet: with a child
 * theme active it is still this theme that gets updated.
 *
 * @param mixed $value The `update_themes` transient being written.
 * @return mixed
 */
function tempo_book_it_check_for_update( $value ) {
	if ( ! is_object( $value ) ) {
		return $value;
	}

	$stylesheet = get_template();
	$theme      = wp_get_theme( $stylesheet );

	if ( ! $theme->exists() ) {
		return $value;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core's own flag on the updates screen, read only to skip a cache.
	if ( ! empty( $_GET['force-check'] ) ) {
		delete_site_transient( 'tempo_book_it_update_manifest' );
	}

	$offer = tempo_book_it_update_offer( $theme, true );

	if ( ! $offer ) {
		return $value;
	}

	if ( version_compare( $offer['new_version'], $theme->get( 'Version' ), '>' ) ) {
		$value->response[ $stylesheet ] = $offer;
		unset( $value->no_update[ $stylesheet ] );

		return $value;
	}

	// Up to date. Core still wants the record — without it the theme gets no
	// auto-update toggle on Appearance → Themes.
	$value->no_update[ $stylesheet ] = $offer;
	unset( $value->response[ $stylesheet ] );

	return $value;
}
add_filter( 'pre_set_site_transient_update_themes', 'tempo_book_it_check_for_update' );

/**
 * Answer core's own update route for themes carrying an `Update URI`.
 *
 * The hook name comes from the host in that header, so this fires for every
 * github.com-hosted theme on the site — hence the guard. Core compares the
 * returned version against the installed one itself.
 *
 * @param array|false $update           Update offer so far.
 * @param array       $theme_data       Theme headers.
 * @param string      $theme_stylesheet Directory name of the theme being checked.
 * @return array|false
 */
function tempo_book_it_update_uri_response( $update, $theme_data, $theme_stylesheet ) {
	if ( get_template() !== $theme_stylesheet ) {
		return $update;
	}

	$offer = tempo_book_it_update_offer( wp_get_theme( $theme_stylesheet ), true );

	return $offer ? $offer : $update;
}
add_filter( 'update_themes_github.com', 'tempo_book_it_update_uri_response', 10, 3 );
