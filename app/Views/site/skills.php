<?php
/**
 * Compétences : thèmes généraux > sous-catégories (cartes) > compétences.
 *
 * En haut : introduction, liens d'ancre vers chaque thème et échelle des
 * niveaux (affichée une seule fois, seulement si au moins une compétence a
 * un niveau). Chaque thème est une section (h2 + icône) ; ses sous-catégories
 * sont des cartes. Un thème qui n'a qu'une sous-catégorie du même nom
 * (Langues, Savoir-être…) affiche une seule carte pleine largeur, sans
 * répéter le titre.
 *
 * Variables (SkillController::index) :
 *   $themes list  SkillTheme::tree() : thèmes et sous-catégories vides déjà exclus
 */

declare(strict_types=1);

$themes = isset($themes) && is_array($themes) ? $themes : [];

// Nombre de compétences par thème et présence d'au moins un niveau (légende).
$hasLevels = false;
foreach ($themes as &$theme) {
    $theme['skill_count'] = 0;
    foreach ($theme['categories'] as $category) {
        foreach ($category['skills'] as $skill) {
            $theme['skill_count']++;
            if (($skill['level'] ?? null) !== null) {
                $hasLevels = true;
            }
        }
    }
}
unset($theme);
?>
<?= partial('site/page-header', [
    'comment' => '-- competences.lua',
    'title'   => 'Compétences',
    'lead'    => 'Ce que je sais faire aujourd\'hui, rangé par grands thèmes. Les niveaux de 1 à 5 sont auto-évalués et mis à jour au fil de la formation.',
]) ?>

<div class="page-body stack-lg">
<?php if ($themes === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 compétence',
    'title'   => 'Compétences en cours de rédaction',
    'text'    => 'La liste de mes compétences sera bientôt disponible.',
    'icon'    => 'tools',
]) ?>
<?php else: ?>
    <nav class="theme-nav" aria-label="Thèmes de compétences">
        <ul class="theme-nav__list">
<?php foreach ($themes as $theme):
    $count = (int) $theme['skill_count'];
?>
            <li><a class="chip theme-nav__link" href="#theme-<?= e((string) $theme['slug']) ?>"><?= icon((string) ($theme['icon'] ?? ''), 16) ?><?= e((string) $theme['name']) ?><span class="chip__count"><?= $count ?><span class="sr-only"> compétence<?= $count > 1 ? 's' : '' ?></span></span></a></li>
<?php endforeach; ?>
        </ul>
    </nav>

<?php if ($hasLevels): ?>
    <section class="card card--pad level-legend" aria-labelledby="titre-echelle">
        <h2 class="card__title level-legend__title" id="titre-echelle">Échelle des niveaux</h2>
        <ol class="level-legend__list">
<?php for ($level = 1; $level <= 5; $level++): ?>
            <li class="level-legend__item">
                <span class="meter level-<?= $level ?>" aria-hidden="true"><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span></span>
                <span class="level-legend__label"><span class="level-legend__num"><?= $level ?></span> <?= e(level_label($level)) ?></span>
            </li>
<?php endfor; ?>
        </ol>
    </section>
<?php endif; ?>

<?php foreach ($themes as $theme):
    $slug       = (string) $theme['slug'];
    $titleId    = 'titre-theme-' . $slug;
    $categories = $theme['categories'];
    // Une seule sous-catégorie portant le nom du thème : pas de titre répété.
    $solo = count($categories) === 1
        && mb_strtolower(trim((string) $categories[0]['name'])) === mb_strtolower(trim((string) $theme['name']));
    $themeIcon = icon((string) ($theme['icon'] ?? ''), 22);
    if ($themeIcon === '') {
        $themeIcon = icon('code', 22);
    }
?>
    <section class="skill-theme" id="theme-<?= e($slug) ?>" aria-labelledby="<?= e($titleId) ?>">
        <div class="skill-theme__head">
            <p class="skill-theme__comment" aria-hidden="true">-- <?= e($slug) ?>/</p>
            <h2 class="skill-theme__title" id="<?= e($titleId) ?>"><span class="skill-theme__icon"><?= $themeIcon ?></span><?= e((string) $theme['name']) ?></h2>
        </div>
<?php if ($solo): ?>
<?= partial('site/skill-category', ['category' => $categories[0], 'showTitle' => false, 'wide' => true]) ?>
<?php else: ?>
        <div class="skill-theme__grid<?= in_array(count($categories), [2, 4], true) ? ' skill-theme__grid--pairs' : '' ?>">
<?php foreach ($categories as $category): ?>
<?= partial('site/skill-category', ['category' => $category, 'headingLevel' => 3]) ?>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </section>
<?php endforeach; ?>
<?php endif; ?>
</div>
