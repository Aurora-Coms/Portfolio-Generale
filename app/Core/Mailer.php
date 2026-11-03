<?php
/**
 * Envoi d'e-mails (notification des messages de contact).
 *
 * Client SMTP minimal intégré (aucune dépendance) : SSL implicite (port 465)
 * ou STARTTLS (port 587), authentification AUTH LOGIN, certificat du serveur
 * vérifié. Repli possible sur mail() selon la configuration.
 *
 * Anti-injection d'en-têtes : toutes les valeurs d'en-tête sont débarrassées
 * des retours à la ligne et les adresses sont validées.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class Mailer
{
    /**
     * @return bool true si l'e-mail est parti (false : erreur journalisée).
     */
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null, ?string $replyName = null): bool
    {
        $transport = (string) Config::get('mail.transport', 'none');
        if ($transport === 'none') {
            return false;
        }

        $from     = (string) Config::get('mail.from_email', '');
        $fromName = (string) Config::get('mail.from_name', 'Portfolio');
        if (!self::validAddress($to) || !self::validAddress($from) || ($replyTo !== null && !self::validAddress($replyTo))) {
            error_log('[mail] Adresse e-mail invalide dans la configuration ou la demande.');
            return false;
        }

        $host = (string) (parse_url((string) Config::get('base_url'), PHP_URL_HOST) ?: 'localhost');
        $headers = [
            'Date'                      => date(DATE_RFC2822),
            'From'                      => self::address($from, $fromName),
            'To'                        => self::address($to),
            'Subject'                   => self::encodeHeader($subject),
            'Message-ID'                => '<' . bin2hex(random_bytes(12)) . '@' . $host . '>',
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
            'Auto-Submitted'            => 'auto-generated',
        ];
        if ($replyTo !== null) {
            $headers['Reply-To'] = self::address($replyTo, $replyName);
        }
        $encodedBody = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));

        try {
            if ($transport === 'smtp') {
                self::sendSmtp($from, $to, $headers, $encodedBody);
                return true;
            }
            if ($transport === 'mail') {
                $subjectHeader = $headers['Subject'];
                unset($headers['Subject'], $headers['To']);
                $lines = [];
                foreach ($headers as $name => $value) {
                    $lines[] = $name . ': ' . $value;
                }
                return mail($to, $subjectHeader, $encodedBody, implode("\r\n", $lines), '-f' . $from);
            }
        } catch (Throwable $e) {
            // Le mot de passe SMTP n'apparaît jamais dans le message journalisé.
            error_log('[mail] Échec de l\'envoi : ' . $e->getMessage());
        }
        return false;
    }

    public static function validAddress(string $email): bool
    {
        return !preg_match('/[\r\n]/', $email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function sendSmtp(string $from, string $to, array $headers, string $body): void
    {
        $host       = (string) Config::get('mail.host', '');
        $port       = (int) Config::get('mail.port', 465);
        $encryption = (string) Config::get('mail.encryption', 'ssl');
        $timeout    = (int) Config::get('mail.timeout', 10);

        $context = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'peer_name'        => $host,
        ]]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException("connexion SMTP impossible ({$errno})");
        }
        stream_set_timeout($socket, $timeout);

        try {
            $ehloHost = (string) (parse_url((string) Config::get('base_url'), PHP_URL_HOST) ?: 'localhost');
            self::expect($socket, [220]);
            self::command($socket, 'EHLO ' . $ehloHost, [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('négociation STARTTLS impossible');
                }
                self::command($socket, 'EHLO ' . $ehloHost, [250]);
            }

            $user = (string) Config::get('mail.username', '');
            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($user), [334]);
                self::command($socket, base64_encode((string) Config::get('mail.password', '')), [235], true);
            }

            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $message = '';
            foreach ($headers as $name => $value) {
                $message .= $name . ': ' . $value . "\r\n";
            }
            $message .= "\r\n" . $body . "\r\n";
            // « Dot-stuffing » : une ligne commençant par « . » est doublée.
            $message = preg_replace('/^\./m', '..', $message) ?? $message;
            fwrite($socket, $message . ".\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource  $socket
     * @param list<int> $codes
     */
    private static function command($socket, string $line, array $codes, bool $secret = false): void
    {
        fwrite($socket, $line . "\r\n");
        try {
            self::expect($socket, $codes);
        } catch (RuntimeException $e) {
            throw new RuntimeException(($secret ? '[AUTH]' : strtok($line, ' ')) . ' : ' . $e->getMessage());
        }
    }

    /**
     * Lit une réponse SMTP (éventuellement multiligne) et vérifie son code.
     *
     * @param resource  $socket
     * @param list<int> $codes
     */
    private static function expect($socket, array $codes): void
    {
        $response = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('réponse inattendue du serveur (' . ($code ?: 'aucune') . ')');
        }
    }

    private static function address(string $email, ?string $name = null): string
    {
        $email = str_replace(["\r", "\n"], '', $email);
        if ($name === null || trim($name) === '') {
            return '<' . $email . '>';
        }
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /** Encode un en-tête en UTF-8 (RFC 2047) après suppression des retours à la ligne. */
    private static function encodeHeader(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
