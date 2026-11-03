<?php
/**
 * Carte d'une catégorie de compétences avec ses compétences et leur niveau.
 *
 * Variables :
 *   $category     array  ligne de SkillCategory::withSkills() : name, slug, icon, skills[]
 *                        (chaque compétence : name, level 1-5, description)
 *   $headingLevel int    (optionnel) niveau du titre, 3 par défaut (2 si pas de h2 au-dessus)
 *   $reveal       bool   (optionnel) apparition au défilement (défaut true)
 */

declare(strict_types=1);

$category     = isset($category) && is_array($category) ? $category : [];
$headingLevel = max(2, min(4, (int) ($headingLevel ?? 3)));
$reveal       = (bool) ($reveal ?? true);
$skills       = is_array($category['skills'] ?? null) ? $category['skills'] : [];
$iconName     = (string) ($category['icon'] ?? '');
$iconSvg      = icon($iconName !== '' ? $iconName : 'code', 22);
if ($iconSvg === '') {
    $iconSvg = icon('code', 22);
}
?>
<section class="card skill-card"<?= $reveal ? ' data-reveal' : '' ?>>
    <div class="skill-card__head">
        <span class="skill-card__icon"><?= $iconSvg ?></span>
        <h<?= $headingLevel ?> class="skill-card__title"><?= e((string) ($category['name'] ?? '')) ?></h<?= $headingLevel ?>>
    </div>
<?php if ($skills === []): ?>
    <p class="text-muted">Compétences à venir.</p>
<?php else: ?>
    <ul class="skill-list">
<?php foreach ($skills as $skill):
    $level = max(1, min(5, (int) ($skill['level'] ?? 1)));
?>
        <li class="skill">
            <div class="skill__head">
                <span class="skill__name"><?= e((string) ($skill['name'] ?? '')) ?></span>
                <span class="skill__level" aria-hidden="true"><?= e(level_label($level)) ?></span>
            </div>
<?= partial('site/skill-meter', ['level' => $level]) ?>
<?php if (trim((string) ($skill['description'] ?? '')) !== ''): ?>
            <p class="skill__desc"><?= e((string) $skill['description']) ?></p>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
