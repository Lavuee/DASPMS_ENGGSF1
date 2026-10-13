<?php
/*
    Simple full-page message in the DASPMS design (same frame as the account pages:
    top bar, centered card, small footer; styles from assets/css/auth.css).
    Used for "not found" / "blocked" situations and the 404 page.

    Links are absolute from the app folder, so the page works from any folder and
    when Apache shows it for a mistyped URL.
*/

// URL path of the app folder, e.g. "/DASPMS_ENGGSF1"
function errorPageBase() {
    $root = realpath(__DIR__ . '/..');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($root && $docRoot && stripos($root, $docRoot) === 0) {
        return rtrim(str_replace('\\', '/', substr($root, strlen($docRoot))), '/');
    }

    return '';
}

/*
    $actions: list of ['label' => ..., 'href' => ... (relative to the app folder, or 'javascript:history.back()'), 'primary' => bool]
    Ends the request.
*/
function renderErrorPage($httpCode, $kicker, $heading, $message, array $actions = []) {
    if (!headers_sent()) {
        http_response_code($httpCode);
    }

    $base = errorPageBase();
    $asset = function ($path) use ($base) {
        $file = __DIR__ . '/../' . $path;
        return $base . '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    };
    $link = function ($href) use ($base) {
        return strpos($href, 'javascript:') === 0 ? $href : $base . '/' . ltrim($href, '/');
    };
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };

    if (empty($actions)) {
        $actions = [['label' => 'Back to home', 'href' => '', 'primary' => true]];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($heading) ?> | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="<?= $e($asset('assets/images/logo.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= $asset('assets/css/auth.css') ?>">
    <style>
        .error-code { margin: 0 0 6px; color: var(--ink-3); font-family: 'JetBrains Mono', ui-monospace, Consolas, monospace; font-size: 0.8rem; letter-spacing: 0.08em; }
        .error-actions { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
        .error-actions .auth-text-link { align-self: center; }
    </style>
</head>
<body class="auth-body auth-body-small">
    <header class="auth-topbar">
        <a href="<?= $e($link('')) ?>" class="auth-brand">
            <img src="<?= $e($asset('assets/images/logo.png')) ?>" alt="Norily's Vehicle Repair Shop logo" class="auth-brand-logo">
            <span class="auth-brand-text">
                <strong>Norily's</strong>
                <small>Vehicle Repair Shop</small>
            </span>
        </a>
        <a href="<?= $e($link('')) ?>" class="auth-back"><span aria-hidden="true">&larr;</span> Back to home</a>
    </header>

    <main class="auth-main">
        <section class="auth-card auth-card-small">
            <p class="error-code">Error <?= intval($httpCode) ?></p>
            <p class="auth-kicker"><?= $e($kicker) ?></p>
            <h1 class="auth-title"><?= $e($heading) ?></h1>
            <p class="auth-intro"><?= $e($message) ?></p>

            <div class="error-actions">
                <?php foreach ($actions as $action): ?>
                    <?php if (!empty($action['primary'])): ?>
                        <a href="<?= $e($link($action['href'])) ?>" class="auth-submit"><?= $e($action['label']) ?></a>
                    <?php else: ?>
                        <a href="<?= $e($link($action['href'])) ?>" class="auth-text-link"><?= $e($action['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="auth-footer">
        <span>&copy; <?= date('Y') ?> Norily's</span>
        <a href="<?= $e($link('public_pages/terms.php')) ?>">Terms</a>
        <a href="<?= $e($link('public_pages/privacy.php')) ?>">Privacy</a>
        <a href="<?= $e($link('public_pages/contact.php')) ?>">Help</a>
    </footer>
</body>
</html>
    <?php
    exit;
}
