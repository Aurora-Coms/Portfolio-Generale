<?php
/**
 * Tests de la couche d'accès aux données (CLI) :
 *   PF_DB_NAME=portfolio_test_models php tests/models-test.php
 *
 * Le script VIDE les tables de contenu de la base visée : il refuse donc de
 * tourner sur une base dont le nom ne commence pas par « portfolio_test_ ».
 * Il est relançable et nettoie derrière lui.
 */

declare(strict_types=1);

define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();
restore_error_handler();

use App\Core\Config;
use App\Core\Database;
use App\Models\Category;
use App\Models\Interest;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SkillTheme;
use App\Models\Tag;
use App\Models\TimelineEntry;

$dbName = (string) Config::get('db.name', '');
if (!str_starts_with($dbName, 'portfolio_test_')) {
    fwrite(STDERR, "Refus : base « {$dbName} ». Lancez avec PF_DB_NAME=portfolio_test_… (base jetable).\n");
    exit(2);
}

$failures = 0;
$count = 0;
function check(string $label, bool $ok): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures++;
        echo "ÉCHEC : {$label}\n";
    }
}

/** Vide les tables de contenu (base de test uniquement). */
function reset_tables(): void
{
    $pdo = Database::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['project_tags', 'project_categories', 'project_images', 'projects', 'categories', 'tags',
              'timeline_entries', 'skills', 'skill_categories', 'skill_themes', 'interests', 'messages'] as $table) {
        $pdo->exec('TRUNCATE TABLE ' . $table);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/** Nombre de requêtes préparées exécutées par $fn (compteur de session MariaDB/MySQL). */
function query_count(callable $fn): int
{
    $read = static fn (): int => (int) Database::fetch("SHOW SESSION STATUS LIKE 'Com_stmt_execute'")['Value'];
    $before = $read();
    $fn();
    $after = $read();
    return $after - $before - 1; // la lecture du compteur compte elle-même pour 1
}

/** Faux résultat d'ImageProcessor. */
function fake_file(string $name, int $w = 1600, int $h = 900): array
{
    return ['filename' => $name . '.webp', 'thumb_filename' => $name . '-thumb.webp', 'width' => $w, 'height' => $h];
}

/** @param list<array<string, mixed>> $rows */
function ids_of(array $rows): array
{
    return array_map(static fn (array $r): int => (int) $r['id'], $rows);
}

function throws(callable $fn, string $class = Throwable::class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

reset_tables();

// =============================================================================
//  Tag
// =============================================================================
$tLua   = Tag::create(['name' => 'Lua', 'slug' => 'lua']);
$tCisco = Tag::create(['name' => 'Cisco', 'slug' => 'cisco']);
$tLinux = Tag::create(['name' => 'Linux', 'slug' => 'linux', 'id' => 999, 'created_at' => '2000-01-01']);
check('Tag::create renvoie un id', $tLua > 0 && $tCisco > $tLua);
check('Tag::create ignore les colonnes hors liste blanche', $tLinux !== 999 && (string) Tag::find($tLinux)['created_at'] !== '2000-01-01 00:00:00');
check('Tag::create sans colonne autorisée lève une exception', throws(fn () => Tag::create(['evil' => 'x']), InvalidArgumentException::class));
check('Tag : clé SQL malveillante ignorée', throws(fn () => Tag::create(['name = 1; --' => 'x']), InvalidArgumentException::class));
check('Tag::find', Tag::find($tLua)['name'] === 'Lua');
check('Tag::find inexistant', Tag::find(424242) === null);
Tag::update($tLinux, ['name' => 'GNU/Linux', 'slug' => 'gnu-linux', 'id' => 1]);
check('Tag::update', Tag::find($tLinux)['slug'] === 'gnu-linux' && Tag::find(1)['name'] === 'Lua');
check('Tag::slugExists', Tag::slugExists('lua') && !Tag::slugExists('lua', $tLua) && !Tag::slugExists('python'));
check('Tag::nameExists insensible à la casse', Tag::nameExists('LUA') && !Tag::nameExists('lua', $tLua) && !Tag::nameExists('Python'));
$tTmp = Tag::create(['name' => 'Temporaire', 'slug' => 'temporaire']);

// =============================================================================
//  Category
// =============================================================================
$cRoblox = Category::create(['section' => 'game', 'name' => 'Roblox', 'slug' => 'roblox', 'position' => 1]);
$cScifi  = Category::create(['section' => 'game', 'name' => 'Sci-fi', 'slug' => 'sci-fi', 'position' => 2, 'description' => 'Futur']);
$cEmpty  = Category::create(['section' => 'game', 'name' => 'Aventure', 'slug' => 'aventure', 'position' => 0]);
$cReseau = Category::create(['section' => 'tech', 'name' => 'Réseau', 'slug' => 'reseau', 'position' => 1]);
$cSys    = Category::create(['section' => 'tech', 'name' => 'Systèmes', 'slug' => 'systemes', 'position' => 0]);
check('Category::create / find', Category::find($cScifi)['description'] === 'Futur' && Category::find($cScifi)['section'] === 'game');
check('Category::find inexistant', Category::find(424242) === null);
Category::update($cScifi, ['description' => 'Science-fiction', 'section' => 'game', 'bogus' => 1]);
check('Category::update', Category::find($cScifi)['description'] === 'Science-fiction');
check('Category::slugExists par section', Category::slugExists('game', 'roblox') && !Category::slugExists('tech', 'roblox'));
check('Category::slugExists avec exception', !Category::slugExists('game', 'roblox', $cRoblox));
$all = Category::all();
check('Category::all triées par section puis position',
    ids_of($all) === [$cEmpty, $cRoblox, $cScifi, $cSys, $cReseau]);
check('Category::all a project_count entier', $all[0]['project_count'] === 0);

// =============================================================================
//  Project : création, liaisons
// =============================================================================
$base = ['summary' => 'Résumé', 'status' => 'done'];
$pA = Project::create($base + ['section' => 'game', 'title' => 'Jeu A', 'slug' => 'jeu-a', 'position' => 1,
        'project_date' => '2025-01-10', 'is_published' => true, 'is_featured' => true],
    [$cRoblox, (string) $cScifi, $cReseau, 424242, 'x', -3, $cRoblox], [$tLua, $tCisco, 424242]);
$pB = Project::create($base + ['section' => 'game', 'title' => 'Jeu B', 'slug' => 'jeu-b', 'position' => 1,
        'project_date' => '2025-06-01', 'is_published' => 1], [$cRoblox], []);
$pC = Project::create($base + ['section' => 'game', 'title' => 'Jeu C', 'slug' => 'jeu-c', 'position' => 0,
        'project_date' => null, 'is_published' => 1], [], [$tLua]);
$pD = Project::create($base + ['section' => 'game', 'title' => 'Brouillon', 'slug' => 'brouillon', 'position' => 0,
        'is_published' => 0, 'is_featured' => 1], [$cScifi, $cEmpty], []);
$pT = Project::create($base + ['section' => 'tech', 'title' => 'Réseau VLAN', 'slug' => 'reseau-vlan', 'position' => 0,
        'project_date' => '2024-03-01', 'is_published' => 1, 'is_featured' => 1], [$cReseau, $cRoblox], [$tCisco, $tLinux]);
$pE = Project::create($base + ['section' => 'game', 'title' => 'Jeu E', 'slug' => 'jeu-e', 'position' => 1,
        'project_date' => '2025-06-01', 'is_published' => 1], [], []);

$a = Project::find($pA);
check('Project::find ligne brute', $a['title'] === 'Jeu A' && (int) $a['is_published'] === 1 && $a['section'] === 'game');
check('Project::create : catégories d\'une autre section, inexistantes et invalides ignorées',
    $a['category_ids'] === [min($cRoblox, $cScifi), max($cRoblox, $cScifi)]);
check('Project::create : tags inexistants ignorés', $a['tag_ids'] === [$tLua, $tCisco]);
check('Project::find : ids entiers', is_int($a['category_ids'][0]) && is_int($a['tag_ids'][0]));
check('Project::find inexistant', Project::find(424242) === null);
check('Project::create (tech) : catégorie « game » ignorée', Project::find($pT)['category_ids'] === [$cReseau]);
check('Project::create transaction : échec => rien créé', throws(fn () => Project::create(
    $base + ['section' => 'game', 'title' => 'Doublon', 'slug' => 'jeu-a'], [$cRoblox], [])) && Project::counts()['game'] === 5);
check('Project::create : youtube_id invalide refusé par la base', throws(fn () => Project::create(
    $base + ['section' => 'game', 'title' => 'YT', 'slug' => 'yt', 'youtube_id' => '<script>'], [], [])));

// =============================================================================
//  ProjectImage
// =============================================================================
$i1 = ProjectImage::add($pA, fake_file('a1'), '  Capture 1  ');
$i2 = ProjectImage::add($pA, fake_file('a2', 800, 600), 'Capture 2');
$i3 = ProjectImage::add($pA, fake_file('a3'), str_repeat('é', 250));
$positions = static fn (int $p): array => array_map(
    static fn (array $r): array => [(int) $r['id'], (int) $r['position']], ProjectImage::forProject($p));
check('ProjectImage::add : positions 0, 1, 2', $positions($pA) === [[$i1, 0], [$i2, 1], [$i3, 2]]);
check('ProjectImage::add : alt nettoyé', ProjectImage::find($i1)['alt_text'] === 'Capture 1');
check('ProjectImage::add : alt tronqué à 200', mb_strlen((string) ProjectImage::find($i3)['alt_text']) === 200);
check('ProjectImage::find', ProjectImage::find($i2)['filename'] === 'a2.webp' && (int) ProjectImage::find($i2)['width'] === 800);
check('ProjectImage::find inexistant', ProjectImage::find(424242) === null);
check('ProjectImage::countForProject', ProjectImage::countForProject($pA) === 3 && ProjectImage::countForProject($pB) === 0);
ProjectImage::updateAlt($i2, 'Nouvelle légende');
check('ProjectImage::updateAlt', ProjectImage::find($i2)['alt_text'] === 'Nouvelle légende');

ProjectImage::moveUp($i1);
check('ProjectImage::moveUp en tête : aucun effet', $positions($pA) === [[$i1, 0], [$i2, 1], [$i3, 2]]);
ProjectImage::moveDown($i3);
check('ProjectImage::moveDown en dernier : aucun effet', $positions($pA) === [[$i1, 0], [$i2, 1], [$i3, 2]]);
ProjectImage::moveUp($i3);
check('ProjectImage::moveUp échange avec la voisine', $positions($pA) === [[$i1, 0], [$i3, 1], [$i2, 2]]);
ProjectImage::moveDown($i1);
check('ProjectImage::moveDown échange avec la voisine', $positions($pA) === [[$i3, 0], [$i1, 1], [$i2, 2]]);
ProjectImage::makeCover($i2);
check('ProjectImage::makeCover : en tête puis renumérotée', $positions($pA) === [[$i2, 0], [$i3, 1], [$i1, 2]]);
// Positions en désordre (doublons) : makeCover et move les remettent d'aplomb.
Database::execute('UPDATE project_images SET position = 5 WHERE project_id = ?', [$pA]);
ProjectImage::makeCover($i3);
check('ProjectImage::makeCover renumérote 0..n-1 malgré des doublons', $positions($pA) === [[$i3, 0], [$i1, 1], [$i2, 2]]);
ProjectImage::moveUp(424242);
ProjectImage::makeCover(424242);
check('ProjectImage : déplacement d\'une image inexistante sans effet', $positions($pA) === [[$i3, 0], [$i1, 1], [$i2, 2]]);

$iT1 = ProjectImage::add($pT, fake_file('t1'), 'Schéma');
$iD1 = ProjectImage::add($pD, fake_file('d1'), 'Brouillon');
$iE1 = ProjectImage::add($pE, fake_file('e1'), 'Jeu E');
$deleted = ProjectImage::delete($iE1);
check('ProjectImage::delete renvoie la ligne', $deleted !== null && $deleted['filename'] === 'e1.webp');
check('ProjectImage::delete : ligne supprimée', ProjectImage::find($iE1) === null);
check('ProjectImage::delete inexistant => null', ProjectImage::delete(424242) === null);
$iA4 = ProjectImage::add($pA, fake_file('a4'), 'A4');
ProjectImage::delete($i1);
check('ProjectImage::delete renumérote', $positions($pA) === [[$i3, 0], [$i2, 1], [$iA4, 2]]);
check('ProjectImage::add après suppression : max + 1', (int) ProjectImage::find(ProjectImage::add($pA, fake_file('a5'), ''))['position'] === 3);

// =============================================================================
//  Project : lectures publiques
// =============================================================================
$games = Project::published('game');
// Tri : position ASC, project_date DESC (NULL en dernier), id DESC.
check('Project::published : tri et brouillons exclus', ids_of($games) === [$pC, $pE, $pB, $pA]);
$gA = $games[3];
check('Project::published : cover = image de plus petite position', $gA['cover'] === [
    'thumb_filename' => 'a3-thumb.webp', 'filename' => 'a3.webp', 'alt_text' => str_repeat('é', 200), 'width' => 1600, 'height' => 900]);
check('Project::published : cover null sans image', $games[0]['cover'] === null);
check('Project::published : categories [id, name, slug] par position', $gA['categories'] === [
    ['id' => $cRoblox, 'name' => 'Roblox', 'slug' => 'roblox'], ['id' => $cScifi, 'name' => 'Sci-fi', 'slug' => 'sci-fi']]);
check('Project::published : tags triés par nom', $gA['tags'] === [
    ['id' => $tCisco, 'name' => 'Cisco', 'slug' => 'cisco'], ['id' => $tLua, 'name' => 'Lua', 'slug' => 'lua']]);
check('Project::published : listes vides', $games[1]['categories'] === [] && $games[1]['tags'] === []);
check('Project::published par catégorie', ids_of(Project::published('game', 'roblox')) === [$pB, $pA]);
check('Project::published catégorie sans projet publié', Project::published('game', 'aventure') === []);
check('Project::published catégorie d\'une autre section', Project::published('tech', 'roblox') === []);
check('Project::published slug vide = sans filtre', ids_of(Project::published('game', '')) === ids_of($games));
check('Project::published section inconnue', Project::published('admin') === []);
check('Project::published tech', ids_of(Project::published('tech')) === [$pT]);
check('Project::published sans N+1 (4 requêtes pour 4 projets)', query_count(fn () => Project::published('game')) === 4);
check('Project::featured sans N+1', query_count(fn () => Project::featured(10)) === 4);

$featured = Project::featured();
check('Project::featured : publiés et mis en avant', ids_of($featured) === [$pT, $pA]);
check('Project::featured : même format', array_key_exists('cover', $featured[0]) && $featured[0]['tags'][0]['name'] === 'Cisco');
check('Project::featured limite', ids_of(Project::featured(1)) === [$pT]);
check('Project::featured limite bornée', count(Project::featured(0)) === 1 && count(Project::featured(-5)) === 1);

$detail = Project::findPublishedBySlug('jeu-a');
check('Project::findPublishedBySlug', $detail !== null && $detail['id'] === $pA && $detail['cover']['filename'] === 'a3.webp');
check('Project::findPublishedBySlug : toutes les images par position',
    array_column($detail['images'], 'filename') === ['a3.webp', 'a2.webp', 'a4.webp', 'a5.webp']
    && array_map('intval', array_column($detail['images'], 'position')) === [0, 1, 2, 3]);
check('Project::findPublishedBySlug : 5 requêtes (projet, 3 hydratations, images)', query_count(fn () => Project::findPublishedBySlug('jeu-a')) === 5);
check('Project::findPublishedBySlug : brouillon invisible', Project::findPublishedBySlug('brouillon') === null);
check('Project::findPublishedBySlug : inconnu', Project::findPublishedBySlug('nope') === null);

// =============================================================================
//  Project : administration
// =============================================================================
$admin = Project::adminList();
check('Project::adminList : tous, brouillons compris', count($admin) === 6 && in_array($pD, ids_of($admin), true));
$byId = array_column($admin, null, 'id');
check('Project::adminList : image_count', $byId[$pA]['image_count'] === 4 && $byId[$pB]['image_count'] === 0);
check('Project::adminList : categories', array_column($byId[$pD]['categories'], 'slug') === ['aventure', 'sci-fi']);
check('Project::adminList par section', ids_of(Project::adminList('tech')) === [$pT]);
check('Project::adminList section inconnue', Project::adminList('x') === []);

check('Project::counts', Project::counts() === ['game' => 5, 'tech' => 1, 'published' => 5, 'drafts' => 1, 'featured' => 3]);

Project::toggle($pD, 'is_published');
check('Project::toggle is_published', (int) Project::find($pD)['is_published'] === 1);
Project::toggle($pD, 'is_published');
Project::toggle($pA, 'is_featured');
check('Project::toggle is_featured', (int) Project::find($pA)['is_featured'] === 0 && (int) Project::find($pD)['is_published'] === 0);
check('Project::toggle champ hors liste blanche', throws(fn () => Project::toggle($pA, 'section'), InvalidArgumentException::class)
    && throws(fn () => Project::toggle($pA, 'is_published = 1, title'), InvalidArgumentException::class));

Project::update($pA, ['title' => 'Jeu A modifié', 'id' => 777, 'slug' => 'jeu-a', 'created_at' => '2000-01-01 00:00:00'],
    [$cScifi, $cReseau], [$tLinux]);
$a = Project::find($pA);
check('Project::update colonnes', $a['title'] === 'Jeu A modifié' && Project::find(777) === null && $a['created_at'] !== '2000-01-01 00:00:00');
check('Project::update remplace les liaisons', $a['category_ids'] === [$cScifi] && $a['tag_ids'] === [$tLinux]);
Project::update($pA, ['section' => 'tech'], [$cScifi, $cReseau, $cSys], []);
$a = Project::find($pA);
check('Project::update changement de section : catégories de la nouvelle section', $a['section'] === 'tech'
    && $a['category_ids'] === [min($cReseau, $cSys), max($cReseau, $cSys)] && $a['tag_ids'] === []);
Project::update($pA, ['section' => 'game'], [$cRoblox], [$tLua, $tCisco]);
check('Project::update transaction : échec => liaisons intactes', throws(fn () => Project::update($pA, ['slug' => 'jeu-b'], [], []))
    && Project::find($pA)['category_ids'] === [$cRoblox] && Project::find($pA)['slug'] === 'jeu-a');
Project::update(424242, ['title' => 'x'], [$cRoblox], []);
check('Project::update inexistant sans effet', Project::find(424242) === null);

check('Project::uniqueSlug libre', Project::uniqueSlug('Nouveau Jeu !') === 'nouveau-jeu');
check('Project::uniqueSlug pris => suffixe', Project::uniqueSlug('Jeu A') === 'jeu-a-2');
$pA2 = Project::create($base + ['section' => 'game', 'title' => 'Jeu A bis', 'slug' => 'jeu-a-2'], [], []);
check('Project::uniqueSlug suffixe suivant', Project::uniqueSlug('jeu-a') === 'jeu-a-3');
check('Project::uniqueSlug exception', Project::uniqueSlug('jeu-a', $pA) === 'jeu-a');
check('Project::uniqueSlug base vide', Project::uniqueSlug('!!!') === 'projet');
check('Project::uniqueSlug longueur bornée', strlen(Project::uniqueSlug(str_repeat('a', 300))) <= 140);
Project::delete($pA2);

// =============================================================================
//  Category / Tag : compteurs
// =============================================================================
$game = Category::forSection('game');
$byId = array_column($game, null, 'id');
check('Category::forSection par position', ids_of($game) === [$cEmpty, $cRoblox, $cScifi]);
check('Category::forSection project_count = projets publiés', $byId[$cRoblox]['project_count'] === 2
    && $byId[$cScifi]['project_count'] === 0 && $byId[$cEmpty]['project_count'] === 0);
check('Category::forSection onlyUsed', ids_of(Category::forSection('game', true)) === [$cRoblox]);
check('Category::forSection section inconnue', Category::forSection('x') === []);
check('Category::all project_count (brouillons compris)', array_column(Category::all(), 'project_count', 'id')[$cScifi] === 1);
$tags = array_column(Tag::all(), null, 'id');
check('Tag::all par nom + project_count', array_column(Tag::all(), 'name') === ['Cisco', 'GNU/Linux', 'Lua', 'Temporaire']
    && $tags[$tLua]['project_count'] === 2 && $tags[$tTmp]['project_count'] === 0);

// Suppressions en cascade des liaisons.
ProjectImage::add($pB, fake_file('b1'), 'B');
check('Tag::delete', Tag::delete($tCisco) && Tag::find($tCisco) === null && Project::find($pA)['tag_ids'] === [$tLua]
    && Project::find($pT)['tag_ids'] === [$tLinux]);
check('Tag::delete inexistant', !Tag::delete(424242));
check('Category::delete cascade liaisons', Category::delete($cRoblox) && Project::find($pA)['category_ids'] === []
    && Project::published('game', 'roblox') === []);
check('Category::delete inexistant', !Category::delete(424242));

// =============================================================================
//  Project::delete
// =============================================================================
$images = Project::delete($pA);
check('Project::delete renvoie les images', array_column($images, 'filename') === ['a3.webp', 'a2.webp', 'a4.webp', 'a5.webp']);
check('Project::delete : projet supprimé', Project::find($pA) === null);
check('Project::delete : images en cascade', ProjectImage::countForProject($pA) === 0
    && (int) Database::value('SELECT COUNT(*) FROM project_images WHERE project_id = ?', [$pA]) === 0);
check('Project::delete : liaisons en cascade',
    (int) Database::value('SELECT COUNT(*) FROM project_tags WHERE project_id = ?', [$pA]) === 0
    && (int) Database::value('SELECT COUNT(*) FROM project_categories WHERE project_id = ?', [$pA]) === 0);
check('Project::delete inexistant => liste vide', Project::delete(424242) === []);

// =============================================================================
//  TimelineEntry
// =============================================================================
$e1 = TimelineEntry::create(['type' => 'education', 'title' => 'BTS CIEL option IR', 'organization' => 'La Salle Avignon',
    'start_date' => '2025-09-01', 'date_precision' => 'year', 'is_published' => 1, 'position' => 0]);
$e2 = TimelineEntry::create(['type' => 'internship', 'title' => 'Stage maintenance', 'organization' => 'CNRS LNC',
    'location' => 'Marseille', 'start_date' => '2024-05-13', 'end_date' => '2024-06-14', 'is_published' => 1]);
$e3 = TimelineEntry::create(['type' => 'other', 'title' => 'Brouillon', 'organization' => 'X',
    'start_date' => '2026-01-01', 'is_published' => 0]);
$e4 = TimelineEntry::create(['type' => 'internship', 'title' => 'Stage 2', 'organization' => 'Mistral',
    'start_date' => '2024-05-13', 'position' => 1]);
check('TimelineEntry::create : défauts', (int) TimelineEntry::find($e4)['is_published'] === 1
    && TimelineEntry::find($e4)['date_precision'] === 'month' && TimelineEntry::find($e4)['end_date'] === null);
check('TimelineEntry::published : tri start_date DESC puis position', ids_of(TimelineEntry::published()) === [$e1, $e2, $e4]);
check('TimelineEntry::all : brouillons compris', ids_of(TimelineEntry::all()) === [$e3, $e1, $e2, $e4]);
TimelineEntry::update($e3, ['is_published' => true, 'title' => 'Publié', 'id' => 1]);
check('TimelineEntry::update', TimelineEntry::find($e3)['title'] === 'Publié' && count(TimelineEntry::published()) === 4
    && TimelineEntry::find($e1)['title'] === 'BTS CIEL option IR');
check('TimelineEntry : dates incohérentes refusées', throws(fn () => TimelineEntry::update($e2, ['end_date' => '2020-01-01'])));
check('TimelineEntry::delete', TimelineEntry::delete($e3) && TimelineEntry::find($e3) === null && !TimelineEntry::delete($e3));
check('TimelineEntry::find inexistant', TimelineEntry::find(424242) === null);

// =============================================================================
//  SkillTheme
// =============================================================================
$thInfo  = SkillTheme::create(['name' => 'Informatique & réseaux', 'slug' => 'informatique-reseaux', 'icon' => 'network', 'position' => 2]);
$thDev   = SkillTheme::create(['name' => 'Développement', 'slug' => 'developpement', 'icon' => 'code', 'position' => 1]);
$thEmpty = SkillTheme::create(['name' => 'Langues', 'slug' => 'langues', 'icon' => 'globe', 'position' => 3, 'id' => 999]);
check('SkillTheme::create / find', $thEmpty !== 999 && SkillTheme::find($thInfo)['name'] === 'Informatique & réseaux'
    && SkillTheme::find($thDev)['icon'] === 'code');
check('SkillTheme::find inexistant', SkillTheme::find(424242) === null);
check('SkillTheme::create sans colonne autorisée lève une exception',
    throws(fn () => SkillTheme::create(['category_count' => 3]), InvalidArgumentException::class));
check('SkillTheme : slug en double refusé', throws(fn () => SkillTheme::create(['name' => 'X', 'slug' => 'langues'])));
SkillTheme::update($thEmpty, ['icon' => 'book', 'category_count' => 9, 'slug' => 'langues']);
check('SkillTheme::update (liste blanche)', SkillTheme::find($thEmpty)['icon'] === 'book');
check('SkillTheme::slugExists', SkillTheme::slugExists('langues') && !SkillTheme::slugExists('langues', $thEmpty)
    && !SkillTheme::slugExists('autre'));

// =============================================================================
//  SkillCategory / Skill
// =============================================================================
$scNet = SkillCategory::create(['theme_id' => $thInfo, 'name' => 'Réseau', 'slug' => 'reseau', 'icon' => 'network', 'position' => 1]);
$scLua = SkillCategory::create(['theme_id' => $thDev, 'name' => 'Scripting / Lua', 'slug' => 'lua', 'icon' => 'code', 'position' => 0]);
$scEmpty = SkillCategory::create(['theme_id' => $thInfo, 'name' => 'Vide', 'slug' => 'vide', 'position' => 2]);
check('SkillCategory::find', SkillCategory::find($scNet)['icon'] === 'network' && (int) SkillCategory::find($scNet)['theme_id'] === $thInfo);
check('SkillCategory : thème obligatoire', throws(fn () => SkillCategory::create(['name' => 'X', 'slug' => 'x'])));
check('SkillCategory : thème inexistant refusé', throws(fn () => SkillCategory::create(['theme_id' => 424242, 'name' => 'X', 'slug' => 'x'])));
SkillCategory::update($scNet, ['icon' => 'server', 'skill_count' => 9, 'theme_name' => 'x']);
check('SkillCategory::update', SkillCategory::find($scNet)['icon'] === 'server');
check('SkillCategory::slugExists', SkillCategory::slugExists('lua') && !SkillCategory::slugExists('lua', $scLua)
    && !SkillCategory::slugExists('autre'));

$sk1 = Skill::create(['category_id' => $scNet, 'name' => 'VLAN', 'level' => 3, 'position' => 1]);
$sk2 = Skill::create(['category_id' => $scNet, 'name' => 'Routage', 'level' => 2, 'position' => 0, 'description' => 'OSPF']);
$sk3 = Skill::create(['category_id' => $scLua, 'name' => 'Lua', 'level' => 4]);
check('Skill::find', Skill::find($sk2)['description'] === 'OSPF' && (int) Skill::find($sk1)['level'] === 3);
check('Skill : niveau hors bornes refusé', throws(fn () => Skill::create(['category_id' => $scNet, 'name' => 'X', 'level' => 9])));
check('Skill : niveau 0 refusé', throws(fn () => Skill::create(['category_id' => $scNet, 'name' => 'X', 'level' => 0])));
check('Skill : catégorie inexistante refusée', throws(fn () => Skill::create(['category_id' => 424242, 'name' => 'X', 'level' => 1])));
check('Skill::all + category_name, theme_name, triées par thème',
    ids_of(Skill::all()) === [$sk3, $sk2, $sk1] && Skill::all()[0]['category_name'] === 'Scripting / Lua'
    && Skill::all()[0]['theme_name'] === 'Développement');
Skill::update($sk1, ['level' => 5, 'category_id' => $scLua]);
check('Skill::update', (int) Skill::find($sk1)['level'] === 5 && (int) Skill::find($sk1)['category_id'] === $scLua);
Skill::update($sk1, ['category_id' => $scNet]);

// Niveau facultatif (NULL = sans barre) et libellé personnalisé du niveau.
$skNull = Skill::create(['category_id' => $scLua, 'name' => 'Travail en équipe', 'level' => null, 'position' => 5]);
$skDef  = Skill::create(['category_id' => $scLua, 'name' => 'Autonomie', 'position' => 6]);
check('Skill : level NULL accepté', Skill::find($skNull)['level'] === null && Skill::find($skDef)['level'] === null
    && Skill::find($skNull)['level_label'] === null);
$skLang = Skill::create(['category_id' => $scLua, 'name' => 'Anglais', 'level' => 2, 'level_label' => 'A2', 'position' => 7]);
check('Skill : level_label enregistré', Skill::find($skLang)['level_label'] === 'A2' && (int) Skill::find($skLang)['level'] === 2);
Skill::update($skLang, ['level_label' => 'Langue maternelle', 'level' => 5]);
check('Skill::update level_label', Skill::find($skLang)['level_label'] === 'Langue maternelle');
Skill::update($skLang, ['level_label' => null, 'level' => null]);
check('Skill::update vers NULL', Skill::find($skLang)['level'] === null && Skill::find($skLang)['level_label'] === null);
check('Skill : level_label > 40 caractères refusé',
    throws(fn () => Skill::create(['category_id' => $scLua, 'name' => 'X', 'level' => 1, 'level_label' => str_repeat('a', 41)])));
Skill::delete($skNull);
Skill::delete($skDef);
Skill::delete($skLang);

$with = SkillCategory::withSkills();
check('SkillCategory::withSkills : catégories par position', ids_of($with) === [$scLua, $scNet, $scEmpty]);
check('SkillCategory::withSkills : skills par position', ids_of($with[1]['skills']) === [$sk2, $sk1] && $with[2]['skills'] === []);
check('SkillCategory::withSkills : 2 requêtes', query_count(fn () => SkillCategory::withSkills()) === 2);
$allSc = array_column(SkillCategory::all(), 'skill_count', 'id');
check('SkillCategory::all + skill_count, triées par thème puis position', $allSc === [$scLua => 1, $scNet => 2, $scEmpty => 0]);
check('SkillCategory::all + theme_name', array_column(SkillCategory::all(), 'theme_name', 'id')
    === [$scLua => 'Développement', $scNet => 'Informatique & réseaux', $scEmpty => 'Informatique & réseaux']);
check('SkillTheme::all + category_count, par position', array_column(SkillTheme::all(), 'category_count', 'id')
    === [$thDev => 1, $thInfo => 2, $thEmpty => 0]);

// Arborescence : thèmes > sous-catégories > compétences.
$tree = SkillTheme::tree();
check('SkillTheme::tree : thèmes par position, thème vide exclu', ids_of($tree) === [$thDev, $thInfo]);
check('SkillTheme::tree : sous-catégorie vide exclue', ids_of($tree[1]['categories']) === [$scNet]);
check('SkillTheme::tree : compétences par position', ids_of($tree[1]['categories'][0]['skills']) === [$sk2, $sk1]
    && ids_of($tree[0]['categories'][0]['skills']) === [$sk3]);
check('SkillTheme::tree : 3 requêtes', query_count(fn () => SkillTheme::tree()) === 3);
$full = SkillTheme::tree(true);
check('SkillTheme::tree(true) : tout est inclus', ids_of($full) === [$thDev, $thInfo, $thEmpty]
    && ids_of($full[1]['categories']) === [$scNet, $scEmpty] && $full[1]['categories'][1]['skills'] === []
    && $full[2]['categories'] === []);
check('SkillTheme::tree(true) : 3 requêtes', query_count(fn () => SkillTheme::tree(true)) === 3);

// Changement de thème d'une sous-catégorie.
SkillCategory::update($scEmpty, ['theme_id' => $thEmpty]);
check('SkillCategory::update theme_id', (int) SkillCategory::find($scEmpty)['theme_id'] === $thEmpty
    && ids_of(SkillTheme::tree(true)[2]['categories']) === [$scEmpty]);
check('SkillCategory::update vers un thème inexistant refusé', throws(fn () => SkillCategory::update($scEmpty, ['theme_id' => 424242])));

// Suppression : refusée tant que le thème contient des sous-catégories.
check('SkillTheme::delete non vide => false', SkillTheme::delete($thEmpty) === false && SkillTheme::find($thEmpty) !== null);
check('SkillTheme : suppression SQL directe bloquée par la clé étrangère',
    throws(fn () => Database::execute('DELETE FROM skill_themes WHERE id = ?', [$thInfo]), PDOException::class));

check('SkillCategory::delete non vide => false', SkillCategory::delete($scNet) === false && SkillCategory::find($scNet) !== null);
check('SkillCategory::delete vide => true', SkillCategory::delete($scEmpty) === true && SkillCategory::find($scEmpty) === null);
check('SkillTheme::delete après vidage => true', SkillTheme::delete($thEmpty) === true && SkillTheme::find($thEmpty) === null);
check('SkillTheme::delete inexistant => true', SkillTheme::delete($thEmpty) === true);
check('Skill::delete', Skill::delete($sk3) && Skill::find($sk3) === null && !Skill::delete($sk3));
check('SkillCategory::delete après vidage', SkillCategory::delete($scLua) === true);
check('SkillTheme::delete Développement vidé', SkillTheme::delete($thDev) === true && ids_of(SkillTheme::all()) === [$thInfo]);

// =============================================================================
//  Interest
// =============================================================================
$in1 = Interest::create(['label' => 'VTT', 'icon' => 'bike', 'position' => 1]);
$in2 = Interest::create(['label' => 'Roblox Studio', 'icon' => 'gamepad', 'position' => 0, 'description' => 'Lua']);
check('Interest::all par position', ids_of(Interest::all()) === [$in2, $in1]);
check('Interest::find', Interest::find($in2)['description'] === 'Lua' && Interest::find(424242) === null);
Interest::update($in1, ['description' => 'Descente', 'label' => 'VTT']);
check('Interest::update', Interest::find($in1)['description'] === 'Descente');
check('Interest::delete', Interest::delete($in1) && Interest::all() !== [] && Interest::find($in1) === null);

// =============================================================================
//  Message
// =============================================================================
$ids = [];
for ($i = 1; $i <= 25; $i++) {
    $ids[$i] = Message::create("Nom {$i}", "m{$i}@exemple.fr", "Sujet {$i}", "Corps <b>{$i}</b>");
}
// Dates distinctes : le message 25 est le plus récent.
foreach ($ids as $i => $id) {
    Database::execute('UPDATE messages SET created_at = NOW() - INTERVAL ? MINUTE WHERE id = ?', [100 - $i, $id]);
}
$m = Message::find($ids[1]);
check('Message::create / find', $m['email'] === 'm1@exemple.fr' && $m['body'] === 'Corps <b>1</b>'
    && (int) $m['is_read'] === 0 && (int) $m['mail_sent'] === 0);
check('Message::find inexistant', Message::find(424242) === null);
Message::markMailSent($ids[1]);
check('Message::markMailSent', (int) Message::find($ids[1])['mail_sent'] === 1);
check('Message::unreadCount', Message::unreadCount() === 25);
Message::markRead($ids[2]);
Message::markRead($ids[2]);
check('Message::markRead idempotent', (int) Message::find($ids[2])['is_read'] === 1 && Message::unreadCount() === 24);
Message::toggleRead($ids[3]);
check('Message::toggleRead lu', (int) Message::find($ids[3])['is_read'] === 1);
Message::toggleRead($ids[3]);
check('Message::toggleRead non lu', (int) Message::find($ids[3])['is_read'] === 0 && Message::unreadCount() === 24);

$p1 = Message::paginate(1, 10);
check('Message::paginate format', array_keys($p1) === ['items', 'total'] && $p1['total'] === 25 && count($p1['items']) === 10);
check('Message::paginate tri created_at DESC', ids_of($p1['items']) === array_reverse(array_slice($ids, 15, 10)));
check('Message::paginate page 3', ids_of(Message::paginate(3, 10)['items']) === array_reverse(array_slice($ids, 0, 5)));
check('Message::paginate page au-delà', Message::paginate(9, 10)['items'] === [] && Message::paginate(9, 10)['total'] === 25);
check('Message::paginate page < 1 => 1', ids_of(Message::paginate(0, 10)['items']) === ids_of($p1['items'])
    && ids_of(Message::paginate(-4, 10)['items']) === ids_of($p1['items']));
check('Message::paginate perPage borné', count(Message::paginate(1, 0)['items']) === 1
    && count(Message::paginate(1, 100000)['items']) === 25);
check('Message::paginate page énorme sans erreur', Message::paginate(PHP_INT_MAX, 20)['items'] === []);
check('Message::paginate défaut 20', count(Message::paginate(1)['items']) === 20);
check('Message::latest', ids_of(Message::latest(3)) === [$ids[25], $ids[24], $ids[23]] && count(Message::latest(0)) === 1);
check('Message::delete', Message::delete($ids[25]) && Message::find($ids[25]) === null && !Message::delete($ids[25]));

Database::execute('UPDATE messages SET created_at = NOW() - INTERVAL 13 MONTH WHERE id IN (?, ?)', [$ids[1], $ids[2]]);
Database::execute('UPDATE messages SET created_at = NOW() - INTERVAL 7 MONTH WHERE id = ?', [$ids[3]]);
Database::execute('UPDATE messages SET created_at = NOW() - INTERVAL 11 MONTH WHERE id = ?', [$ids[4]]);
check('Message::purgeOlderThan défaut 12 mois', Message::purgeOlderThan() === 2
    && Message::find($ids[1]) === null && Message::find($ids[4]) !== null);
check('Message::purgeOlderThan(6)', Message::purgeOlderThan(6) === 2 && Message::find($ids[3]) === null);
check('Message::purgeOlderThan(0) borné à 1 mois', Message::purgeOlderThan(0) === 0 && Message::paginate(1)['total'] === 20);

// --- Nettoyage -----------------------------------------------------------------
reset_tables();
check('Nettoyage', (int) Database::value('SELECT COUNT(*) FROM projects') === 0 && (int) Database::value('SELECT COUNT(*) FROM messages') === 0);

echo $failures === 0 ? "OK : {$count} tests réussis.\n" : "{$failures} échec(s) sur {$count}.\n";
exit($failures === 0 ? 0 : 1);
