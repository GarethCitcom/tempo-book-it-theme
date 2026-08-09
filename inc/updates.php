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
 * briefly-cached copy: WordPress decides how often a site checks for theme
 * updates, and this only runs when it does, so one request per check is the
 * right cost.
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
 * Fetch the release manifest, briefly cached.
 *
 * The cache is deliberately short. WordPress already decides how often a site
 * asks about theme updates — twice a day on cron, hourly on the themes screen,
 * every minute on Dashboard → Updates — and this function only runs when one of
 * those checks happens. A long cache on top of that does not save requests
 * worth saving; it just means a site can be told "up to date" hours after a
 * release, which is exactly what a long cache did the first time this shipped.
 *
 * So: minutes, not hours. Enough to collapse repeat calls inside a single
 * check, and short enough that a release published a moment ago is seen by the
 * next check that runs.
 *
 * The cache holds one of three things: a manifest stamped with the theme
 * version that fetched it, the string 'unavailable' after a failed attempt (so
 * a site with no outbound access is not made to wait on a timeout at every
 * check), or nothing at all. The stamp matters — see below.
 *
 * @param bool $allow_remote Whether an expired cache may be refilled over the
 *                           network. False from any path that runs on ordinary
 *                           page loads.
 * @return array|false Manifest data, or false when none is available.
 */
function tempo_book_it_update_manifest( $allow_remote = false ) {
	$installed = (string) wp_get_theme( get_template() )->get( 'Version' );
	$cached    = get_site_transient( 'tempo_book_it_update_manifest' );

	// Only an entry written by this exact copy of the theme is trusted. One
	// left behind by an older copy carries whatever expiry that copy chose,
	// and no amount of shortening the cache here can cut it short.
	//
	// Not hypothetical: a twelve-hour entry written by 0.5.3 went on
	// answering "0.5.3 is the newest" through three later releases, and
	// through two updates of this very file, because updating the code
	// cannot reach into a cache the old code had already filled.
	if ( is_array( $cached ) && isset( $cached['for'], $cached['manifest'] )
		&& $cached['for'] === $installed && is_array( $cached['manifest'] ) ) {
		return $cached['manifest'];
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
		set_site_transient( 'tempo_book_it_update_manifest', 'unavailable', 15 * MINUTE_IN_SECONDS );

		return false;
	}

	$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] )
		|| ! tempo_book_it_update_package_allowed( $manifest['download_url'] ) ) {
		set_site_transient( 'tempo_book_it_update_manifest', 'unavailable', 15 * MINUTE_IN_SECONDS );

		return false;
	}

	set_site_transient(
		'tempo_book_it_update_manifest',
		array(
			'for'      => $installed,
			'manifest' => $manifest,
		),
		5 * MINUTE_IN_SECONDS
	);

	return $manifest;
}

/**
 * Drop the cached manifest whenever anything on the site is upgraded.
 *
 * Chiefly for this theme's own update: the moment it lands, the cache
 * describes the version that was just replaced. Firing on every upgrade
 * rather than only ours costs one HTTP request at the next check and needs no
 * guesswork about which upgrade this was.
 */
function tempo_book_it_update_flush_manifest() {
	delete_site_transient( 'tempo_book_it_update_manifest' );
}
add_action( 'upgrader_process_complete', 'tempo_book_it_update_flush_manifest' );
add_action( 'switch_theme', 'tempo_book_it_update_flush_manifest' );

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
		tempo_book_it_update_record( 'no manifest', $theme->get( 'Version' ), '' );

		return $value;
	}

	if ( version_compare( $offer['new_version'], $theme->get( 'Version' ), '>' ) ) {
		$value->response[ $stylesheet ] = $offer;
		unset( $value->no_update[ $stylesheet ] );

		tempo_book_it_update_record( 'update offered', $theme->get( 'Version' ), $offer['new_version'] );

		return $value;
	}

	// Up to date. Core still wants the record — without it the theme gets no
	// auto-update toggle on Appearance → Themes.
	$value->no_update[ $stylesheet ] = $offer;
	unset( $value->response[ $stylesheet ] );

	tempo_book_it_update_record( 'already current', $theme->get( 'Version' ), $offer['new_version'] );

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

/* -------------------------------------------------------------------------
 * Diagnostics
 *
 * An update channel that quietly does nothing is indistinguishable, from the
 * outside, from one that is working and has nothing to offer. These two
 * functions make the difference visible under Tools → Site Health → Info →
 * "Tempo Book It theme updates", so a site that is not seeing a release can
 * say which step failed instead of leaving it to guesswork.
 * ---------------------------------------------------------------------- */

/**
 * Remember what the last update check concluded.
 *
 * Written on every check, read only by the Site Health panel. The value it
 * records is the one thing no amount of poking at the outside of a site can
 * recover: whether this code ran at all, and what it decided when it did.
 *
 * @param string $decision  What happened, in words.
 * @param string $installed Version installed at the time.
 * @param string $latest    Version the manifest reported, or ''.
 */
function tempo_book_it_update_record( $decision, $installed, $latest ) {
	update_option(
		'tempo_book_it_update_last_check',
		array(
			'time'      => time(),
			'decision'  => $decision,
			'installed' => $installed,
			'latest'    => $latest,
		),
		false
	);
}

/**
 * Describe what is sitting in the manifest cache, for the panel.
 *
 * The line that matters when a site is being told the wrong thing: a check
 * reads this, not the network, so a cache saying one version while a live
 * fetch says another is the whole explanation.
 *
 * @return string
 */
