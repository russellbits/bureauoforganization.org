<?php
declare(strict_types=1);

/**
 * Shared plumbing for the suggestion form.
 *
 * Required by both issue-code.php (hands out a code) and submit-suggestion.php
 * (checks one). They emit the same message markup so the styling in
 * SuggestionBox.astro stays the single source of truth.
 *
 * Requesting this file directly is harmless: it defines functions and produces
 * no output of its own.
 */

/** How long an issued code stays valid, in minutes. */
const SUGGESTION_CODE_TTL_MINUTES = 15;

/** Longest accepted suggestion URL, in bytes. */
const SUGGESTION_MAX_URL_BYTES = 2048;

/** Session key holding the outstanding code. */
const SUGGESTION_SESSION_KEY = 'suggestion_code';

/**
 * Where suggestions are recorded.
 *
 * Deliberately OUTSIDE the web root. Everything in public/ is re-copied into
 * the build directory on every `astro build`, so a data file kept there is
 * truncated to its empty seed contents on each build -- and `npm run deploy`
 * then pushes that empty copy over the live file. One level up is the project
 * root locally and the account root on the host, both writable and neither
 * served.
 *
 * Set SUGGESTION_STORE in the environment if the host layout differs.
 *
 * @return string absolute path to the data file
 */
function suggestion_store_path(): string
{
    $configured = getenv('SUGGESTION_STORE');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }

    return dirname(__DIR__) . '/suggestions.md';
}

function suggestion_session_start(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    // Adopting a client-supplied session id invites session fixation.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    return session_start();
}

/**
 * Mint a fresh code for this session and return it for display.
 */
function suggestion_issue_code(): string
{
    $code = (string) random_int(1000, 9999);

    $_SESSION[SUGGESTION_SESSION_KEY] = $code;
    $_SESSION['suggestion_code_at'] = time();

    return $code;
}

/**
 * Check a candidate code and spend it, whoever wins.
 *
 * Single use is the point: a code that could be replayed, or reused to pad out
 * a brute-force run, buys us nothing. The session copy is removed before the
 * comparison, so every path through the caller leaves it consumed.
 *
 * @return bool true only when the code was ours, unexpired, and correct.
 */
function suggestion_consume_code(string $candidate): bool
{
    $stored = $_SESSION[SUGGESTION_SESSION_KEY] ?? null;
    $issued = (int) ($_SESSION['suggestion_code_at'] ?? 0);

    unset($_SESSION[SUGGESTION_SESSION_KEY], $_SESSION['suggestion_code_at']);

    if (!is_string($stored) || $stored === '') {
        return false;               // nothing outstanding, or the session died
    }
    if ($issued === 0) {
        return false;               // no timestamp means we cannot trust it
    }
    if (time() - $issued > SUGGESTION_CODE_TTL_MINUTES * 60) {
        return false;
    }

    // Timing-safe: a fixed-length numeric compare is not where we want to be
    // clever in hindsight.
    return hash_equals($stored, $candidate);
}

/**
 * Snapshot the outstanding code, so a server-side failure can hand it back.
 *
 * @return array{code: string, at: int}|null null when nothing was outstanding
 */
function suggestion_snapshot_code(): ?array
{
    if (!isset($_SESSION[SUGGESTION_SESSION_KEY])) {
        return null;
    }

    return [
        'code' => (string) $_SESSION[SUGGESTION_SESSION_KEY],
        'at'   => (int) ($_SESSION['suggestion_code_at'] ?? 0),
    ];
}

/**
 * Put a verified code back after WE failed to file the suggestion.
 *
 * Spend-on-attempt is right for a visitor's own mistake -- a wrong code or a
 * bad URL should not be replayable. But when the disk refuses a suggestion that
 * already passed every check, burning the code would force them to retype the
 * whole thing for a fault that is ours, not theirs.
 *
 * @param array{code: string, at: int}|null $snapshot
 */
function suggestion_restore_code(?array $snapshot): void
{
    if ($snapshot === null) {
        return;
    }

    $_SESSION[SUGGESTION_SESSION_KEY] = $snapshot['code'];
    $_SESSION['suggestion_code_at']   = $snapshot['at'];
}

/**
 * Validate a suggested URL.
 *
 * Returns the cleaned URL, or null if it is unusable. Whitespace and control
 * characters are rejected outright rather than stripped: repairing "exa mple.com"
 * into "example.com" would record an address the visitor never typed, and
 * joining "example.com/\nfoo: bar" into one path quietly hides what happened.
 *
 * The character blacklist does double duty: it keeps the value safe to embed in
 * a quoted YAML scalar and safe to echo inside an HTML attribute.
 *
 * @return string|null the URL with scheme and host lowercased, or null
 */
function suggestion_validate_url(string $raw): ?string
{
    // Surrounding whitespace is a typing artefact; anything inside is not.
    $url = trim($raw);

    if ($url === '' || strlen($url) > SUGGESTION_MAX_URL_BYTES) {
        return null;
    }

    // Spaces, tabs, newlines and control bytes.
    if (preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
        return null;
    }

    // http(s) only. This also rejects javascript:, data:, file: and friends.
    if (!preg_match('~^https?://~i', $url)) {
        return null;
    }

    // Anything that would break out of a quoted YAML scalar or an HTML
    // attribute once we echo it back in the confirmation.
    if (preg_match('/["\'`\\\\<>]/', $url)) {
        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    // Credentials in a URL are a phishing trick and nobody needs them here.
    if (isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    $host = $parts['host'];
    $is_ip = filter_var($host, FILTER_VALIDATE_IP) !== false;
    // ASCII host with a real-looking tail. Punycode (xn--...) passes this, so
    // IDN domains still work; a bare intranet name does not.
    $is_domain = preg_match('/^[A-Za-z0-9._~-]+\.[A-Za-z]{2,}$/', $host) === 1;

    if (!$is_ip && !$is_domain) {
        return null;
    }

    // Lowercase the scheme and host, which are case-insensitive; leave the path,
    // query and fragment alone, because they are not. Rebuilding from the parsed
    // parts is plainer than substring surgery, and safe now that credentials are
    // ruled out above.
    $rebuilt = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (isset($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }
    foreach (['path' => '', 'query' => '?', 'fragment' => '#'] as $piece => $prefix) {
        if (isset($parts[$piece])) {
            $rebuilt .= $prefix . $parts[$piece];
        }
    }

    return $rebuilt;
}

/** Escape for HTML text and attribute values. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Print one of the two message boxes. $body is trusted markup built by the
 * caller; every piece of user data in it must already have gone through e().
 */
function suggestion_message(string $kind, string $heading, string $body): void
{
    printf(
        '<div class="message-box is-%s"><div class="message-heading">%s</div><div class="message-body">%s</div></div>',
        e($kind),
        e($heading),
        $body
    );
}

/**
 * Print an error box and stop.
 *
 * Untyped return on purpose: `: never` would need PHP 8.1, and this deploys to
 * whatever version the FTP host runs. exit() already prevents fall-through.
 */
function suggestion_reject(string $heading, string $body): void
{
    suggestion_message('error', $heading, $body);
    exit;
}
