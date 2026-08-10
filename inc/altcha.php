<?php

/**
 * Proof-of-work challenge for the unauthenticated authentication forms.
 *
 * ALTCHA asks the visitor's browser to find a number that hashes to a value we
 * chose in advance. A person waits about a second for it; a bot pays that cost
 * on every attempt, which is what makes bulk submissions expensive. Nothing
 * leaves the site: the challenge is minted and checked here, so no visitor data
 * reaches a third party.
 *
 * The widget bundle in assets/js/vendor is the official ALTCHA client. Its
 * challenge format is documented as "v1": we hand it a challenge object, it
 * returns a base64-encoded payload in a hidden field named "altcha".
 *
 * @package tempo-book-it-theme
 */

defined('ABSPATH') || exit;

/**
 * Key used to sign challenges, derived so the site salt is never exposed.
 *
 * @return string
 */
function tempo_book_it_altcha_hmac_key()
{
	return hash_hmac('sha256', 'tempo-book-it-altcha', wp_salt('auth'));
}

/**
 * Mint a challenge.
 *
 * The answer is a number the browser has to find by hashing; the signature lets
 * us confirm on submission that we are the ones who set the puzzle, without
 * storing anything in the meantime. The expiry travels inside the salt, which
 * the client echoes back verbatim.
 *
 * @return array
 */
function tempo_book_it_altcha_challenge()
{
	/**
	 * Filter the upper bound on the answer, i.e. how much work solving costs.
	 *
	 * @param int $maxnumber Highest possible answer.
	 */
	$maxnumber = max(1000, (int) apply_filters('tempo_book_it_altcha_maxnumber', 100000));

	/**
	 * Filter how long a challenge stays valid.
	 *
	 * @param int $ttl Lifetime in seconds.
	 */
	$ttl = max(MINUTE_IN_SECONDS, (int) apply_filters('tempo_book_it_altcha_expiry', 10 * MINUTE_IN_SECONDS));

	$salt      = bin2hex(random_bytes(12)) . '?expires=' . (time() + $ttl);
	$number    = random_int(0, $maxnumber);
	$challenge = hash('sha256', $salt . $number);

	return array(
		'algorithm' => 'SHA-256',
		'challenge' => $challenge,
		'maxnumber' => $maxnumber,
		'salt'      => $salt,
		'signature' => hash_hmac('sha256', $challenge, tempo_book_it_altcha_hmac_key()),
	);
}

/**
 * Check a submitted solution.
 *
 * @param string $payload Base64-encoded JSON payload from the widget.
 * @return bool Whether the solution is genuine, unexpired and unused.
 */
function tempo_book_it_altcha_verify($payload)
{
	/**
	 * Short-circuit verification, e.g. to delegate to an ALTCHA Sentinel server.
	 *
	 * Return a boolean to decide the outcome, or null to verify locally.
	 *
	 * @param bool|null $result  Verification outcome.
	 * @param string    $payload Raw payload from the widget.
	 */
	$override = apply_filters('tempo_book_it_altcha_verify_override', null, $payload);
	if (is_bool($override)) {
		return $override;
	}

	if (! is_string($payload) || '' === $payload || strlen($payload) > 2048) {
		return false;
	}

	$json = base64_decode($payload, true);
	if (false === $json) {
		return false;
	}
	$data = json_decode($json, true);
	if (! is_array($data)) {
		return false;
	}
	foreach (array('algorithm', 'challenge', 'salt', 'signature') as $field) {
		if (! isset($data[$field]) || ! is_string($data[$field])) {
			return false;
		}
	}
	if (! isset($data['number']) || ! preg_match('/^\d{1,10}$/', (string) $data['number'])) {
		return false;
	}
	if ('SHA-256' !== $data['algorithm']) {
		return false;
	}

	$separator = strpos($data['salt'], '?');
	if (false === $separator) {
		return false;
	}
	$params = array();
	parse_str(substr($data['salt'], $separator + 1), $params);
	if (! isset($params['expires']) || ! is_numeric($params['expires'])) {
		return false;
	}
	$expires = (int) $params['expires'];
	if ($expires <= time()) {
		return false;
	}

	if (! hash_equals($data['challenge'], hash('sha256', $data['salt'] . $data['number']))) {
		return false;
	}
	if (! hash_equals(hash_hmac('sha256', $data['challenge'], tempo_book_it_altcha_hmac_key()), $data['signature'])) {
		return false;
	}

	// A solved challenge is worth replaying, so spend it. The marker only has to
	// outlive the challenge itself.
	$used_key = 'tempo_altcha_used_' . substr(hash('sha256', $data['challenge']), 0, 32);
	if (get_transient($used_key)) {
		return false;
	}
	set_transient($used_key, 1, max(1, $expires - time()));

	return true;
}

