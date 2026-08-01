<?php

/**
 * Theme-owned sign-in and password recovery experience.
 *
 * Authentication, cookies and password resets still go through WordPress core;
 * this file only replaces the public route and presentation.
 *
 * @package tempo-book-it-theme
 */

defined('ABSPATH') || exit;

/** The public path for the theme's authentication screen. */
function tempo_book_it_login_path()
{
	return (string) apply_filters('tempo_book_it_login_path', '/sign-in/');
}

/**
 * Build a URL for one of the custom authentication views.
 *
 * @param string $redirect_to Destination after a successful sign-in.
 * @param string $action      login|register|registered|lostpassword|checkemail|rp|resetpass|resetcomplete.
 */
function tempo_book_it_login_url($redirect_to = '', $action = 'login')
{
	$url = home_url(tempo_book_it_login_path());
	if ('login' !== $action) {
		$url = add_query_arg('action', $action, $url);
	}
	if ($redirect_to) {
		$url = add_query_arg('redirect_to', $redirect_to, $url);
	}
	return $url;
}

/** Whether the current request points at the custom sign-in route. */
function tempo_book_it_is_login_request()
{
	$request_path = isset($_SERVER['REQUEST_URI'])
		? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		: '';
	$login_path = (string) wp_parse_url(tempo_book_it_login_url(), PHP_URL_PATH);

	return untrailingslashit($request_path) === untrailingslashit($login_path);
}

/** Keep WordPress-generated login links inside the theme experience. */
function tempo_book_it_filter_login_url($login_url, $redirect, $force_reauth)
{
	$url = tempo_book_it_login_url($redirect);
	if ($force_reauth) {
		$url = add_query_arg('reauth', '1', $url);
	}
	return $url;
}
add_filter('login_url', 'tempo_book_it_filter_login_url', 10, 3);

/** Keep WordPress-generated password recovery links inside the theme. */
function tempo_book_it_filter_lostpassword_url($lostpassword_url, $redirect)
{
	return tempo_book_it_login_url($redirect, 'lostpassword');
}
add_filter('lostpassword_url', 'tempo_book_it_filter_lostpassword_url', 10, 2);

/** Keep WordPress-generated registration links inside the theme experience. */
function tempo_book_it_filter_register_url($register_url = '')
{
	return tempo_book_it_login_url('', 'register');
}
add_filter('register_url', 'tempo_book_it_filter_register_url');

/** Whether the companion plugin can safely create member accounts. */
function tempo_book_it_registration_available()
{
	return function_exists('dsb_registration_available') && dsb_registration_available();
}

/** Sanitized values retained when the registration form needs correction. */
function tempo_book_it_registration_values()
{
	$post = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- values only; submission nonce is checked by the caller.
	return array(
		'account_type'       => sanitize_key($post['account_type'] ?? 'student'),
		'student_first_name' => sanitize_text_field($post['student_first_name'] ?? ''),
		'student_last_name'  => sanitize_text_field($post['student_last_name'] ?? ''),
		'student_email'      => sanitize_email($post['student_email'] ?? ''),
		'parent_first_name'  => sanitize_text_field($post['parent_first_name'] ?? ''),
		'parent_last_name'   => sanitize_text_field($post['parent_last_name'] ?? ''),
		'parent_email'       => sanitize_email($post['parent_email'] ?? ''),
		'parent_mobile'      => sanitize_text_field($post['parent_mobile'] ?? ''),
		'relationship'       => sanitize_text_field($post['relationship'] ?? ''),
	);
}

/**
 * Small abuse-control window for the unauthenticated registration endpoint.
 * The remote address is salted and hashed; the raw address is never stored.
 */
function tempo_book_it_registration_attempt_allowed()
{
	$limit  = max(1, (int) apply_filters('tempo_book_it_registration_attempt_limit', 5));
	$window = max(MINUTE_IN_SECONDS, (int) apply_filters('tempo_book_it_registration_attempt_window', 15 * MINUTE_IN_SECONDS));
	$remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
	$key    = 'tempo_reg_' . substr(hash_hmac('sha256', $remote, wp_salt('nonce')), 0, 32);
	$count  = (int) get_transient($key);
	if ($count >= $limit) {
		return false;
	}
	set_transient($key, $count + 1, $window);
	return true;
}

/**
 * Replace the wp-login.php reset URL in WordPress's standard recovery email.
 */
function tempo_book_it_retrieve_password_message($message, $key, $user_login, $user_data)
{
	$locale   = get_user_locale($user_data);
	$core_url = network_site_url(
		'wp-login.php?login=' . rawurlencode($user_login) . '&key=' . $key . '&action=rp',
		'login'
	) . '&wp_lang=' . $locale;
	$theme_url = add_query_arg(
		array(
			'action'  => 'rp',
			'key'     => $key,
			'login'   => $user_login,
			'wp_lang' => $locale,
		),
		tempo_book_it_login_url()
	);

	return str_replace($core_url, $theme_url, $message);
}
add_filter('retrieve_password_message', 'tempo_book_it_retrieve_password_message', 10, 4);

