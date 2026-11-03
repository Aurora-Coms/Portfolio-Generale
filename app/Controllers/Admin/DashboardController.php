<?php
/**
 * Tableau de bord : chiffres clés, points de vigilance (sécurité et
 * configuration), derniers messages, brouillons à compléter, raccourcis.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\PdfUpload;
use App\Models\Message;
use App\Models\Project;

final class DashboardController extends AdminController
{
    /** GET /admin */
    public function index(): void
    {
        $projects = Project::adminList();
        $drafts   = array_values(array_filter($projects, static fn (array $p): bool => (int) $p['is_published'] === 0));
        $featuredVisible = count(array_filter(
            $projects,
            static fn (array $p): bool => (int) $p['is_published'] === 1 && (int) $p['is_featured'] === 1
        ));

        $user = Auth::user() ?? [];

        $this->render('admin/dashboard/index', [
            'counts'          => Project::counts(),
            'featuredVisible' => $featuredVisible,
            'unread'          => Message::unreadCount(),
            'messages'        => Message::latest(5),
            'drafts'          => $drafts,
            'alerts'          => $this->alerts($user),
            'username'        => (string) ($user['username'] ?? ''),
            'meta'            => ['title' => 'Tableau de bord'],
        ]);
    }

    /**
     * Points de vigilance, du plus grave au moins grave.
     * Chaque alerte : type (error|warning|info), title, message, link (href, label) facultatif.
     *
     * @param array<string, mixed> $user
     * @return list<array{type: string, title: string, message: string, link: ?array{href: string, label: string}}>
     */
    private function alerts(array $user): array
    {
        $alerts = [];

        if (is_file(PUBLIC_PATH . '/setup-admin.php')) {
            $alerts[] = [
                'type'    => 'error',
                'title'   => 'Fichier d\'installation encore en ligne',
                'message' => 'Le fichier public_html/setup-admin.php est toujours présent : supprimez-le de l\'hébergement (gestionnaire de fichiers ou FTP).',
                'link'    => null,
            ];
        }

        if (trim((string) Config::get('security.setup_token', '')) !== '') {
            $alerts[] = [
                'type'    => 'error',
                'title'   => 'Jeton d\'installation non vidé',
                'message' => 'Dans app/config.php, videz la valeur security.setup_token (\'\') maintenant que le compte administrateur existe.',
                'link'    => null,
            ];
        }

        if (Config::isDev()) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => 'Mode développement actif',
                'message' => 'app/config.php contient env = \'development\' : les erreurs détaillées sont affichées. En ligne, utilisez \'production\'.',
                'link'    => null,
            ];
        }

        if ((int) ($user['totp_enabled'] ?? 0) !== 1) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => 'Double authentification désactivée',
                'message' => 'Activez-la pour que le mot de passe seul ne suffise pas à ouvrir l\'administration.',
                'link'    => ['href' => '/admin/compte', 'label' => 'Activer la double authentification'],
            ];
        }

        $bannerOn = setting('banner_enabled') === '1';
        $end      = setting('internship_end');
        if ($bannerOn && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) === 1 && $end < date('Y-m-d')) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => 'Bandeau de stage dépassé',
                'message' => 'La date de fin de stage (' . date_fr($end) . ') est passée alors que le bandeau « Recherche de stage » est toujours affiché.',
                'link'    => ['href' => '/admin/reglages', 'label' => 'Modifier les réglages'],
            ];
        }

        if (!PdfUpload::exists()) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => 'Aucun CV en ligne',
                'message' => 'Le lien « CV (PDF) » du site ne fonctionnera pas tant qu\'aucun CV n\'a été envoyé.',
                'link'    => ['href' => '/admin/reglages', 'label' => 'Envoyer le CV'],
            ];
        }

        if (trim(setting('contact_email')) === '') {
            $alerts[] = [
                'type'    => 'info',
                'title'   => 'Adresse de contact vide',
                'message' => 'Les messages du formulaire ne seront pas envoyés par e-mail : ils restent consultables ici, dans Messages.',
                'link'    => ['href' => '/admin/reglages', 'label' => 'Renseigner l\'adresse'],
            ];
        } elseif ((string) Config::get('mail.transport', 'none') === 'none') {
            $alerts[] = [
                'type'    => 'info',
                'title'   => 'Envoi d\'e-mails désactivé',
                'message' => 'app/config.php contient mail.transport = \'none\' : les messages ne sont pas transférés par e-mail, consultez-les ici.',
                'link'    => null,
            ];
        }

        return $alerts;
    }
}
