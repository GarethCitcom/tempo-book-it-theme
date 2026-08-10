<?php

/**
 * A record of the moments the authentication limits took effect.
 *
 * Only transitions are written: the attempt that tips a counter over its limit,
 * and any administrator who lifts a block. Ordinary failed sign-ins are not
 * recorded. That keeps the history readable, and it keeps the site from writing
 * to the same row on every request at exactly the moment it is under attack.
 *
 * Addresses are never stored. Entries carry the same one-way key the counter
 * itself uses, which is enough both to lift a block and to see that two entries
 * came from one connection, without the address ever reaching the database.
 *
 * The option is read, changed and written back, which is not atomic: two
 * counters tipping in the same instant can lose an entry or record one twice.
 * That is accepted. The transients are what actually hold a member out; this is
 * the account of it, so a lost line costs a line of history and nothing more.
 * Recording only transitions keeps writes down to roughly one per counter per
 * window, and duplicates are folded back together when the list is read.
 *
 * @package tempo-book-it-theme
 */

defined('ABSPATH') || exit;

/** Where the history is kept. */
define('TEMPO_BOOK_IT_SECURITY_LOG_OPTION', 'tempo_book_it_security_log');

/**
 * How many entries to keep.
 *
 * @return int
 */
function tempo_book_it_security_log_limit()
{
	/**
	 * Filter how many entries the history holds before the oldest fall off.
	 *
	 * @param int $limit Number of entries.
	 */
	return max(1, (int) apply_filters('tempo_book_it_security_log_limit', 200));
}

/**
 * How long an entry is kept.
 *
 * @return int Seconds.
 */
function tempo_book_it_security_log_retention()
{
	/**
	 * Filter how long entries are kept before they are discarded.
	 *
	 * @param int $retention Lifetime in seconds.
	 */
	return max(DAY_IN_SECONDS, (int) apply_filters('tempo_book_it_security_log_retention', 90 * DAY_IN_SECONDS));
}

/**
 * The kinds of entry, and how each reads on screen.
 *
 * This is also the guest list: anything not named here cannot be recorded.
 *
 * @return array Slug to label.
 */
function tempo_book_it_security_log_types()
{
	return array(
		'login_lockout_account'         => __('Too many failed sign-ins for this account', 'tempo-book-it-theme'),
		'login_lockout_connection'      => __('Too many failed sign-ins from this connection', 'tempo-book-it-theme'),
		'lostpassword_limit_account'    => __('Too many password reset requests for this account', 'tempo-book-it-theme'),
		'lostpassword_limit_connection' => __('Too many password reset requests from this connection', 'tempo-book-it-theme'),
		'registration_limit_connection' => __('Too many registration attempts from this connection', 'tempo-book-it-theme'),
		'unlocked'                      => __('Block lifted by an administrator', 'tempo-book-it-theme'),
		'log_cleared'                   => __('Log cleared by an administrator', 'tempo-book-it-theme'),
	);
}

/**
 * The kinds of entry that describe something being held back.
 *
 * Drawn from the same list as the labels so the two cannot drift apart.
 *
 * @return string[]
 */
function tempo_book_it_security_block_types()
{
	return array(
		'login_lockout_account',
		'login_lockout_connection',
		'lostpassword_limit_account',
		'lostpassword_limit_connection',
		'registration_limit_connection',
	);
}

/**
 * A short, one-way handle for the connection a request came from.
 *
 * These are the opening characters of the very hash that keys the counter, so
 * the same connection always shows the same handle and the address itself is
 * never recoverable from it.
 *
 * @param string|null $ip Address to describe. Defaults to the current one.
 * @return string Eight hexadecimal characters.
 */
function tempo_book_it_connection_fingerprint($ip = null)
{
	$ip = (null === $ip) ? tempo_book_it_client_ip() : (string) $ip;
	return substr(tempo_book_it_rate_limit_key('login_ip', $ip), -32, 8);
}

/**
 * Tidy a label before it is written down.
 *
 * Subjects arrive from submitted forms, so they are stripped of markup and cut
 * short. The cut is longer than any real account name or address, so a label
 * that has been shortened cannot match an account — which is what lets the
 * unlock path look a stored label up again without tracking whether it was cut.
 *
 * @param string $value Raw label.
 * @return string
 */
function tempo_book_it_security_log_subject($value)
{
	$subject = sanitize_text_field((string) $value);
	$subject = function_exists('mb_substr') ? mb_substr($subject, 0, 100, 'UTF-8') : substr($subject, 0, 100);
	return trim($subject);
}

/**
 * The history, newest first.
 *
 * Reading never writes, so nothing is discarded here.
 *
 * @return array
 */
function tempo_book_it_security_log_get()
{
	$log = get_option(TEMPO_BOOK_IT_SECURITY_LOG_OPTION, array());
	return is_array($log) ? $log : array();
}