/** Theme assets for the custom route and a branded fallback on wp-login.php. */
function tempo_book_it_login_styles()
{
	$version = wp_get_theme()->get('Version');
	wp_enqueue_style(
		'tempo-book-it-theme-login',
		get_theme_file_uri('assets/css/login.css'),
		array(),
		$version
	);
	wp_enqueue_script(
		'tempo-book-it-theme-login',
		get_theme_file_uri('assets/js/login.js'),
		array(),
		$version,
		true
	);

	$primary   = function_exists('dsb_brand_colour') ? sanitize_hex_color(dsb_brand_colour('primary')) : '';
	$secondary = function_exists('dsb_brand_colour') ? sanitize_hex_color(dsb_brand_colour('secondary')) : '';
	$primary   = $primary ?: tempo_book_it_brand_colour_fallback('primary');
	$secondary = $secondary ?: tempo_book_it_brand_colour_fallback('secondary');
	$vars      = sprintf(
		'--tempo-brand-primary:%1$s;--tempo-brand-secondary:%2$s;--tempo-brand-on-primary:%3$s;--tempo-brand-on-secondary:%4$s;',
		$primary,
		$secondary,
		tempo_brand_colour_contrast('primary'),
		tempo_brand_colour_contrast('secondary')
	);
	$logo = tempo_logo_url();

	wp_add_inline_style(
		'tempo-book-it-theme-login',
		'body.tempo-login,body.login{' . $vars . '}#login h1 a{background-image:url(' . esc_url($logo) . ');}'
	);
}
add_action('login_enqueue_scripts', 'tempo_book_it_login_styles');

function tempo_book_it_login_headerurl()
{
	return home_url('/');
}
add_filter('login_headerurl', 'tempo_book_it_login_headerurl');

function tempo_book_it_login_headertext()
{
	return get_bloginfo('name');
}
add_filter('login_headertext', 'tempo_book_it_login_headertext');

/** Send direct visits to the ordinary core login form to the theme route. */
function tempo_book_it_redirect_core_login()
{
	if ('GET' !== strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) {
		return;
	}
	$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
	if (! in_array($action, array('login', 'register', 'lostpassword', 'retrievepassword', 'rp', 'resetpass'), true)) {
		return;
	}
	if (isset($_REQUEST['interim-login'])) {
		return;
	}

	$redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
	$map      = array(
		'retrievepassword' => 'lostpassword',
		'resetpass'        => 'resetpass',
	);
	$action   = isset($map[$action]) ? $map[$action] : $action;
	$url      = tempo_book_it_login_url($redirect, $action);
	foreach (array('key', 'login', 'wp_lang', 'error', 'checkemail') as $key) {
		if (isset($_REQUEST[$key])) {
			$url = add_query_arg($key, sanitize_text_field(wp_unslash($_REQUEST[$key])), $url);
		}
	}
	wp_safe_redirect($url);
	exit;
}
add_action('login_init', 'tempo_book_it_redirect_core_login');

/** Extract and validate a requested post-login destination. */
function tempo_book_it_requested_redirect()
{
	$value = '';
	if (isset($_POST['redirect_to'])) {
		$value = esc_url_raw(wp_unslash($_POST['redirect_to']));
	} elseif (isset($_GET['redirect_to'])) {
		$value = esc_url_raw(wp_unslash($_GET['redirect_to']));
	}
	if ('' === $value) {
		return home_url('/');
	}
	return wp_validate_redirect($value, home_url('/'));
}

/** Read the reset cookie and validate its login/key pair. */
function tempo_book_it_reset_user()
{
	$cookie_name = 'wp-resetpass-' . COOKIEHASH;
	if (empty($_COOKIE[$cookie_name]) || false === strpos($_COOKIE[$cookie_name], ':')) {
		return new WP_Error('invalid_key', __('This password reset link is not valid.', 'tempo-book-it-theme'));
	}
	list($login, $key) = explode(':', wp_unslash($_COOKIE[$cookie_name]), 2);
	return check_password_reset_key($key, $login);
}

/** Return the key held in the HttpOnly reset cookie for form verification. */
function tempo_book_it_reset_key()
{
	$cookie_name = 'wp-resetpass-' . COOKIEHASH;
	if (empty($_COOKIE[$cookie_name]) || false === strpos($_COOKIE[$cookie_name], ':')) {
		return '';
	}
	list(, $key) = explode(':', wp_unslash($_COOKIE[$cookie_name]), 2);
	return (string) $key;
}

