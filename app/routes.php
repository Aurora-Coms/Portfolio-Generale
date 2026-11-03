<?php
/**
 * Table des routes du site.
 *
 * Toutes les routes POST sont protégées contre le CSRF par le routeur.
 * Toutes les routes /admin (sauf connexion) utilisent un contrôleur héritant
 * d'AdminController, qui exige une session administrateur.
 *
 * @var App\Core\Router $router
 */

declare(strict_types=1);

use App\Controllers\Site;
use App\Controllers\Admin;

// -----------------------------------------------------------------------------
//  Site public
// -----------------------------------------------------------------------------
$router->get('/', [Site\HomeController::class, 'index']);

$router->get('/projets', [Site\ProjectController::class, 'index']);            // redirige vers /projets/jeux
$router->get('/projets/jeux', [Site\ProjectController::class, 'games']);       // ?categorie=slug
$router->get('/projets/techniques', [Site\ProjectController::class, 'tech']);  // ?categorie=slug
$router->get('/projet/{slug}', [Site\ProjectController::class, 'show']);

$router->get('/parcours', [Site\TimelineController::class, 'index']);
$router->get('/competences', [Site\SkillController::class, 'index']);
$router->get('/a-propos', [Site\AboutController::class, 'index']);

$router->get('/contact', [Site\ContactController::class, 'show']);
$router->post('/contact', [Site\ContactController::class, 'send']);

$router->get('/cv', [Site\CvController::class, 'download']);
$router->get('/mentions-legales', [Site\LegalController::class, 'index']);
$router->get('/sitemap.xml', [Site\SitemapController::class, 'index']);
$router->get('/robots.txt', [Site\SitemapController::class, 'robots']);

// -----------------------------------------------------------------------------
//  Administration : connexion
// -----------------------------------------------------------------------------
$router->get('/admin/connexion', [Admin\AuthController::class, 'loginForm']);
$router->post('/admin/connexion', [Admin\AuthController::class, 'login']);
$router->get('/admin/connexion/verification', [Admin\AuthController::class, 'twoFactorForm']);
$router->post('/admin/connexion/verification', [Admin\AuthController::class, 'twoFactor']);
$router->post('/admin/deconnexion', [Admin\AuthController::class, 'logout']);

// -----------------------------------------------------------------------------
//  Administration : contenu (protégé)
// -----------------------------------------------------------------------------
$router->get('/admin', [Admin\DashboardController::class, 'index']);

// Projets
$router->resource('/admin/projets', Admin\ProjectController::class);
$router->post('/admin/projets/{id}/publier', [Admin\ProjectController::class, 'togglePublished']);
$router->post('/admin/projets/{id}/vedette', [Admin\ProjectController::class, 'toggleFeatured']);

// Images d'un projet
$router->get('/admin/projets/{id}/images', [Admin\ImageController::class, 'index']);
$router->post('/admin/projets/{id}/images', [Admin\ImageController::class, 'upload']);
$router->post('/admin/images/{id}', [Admin\ImageController::class, 'update']);          // texte alternatif
$router->post('/admin/images/{id}/monter', [Admin\ImageController::class, 'moveUp']);
$router->post('/admin/images/{id}/descendre', [Admin\ImageController::class, 'moveDown']);
$router->post('/admin/images/{id}/couverture', [Admin\ImageController::class, 'makeCover']);
$router->post('/admin/images/{id}/supprimer', [Admin\ImageController::class, 'destroy']);

// Catégories de projets, tags
$router->resource('/admin/categories', Admin\CategoryController::class);
$router->resource('/admin/tags', Admin\TagController::class);

// Parcours, compétences, centres d'intérêt
$router->resource('/admin/parcours', Admin\TimelineController::class);
$router->resource('/admin/competences/themes', Admin\SkillThemeController::class);
$router->resource('/admin/competences/categories', Admin\SkillCategoryController::class);
$router->resource('/admin/competences', Admin\SkillController::class);
$router->resource('/admin/interets', Admin\InterestController::class);

// Texte « À propos »
$router->get('/admin/a-propos', [Admin\AboutController::class, 'edit']);
$router->post('/admin/a-propos', [Admin\AboutController::class, 'update']);

// Messages de contact
$router->get('/admin/messages', [Admin\MessageController::class, 'index']);
$router->get('/admin/messages/{id}', [Admin\MessageController::class, 'show']);
$router->post('/admin/messages/{id}/lu', [Admin\MessageController::class, 'toggleRead']);
$router->post('/admin/messages/{id}/supprimer', [Admin\MessageController::class, 'destroy']);

// Réglages du site et CV
$router->get('/admin/reglages', [Admin\SettingsController::class, 'edit']);
$router->post('/admin/reglages', [Admin\SettingsController::class, 'update']);
$router->post('/admin/reglages/cv', [Admin\SettingsController::class, 'uploadCv']);
$router->post('/admin/reglages/cv/supprimer', [Admin\SettingsController::class, 'deleteCv']);

// Compte : mot de passe et double authentification
$router->get('/admin/compte', [Admin\AccountController::class, 'index']);
$router->post('/admin/compte/mot-de-passe', [Admin\AccountController::class, 'updatePassword']);
$router->get('/admin/compte/2fa', [Admin\AccountController::class, 'setupTwoFactor']);
$router->post('/admin/compte/2fa', [Admin\AccountController::class, 'enableTwoFactor']);
$router->post('/admin/compte/2fa/desactiver', [Admin\AccountController::class, 'disableTwoFactor']);
$router->post('/admin/compte/2fa/codes', [Admin\AccountController::class, 'regenerateCodes']);
