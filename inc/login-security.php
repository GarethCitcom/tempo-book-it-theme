<?php

/**
 * Authentication hardening: rate limiting, brute-force throttling, and
 * user-enumeration protections.
 *
 * The theme owns the public sign-in route (see inc/custom-login.php), but
 * WordPress core keeps its own entry points — wp-login.php POSTs, XML-RPC and
 * WooCommerce forms all reach wp_signon() directly. Protections here therefore
 * hook the core authentication filters rather than the theme route, so they
 * apply no matter which door a request comes through.
 *
 * @package tempo-book-it-theme
 */

defined('ABSPATH') || exit;

/* -------------------------------------------------------------------------
 * Shared rate-limit primitives
 * ---------------------------------------------------------------------- */

/**
 * The requesting client's address.
 *
 * REMOTE_ADDR is used as-is. It cannot be spoofed while PHP talks to the client
 * directly, but it reports the proxy address on sites behind a CDN or reverse
 * proxy. Such sites must supply the real client address through the filter —
 * X-Forwarded-For is attacker-controlled and is deliberately not read here.
 *
 * @return string
 */
function tempo_book_it_client_ip()
{
	$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';

	/**
	 * Filter the address used to key rate limits.
	 *
	 * @param string $ip Client address.
	 */
	return (string) apply_filters('tempo_book_it_client_ip', $ip);
}

/**
 * Build a transient key for a rate-limit counter.
 *
 * The value is salted and hashed, so raw addresses and usernames are never
 * written to the database.
 *
 * @param string $scope Short counter name, e.g. login_ip, login_user, reg_ip.
 * @param string $value Address, username or user ID the counter belongs to.
 * @return string
 */
function tempo_book_it_rate_limit_key($scope, $value)
{
	return 'tempo_rl_' . $scope . '_' . substr(hash_hmac('sha256', (string) $value, wp_salt('nonce')), 0, 32);
}

/**
 * Read a counter without recording an attempt.
 *
 * @param string $key Counter key.
 * @return int
 */
function tempo_book_it_rate_limit_count($key)
{
	return (int) get_transient($key);
}

/**
 * Record an attempt and return the new count.
 *
 * Storing the counter refreshes its lifetime, so a run of attempts keeps the
 * window open until the client pauses for the full period. Counters live in
 * transients: with a persistent object cache they may be evicted early, which
 * fails open by design.
 *
 * @param string $key    Counter key.
 * @param int    $window Seconds the counter should live for.
 * @return int
 */
function tempo_book_it_rate_limit_hit($key, $window)
{
	$count = tempo_book_it_rate_limit_count($key) + 1;
	set_transient($key, $count, $window);
	return $count;
}

/**
 * Forget a counter.
 *
 * @param string $key Counter key.
 */
function tempo_book_it_rate_limit_clear($key)
{
	delete_transient($key);
}