/** Clear the short-lived password-reset cookie. */
function tempo_book_it_clear_reset_cookie()
{
	setcookie(
		'wp-resetpass-' . COOKIEHASH,
		' ',
		time() - YEAR_IN_SECONDS,
		(string) wp_parse_url(tempo_book_it_login_url(), PHP_URL_PATH),
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);
}

/** Process and render the theme-owned authentication endpoint. */
function tempo_book_it_custom_login()
{
	if (! tempo_book_it_is_login_request()) {
		return;
	}

	nocache_headers();
	header('Referrer-Policy: no-referrer');
	header('X-Content-Type-Options: nosniff');
	$allowed_actions = array('login', 'register', 'registered', 'lostpassword', 'checkemail', 'rp', 'resetpass', 'resetcomplete');
	$action          = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
	$action          = in_array($action, $allowed_actions, true) ? $action : 'login';
	$redirect_to     = tempo_book_it_requested_redirect();
	$errors          = new WP_Error();
	$user_login      = '';
	$reset_user      = null;
	$registration    = array('account_type' => 'student');

	if (in_array($action, array('login', 'register'), true) && is_user_logged_in() && empty($_GET['reauth'])) {
		wp_safe_redirect($redirect_to);
		exit;
	}

	if (in_array($action, array('rp', 'resetpass'), true) && isset($_GET['key'], $_GET['login'])) {
		$cookie_value = sprintf('%s:%s', wp_unslash($_GET['login']), wp_unslash($_GET['key']));
		setcookie(
			'wp-resetpass-' . COOKIEHASH,
			$cookie_value,
			0,
			(string) wp_parse_url(tempo_book_it_login_url(), PHP_URL_PATH),
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
		wp_safe_redirect(tempo_book_it_login_url('', 'resetpass'));
		exit;
	}
	if ('rp' === $action) {
		$action = 'resetpass';
	}

	if ('POST' === strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) {
		if ('login' === $action) {
			$user_login = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
			if (! isset($_POST['tempo_login_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tempo_login_nonce'])), 'tempo_login')) {
				$errors->add('invalid_nonce', __('Your session expired. Refresh the page and try again.', 'tempo-book-it-theme'));
			} else {
				$user = wp_signon(
					array(
						'user_login'    => $user_login,
						'user_password' => isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '',
						'remember'      => ! empty($_POST['rememberme']),
					),
					is_ssl()
				);
				if (is_wp_error($user)) {
					$errors = $user;
				} else {
					$destination = apply_filters('login_redirect', $redirect_to, $redirect_to, $user);
					$destination = wp_validate_redirect($destination, home_url('/'));
					wp_safe_redirect($destination ?: home_url('/'));
					exit;
				}
			}
		} elseif ('register' === $action) {
			$registration = tempo_book_it_registration_values();
			$honeypot     = isset($_POST['contact_fax']) ? trim((string) wp_unslash($_POST['contact_fax'])) : '';
			if (! isset($_POST['tempo_register_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tempo_register_nonce'])), 'tempo_register')) {
				$errors->add('invalid_nonce', __('Your session expired. Refresh the page and try again.', 'tempo-book-it-theme'));
			} elseif ('' !== $honeypot) {
				wp_safe_redirect(tempo_book_it_login_url($redirect_to, 'registered'));
				exit;
			} elseif (! tempo_book_it_registration_attempt_allowed()) {
				$errors->add('registration_rate_limit', __('Too many registration attempts were made from this connection. Wait 15 minutes and try again.', 'tempo-book-it-theme'));
			} elseif (! tempo_book_it_registration_available() || ! function_exists('dsb_register_member')) {
				$errors->add('registration_unavailable', __('Account registration is not available right now. Please contact the school.', 'tempo-book-it-theme'));
			} else {
				$result = dsb_register_member($registration);
				if (is_wp_error($result)) {
					$errors = $result;
				} else {
					wp_safe_redirect(tempo_book_it_login_url($redirect_to, 'registered'));
					exit;
				}
			}
		} elseif ('lostpassword' === $action) {
			$user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
			if (! isset($_POST['tempo_lostpassword_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tempo_lostpassword_nonce'])), 'tempo_lostpassword')) {
				$errors->add('invalid_nonce', __('Your session expired. Refresh the page and try again.', 'tempo-book-it-theme'));
			} else {
				$result = retrieve_password($user_login);
				if (! is_wp_error($result) || array_intersect(array('invalid_email', 'invalidcombo', 'invalid_username'), $result->get_error_codes())) {
					wp_safe_redirect(tempo_book_it_login_url($redirect_to, 'checkemail'));
					exit;
				}
				$errors = $result;
			}
		} elseif ('resetpass' === $action) {
			$reset_user = tempo_book_it_reset_user();
			$cookie_key = tempo_book_it_reset_key();
			$post_key   = isset($_POST['rp_key']) ? wp_unslash($_POST['rp_key']) : '';
			if (is_wp_error($reset_user) || ! $cookie_key || ! hash_equals($cookie_key, $post_key)) {
				tempo_book_it_clear_reset_cookie();
				wp_safe_redirect(add_query_arg('error', 'invalidkey', tempo_book_it_login_url('', 'lostpassword')));
				exit;
			}
			$pass1 = isset($_POST['pass1']) ? trim(wp_unslash($_POST['pass1'])) : '';
			$pass2 = isset($_POST['pass2']) ? trim(wp_unslash($_POST['pass2'])) : '';
			if (! isset($_POST['tempo_reset_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tempo_reset_nonce'])), 'tempo_reset_password')) {
				$errors->add('invalid_nonce', __('Your session expired. Refresh the page and try again.', 'tempo-book-it-theme'));
			} elseif ('' === $pass1) {
				$errors->add('empty_password', __('Enter a new password.', 'tempo-book-it-theme'));
			} elseif ($pass1 !== $pass2) {
				$errors->add('password_mismatch', __('The passwords do not match.', 'tempo-book-it-theme'));
			}
			do_action('validate_password_reset', $errors, $reset_user);
			if (! $errors->has_errors()) {
				reset_password($reset_user, $pass1);
				tempo_book_it_clear_reset_cookie();
				wp_safe_redirect(tempo_book_it_login_url('', 'resetcomplete'));
				exit;
			}
		}
	}

	if ('resetpass' === $action && ! $reset_user) {
		$reset_user = tempo_book_it_reset_user();
		if (is_wp_error($reset_user)) {
			tempo_book_it_clear_reset_cookie();
			wp_safe_redirect(add_query_arg('error', 'invalidkey', tempo_book_it_login_url('', 'lostpassword')));
			exit;
		}
	}

	status_header(200);
	tempo_book_it_render_login($action, $errors, $redirect_to, $user_login, $reset_user, $registration);
	exit;
}
add_action('template_redirect', 'tempo_book_it_custom_login', 0);

