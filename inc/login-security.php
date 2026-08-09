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

/* -------------------------------------------------------------------------
 * Sign-in brute-force throttle
 * ---------------------------------------------------------------------- */

/**
 * Attempt allowances for the sign-in endpoint.
 *
 * The per-address limit is the looser of the two so that a shared office or
 * school connection is not locked out by one forgetful member; the per-account
 * limit catches an attack spread over many addresses against a single account.
 *
 * @return array{ip_limit:int,user_limit:int,window:int}
 */
function tempo_book_it_login_limits()
{
	return array(
		'ip_limit'   => max(1, (int) apply_filters('tempo_book_it_login_ip_attempt_limit', 10)),
		'user_limit' => max(1, (int) apply_filters('tempo_book_it_login_user_attempt_limit', 5)),
		'window'     => max(MINUTE_IN_SECONDS, (int) apply_filters('tempo_book_it_login_attempt_window', 15 * MINUTE_IN_SECONDS)),
	);
}

/**
 * Fold a submitted login into the form used for per-account counters.
 *
 * @param string $username Submitted username or email address.
 * @return string
 */
function tempo_book_it_normalize_login($username)
{
	return strtolower(trim((string) $username));
}

/**
 * Refuse authentication once too many recent attempts have failed.
 *
 * Priority 21 matters. Core registers wp_authenticate_username_password,
 * wp_authenticate_email_password and wp_authenticate_application_password at 20,
 * wp_authenticate_cookie at 30 and the spam check at 99. The credential handlers
 * discard an incoming WP_Error whenever both credentials are filled in, so a
 * throttle placed before them is silently overridden the moment an attacker
 * guesses correctly — exactly the case it exists to stop. Running at 21 puts it
 * after those handlers, whose result it may replace, and before the cookie and
 * spam handlers, which pass a WP_Error through untouched.
 *
 * One password hash is still computed for a request that is about to be
 * refused; blocking the attempt authoritatively is worth that cost. While a
 * lockout is in force even correct credentials are rejected.
 *
 * @param null|WP_User|WP_Error $user     Result from earlier handlers.
 * @param string                $username Submitted username or email address.
 * @param string                $password Submitted password.
 * @return null|WP_User|WP_Error
 */
function tempo_book_it_throttle_authenticate($user, $username, $password)
{
	if ('' === trim((string) $username)) {
		return $user;
	}

	// REST clients authenticate through determine_current_user() rather than
	// wp_authenticate(), so their failures never reach our counters. Skipping
	// them here keeps an application password from being locked out by a
	// plugin that routes REST authentication through this filter anyway.
	$serving_rest = function_exists('wp_is_serving_rest_request')
		? wp_is_serving_rest_request()
		: (defined('REST_REQUEST') && REST_REQUEST);

	/**
	 * Filter whether the sign-in throttle should stand aside for this request.
	 *
	 * @param bool   $bypass   Whether to skip the throttle.
	 * @param string $username Submitted username or email address.
	 */
	if (apply_filters('tempo_book_it_login_throttle_bypass', $serving_rest, $username)) {
		return $user;
	}

	$limits   = tempo_book_it_login_limits();
	$ip_key   = tempo_book_it_rate_limit_key('login_ip', tempo_book_it_client_ip());
	$user_key = tempo_book_it_rate_limit_key('login_user', tempo_book_it_normalize_login($username));

	if (
		tempo_book_it_rate_limit_count($ip_key) >= $limits['ip_limit']
		|| tempo_book_it_rate_limit_count($user_key) >= $limits['user_limit']
	) {
		return new WP_Error(
			'tempo_too_many_attempts',
			sprintf(
				/* translators: %d: number of minutes the sign-in lockout lasts. */
				__('Too many failed sign-in attempts. Please wait %d minutes and try again.', 'tempo-book-it-theme'),
				(int) ceil($limits['window'] / MINUTE_IN_SECONDS)
			)
		);
	}

	return $user;
}
add_filter('authenticate', 'tempo_book_it_throttle_authenticate', 21, 3);

/**
 * Count a failed sign-in against the address and the account.
 *
 * Attempts refused by the throttle itself are ignored. Counting them would let
 * a bot that keeps knocking hold the window open indefinitely, which punishes
 * the legitimate members sharing that address rather than the bot.
 *
 * @param string        $username Submitted username or email address.
 * @param WP_Error|null $error    Reason the attempt failed.
 */
function tempo_book_it_record_login_failure($username, $error = null)
{
	if ('' === trim((string) $username)) {
		return;
	}
	if ($error instanceof WP_Error && in_array('tempo_too_many_attempts', $error->get_error_codes(), true)) {
		return;
	}

	$limits = tempo_book_it_login_limits();
	tempo_book_it_rate_limit_hit(tempo_book_it_rate_limit_key('login_ip', tempo_book_it_client_ip()), $limits['window']);
	tempo_book_it_rate_limit_hit(tempo_book_it_rate_limit_key('login_user', tempo_book_it_normalize_login($username)), $limits['window']);
}
add_action('wp_login_failed', 'tempo_book_it_record_login_failure', 10, 2);

/**
 * Clear the counters once someone signs in successfully.
 *
 * @param string  $user_login Login of the user who signed in.
 * @param WP_User $user       The user who signed in.
 */
function tempo_book_it_clear_login_failures($user_login, $user = null)
{
	tempo_book_it_rate_limit_clear(tempo_book_it_rate_limit_key('login_user', tempo_book_it_normalize_login($user_login)));
	tempo_book_it_rate_limit_clear(tempo_book_it_rate_limit_key('login_ip', tempo_book_it_client_ip()));
}
add_action('wp_login', 'tempo_book_it_clear_login_failures', 10, 2);

