<?php
/**
 * Carte d'une sous-catégorie de compétences avec ses compétences.
 *
 * Compétence AVEC niveau : barre de 5 segments + libellé (level_label s'il
 * existe, sinon le libellé standard) ; le texte lu est « Niveau N sur 5 : Libellé ».
 * Compétence SANS niveau (level NULL) : pas de barre, nom + description
 * (savoir-être, atouts), présentés en liste.
 *
 * Variables :
 *   $category     array  ligne de SkillTheme::tree() (ou SkillCategory::withSkills()) :
 *                        name, slug, icon, skills[] (chaque compétence : name,
 *                        level 1-5 ou null, level_label, description)
 *   $headingLevel int    (optionnel) niveau du titre, 3 par défaut (2 si pas de h2 au-dessus)
 *   $showTitle    bool   (optionnel) afficher l'en-tête (icône + titre), défaut true.
 *                        false quand le thème n'a qu'une sous-catégorie du même nom.
 *   $wide         bool   (optionnel) carte pleine largeur : compétences en colonnes dès 640 px
 *   $reveal       bool   (optionnel) apparition au défilement (défaut true)
 */

declare(strict_types=1);

$category     = isset($category) && is_array($category) ? $category : [];
$headingLevel = max(2, min(4, (int) ($headingLevel ?? 3)));
$showTitle    = (bool) ($showTitle ?? true);
$wide         = (bool) ($wide ?? false);
$reveal       = (bool) ($reveal ?? true);
$skills       = is_array($category['skills'] ?? null) ? $category['skills'] : [];
$name         = (string) ($category['name'] ?? '');
$iconName     = (string) ($category['icon'] ?? '');
$iconSvg      = icon($iconName !== '' ? $iconName : 'code', 22);
if ($iconSvg === '') {
    $iconSvg = icon('code', 22);
}
$tag   = $showTitle ? 'section' : 'div';
$class = 'card skill-card' . ($wide ? ' skill-card--wide' : '');
?>
<<?= $tag ?> class="<?= $class ?>"<?= $reveal ? ' data-reveal' : '' ?>>
<?php if ($showTitle): ?>
    <div class="skill-card__head">
        <span class="skill-card__icon"><?= $iconSvg ?></span>
        <h<?= $headingLevel ?> class="skill-card__title"><?= e($name) ?></h<?= $headingLevel ?>>
    </div>
<?php endif; ?>
<?php if ($skills === []): ?>
    <p class="text-muted">Compétences à venir.</p>
<?php else: ?>
    <ul class="skill-list<?= $wide ? ' skill-list--cols' : '' ?>"<?= $showTitle ? '' : ' aria-label="' . e($name) . '"' ?>>
<?php foreach ($skills as $skill):
    $skillName   = (string) ($skill['name'] ?? '');
    $description = trim((string) ($skill['description'] ?? ''));
    $customLabel = trim((string) ($skill['level_label'] ?? ''));
    $hasLevel    = ($skill['level'] ?? null) !== null;
?>
<?php if ($hasLevel):
    $level = max(1, min(5, (int) $skill['level']));
?>
        <li class="skill">
            <div class="skill__head">
                <span class="skill__name"><?= e($skillName) ?></span>
<?php if ($customLabel !== ''): ?>
                <span class="skill__level skill__level--custom" aria-hidden="true"><?= e($customLabel) ?></span>
<?php else: ?>
                <span class="skill__level" aria-hidden="true"><?= e(level_label($level)) ?></span>
<?php endif; ?>
            </div>
<?= partial('site/skill-meter', ['level' => $level, 'label' => $customLabel]) ?>
<?php if ($description !== ''): ?>
            <p class="skill__desc"><?= e($description) ?></p>
<?php endif; ?>
        </li>
<?php else: ?>
        <li class="skill skill--plain">
            <div class="skill__head">
                <span class="skill__name"><span class="skill__mark" aria-hidden="true"><?= icon('check', 16) ?></span><?= e($skillName) ?></span>
<?php if ($customLabel !== ''): ?>
                <span class="skill__level skill__level--custom"><?= e($customLabel) ?></span>
<?php endif; ?>
            </div>
<?php if ($description !== ''): ?>
            <p class="skill__desc"><?= e($description) ?></p>
<?php endif; ?>
        </li>
<?php endif; ?>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</<?= $tag ?>>
