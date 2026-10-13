<?php
/*
    The public navigation links: the one source for the public header
    on every public page, so their wording and order never drift apart.

    Expects: $basePath ('' on the home page, '../' inside public_pages)
             $activePage ('services', 'parts', 'how-it-works', 'about', 'contact', or '' for none)
*/
$publicNavLinks = [
    'services' => ['Services', 'public_pages/services.php'],
    'parts' => ['Parts', 'public_pages/parts.php'],
    'how-it-works' => ['How it works', 'public_pages/how-it-works.php'],
    'about' => ['About', 'public_pages/about.php'],
    'contact' => ['Contact', 'public_pages/contact.php'],
];
$navBase = $basePath ?? '';
$navActive = $activePage ?? '';
?>
<ul class="pub-nav-list">
    <?php foreach ($publicNavLinks as $key => [$label, $url]): ?>
        <li>
            <a
                href="<?= $navBase . $url ?>"
                class="pub-nav-link<?= $navActive === $key ? ' is-active' : '' ?>"
                <?= $navActive === $key ? 'aria-current="page"' : '' ?>
            ><?= $label ?></a>
        </li>
    <?php endforeach; ?>
    <li><a href="<?= $navBase ?>views/login.php" class="pub-nav-link">Log in</a></li>
    <li class="pub-nav-cta-item"><a href="<?= $navBase ?>views/register.php" class="pub-nav-cta">Create account</a></li>
</ul>