/* -------------------------------------------------------------------------
 * Password recovery
 * ---------------------------------------------------------------------- */

/**
 * Limit how often password reset emails can be requested.
 *
 * Without a limit the recovery form is a way to bomb someone's inbox and to
 * burn the site's sending reputation. Core fires this action inside
 * retrieve_password() before any mail goes out and abandons the request when
 * the error object is not empty, so adding an error here is enough to stop the
 * email — for the theme route and for wp-login.php alike.
 *
 * @param WP_Error         $errors    Errors gathered so far.
 * @param WP_User|WP_Error $user_data The account the request resolved to.
 */
function tempo_book_it_limit_lostpassword($errors, $user_data = false)
{
	if (! $errors instanceof WP_Error) {
		return;
	}
	if (in_array('tempo_too_many_attempts', $errors->get_error_codes(), true)) {
		return;
	}

	$message = __('Too many password reset requests. Please wait a while and try again.', 'tempo-book-it-theme');

	$ip_limit  = max(1, (int) apply_filters('tempo_book_it_lostpassword_ip_limit', 5));
	$ip_window = max(MINUTE_IN_SECONDS, (int) apply_filters('tempo_book_it_lostpassword_window', 15 * MINUTE_IN_SECONDS));
	$ip_key    = tempo_book_it_rate_limit_key('lost_ip', tempo_book_it_client_ip());
	if (tempo_book_it_rate_limit_count($ip_key) >= $ip_limit) {
		$errors->add('tempo_too_many_attempts', $message);
		return;
	}

	$user_key = '';
	if ($user_data instanceof WP_User) {
		$user_limit  = max(1, (int) apply_filters('tempo_book_it_lostpassword_user_limit', 3));
		$user_window = max(MINUTE_IN_SECONDS, (int) apply_filters('tempo_book_it_lostpassword_user_window', HOUR_IN_SECONDS));
		$user_key    = tempo_book_it_rate_limit_key('lost_user', (string) $user_data->ID);
		if (tempo_book_it_rate_limit_count($user_key) >= $user_limit) {
			$errors->add('tempo_too_many_attempts', $message);
			return;
		}
	}

	tempo_book_it_rate_limit_hit($ip_key, $ip_window);
	if ($user_key) {
		tempo_book_it_rate_limit_hit($user_key, $user_window);
	}
}
add_action('lostpassword_post', 'tempo_book_it_limit_lostpassword', 10, 2);

/* -------------------------------------------------------------------------
 * Password policy
 * ---------------------------------------------------------------------- */

/**
 * Shortest password members may choose.
 *
 * The reset form quotes this number and the check below enforces it, so the
 * two cannot drift apart.
 *
 * @return int
 */
function tempo_book_it_password_min_length()
{
	/**
	 * Filter the shortest password members may choose.
	 *
	 * @param int $min Minimum number of characters.
	 */
	return max(1, (int) apply_filters('tempo_book_it_password_min_length', 12));
}

/**
 * Hold new passwords to the standard the reset form advertises.
 *
 * The form has always asked for at least twelve characters; nothing checked.
 * Core fires this action for the theme route and for wp-login.php, so the rule
 * applies wherever a password is chosen. Length is the requirement that
 * actually resists guessing, so there is no character-class rule to push people
 * towards a short password with a symbol stuck on the end.
 *
 * @param WP_Error $errors Errors gathered so far.
 * @param WP_User  $user   Account the password belongs to.
 */
function tempo_book_it_validate_password_reset($errors, $user = null)
{
	if (! $errors instanceof WP_Error) {
		return;
	}

	// The action also fires while the form is merely being displayed, and the
	// empty case already has its own message.
	$password = isset($_POST['pass1']) ? trim((string) wp_unslash($_POST['pass1'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the reset handlers verify their own nonce before this runs.
	if ('' === $password) {
		return;
	}

	$min = tempo_book_it_password_min_length();
	if (strlen($password) < $min) {
		$errors->add(
			'tempo_password_too_short',
			sprintf(
				/* translators: %d: minimum number of characters in a password. */
				__('Use at least %d characters with a mix of words, numbers and symbols.', 'tempo-book-it-theme'),
				$min
			)
		);
	}

	if ($user instanceof WP_User) {
		$lowered = strtolower($password);
		if ($lowered === strtolower($user->user_login) || $lowered === strtolower($user->user_email)) {
			$errors->add(
				'tempo_password_matches_identity',
				__('Your password cannot be the same as your username or email address.', 'tempo-book-it-theme')
			);
		}
	}
}
add_action('validate_password_reset', 'tempo_book_it_validate_password_reset', 10, 2);

/* -------------------------------------------------------------------------
 * XML-RPC
 * ---------------------------------------------------------------------- */

/**
 * Turn off XML-RPC.
 *
 * It is an unthrottled credential-guessing endpoint that nothing on a member
 * portal needs, and system.multicall lets an attacker test many passwords in a
 * single request. This closes the authenticated methods; pingback methods stay
 * callable, which is a separate concern.
 *
 * @param bool $enabled Whether core considers XML-RPC available.
 * @return bool
 */
function tempo_book_it_xmlrpc_enabled($enabled)
{
	/**
	 * Filter whether XML-RPC stays available.
	 *
	 * @param bool $enabled Whether to allow XML-RPC.
	 */
	return (bool) apply_filters('tempo_book_it_xmlrpc_enabled', false);
}
add_filter('xmlrpc_enabled', 'tempo_book_it_xmlrpc_enabled');

/**
 * Stop advertising the XML-RPC endpoint.
 *
 * @param array $headers Response headers.
 * @return array
 */
function tempo_book_it_remove_pingback_header($headers)
{
	unset($headers['X-Pingback']);
	return $headers;
}
add_filter('wp_headers', 'tempo_book_it_remove_pingback_header');
