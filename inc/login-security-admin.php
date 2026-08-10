<?php

/**
 * The Sign-in security screen, under Users.
 *
 * Shows what the authentication limits are currently holding back, lets an
 * administrator let someone through early, and shows the history of both.
 *
 * This file is only loaded in the admin (see functions.php). Everything it
 * hooks — the menu, the screen, the form handlers — runs inside wp-admin, and
 * admin-post.php counts as the admin, so nothing is missed by loading it there
 * alone. The functions that actually lift a block live in login-security.php
 * so they stay available to WP-CLI.
 *
 * @package tempo-book-it-theme
 */

defined('ABSPATH') || exit;

/** The screen's page slug. */
define('TEMPO_BOOK_IT_SECURITY_PAGE', 'tempo-book-it-sign-in-security');

/**
 * Who may see and use the screen.
 *
 * Used by the menu, the screen itself and every form handler, so the four
 * cannot disagree. The default matches tempo_is_school_admin(), which already
 * treats manage_options as enough; the filter is how a site hands the screen
 * to office staff who are not full administrators.
 *
 * @return string
 */
function tempo_book_it_security_admin_capability()
{
	/**
	 * Filter the capability required to manage sign-in security.
	 *
	 * @param string $capability Capability name.
	 */
	return (string) apply_filters('tempo_book_it_security_admin_capability', 'manage_options');
}

/**
 * A link back to the screen.
 *
 * @param array $args Extra query arguments.
 * @return string
 */
function tempo_book_it_security_admin_url($args = array())
{
	$url = admin_url('users.php?page=' . TEMPO_BOOK_IT_SECURITY_PAGE);
	return $args ? add_query_arg($args, $url) : $url;
}

/**
 * Put the screen under Users, beside the member list it relates to.
 */
function tempo_book_it_security_admin_menu()
{
	add_users_page(
		__('Sign-in security', 'tempo-book-it-theme'),
		__('Sign-in security', 'tempo-book-it-theme'),
		tempo_book_it_security_admin_capability(),
		TEMPO_BOOK_IT_SECURITY_PAGE,
		'tempo_book_it_security_admin_page'
	);
}
add_action('admin_menu', 'tempo_book_it_security_admin_menu');

// Opening the screen is a rare, signed-in request, so it is a good moment to
// let go of anything past its retention.
add_action('load-users_page_' . TEMPO_BOOK_IT_SECURITY_PAGE, 'tempo_book_it_security_log_prune');

/**
 * What each outcome says once the browser comes back to the screen.
 *
 * An allow list, so nothing typed into the address bar can reach the page.
 *
 * @return array Result code to type and message.
 */
function tempo_book_it_security_admin_notices()
{
	return array(
		'unlocked'             => array(
			'type'    => 'success',
			'message' => __('Block lifted. They can try signing in again straight away.', 'tempo-book-it-theme'),
		),
		'unlocked_none'        => array(
			'type'    => 'info',
			'message' => __('Nothing was still blocked there — it had already lifted on its own.', 'tempo-book-it-theme'),
		),
		'unlocked_member'      => array(
			'type'    => 'success',
			'message' => __('Any sign-in block on that member has been lifted.', 'tempo-book-it-theme'),
		),
		'unlocked_member_none' => array(
			'type'    => 'info',
			'message' => __('That member was not blocked. Nothing needed lifting.', 'tempo-book-it-theme'),
		),
		'unknown_member'       => array(
			'type'    => 'info',
			'message' => __('No account matched that name. Anything recorded against what was typed has been cleared anyway.', 'tempo-book-it-theme'),
		),
		'unlock_empty'         => array(
			'type'    => 'error',
			'message' => __('Enter a username or email address.', 'tempo-book-it-theme'),
		),
		'unlock_missing'       => array(
			'type'    => 'error',
			'message' => __('That entry is no longer in the log.', 'tempo-book-it-theme'),
		),
		'log_cleared'          => array(
			'type'    => 'success',
			'message' => __('Sign-in security log cleared.', 'tempo-book-it-theme'),
		),
	);
}

/**
 * Draw the screen.
 */
