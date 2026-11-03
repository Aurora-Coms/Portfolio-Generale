<?php
/**
 * Pagination (site et administration).
 *
 * Variables :
 *   $page   int     page courante (1 = première)
 *   $pages  int     nombre total de pages ; rien n'est affiché si <= 1
 *   $path   string  chemin de la liste, ex. '/admin/messages' (défaut : chemin courant)
 *   $query  array   (optionnel) autres paramètres à conserver, ex. ['categorie' => 'roblox']
 *   $param  string  (optionnel) nom du paramètre de page, défaut 'page'
 */

declare(strict_types=1);

$pages = max(1, (int) ($pages ?? 1));
$page  = max(1, min($pages, (int) ($page ?? 1)));
$path  = (string) ($path ?? App\Core\Request::path());
$query = isset($query) && is_array($query) ? $query : [];
$param = (string) ($param ?? 'page');

if ($pages <= 1) {
    return;
}

$url = static function (int $n) use ($path, $query, $param): string {
    $q = $query;
    if ($n > 1) {
        $q[$param] = $n;
    } else {
        unset($q[$param]);
    }
    $qs = http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    return $path . ($qs !== '' ? '?' . $qs : '');
};

// Pages affichées : 1, …, page-1, page, page+1, …, dernière.
$numbers = [];
for ($n = 1; $n <= $pages; $n++) {
    if ($n === 1 || $n === $pages || abs($n - $page) <= 1) {
        $numbers[] = $n;
    } elseif (end($numbers) !== null) {
        $numbers[] = null;
    }
}
?>
<nav class="pagination" aria-label="Pagination">
    <ul class="pagination__list">
<?php if ($page > 1): ?>
        <li><a class="pagination__link" href="<?= e($url($page - 1)) ?>" rel="prev"><?= icon('arrow-left', 16) ?><span>Précédent</span></a></li>
<?php endif; ?>
<?php foreach ($numbers as $n): ?>
<?php if ($n === null): ?>
        <li><span class="pagination__gap" aria-hidden="true">…</span></li>
<?php else: ?>
        <li><a class="pagination__link" href="<?= e($url($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>><span class="sr-only">Page </span><?= $n ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($page < $pages): ?>
        <li><a class="pagination__link" href="<?= e($url($page + 1)) ?>" rel="next"><span>Suivant</span><?= icon('arrow', 16) ?></a></li>
<?php endif; ?>
    </ul>
</nav>
