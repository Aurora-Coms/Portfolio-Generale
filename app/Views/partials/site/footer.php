<?php
/**
 * Pied de page façon barre d'état d'éditeur.
 *
 * Variables :
 *   $siteName string
 *   $links    array{linkedin:string, github:string}  URL externes déjà validées (ou '')
 */

declare(strict_types=1);

$siteName = isset($siteName) ? (string) $siteName : setting('site_name', 'Alexandre Derbez');
$links    = isset($links) && is_array($links) ? $links : ['linkedin' => '', 'github' => ''];

// « stage de mai à juin 2026 », calculé depuis les dates du stage recherché.
$availability = '';
if (setting('banner_enabled') === '1') {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', setting('internship_start'));
    $end   = DateTimeImmutable::createFromFormat('!Y-m-d', setting('internship_end'));
    if ($start !== false) {
        $m1 = month_fr((int) $start->format('n'));
        $de = preg_match('/^[aeiouéèêâîôû]/u', $m1) ? "d'" : 'de ';
        if ($end === false) {
            $availability = 'stage à partir ' . $de . $m1 . ' ' . $start->format('Y');
        } elseif ($start->format('Y-m') === $end->format('Y-m')) {
            $availability = 'stage en ' . $m1 . ' ' . $start->format('Y');
        } elseif ($start->format('Y') === $end->format('Y')) {
            $availability = 'stage ' . $de . $m1 . ' à ' . month_fr((int) $end->format('n')) . ' ' . $end->format('Y');
        } else {
            $availability = 'stage ' . $de . $m1 . ' ' . $start->format('Y') . ' à '
                . month_fr((int) $end->format('n')) . ' ' . $end->format('Y');
        }
    }
}
?>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <p class="statusbar">
<?php if ($availability !== ''): ?>
            <span class="statusbar__available"><span aria-hidden="true">● </span>disponible · <?= e($availability) ?></span>
<?php endif; ?>
            <span>Lua</span>
            <span>UTF-8</span>
            <span>© <?= e(date('Y')) ?> <?= e($siteName) ?></span>
        </p>
        <nav aria-label="Liens du pied de page">
            <ul class="footer-links">
<?php if ($links['linkedin'] !== ''): ?>
                <li><a href="<?= e($links['linkedin']) ?>" rel="noopener noreferrer">LinkedIn</a></li>
<?php endif; ?>
<?php if ($links['github'] !== ''): ?>
                <li><a href="<?= e($links['github']) ?>" rel="noopener noreferrer">GitHub</a></li>
<?php endif; ?>
<?php if (App\Core\PdfUpload::exists()): ?>
                <li><a href="/cv">CV</a></li>
<?php endif; ?>
                <li><a href="/mentions-legales">Mentions légales</a></li>
            </ul>
        </nav>
    </div>
</footer>