/**
 * Verify the solution submitted with the current request.
 *
 * @return bool
 */
function tempo_book_it_check_altcha()
{
	$payload = isset($_POST['altcha']) ? wp_unslash($_POST['altcha']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify their own nonce; this only reads the proof-of-work payload.
	return tempo_book_it_altcha_verify(is_string($payload) ? $payload : '');
}

/**
 * Whether the sign-in form should carry a challenge.
 *
 * Sign-in is the one form real members use every day, so it stays frictionless
 * until a connection starts guessing. Registration and password recovery carry
 * a challenge on every request — they are rare enough for members that the
 * extra second costs nothing, and they are what bots go after.
 *
 * Both the form and the submission handler ask this same question, so what is
 * rendered and what is enforced cannot drift apart.
 *
 * @return bool
 */
function tempo_book_it_altcha_required_for_login()
{
	/**
	 * Filter how many failed attempts from an address trigger the challenge.
	 *
	 * @param int $threshold Number of recent failures.
	 */
	$threshold = max(1, (int) apply_filters('tempo_book_it_login_captcha_threshold', 2));
	$failures  = tempo_book_it_rate_limit_count(tempo_book_it_rate_limit_key('login_ip', tempo_book_it_client_ip()));

	return $failures >= $threshold;
}

/**
 * Render the widget inside a form, immediately before its submit button.
 */
function tempo_book_it_altcha_field()
{
	/**
	 * Filter a URL the widget should fetch challenges from.
	 *
	 * Left empty the challenge is minted here and embedded in the page, which
	 * needs no extra service. Point this at an ALTCHA Sentinel endpoint to hand
	 * challenge issuing over to it, and pair it with
	 * tempo_book_it_altcha_verify_override to check the answers there too.
	 *
	 * @param string $url Challenge endpoint.
	 */
	$challenge_url = (string) apply_filters('tempo_book_it_altcha_challengeurl', '');

	// An embedded challenge is minted when the page renders, so a form left open
	// longer than the challenge lifetime fails once; the reloaded form then
	// carries a fresh one.
	$challenge = $challenge_url ? esc_url($challenge_url) : wp_json_encode(tempo_book_it_altcha_challenge());
?>
	<div class="tempo-field tempo-field--altcha">
		<altcha-widget auto="onsubmit" challenge="<?php echo esc_attr($challenge); ?>"></altcha-widget>
		<noscript>
			<p class="tempo-field__hint"><?php esc_html_e('JavaScript must be turned on to complete the security check and submit this form.', 'tempo-book-it-theme'); ?></p>
		</noscript>
	</div>
<?php
}

/**
 * Load the widget on the authentication screens only.
 */
function tempo_book_it_enqueue_altcha()
{
	$path    = 'assets/js/vendor/altcha.min.js';
	$file    = get_theme_file_path($path);
	$version = file_exists($file) ? (string) filemtime($file) : wp_get_theme()->get('Version');

	wp_enqueue_script(
		'tempo-book-it-theme-altcha',
		get_theme_file_uri($path),
		array(),
		$version,
		true
	);
}
add_action('login_enqueue_scripts', 'tempo_book_it_enqueue_altcha');
