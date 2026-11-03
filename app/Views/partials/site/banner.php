<?php
/**
 * Bandeau « Recherche de stage », affiché si le réglage banner_enabled vaut « 1 ».
 * Sans variable : tout vient des réglages (banner_title, banner_text,
 * internship_start, internship_end).
 */

declare(strict_types=1);

if (setting('banner_enabled') !== '1') {
    return;
}

$title = setting('banner_title', 'Recherche de stage');
$text  = setting('banner_text');
$start = setting('internship_start');
$end   = setting('internship_end');

// « du 18 mai au 26 juin 2026 » : l'année n'est écrite qu'une fois si identique.
$period = '';
$from = date_fr($start);
$to   = date_fr($end);
if ($from !== '' && $to !== '') {
    if (substr($start, 0, 4) === substr($end, 0, 4)) {
        $from = preg_replace('/\s\d{4}$/', '', $from) ?? $from;
    }
    $period = 'du ' . $from . ' au ' . $to;
} elseif ($from !== '') {
    $period = 'à partir du ' . $from;
}
?>
<aside class="banner" aria-label="<?= e($title) ?>">
    <p class="banner__text">
        <?= icon('info', 20) ?>
        <span><strong><?= e($title) ?></strong><?= $text !== '' ? ' ' . e($text) : '' ?><?= $period !== '' ? ' : ' . e($period) : '' ?></span>
    </p>
    <a class="btn btn--code" href="/contact?sujet=stage">contact.sh --stage</a>
</aside>
