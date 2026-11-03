<?php
/**
 * Compétences par catégorie, avec l'échelle des niveaux (1 à 5).
 *
 * Variables (SkillController::index) :
 *   $categories list  SkillCategory::withSkills()
 */

declare(strict_types=1);

$categories = isset($categories) && is_array($categories) ? $categories : [];
// Une catégorie sans compétence n'apporte rien au visiteur.
$categories = array_values(array_filter($categories, static fn (array $c): bool => !empty($c['skills'])));
?>
<?= partial('site/page-header', [
    'comment' => '-- competences.lua',
    'title'   => 'Compétences',
    'lead'    => 'Ce que je sais faire aujourd\'hui, en réseaux, systèmes, cybersécurité et développement. Les niveaux sont auto-évalués et mis à jour au fil de la formation.',
]) ?>

<div class="page-body stack-lg">
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

<?php if ($categories === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 compétence',
    'title'   => 'Compétences en cours de rédaction',
    'text'    => 'La liste de mes compétences sera bientôt disponible.',
    'icon'    => 'tools',
]) ?>
<?php else: ?>
    <div class="grid grid--3">
<?php foreach ($categories as $category): ?>
<?= partial('site/skill-category', ['category' => $category, 'headingLevel' => 2]) ?>
<?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