function tempo_book_it_security_admin_page()
{
	if (! current_user_can(tempo_book_it_security_admin_capability())) {
		wp_die(esc_html__('You are not allowed to manage sign-in security.', 'tempo-book-it-theme'), '', array('response' => 403));
	}

	$labels    = tempo_book_it_security_log_types();
	$blocks    = tempo_book_it_current_blocks();
	$log       = tempo_book_it_security_log_get();
	$post_url  = admin_url('admin-post.php');
	$date_form = get_option('date_format') . ' ' . get_option('time_format');

	/**
	 * Filter how many past entries the screen lists.
	 *
	 * @param int $limit Number of entries.
	 */
	$display_limit = max(1, (int) apply_filters('tempo_book_it_security_log_display_limit', 50));
?>
	<div class="wrap">
		<h1><?php esc_html_e('Sign-in security', 'tempo-book-it-theme'); ?></h1>
		<p><?php esc_html_e('Repeated failed sign-ins, password reset requests and registrations are limited automatically. Anything currently held back is listed here and can be released early.', 'tempo-book-it-theme'); ?></p>

		<?php
		$result  = isset($_GET['tempo_result']) ? sanitize_key(wp_unslash($_GET['tempo_result'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a result code after a redirect, not acting on a submission.
		$notices = tempo_book_it_security_admin_notices();
		if ($result && isset($notices[$result])) {
			wp_admin_notice(
				esc_html($notices[$result]['message']),
				array('type' => $notices[$result]['type'])
			);
		}
		?>

		<h2><?php esc_html_e('Currently blocked', 'tempo-book-it-theme'); ?></h2>
		<?php if (! $blocks) : ?>
			<p><strong><?php esc_html_e('Nothing is blocked.', 'tempo-book-it-theme'); ?></strong> <?php esc_html_e('Members can sign in as normal.', 'tempo-book-it-theme'); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Blocked', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Why', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Connection', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Since', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Lifts by itself', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'tempo-book-it-theme'); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($blocks as $entry) : ?>
						<tr>
							<td>
								<?php
								if ('' !== $entry['subject']) {
									echo esc_html($entry['subject']);
								} else {
									/* translators: %s: short one-way handle for a connection. */
									printf(esc_html__('Connection %s', 'tempo-book-it-theme'), '<code>' . esc_html($entry['fingerprint']) . '</code>'); // phpcs:ignore WordPress.Security.EscapeOutput -- both the format and the value are escaped above.
								}
								?>
							</td>
							<td><?php echo esc_html(isset($labels[$entry['type']]) ? $labels[$entry['type']] : $entry['type']); ?></td>
							<td><?php echo $entry['fingerprint'] ? '<code>' . esc_html($entry['fingerprint']) . '</code>' : '&#8212;'; // phpcs:ignore WordPress.Security.EscapeOutput -- value escaped inline. ?></td>
							<td>
								<?php
								/* translators: %s: length of time, e.g. "5 mins". */
								printf(esc_html__('%s ago', 'tempo-book-it-theme'), esc_html(human_time_diff($entry['time'], time())));
								?>
							</td>
							<td>
								<?php
								if ($entry['expires_at'] > time()) {
									/* translators: %s: length of time, e.g. "10 mins". */
									printf(esc_html__('in about %s', 'tempo-book-it-theme'), esc_html(human_time_diff(time(), $entry['expires_at'])));
								} else {
									esc_html_e('once attempts stop', 'tempo-book-it-theme');
								}
								?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url($post_url); ?>">
									<input type="hidden" name="action" value="tempo_book_it_unlock_entry">
									<input type="hidden" name="entry" value="<?php echo esc_attr($entry['id']); ?>">
									<?php wp_nonce_field('tempo_unlock_entry_' . $entry['id']); ?>
									<button type="submit" class="button"><?php esc_html_e('Unlock', 'tempo-book-it-theme'); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e('A block usually clears itself. The time shown is the earliest it will, and continued attempts push it back. Unlocking a connection also removes the extra security check the sign-in page shows after repeated failures from it.', 'tempo-book-it-theme'); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e('Unlock a member', 'tempo-book-it-theme'); ?></h2>
		<p><?php esc_html_e('If a member is stuck, enter the username or the email address they sign in with. This clears the failed-attempt count held against their account under both forms, so they can try again immediately. It does not affect the connection they are using.', 'tempo-book-it-theme'); ?></p>
		<form method="post" action="<?php echo esc_url($post_url); ?>">
			<input type="hidden" name="action" value="tempo_book_it_unlock_login">
			<?php wp_nonce_field('tempo_unlock_login'); ?>
			<p>
				<label for="tempo-unlock-login"><?php esc_html_e('Username or email address', 'tempo-book-it-theme'); ?></label><br>
				<input type="text" id="tempo-unlock-login" name="login" class="regular-text" autocomplete="off" required>
				<?php submit_button(__('Unlock', 'tempo-book-it-theme'), 'secondary', 'submit', false); ?>
			</p>
		</form>

		<h2><?php esc_html_e('Recent events', 'tempo-book-it-theme'); ?></h2>
		<?php if (! $log) : ?>
			<p><strong><?php esc_html_e('No sign-in problems have been recorded.', 'tempo-book-it-theme'); ?></strong></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('When', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('What', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Who or what', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('Connection', 'tempo-book-it-theme'); ?></th>
						<th scope="col"><?php esc_html_e('By', 'tempo-book-it-theme'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach (array_slice($log, 0, $display_limit) as $entry) : ?>
						<tr>
							<td><?php echo esc_html(wp_date($date_form, $entry['time'])); ?></td>
							<td><?php echo esc_html(isset($labels[$entry['type']]) ? $labels[$entry['type']] : $entry['type']); ?></td>
							<td>
								<?php
								if ('' !== $entry['subject']) {
									echo esc_html($entry['subject']);
								} elseif ($entry['fingerprint']) {
									/* translators: %s: short one-way handle for a connection. */
									printf(esc_html__('Connection %s', 'tempo-book-it-theme'), '<code>' . esc_html($entry['fingerprint']) . '</code>'); // phpcs:ignore WordPress.Security.EscapeOutput -- both the format and the value are escaped above.
								} else {
									echo '&#8212;';
								}
								?>
							</td>
							<td><?php echo $entry['fingerprint'] ? '<code>' . esc_html($entry['fingerprint']) . '</code>' : '&#8212;'; // phpcs:ignore WordPress.Security.EscapeOutput -- value escaped inline. ?></td>
							<td><?php echo $entry['actor_name'] ? esc_html($entry['actor_name']) : '&#8212;'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url($post_url); ?>">
				<input type="hidden" name="action" value="tempo_book_it_clear_security_log">
				<?php wp_nonce_field('tempo_clear_security_log'); ?>
				<p>
					<?php submit_button(__('Clear log', 'tempo-book-it-theme'), 'secondary', 'submit', false); ?>
				</p>
			</form>
			<p class="description"><?php esc_html_e('Clearing removes the history only. Blocks in force are not affected.', 'tempo-book-it-theme'); ?></p>
		<?php endif; ?>
	</div>
<?php
}

/**
 * Refuse anyone who should not be here.
 *
 * Called after the nonce has been checked, matching the order used elsewhere
 * in the theme.
 */
function tempo_book_it_security_admin_guard()
{
	if (! current_user_can(tempo_book_it_security_admin_capability())) {
		wp_die(esc_html__('You are not allowed to manage sign-in security.', 'tempo-book-it-theme'), '', array('response' => 403));
	}
}

/**
 * Send the browser back to the screen with the outcome.
 *
 * @param string $result Result code.
 */
function tempo_book_it_security_admin_return($result)
{
	wp_safe_redirect(tempo_book_it_security_admin_url(array('tempo_result' => $result)));
	exit;
}

/**
 * Lift the block a listed entry describes.
 */
function tempo_book_it_security_handle_unlock_entry()
{
	$entry_id = isset($_POST['entry']) ? sanitize_key(wp_unslash($_POST['entry'])) : '';

	// The entry is named in the nonce, so a token minted for one row cannot
	// lift another, and an invented handle fails here rather than further in.
	check_admin_referer('tempo_unlock_entry_' . $entry_id);
	tempo_book_it_security_admin_guard();

	if (null === tempo_book_it_security_log_find($entry_id)) {
		tempo_book_it_security_admin_return('unlock_missing');
	}

	$cleared = tempo_book_it_unlock_entry($entry_id);
	tempo_book_it_security_admin_return($cleared > 0 ? 'unlocked' : 'unlocked_none');
}
add_action('admin_post_tempo_book_it_unlock_entry', 'tempo_book_it_security_handle_unlock_entry');

/**
 * Lift any block held against a named member.
 */
function tempo_book_it_security_handle_unlock_login()
{
	// Not sanitize_user(): the field takes an email address just as readily,
	// and that would mangle one.
	$login = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';

	check_admin_referer('tempo_unlock_login');
	tempo_book_it_security_admin_guard();

	if ('' === trim($login)) {
		tempo_book_it_security_admin_return('unlock_empty');
	}

	// One key means nothing matched an account: only the typed string itself.
	$matched = count(tempo_book_it_login_counter_keys($login)) > 1;
	$cleared = tempo_book_it_unlock_login($login);

	if (! $matched) {
		tempo_book_it_security_admin_return('unknown_member');
	}
	tempo_book_it_security_admin_return($cleared > 0 ? 'unlocked_member' : 'unlocked_member_none');
}
add_action('admin_post_tempo_book_it_unlock_login', 'tempo_book_it_security_handle_unlock_login');

/**
 * Empty the history.
 */
function tempo_book_it_security_handle_clear_log()
{
	check_admin_referer('tempo_clear_security_log');
	tempo_book_it_security_admin_guard();

	tempo_book_it_security_log_clear(get_current_user_id());
	tempo_book_it_security_admin_return('log_cleared');
}
add_action('admin_post_tempo_book_it_clear_security_log', 'tempo_book_it_security_handle_clear_log');
