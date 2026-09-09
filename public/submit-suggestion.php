<?php
// Always set Content-Type for HTMX
header('Content-Type: text/html; charset=utf-8');

// Reject any request that isn't a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<p class="error-message">Method Not Allowed</p>';
    exit;
}

// Retrieve the suggestion input
$suggestion = isset($_POST['suggestion']) ? trim($_POST['suggestion']) : '';

// Verify that it starts with a 4-digit code
if (!preg_match('/^(\d{4})/', $suggestion, $matches)) {
    // Return 200 so HTMX swaps the warning message directly into the box
    echo '<div class="error-message"><p>Invalid suggestion format. Please include the 4-digit code at the beginning.</p></div>';
    exit;
}

$code = $matches[1];
$urlPart = trim(substr($suggestion, 4));

if (empty($urlPart)) {
    echo '<div class="error-message"><p>Please provide a valid URL.</p></div>';
    exit;
}

// Build the Markdown payload
$timestamp = date('c');
$entry = "---
timestamp: {$timestamp}
code: {$code}
url: {$urlPart}
---

### Suggestion

This site was suggested: {$urlPart}

Submitted at: {$timestamp}

---";

$filePath = __DIR__ . '/suggestions.md';

// Append to the file
if (file_put_contents($filePath, "\n\n" . $entry, FILE_APPEND | LOCK_EX) === false) {
    echo '<div class="error-message"><p>Error saving suggestion to disk. Check folder write permissions.</p></div>';
    exit;
}

// Success output
?>
<div id="suggestion-box">
    <div class="success-message">
        <p>We have received your suggestion. For your sake, we hope the algorithm is pleased.</p>
    </div>
</div>
