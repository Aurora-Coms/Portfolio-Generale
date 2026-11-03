<?php
/**
 * Niveau de compétence en 5 segments (classes level-1 à level-5).
 * Le niveau est annoncé en texte : « Niveau 4 sur 5 : Avancé ».
 *
 * Variables :
 *   $level int  1 à 5
 */

declare(strict_types=1);

$level = max(1, min(5, (int) ($level ?? 1)));
?>
<span class="meter level-<?= $level ?>" aria-hidden="true"><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span><span class="meter__seg"></span></span>
<span class="sr-only">Niveau <?= $level ?> sur 5 : <?= e(level_label($level)) ?></span>