/** Return user-facing error copy without exposing which credential failed. */
function tempo_book_it_login_error_messages($errors)
{
	if (! $errors->has_errors()) {
		return array();
	}
	$credential_codes = array('invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password');
	if (array_intersect($credential_codes, $errors->get_error_codes())) {
		return array(__('The email address or password is not right. Check both and try again.', 'tempo-book-it-theme'));
	}
	$messages = array();
	foreach ($errors->get_error_messages() as $message) {
		$messages[] = wp_strip_all_tags($message);
	}
	return array_unique($messages);
}

/**
 * Render the standalone authentication document.
 *
 * @param string           $action      Current authentication view.
 * @param WP_Error         $errors      Validation/authentication errors.
 * @param string           $redirect_to Destination after sign-in.
 * @param string           $user_login  Previously entered login value.
 * @param WP_User|WP_Error $reset_user  User associated with a valid reset key.
 * @param array            $registration Previously submitted registration fields.
 */
function tempo_book_it_render_login($action, $errors, $redirect_to, $user_login, $reset_user, $registration = array())
{
	$site_name       = get_bloginfo('name');
	$logo            = tempo_logo_url();
	$panel_image     = tempo_login_panel_image_url();
	$student_word    = function_exists('dsb_vocab') ? dsb_vocab('student') : __('student', 'tempo-book-it-theme');
	$classes_word    = tempo_vocab('classes');
	$class_word      = tempo_vocab('class');
	$business_type   = function_exists('dsb_business_type') ? dsb_business_type() : __('studio', 'tempo-book-it-theme');
	$student_label   = mb_strtoupper(mb_substr($student_word, 0, 1)) . mb_substr($student_word, 1);
	$registration    = wp_parse_args($registration, array('account_type' => 'student'));
	$registration_as = 'parent' === $registration['account_type'] ? 'parent' : 'student';
	$titles    = array(
		'login'         => __('Welcome back', 'tempo-book-it-theme'),
		'register'      => __('Create your account', 'tempo-book-it-theme'),
		'registered'    => __('Check your email', 'tempo-book-it-theme'),
		'lostpassword'  => __('Reset your password', 'tempo-book-it-theme'),
		'checkemail'    => __('Check your email', 'tempo-book-it-theme'),
		'resetpass'     => __('Choose a new password', 'tempo-book-it-theme'),
		'resetcomplete' => __('Password updated', 'tempo-book-it-theme'),
	);
	$title = isset($titles[$action]) ? $titles[$action] : $titles['login'];

	do_action('login_enqueue_scripts');
?>
	<!doctype html>
	<html <?php language_attributes(); ?>>

	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<title><?php echo esc_html($title . ' - ' . $site_name); ?></title>
		<?php wp_print_styles(); ?>
		<?php do_action('login_head'); ?>
	</head>

	<body class="tempo-login tempo-login--<?php echo esc_attr($action); ?>">
		<a class="tempo-login__skip" href="#tempo-login-form"><?php echo 'register' === $action ? esc_html__('Skip to registration', 'tempo-book-it-theme') : esc_html__('Skip to sign in', 'tempo-book-it-theme'); ?></a>
		<main class="tempo-login__shell">
			<section class="tempo-login__form-panel">
				<div class="tempo-login__form-wrap">
					<a class="tempo-login__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($site_name); ?>">
						<img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_name); ?>">
					</a>

					<div class="tempo-login__intro">
						<p class="tempo-login__eyebrow"><?php esc_html_e('Member portal', 'tempo-book-it-theme'); ?></p>
						<h1><?php echo esc_html($title); ?></h1>
						<?php if ('login' === $action) : ?>
							<p><?php /* translators: %s: vocabulary word for classes (plural). */ echo esc_html(sprintf(__('Sign in to manage your %s, bookings and account.', 'tempo-book-it-theme'), $classes_word)); ?></p>
						<?php elseif ('register' === $action) : ?>
							<p><?php echo esc_html(sprintf(__('Tell us whether you are registering yourself or a %s you care for.', 'tempo-book-it-theme'), $student_word)); ?></p>
						<?php elseif ('registered' === $action) : ?>
							<p><?php esc_html_e('Your account is ready. Use the secure link we sent to choose your password.', 'tempo-book-it-theme'); ?></p>
						<?php elseif ('lostpassword' === $action) : ?>
							<p><?php esc_html_e('Enter your email address or username and we will send you a secure reset link.', 'tempo-book-it-theme'); ?></p>
						<?php elseif ('checkemail' === $action) : ?>
							<p><?php esc_html_e('If an account matches those details, a password reset link is on its way.', 'tempo-book-it-theme'); ?></p>
						<?php elseif ('resetpass' === $action) : ?>
							<p><?php esc_html_e('Use a strong password you do not use anywhere else.', 'tempo-book-it-theme'); ?></p>
						<?php else : ?>
							<p><?php esc_html_e('Your new password is ready. You can sign in now.', 'tempo-book-it-theme'); ?></p>
						<?php endif; ?>
					</div>

					<?php foreach (tempo_book_it_login_error_messages($errors) as $message) : ?>
						<div class="tempo-login__notice tempo-login__notice--error" role="alert">
							<span aria-hidden="true">!</span>
							<p><?php echo esc_html($message); ?></p>
						</div>
					<?php endforeach; ?>
					<?php if (in_array((isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : ''), array('invalidkey', 'expiredkey'), true)) : ?>
						<div class="tempo-login__notice tempo-login__notice--error" role="alert"><span aria-hidden="true">!</span>
							<p><?php esc_html_e('That reset link has expired or was already used. Request a new one below.', 'tempo-book-it-theme'); ?></p>
						</div>
					<?php endif; ?>

					<?php if ('login' === $action) : ?>
						<?php do_action('login_form_login'); ?>
						<form id="tempo-login-form" class="tempo-login__form" action="<?php echo esc_url(tempo_book_it_login_url($redirect_to)); ?>" method="post">
							<div class="tempo-field">
								<label for="tempo-user-login"><?php esc_html_e('Email address or username', 'tempo-book-it-theme'); ?></label>
								<input id="tempo-user-login" name="log" type="text" value="<?php echo esc_attr($user_login); ?>" autocomplete="username" required autofocus>
							</div>
							<div class="tempo-field">
								<div class="tempo-field__label-row">
									<label for="tempo-password"><?php esc_html_e('Password', 'tempo-book-it-theme'); ?></label>
									<a href="<?php echo esc_url(tempo_book_it_login_url($redirect_to, 'lostpassword')); ?>"><?php esc_html_e('Forgot password?', 'tempo-book-it-theme'); ?></a>
								</div>
								<div class="tempo-field__password">
									<input id="tempo-password" name="pwd" type="password" autocomplete="current-password" required>
									<button class="tempo-password-toggle" type="button" aria-controls="tempo-password" aria-pressed="false"><span class="screen-reader-text"><?php esc_html_e('Show password', 'tempo-book-it-theme'); ?></span><span aria-hidden="true"></span></button>
								</div>
							</div>
							<label class="tempo-login__remember"><input name="rememberme" type="checkbox" value="forever"><span><?php esc_html_e('Keep me signed in', 'tempo-book-it-theme'); ?></span></label>
							<?php wp_nonce_field('tempo_login', 'tempo_login_nonce'); ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
							<?php do_action('login_form'); ?>
							<button class="tempo-login__submit" type="submit"><?php esc_html_e('Sign in', 'tempo-book-it-theme'); ?><span aria-hidden="true">&rarr;</span></button>
						</form>
						<?php if (tempo_book_it_registration_available()) : ?>
							<p class="tempo-login__register-prompt"><?php esc_html_e('New here?', 'tempo-book-it-theme'); ?> <a href="<?php echo esc_url(tempo_book_it_login_url($redirect_to, 'register')); ?>"><?php esc_html_e('Create an account', 'tempo-book-it-theme'); ?></a></p>
						<?php endif; ?>
					<?php elseif ('register' === $action) : ?>
						<?php do_action('login_form_register'); ?>
						<form id="tempo-login-form" class="tempo-login__form tempo-registration" action="<?php echo esc_url(tempo_book_it_login_url($redirect_to, 'register')); ?>" method="post" data-registration-form>
							<fieldset class="tempo-registration__choice">
								<legend><?php esc_html_e('Who are you creating this account for?', 'tempo-book-it-theme'); ?></legend>
								<div class="tempo-registration__choice-grid">
									<label class="tempo-registration__choice-card">
										<input type="radio" name="account_type" value="student" <?php checked($registration_as, 'student'); ?> required>
										<span><strong><?php echo esc_html(sprintf(__('I am the %s', 'tempo-book-it-theme'), $student_word)); ?></strong><small><?php esc_html_e('Create my own login', 'tempo-book-it-theme'); ?></small></span>
									</label>
									<label class="tempo-registration__choice-card">
										<input type="radio" name="account_type" value="parent" <?php checked($registration_as, 'parent'); ?> required>
										<span><strong><?php esc_html_e('I am a parent or guardian', 'tempo-book-it-theme'); ?></strong><small><?php echo esc_html(sprintf(__('Register a %s I care for', 'tempo-book-it-theme'), $student_word)); ?></small></span>
									</label>
								</div>
							</fieldset>

							<fieldset class="tempo-registration__section">
								<legend><?php echo esc_html(sprintf(__('%s details', 'tempo-book-it-theme'), $student_label)); ?></legend>
								<div class="tempo-registration__row">
									<div class="tempo-field"><label for="tempo-student-first-name"><?php esc_html_e('First name', 'tempo-book-it-theme'); ?></label><input id="tempo-student-first-name" name="student_first_name" type="text" value="<?php echo esc_attr($registration['student_first_name'] ?? ''); ?>" autocomplete="given-name" maxlength="100" required></div>
									<div class="tempo-field"><label for="tempo-student-last-name"><?php esc_html_e('Last name', 'tempo-book-it-theme'); ?></label><input id="tempo-student-last-name" name="student_last_name" type="text" value="<?php echo esc_attr($registration['student_last_name'] ?? ''); ?>" autocomplete="family-name" maxlength="100" required></div>
								</div>
							</fieldset>

							<div class="tempo-registration__details tempo-registration__details--student" data-registration-panel="student">
								<div class="tempo-field">
									<label for="tempo-student-email"><?php esc_html_e('Your email address', 'tempo-book-it-theme'); ?></label>
									<input id="tempo-student-email" name="student_email" type="email" value="<?php echo esc_attr($registration['student_email'] ?? ''); ?>" autocomplete="email" data-registration-required>
									<p class="tempo-field__hint"><?php esc_html_e('We will email you a secure link to choose your password.', 'tempo-book-it-theme'); ?></p>
								</div>
							</div>

							<fieldset class="tempo-registration__section tempo-registration__details tempo-registration__details--parent" data-registration-panel="parent">
								<legend><?php esc_html_e('Parent or guardian details', 'tempo-book-it-theme'); ?></legend>
								<div class="tempo-registration__row">
									<div class="tempo-field"><label for="tempo-parent-first-name"><?php esc_html_e('First name', 'tempo-book-it-theme'); ?></label><input id="tempo-parent-first-name" name="parent_first_name" type="text" value="<?php echo esc_attr($registration['parent_first_name'] ?? ''); ?>" autocomplete="given-name" maxlength="100" data-registration-required></div>
									<div class="tempo-field"><label for="tempo-parent-last-name"><?php esc_html_e('Last name', 'tempo-book-it-theme'); ?></label><input id="tempo-parent-last-name" name="parent_last_name" type="text" value="<?php echo esc_attr($registration['parent_last_name'] ?? ''); ?>" autocomplete="family-name" maxlength="100" data-registration-required></div>
								</div>
								<div class="tempo-field"><label for="tempo-relationship"><?php echo esc_html(sprintf(__('Relationship to the %s', 'tempo-book-it-theme'), $student_word)); ?></label><input id="tempo-relationship" name="relationship" type="text" value="<?php echo esc_attr($registration['relationship'] ?? ''); ?>" placeholder="<?php esc_attr_e('For example, parent or guardian', 'tempo-book-it-theme'); ?>" maxlength="80" data-registration-required></div>
								<div class="tempo-registration__row">
									<div class="tempo-field"><label for="tempo-parent-email"><?php esc_html_e('Email address', 'tempo-book-it-theme'); ?></label><input id="tempo-parent-email" name="parent_email" type="email" value="<?php echo esc_attr($registration['parent_email'] ?? ''); ?>" autocomplete="email" data-registration-required></div>
									<div class="tempo-field"><label for="tempo-parent-mobile"><?php esc_html_e('Mobile number', 'tempo-book-it-theme'); ?></label><input id="tempo-parent-mobile" name="parent_mobile" type="tel" value="<?php echo esc_attr($registration['parent_mobile'] ?? ''); ?>" autocomplete="tel" maxlength="40" data-registration-required></div>
								</div>
								<p class="tempo-field__hint"><?php esc_html_e('Your login will manage the linked student account. We will email you a secure link to choose your password.', 'tempo-book-it-theme'); ?></p>
							</fieldset>

							<div class="tempo-registration__honeypot" aria-hidden="true"><label for="tempo-contact-fax">Fax</label><input id="tempo-contact-fax" name="contact_fax" type="text" value="" autocomplete="off" tabindex="-1"></div>
							<?php wp_nonce_field('tempo_register', 'tempo_register_nonce'); ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
							<?php do_action('register_form'); ?>
							<button class="tempo-login__submit" type="submit"><?php esc_html_e('Create account', 'tempo-book-it-theme'); ?><span aria-hidden="true">&rarr;</span></button>
						</form>
						<a class="tempo-login__back" href="<?php echo esc_url(tempo_book_it_login_url($redirect_to)); ?>">&larr; <?php esc_html_e('Back to sign in', 'tempo-book-it-theme'); ?></a>
					<?php elseif ('lostpassword' === $action) : ?>
						<?php do_action('login_form_lostpassword'); ?>
						<form id="tempo-login-form" class="tempo-login__form" action="<?php echo esc_url(tempo_book_it_login_url($redirect_to, 'lostpassword')); ?>" method="post">
							<div class="tempo-field">
								<label for="tempo-user-login"><?php esc_html_e('Email address or username', 'tempo-book-it-theme'); ?></label>
								<input id="tempo-user-login" name="user_login" type="text" value="<?php echo esc_attr($user_login); ?>" autocomplete="username" required autofocus>
							</div>
							<?php wp_nonce_field('tempo_lostpassword', 'tempo_lostpassword_nonce'); ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
							<?php do_action('lostpassword_form'); ?>
							<button class="tempo-login__submit" type="submit"><?php esc_html_e('Send reset link', 'tempo-book-it-theme'); ?><span aria-hidden="true">&rarr;</span></button>
						</form>
						<a class="tempo-login__back" href="<?php echo esc_url(tempo_book_it_login_url($redirect_to)); ?>">&larr; <?php esc_html_e('Back to sign in', 'tempo-book-it-theme'); ?></a>
					<?php elseif ('resetpass' === $action && $reset_user instanceof WP_User) : ?>
						<?php do_action('login_form_resetpass'); ?>
						<form id="tempo-login-form" class="tempo-login__form" action="<?php echo esc_url(tempo_book_it_login_url('', 'resetpass')); ?>" method="post">
							<div class="tempo-field">
								<label for="tempo-new-password"><?php esc_html_e('New password', 'tempo-book-it-theme'); ?></label>
								<div class="tempo-field__password"><input id="tempo-new-password" name="pass1" type="password" autocomplete="new-password" required autofocus><button class="tempo-password-toggle" type="button" aria-controls="tempo-new-password" aria-pressed="false"><span class="screen-reader-text"><?php esc_html_e('Show password', 'tempo-book-it-theme'); ?></span><span aria-hidden="true"></span></button></div>
								<p class="tempo-field__hint"><?php esc_html_e('Use at least 12 characters with a mix of words, numbers and symbols.', 'tempo-book-it-theme'); ?></p>
							</div>
							<div class="tempo-field"><label for="tempo-confirm-password"><?php esc_html_e('Confirm new password', 'tempo-book-it-theme'); ?></label>
								<div class="tempo-field__password"><input id="tempo-confirm-password" name="pass2" type="password" autocomplete="new-password" required><button class="tempo-password-toggle" type="button" aria-controls="tempo-confirm-password" aria-pressed="false"><span class="screen-reader-text"><?php esc_html_e('Show password', 'tempo-book-it-theme'); ?></span><span aria-hidden="true"></span></button></div>
							</div>
							<?php wp_nonce_field('tempo_reset_password', 'tempo_reset_nonce'); ?>
							<input type="hidden" name="rp_key" value="<?php echo esc_attr(tempo_book_it_reset_key()); ?>">
							<?php do_action('resetpass_form', $reset_user); ?>
							<button class="tempo-login__submit" type="submit"><?php esc_html_e('Save new password', 'tempo-book-it-theme'); ?><span aria-hidden="true">&rarr;</span></button>
						</form>
					<?php else : ?>
						<div id="tempo-login-form" class="tempo-login__notice tempo-login__notice--success" role="status"><span aria-hidden="true">&#10003;</span>
							<p><?php echo in_array($action, array('checkemail', 'registered'), true) ? esc_html__('The email can take a few minutes to arrive. Check your spam folder too.', 'tempo-book-it-theme') : esc_html__('Your password has been changed successfully.', 'tempo-book-it-theme'); ?></p>
						</div>
						<a class="tempo-login__submit tempo-login__submit--link" href="<?php echo esc_url(tempo_book_it_login_url($redirect_to)); ?>"><?php esc_html_e('Return to sign in', 'tempo-book-it-theme'); ?><span aria-hidden="true">&rarr;</span></a>
					<?php endif; ?>

					<p class="tempo-login__privacy">
						<?php esc_html_e('Secure access for members of', 'tempo-book-it-theme'); ?> <?php echo esc_html($site_name); ?>.
						<?php if (get_privacy_policy_url()) : ?><a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php esc_html_e('Privacy', 'tempo-book-it-theme'); ?></a><?php endif; ?>
					</p>
				</div>
			</section>

			<aside
				class="tempo-login__showcase<?php echo $panel_image ? ' tempo-login__showcase--image' : ''; ?>"
				<?php if ($panel_image) : ?>
				aria-hidden="true"
				style="<?php echo esc_attr('background-image: url("' . $panel_image . '");'); ?>"
				<?php else : ?>
				aria-label="<?php /* translators: %s: business-type label, e.g. "Football Club". */ echo esc_attr(sprintf(__('Your %s portal', 'tempo-book-it-theme'), $business_type)); ?>"
				<?php endif; ?>>
				<?php if (! $panel_image) : ?>
					<div class="tempo-login__showcase-grid" aria-hidden="true"></div>
					<div class="tempo-login__ribbon tempo-login__ribbon--one" aria-hidden="true"></div>
					<div class="tempo-login__ribbon tempo-login__ribbon--two" aria-hidden="true"></div>
					<div class="tempo-login__showcase-content">
						<p class="tempo-login__showcase-kicker"><?php esc_html_e('Everything in one place', 'tempo-book-it-theme'); ?></p>
						<h2><?php /* translators: %s: business-type label, e.g. "Football Club". */ echo esc_html(sprintf(__('Your %s, all in step.', 'tempo-book-it-theme'), $business_type)); ?></h2>
						<p><?php /* translators: %s: vocabulary word for classes (plural), capitalised. */ echo esc_html(sprintf(__('%s, bookings and account details.', 'tempo-book-it-theme'), mb_strtoupper(mb_substr($classes_word, 0, 1)) . mb_substr($classes_word, 1))); ?></p>
						<div class="tempo-portal-card" aria-hidden="true">
							<div class="tempo-portal-card__top"><span></span><span><?php esc_html_e('This week', 'tempo-book-it-theme'); ?></span><b>&middot;&middot;&middot;</b></div>
							<div class="tempo-portal-card__days"><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div>
							<div class="tempo-portal-card__line"><i></i>
								<div><strong><?php esc_html_e('Move', 'tempo-book-it-theme'); ?></strong><small><?php /* translators: %s: vocabulary word for a class (singular). */ echo esc_html(sprintf(__('Find your next %s', 'tempo-book-it-theme'), $class_word)); ?></small></div><em>&rarr;</em>
							</div>
							<div class="tempo-portal-card__line tempo-portal-card__line--second"><i></i>
								<div><strong><?php esc_html_e('Create', 'tempo-book-it-theme'); ?></strong><small><?php esc_html_e('Keep every booking together', 'tempo-book-it-theme'); ?></small></div><em>&rarr;</em>
							</div>
						</div>
						<div class="tempo-login__pulse" aria-hidden="true"><span></span><?php esc_html_e('Ready when you are', 'tempo-book-it-theme'); ?></div>
					</div>
				<?php endif; ?>
			</aside>
		</main>
		<?php do_action('login_footer'); ?>
		<?php wp_print_footer_scripts(); ?>
	</body>

	</html>
<?php
}
