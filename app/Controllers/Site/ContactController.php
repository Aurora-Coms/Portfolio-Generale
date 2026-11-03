<?php
/**
 * Formulaire de contact.
 *
 * Défenses contre le spam et les abus, dans l'ordre :
 *   0. jeton CSRF : vérifié par le routeur avant d'arriver ici (419 sinon) ;
 *   1. champ piège « website » (invisible) : rempli => on fait comme si l'envoi
 *      avait réussi, sans rien enregistrer ;
 *   2. limitation par IP (App\Core\Throttle, IP jamais stockée en clair) :
 *      3 messages / 10 minutes et 10 / 24 heures => 429 ;
 *   3. jeton horodaté signé (HMAC avec la clé de l'application) : refus si la
 *      signature est fausse, si le formulaire est envoyé en moins de 3 secondes
 *      ou plus de 2 heures après son affichage ;
 *   4. validation des champs (Validator) => 422 avec les erreurs.
 *
 * En cas de succès : message enregistré, notification e-mail si une adresse
 * est configurée, purge occasionnelle des messages de plus de 12 mois (RGPD),
 * puis redirection (PRG) avec un message flash.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Config;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Session;
use App\Core\Throttle;
use App\Core\Validator;
use App\Models\Message;

final class ContactController extends SiteController
{
    /** Nom du champ piège (doit rester vide). */
    private const HONEYPOT = 'website';

    /** Délais du jeton horodaté (secondes). */
    private const MIN_DELAY = 3;
    private const MAX_DELAY = 7200;

    /** Limites d'envoi par IP : [nombre maximal, fenêtre en secondes, message]. */
    private const LIMITS = [
        [3, 600, 'Vous avez envoyé plusieurs messages en peu de temps. Pour limiter le spam, patientez une dizaine de minutes avant d\'en envoyer un nouveau.'],
        [10, 86400, 'Vous avez atteint le nombre maximal de messages pour aujourd\'hui. Réessayez demain.'],
    ];

    /** Longueurs maximales des champs. */
    private const MAX_NAME    = 100;
    private const MAX_SUBJECT = 150;
    private const MIN_MESSAGE = 10;
    private const MAX_MESSAGE = 5000;

    /** Sujet utilisé si le visiteur n'en indique pas. */
    private const DEFAULT_SUBJECT = 'Message depuis le portfolio';

    public function show(): void
    {
        $old = [];
        if (Request::query('sujet') === 'stage') {
            $old['subject'] = $this->internshipSubject();
        }
        $this->form($old, [], 200);
    }

    public function send(): void
    {
        $post = $_POST;
        $ip   = Request::ip();

        // 1. Champ piège : un humain ne le voit pas et ne peut pas l'atteindre.
        $trap = $post[self::HONEYPOT] ?? '';
        if (!is_string($trap) || trim($trap) !== '') {
            $this->succeed();
        }

        $old = self::oldInput($post);

        // 2. Limitation du nombre de messages par IP.
        foreach (self::LIMITS as [$max, $window, $message]) {
            if (Throttle::tooMany('contact', $ip, $max, $window)) {
                $this->form($old, ['_form' => $message], 429);
                return;
            }
        }

        // 3 et 4. Jeton horodaté et validation des champs.
        $v = new Validator($post);
        $name    = $v->string('name', 'Nom', required: true, max: self::MAX_NAME);
        $email   = $v->email('email', 'Adresse e-mail');
        $subject = $v->string('subject', 'Sujet', required: false, max: self::MAX_SUBJECT);
        $body    = $v->text('message', 'Message', required: true, max: self::MAX_MESSAGE, min: self::MIN_MESSAGE);
        if (!$v->bool('consent')) {
            $v->addError('consent', 'Cochez cette case pour accepter que vos données servent à vous répondre.');
        }
        $tokenError = self::checkFormToken($post['form_ts'] ?? null);
        if ($tokenError !== null) {
            $v->addError('_form', $tokenError);
        }

        if ($v->fails() || $name === null || $email === null || $body === null) {
            $this->form($old, $v->errors(), 422);
            return;
        }
        $subject ??= self::DEFAULT_SUBJECT;

        $id = Message::create($name, $email, $subject, $body);
        Throttle::hit('contact', $ip);

        // Notification par e-mail (facultative : le message reste lisible dans l'administration).
        $to = setting('contact_email');
        if ($to !== '' && Mailer::validAddress($to)) {
            $text = "Nouveau message reçu par le formulaire de contact du portfolio.\n\n"
                  . 'Nom : ' . $name . "\n"
                  . 'E-mail : ' . $email . "\n"
                  . 'Sujet : ' . $subject . "\n"
                  . 'Date : ' . datetime_fr(date('Y-m-d H:i:s')) . "\n\n"
                  . "Message :\n" . $body . "\n\n"
                  . "-- \nRépondez directement à cet e-mail pour écrire à " . $name . ".\n"
                  . 'Tous les messages : ' . abs_url('/admin/messages') . "\n";
            if (Mailer::send($to, '[Portfolio] ' . $subject, $text, $email, $name)) {
                Message::markMailSent($id);
            }
        }

        // RGPD : suppression régulière des messages de plus de 12 mois.
        if (random_int(1, 20) === 1) {
            Message::purgeOlderThan(12);
        }

        $this->succeed();
    }

    /**
     * Affiche le formulaire (avec un nouveau jeton horodaté à chaque affichage).
     *
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors, int $status): void
    {
        $internship = $this->internshipWanted() ? $this->internshipSentence() : '';

        $this->render('site/contact', [
            'old'        => $old,
            'errors'     => $errors,
            'formTs'     => self::formToken(),
            'honeypot'   => self::HONEYPOT,
            'limits'     => ['name' => self::MAX_NAME, 'subject' => self::MAX_SUBJECT, 'messageMin' => self::MIN_MESSAGE, 'messageMax' => self::MAX_MESSAGE],
            'linkedin'   => self::safeUrl(setting('linkedin_url')),
            'github'     => self::safeUrl(setting('github_url')),
            'location'   => setting('location'),
            'internship' => $internship,
            'meta'       => [
                'title'       => 'Contact',
                'description' => 'Contacter Alexandre Derbez, étudiant en BTS CIEL option IR à Avignon : proposition de stage, question sur un projet ou sur son parcours.',
                'canonical'   => '/contact',
            ],
        ], $status);
    }

    /** Succès (réel ou simulé pour un robot) : message flash puis redirection (PRG). */
    private function succeed(): never
    {
        Session::flash('success', 'Merci, votre message a bien été envoyé. Je vous répondrai dès que possible.');
        redirect('/contact');
    }

    /**
     * Sujet pré-rempli : « Proposition de stage (18 mai au 26 juin 2026) »,
     * « Proposition de stage (à partir du 18 mai 2026) » sans date de fin.
     */
    private function internshipSubject(): string
    {
        $sentence = $this->internshipSentence();
        if ($sentence === '') {
            return 'Proposition de stage';
        }
        return 'Proposition de stage (' . (str_starts_with($sentence, 'du ') ? substr($sentence, 3) : $sentence) . ')';
    }

    /** Jeton horodaté signé : « horodatage.hmac ». */
    private static function formToken(): string
    {
        $ts = time();
        return $ts . '.' . hash_hmac('sha256', 'contact|' . $ts, Config::appKey());
    }

    /**
     * Vérifie le jeton horodaté ; renvoie un message d'erreur, ou null s'il est valide.
     */
    private static function checkFormToken(mixed $token): ?string
    {
        if (!is_string($token) || !preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $token, $m)) {
            return 'Le formulaire n\'est pas valide. Rechargez la page puis réessayez.';
        }
        $expected = hash_hmac('sha256', 'contact|' . $m[1], Config::appKey());
        if (!hash_equals($expected, $m[2])) {
            return 'Le formulaire n\'est pas valide. Rechargez la page puis réessayez.';
        }
        $age = time() - (int) $m[1];
        if ($age < self::MIN_DELAY) {
            return 'Le formulaire a été envoyé trop rapidement. Patientez quelques secondes puis envoyez-le de nouveau.';
        }
        if ($age > self::MAX_DELAY) {
            return 'Le formulaire a expiré (plus de 2 heures). Vérifiez votre message puis envoyez-le de nouveau.';
        }
        return null;
    }

    /**
     * Valeurs à réafficher après une erreur (jamais le champ piège ni le jeton).
     *
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private static function oldInput(array $post): array
    {
        $old = [];
        foreach (['name' => self::MAX_NAME, 'email' => 254, 'subject' => self::MAX_SUBJECT, 'message' => self::MAX_MESSAGE] as $key => $max) {
            $value = $post[$key] ?? '';
            if (is_string($value) && mb_check_encoding($value, 'UTF-8')) {
                // Longueur bornée : une saisie démesurée n'est pas renvoyée en entier.
                $old[$key] = mb_substr($value, 0, $max + 100);
            }
        }
        $consent = $post['consent'] ?? '';
        $old['consent'] = is_string($consent) && in_array($consent, ['1', 'on'], true) ? '1' : '';
        return $old;
    }
}
