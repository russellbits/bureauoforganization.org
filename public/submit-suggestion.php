<?php
declare(strict_types=1);

/**
 * Verifies and records a site suggestion.
 *
 * Every response is a bare .message-box, never a #suggestion-box wrapper: the
 * form targets #suggestion-message, so the input, button and code panel stay
 * mounted and the visitor can try again after a rejection. The old version
 * swapped a wrapper in on success and a bare error div on failure, which
 * destroyed the form on exactly the paths that needed it most.
 */

require __DIR__ . '/suggestion-common.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    suggestion_reject(
        'Method Not Allowed',
        '<p>This form only answers POST requests.</p>'
    );
}

if (!suggestion_session_start()) {
    suggestion_reject(
        'System Message',
        '<p>The Bureau could not open a session, so there is no code to check '
            . 'yours against. Reload the page and try again.</p>'
    );
}

$value = trim((string) ($_POST['suggestion'] ?? ''));

// Split the leading code from the rest. The `s` modifier matters: a newline in
// the middle of the field must land in $rest so the URL check can see it and
// reject it, rather than slipping past a pattern that stops at line end.
if (!preg_match('/^(\d{4})(.*)$/s', $value, $matches)) {
    suggestion_reject(
        'Incomplete Suggestion',
        '<p>Type the 4-digit code in front of your URL, '
            . 'then the address itself. Nothing else, please.</p>'
    );
}

$code = $matches[1];
$rest = $matches[2];

// Snapshot before consuming, so a write failure of ours can hand the code back.
$outstanding = suggestion_snapshot_code();

// Spends the code whether it matches or not, so a rejection cannot be replayed.
if (!suggestion_consume_code($code)) {
    suggestion_reject(
        'Wrong Code',
        '<p>That code is not the one we handed you, or it has already been used. '
            . 'Click in the field to be shown a fresh one.</p>'
    );
}

$url = suggestion_validate_url($rest);

if ($url === null) {
    suggestion_reject(
        'Bad Address',
        '<p>We need a complete <code>http://</code> or <code>https://</code> address '
            . 'with a real domain on it. No spaces, no quotes, no angle brackets.</p>'
    );
}

$timestamp = date('c');

// The URL is quoted here. suggestion_validate_url() has already rejected the
// characters that would close that quote or break the line, so this scalar
// cannot be escaped out of.
$entry = sprintf(
    "---\ntimestamp: %s\ncode: %s\nurl: \"%s\"\n---\n\n### Suggestion\n\nThis site was suggested: %s\n\nSubmitted at: %s\n\n---\n",
    $timestamp,
    $code,
    $url,
    e($url),
    $timestamp
);

$filePath = suggestion_store_path();

// Check writability before attempting the append, so the failure we report names
// the real problem instead of a generic permissions guess. The path itself goes
// to the error log, never to the browser.
if (!is_writable(dirname($filePath))) {
    error_log('[suggestions] store directory not writable: ' . dirname($filePath));
    // The code was good and the suggestion was fine; this is our fault, so do
    // not make them retype it.
    suggestion_restore_code($outstanding);
    suggestion_reject(
        'Disk Trouble',
        '<p>The recording office is closed. We could not reach the suggestion '
            . 'store, so nothing was filed. Try again in a moment.</p>'
    );
}

if (file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX) === false) {
    error_log('[suggestions] append failed: ' . $filePath);
    suggestion_restore_code($outstanding);
    suggestion_reject(
        'Disk Trouble',
        '<p>We could not write your suggestion to disk. Check the store\'s '
            . 'write permissions. Nothing was filed.</p>'
    );
}

suggestion_message(
    'success',
    'Received',
    '<p>We have received your suggestion. For your sake, we hope the algorithm '
        . 'is pleased.</p><p><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
        . e($url) . '</a></p>'
);