function tempo_book_it_update_cache_state() {
	$cached    = get_site_transient( 'tempo_book_it_update_manifest' );
	$installed = (string) wp_get_theme( get_template() )->get( 'Version' );

	if ( 'unavailable' === $cached ) {
		return __( 'A failed fetch, remembered for a few minutes', 'tempo-book-it-theme' );
	}

	if ( ! is_array( $cached ) ) {
		return __( 'Empty — the next check fetches fresh', 'tempo-book-it-theme' );
	}

	if ( ! isset( $cached['for'], $cached['manifest']['version'] ) ) {
		return __( 'Written by an older copy of the theme, so it is ignored', 'tempo-book-it-theme' );
	}

	if ( $cached['for'] !== $installed ) {
		return sprintf(
			/* translators: %s: theme version that wrote the cache. */
			__( 'Written by version %s, so it is ignored', 'tempo-book-it-theme' ),
			$cached['for']
		);
	}

	return sprintf(
		/* translators: %s: version number held in the cache. */
		__( 'Says %s', 'tempo-book-it-theme' ),
		$cached['manifest']['version']
	);
}

/**
 * Add the update channel's state to Site Health → Info.
 *
 * Everything here is either read from the site or fetched live at the moment
 * the screen is opened, so the panel reports what a check would find now
 * rather than what one found earlier.
 *
 * @param array $info Site Health info sections.
 * @return array
 */
function tempo_book_it_update_debug_info( $info ) {
	$stylesheet = get_template();
	$theme      = wp_get_theme( $stylesheet );
	$transient  = get_site_transient( 'update_themes' );
	$last       = get_option( 'tempo_book_it_update_last_check' );

	// Fetched here rather than read from the cache: the question this panel
	// answers is whether this server can reach the manifest at all.
	$response = wp_remote_get(
		tempo_book_it_update_manifest_url(),
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		$fetch = sprintf(
			/* translators: %s: error message from the HTTP request. */
			__( 'Failed: %s', 'tempo-book-it-theme' ),
			$response->get_error_message()
		);
	} else {
		$code     = wp_remote_retrieve_response_code( $response );
		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		$fetch    = sprintf(
			/* translators: 1: HTTP status code, 2: version in the manifest, or a note that there is none. */
			__( 'HTTP %1$s, manifest version %2$s', 'tempo-book-it-theme' ),
			$code,
			is_array( $manifest ) && ! empty( $manifest['version'] ) ? $manifest['version'] : __( 'not readable', 'tempo-book-it-theme' )
		);
	}

	if ( isset( $transient->response[ $stylesheet ]['new_version'] ) ) {
		$state = sprintf(
			/* translators: %s: version number. */
			__( 'Update pending: %s', 'tempo-book-it-theme' ),
			$transient->response[ $stylesheet ]['new_version']
		);
	} elseif ( isset( $transient->no_update[ $stylesheet ] ) ) {
		$state = __( 'Listed as up to date', 'tempo-book-it-theme' );
	} else {
		$state = __( 'Not listed at all — WordPress is not tracking this theme', 'tempo-book-it-theme' );
	}

	$info['tempo-book-it-theme-updates'] = array(
		'label'  => __( 'Tempo Book It theme updates', 'tempo-book-it-theme' ),
		'fields' => array(
			'installed'  => array(
				'label' => __( 'Installed version', 'tempo-book-it-theme' ),
				'value' => $theme->get( 'Version' ) . ' (' . $stylesheet . ')',
			),
			'update_uri' => array(
				'label' => __( 'Update URI header', 'tempo-book-it-theme' ),
				'value' => $theme->get( 'UpdateURI' ) ? $theme->get( 'UpdateURI' ) : __( 'Missing — WordPress did not read the header', 'tempo-book-it-theme' ),
			),
			'hooked'     => array(
				'label' => __( 'Update check hooked', 'tempo-book-it-theme' ),
				'value' => false === has_filter( 'pre_set_site_transient_update_themes', 'tempo_book_it_check_for_update' )
					? __( 'No — the theme\'s update code is not running', 'tempo-book-it-theme' )
					: __( 'Yes', 'tempo-book-it-theme' ),
			),
			'manifest'   => array(
				'label' => __( 'Manifest', 'tempo-book-it-theme' ),
				'value' => tempo_book_it_update_manifest_url(),
			),
			'fetch'      => array(
				'label' => __( 'Manifest fetched just now', 'tempo-book-it-theme' ),
				'value' => $fetch,
			),
			'cache'      => array(
				'label' => __( 'Cached manifest', 'tempo-book-it-theme' ),
				'value' => tempo_book_it_update_cache_state(),
			),
			'last_check' => array(
				'label' => __( 'Last update check', 'tempo-book-it-theme' ),
				'value' => is_array( $last )
					? sprintf(
						/* translators: 1: how long ago, 2: what was decided, 3: installed version, 4: version found. */
						__( '%1$s ago — %2$s (installed %3$s, found %4$s)', 'tempo-book-it-theme' ),
						human_time_diff( $last['time'] ),
						$last['decision'],
						$last['installed'],
						'' === $last['latest'] ? '—' : $last['latest']
					)
					: __( 'Never — no check has reached this theme', 'tempo-book-it-theme' ),
			),
			'transient'  => array(
				'label' => __( 'In the WordPress update list', 'tempo-book-it-theme' ),
				'value' => $state,
			),
		),
	);

	return $info;
}
add_filter( 'debug_information', 'tempo_book_it_update_debug_info' );