/**
 * Look an entry up by its handle.
 *
 * @param string $entry_id Entry handle.
 * @return array|null
 */
function tempo_book_it_security_log_find($entry_id)
{
	$entry_id = (string) $entry_id;
	if ('' === $entry_id) {
		return null;
	}
	foreach (tempo_book_it_security_log_get() as $entry) {
		if (is_array($entry) && isset($entry['id']) && $entry['id'] === $entry_id) {
			return $entry;
		}
	}
	return null;
}

/**
 * Write an entry.
 *
 * @param string $type Entry kind, from tempo_book_it_security_log_types().
 * @param array  $args subject, keys, expires_at, actor_id, fingerprint.
 * @return string The new entry's handle, or an empty string if nothing was written.
 */
function tempo_book_it_security_log_record($type, $args = array())
{
	$type = (string) $type;
	if (! array_key_exists($type, tempo_book_it_security_log_types())) {
		return '';
	}

	$keys = isset($args['keys']) ? (array) $args['keys'] : array();
	$keys = array_values(array_filter($keys, 'is_string'));

	$actor_id   = isset($args['actor_id']) ? max(0, (int) $args['actor_id']) : 0;
	$actor_name = '';
	if ($actor_id > 0) {
		$actor = get_userdata($actor_id);
		if ($actor) {
			$actor_name = tempo_book_it_security_log_subject($actor->display_name);
		}
	}

	// An explicit fingerprint is honoured as given: it is how an administrator's
	// action carries the fingerprint of the connection it acted on rather than
	// the administrator's own, and how actions that belong to no connection
	// record none at all.
	$fingerprint = array_key_exists('fingerprint', $args)
		? (string) $args['fingerprint']
		: tempo_book_it_connection_fingerprint();

	$entry = array(
		'id'          => substr(md5(uniqid('tempo', true)), 0, 12),
		'time'        => time(),
		'type'        => $type,
		'subject'     => tempo_book_it_security_log_subject(isset($args['subject']) ? $args['subject'] : ''),
		'fingerprint' => $fingerprint,
		'keys'        => $keys,
		'expires_at'  => isset($args['expires_at']) ? max(0, (int) $args['expires_at']) : 0,
		'actor_id'    => $actor_id,
		'actor_name'  => $actor_name,
	);

	$log = tempo_book_it_security_log_get();
	array_unshift($log, $entry);
	$log = tempo_book_it_security_log_sift($log);

	update_option(TEMPO_BOOK_IT_SECURITY_LOG_OPTION, $log, false);

	/**
	 * Fires after an entry is written, for a site that wants to be told.
	 *
	 * @param array $entry The entry just recorded.
	 */
	do_action('tempo_book_it_security_logged', $entry);

	return $entry['id'];
}

/**
 * Drop entries that are too old or beyond the count we keep.
 *
 * An entry without a usable timestamp counts as ancient, so a damaged one
 * clears itself out rather than lingering.
 *
 * @param array $log Entries, newest first.
 * @return array
 */
function tempo_book_it_security_log_sift($log)
{
	$oldest = time() - tempo_book_it_security_log_retention();
	$kept   = array();
	foreach ($log as $entry) {
		if (! is_array($entry) || ! isset($entry['id'])) {
			continue;
		}
		if ((int) (isset($entry['time']) ? $entry['time'] : 0) < $oldest) {
			continue;
		}
		$kept[] = $entry;
	}
	return array_slice($kept, 0, tempo_book_it_security_log_limit());
}

/**
 * Apply the retention rules to what is stored.
 *
 * Hung on a daily housekeeping event core already schedules, so a quiet site
 * still lets go of old names without the theme scheduling anything of its own.
 *
 * @return int How many entries were discarded.
 */
function tempo_book_it_security_log_prune()
{
	$log    = tempo_book_it_security_log_get();
	$sifted = tempo_book_it_security_log_sift($log);
	$gone   = count($log) - count($sifted);
	if ($gone > 0) {
		update_option(TEMPO_BOOK_IT_SECURITY_LOG_OPTION, $sifted, false);
	}
	return $gone;
}
add_action('wp_scheduled_delete', 'tempo_book_it_security_log_prune');

/**
 * Empty the history.
 *
 * The clearing is itself recorded, so a history that has been wiped says so
 * rather than simply looking like a quiet month.
 *
 * @param int $actor_id Administrator doing the clearing.
 * @return int How many entries were removed.
 */
function tempo_book_it_security_log_clear($actor_id = 0)
{
	$removed = count(tempo_book_it_security_log_get());
	update_option(TEMPO_BOOK_IT_SECURITY_LOG_OPTION, array(), false);
	tempo_book_it_security_log_record(
		'log_cleared',
		array(
			'actor_id'    => $actor_id,
			'fingerprint' => '',
		)
	);
	return $removed;
}
