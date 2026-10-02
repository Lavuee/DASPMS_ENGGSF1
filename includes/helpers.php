<?php
/*
    Small output helpers shared by the catalog pages (parts/services, public and customer).
*/

// Decodes stored text (some values are saved HTML-encoded) and falls back when empty.
function plainText($value, $fallback = '') {
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $value !== '' ? $value : $fallback;
}

// Same as plainText(), escaped for safe HTML output.
function displayText($value, $fallback = 'N/A') {
    return htmlspecialchars(plainText($value, $fallback), ENT_QUOTES, 'UTF-8');
}
