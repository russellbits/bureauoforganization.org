<?php
declare(strict_types=1);

/**
 * Hands out a single-use 4-digit captcha code, bound to this session.
 *
 * HTMX loads this into #code-value on page load and again after every submit,
 * so the code rotates whether the attempt worked or not. Only the digits are
 * echoed; the session copy is what submit-suggestion.php compares against.
 *
 * Requires a PHP host. `astro dev` serves public/ as static files, so under it
 * this source comes back as text -- SuggestionBox.astro detects that and shows
 * '----' rather than the file. Use `npm run dev:php` to exercise it locally.
 */

require __DIR__ . '/suggestion-common.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    suggestion_message('error', 'Method Not Allowed', '<p>Codes are handed out over GET only.</p>');
    exit;
}

if (!suggestion_session_start()) {
    // Without a session there is nothing to verify against later, so a code
    // here would be theatre. Say so instead of printing one.
    suggestion_message(
        'error',
        'System Message',
        '<p>The Bureau could not open a session, so it cannot check a code. '
            . 'Try again in a moment, or wave the suggestion through by hand.</p>'
    );
    exit;
}

echo suggestion_issue_code();
