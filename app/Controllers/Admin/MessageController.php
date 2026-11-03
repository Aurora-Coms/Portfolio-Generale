<?php
/**
 * Messages reçus par le formulaire de contact.
 *
 *   GET  /admin/messages                 index       (?page=N, 20 par page)
 *   GET  /admin/messages/{id}            show        (marque le message comme lu)
 *   POST /admin/messages/{id}/lu         toggleRead  (lu <=> non lu)
 *   POST /admin/messages/{id}/supprimer  destroy
 *
 * RGPD : les messages de plus de 12 mois sont supprimés automatiquement
 * (purge lancée à chaque ouverture de la liste).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Request;
use App\Models\Message;

final class MessageController extends AdminController
{
    public const PER_PAGE = 20;

    /** Durée de conservation (mois), rappelée dans l'interface. */
    public const RETENTION_MONTHS = 12;

    public function index(): void
    {
        $purged = Message::purgeOlderThan(self::RETENTION_MONTHS);

        $page   = self::pageFrom(Request::query('page'));
        $result = Message::paginate($page, self::PER_PAGE);
        $pages  = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        // Page au-delà de la dernière (ex. après une suppression) : on y revient.
        if ($page > $pages) {
            redirect(self::listUrl($pages));
        }

        $this->render('admin/messages/index', [
            'messages' => $result['items'],
            'total'    => $result['total'],
            'unread'   => Message::unreadCount(),
            'page'     => $page,
            'pages'    => $pages,
            'purged'   => $purged,
            'months'   => self::RETENTION_MONTHS,
            'meta'     => ['title' => $page > 1 ? 'Messages · page ' . $page : 'Messages'],
        ]);
    }

    public function show(string $id): void
    {
        $message = $this->findOr404($id);
        if ((int) $message['is_read'] === 0) {
            Message::markRead((int) $message['id']);
            $message['is_read'] = 1;
        }

        $this->render('admin/messages/show', [
            'message' => $message,
            'replyTo' => self::replyLink($message),
            'months'  => self::RETENTION_MONTHS,
            'meta'    => ['title' => 'Message de ' . $message['name']],
        ]);
    }

    public function toggleRead(string $id): void
    {
        $message = $this->findOr404($id);
        Message::toggleRead((int) $message['id']);
        $nowRead = (int) $message['is_read'] === 0;
        $this->success(
            self::listUrl(self::pageFrom(Request::input('page'))),
            'Message de ' . $message['name'] . ($nowRead ? ' marqué comme lu.' : ' marqué comme non lu.')
        );
    }

    public function destroy(string $id): void
    {
        $message = $this->findOr404($id);
        Message::delete((int) $message['id']);
        $this->success(
            self::listUrl(self::pageFrom(Request::input('page'))),
            'Message de ' . $message['name'] . ' supprimé.'
        );
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $message = Message::find((int) $id);
        if ($message === null) {
            $this->notFound();
        }
        return $message;
    }

    /** Numéro de page valide (1 par défaut ; borné pour éviter un OFFSET démesuré). */
    private static function pageFrom(string $raw): int
    {
        return preg_match('/^[1-9]\d{0,5}$/', $raw) === 1 ? (int) $raw : 1;
    }

    private static function listUrl(int $page): string
    {
        return '/admin/messages' . ($page > 1 ? '?page=' . $page : '');
    }

    /**
     * Lien « Répondre » : mailto:adresse?subject=Re: sujet.
     * Adresse revérifiée (filter_var) et chaque partie encodée avec rawurlencode :
     * aucun en-tête supplémentaire (cc, bcc, body…) ne peut être injecté.
     * Renvoie '' si l'adresse n'est pas valide.
     *
     * @param array<string, mixed> $message
     */
    private static function replyLink(array $message): string
    {
        $email = (string) $message['email'];
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }
        $at = (int) strrpos($email, '@');
        $address = rawurlencode(substr($email, 0, $at)) . '@' . rawurlencode(substr($email, $at + 1));
        $subject = (string) $message['subject'];
        $subject = preg_match('/^re\s*:/i', $subject) === 1 ? $subject : 'Re: ' . $subject;
        return 'mailto:' . $address . '?subject=' . rawurlencode($subject);
    }
}
