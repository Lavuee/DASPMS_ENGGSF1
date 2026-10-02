<?php
/*
    Minimal .env loader. Reads KEY=VALUE lines from the project-root .env file
    once per request. Real environment variables (e.g. set in Apache) take precedence.
    Secrets (DB password, SMTP password, PayMongo keys) belong in .env, never in source.
*/

function loadEnvFile($path) {
    static $loaded = false;

    if ($loaded || !is_readable($path)) {
        return;
    }

    $loaded = true;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));

        // Strip matching surrounding quotes.
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

/*
    Returns a configuration value. "true"/"false" strings become booleans;
    missing or empty values return $default.
*/
function env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }

    if ($value === null || $value === '') {
        return $default;
    }

    $lower = strtolower($value);

    if ($lower === 'true') return true;
    if ($lower === 'false') return false;

    return $value;
}

loadEnvFile(__DIR__ . '/../.env');
