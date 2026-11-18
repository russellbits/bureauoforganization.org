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
// strip it, rather than slipping past a pattern that stops at line end.
if (!preg_match('/^(\d{4})(.*)$/s', $value, $matches)) {
    suggestion_reject(
        'Incomplete Suggestion',
        '<p>Type the 4-digit code in front of your URL, '
            . 'then the address itself. Nothing else, please.</p>'
    );
}

$code = $matches[1];
$rest = $matches[2];

// Spends the code whether it matches or not, so a rejection cannot be replayed.
if (!suggestion_consume_code($code)) {
    suggestion_reject(
        'Wrong Code',
        '<p>That code is not the one we handed you, or it has already been used. '
            . 'A fresh one is waiting above the field.</p>'
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

$filePath = __DIR__ . '/suggestions.md';

if (file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX) === false) {
    suggestion_reject(
        'Disk Trouble',
        '<p>We could not write your suggestion to disk. Check the folder\'s '
            . 'write permissions.</p>'
    );
}

suggestion_message(
    'success',
    'Received',
    '<p>We have received your suggestion. For your sake, we hope the algorithm '
        . 'is pleased.</p><p><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
        . e($url) . '</a></p>'
);
