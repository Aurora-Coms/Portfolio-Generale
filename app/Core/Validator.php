<?php
/**
 * Validation des données de formulaire.
 *
 *   $v = new Validator($_POST);
 *   $title  = $v->string('title', 'Titre', required: true, max: 120);
 *   $level  = $v->int('level', 'Niveau', min: 1, max: 5);
 *   $email  = $v->email('email', 'E-mail');
 *   if ($v->fails()) { ... $v->errors() ... }
 *
 * Chaque méthode renvoie la valeur nettoyée (ou null) et enregistre un message
 * d'erreur en français en cas de problème.
 */

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    /** Texte sur une ligne (les retours à la ligne sont supprimés). */
    public function string(string $field, string $label, bool $required = false, int $max = 255, int $min = 0): ?string
    {
        $value = $this->raw($field);
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
        return $this->checkLength($field, $label, $value, $required, $max, $min);
    }

    /** Texte multiligne (retours à la ligne conservés, normalisés en \n). */
    public function text(string $field, string $label, bool $required = false, int $max = 20000, int $min = 0): ?string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $this->raw($field));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);
        return $this->checkLength($field, $label, $value, $required, $max, $min);
    }

    public function email(string $field, string $label, bool $required = true): ?string
    {
        $value = $this->string($field, $label, $required, 254);
        if ($value === null) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = "« {$label} » n'est pas une adresse e-mail valide.";
            return null;
        }
        return $value;
    }

    /** URL absolue http(s) uniquement (jamais javascript:, data:…). */
    public function url(string $field, string $label, bool $required = false): ?string
    {
        $value = $this->string($field, $label, $required, 255);
        if ($value === null) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (filter_var($value, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
            $this->errors[$field] = "« {$label} » doit être une adresse commençant par https://";
            return null;
        }
        return $value;
    }

    public function int(string $field, string $label, bool $required = true, ?int $min = null, ?int $max = null): ?int
    {
        $value = trim($this->raw($field));
        if ($value === '') {
            if ($required) {
                $this->errors[$field] = "« {$label} » est obligatoire.";
            }
            return null;
        }
        if (!preg_match('/^-?\d{1,9}$/', $value)) {
            $this->errors[$field] = "« {$label} » doit être un nombre entier.";
            return null;
        }
        $int = (int) $value;
        if (($min !== null && $int < $min) || ($max !== null && $int > $max)) {
            $this->errors[$field] = "« {$label} » doit être compris entre {$min} et {$max}.";
            return null;
        }
        return $int;
    }

    /** Case à cocher : vrai si présente. */
    public function bool(string $field): bool
    {
        $value = $this->data[$field] ?? null;
        return is_string($value) && in_array($value, ['1', 'on', 'true', 'yes'], true);
    }

    /** Date au format AAAA-MM-JJ (champ <input type="date">). */
    public function date(string $field, string $label, bool $required = false): ?string
    {
        $value = trim($this->raw($field));
        if ($value === '') {
            if ($required) {
                $this->errors[$field] = "« {$label} » est obligatoire.";
            }
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->errors[$field] = "« {$label} » n'est pas une date valide.";
            return null;
        }
        return $value;
    }

    /**
     * Valeur parmi une liste autorisée.
     *
     * @param list<string> $allowed
     */
    public function in(string $field, string $label, array $allowed, bool $required = true): ?string
    {
        $value = trim($this->raw($field));
        if ($value === '' && !$required) {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = "« {$label} » : valeur non autorisée.";
            return null;
        }
        return $value;
    }

    /** Slug (lettres minuscules, chiffres, tirets). Vide => null. */
    public function slug(string $field, string $label, int $max = 140): ?string
    {
        $value = trim($this->raw($field));
        if ($value === '') {
            return null;
        }
        $value = Slug::make($value, $max);
        if ($value === '') {
            $this->errors[$field] = "« {$label} » ne contient aucun caractère utilisable.";
            return null;
        }
        return $value;
    }

    /**
     * Identifiant YouTube : accepte l'ID (11 caractères) ou une URL YouTube
     * courante et n'en garde que l'ID. Jamais d'iframe collée.
     */
    public function youtubeId(string $field, string $label): ?string
    {
        $value = trim($this->raw($field));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value)) {
            return $value;
        }
        $patterns = [
            '#^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})(?:[&\#].*)?$#',
            '#^https?://youtu\.be/([A-Za-z0-9_-]{11})(?:[?\#].*)?$#',
            '#^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:[?/\#].*)?$#',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $m)) {
                return $m[1];
            }
        }
        $this->errors[$field] = "« {$label} » : collez l'adresse de la vidéo YouTube (ex. https://youtu.be/xxxxxxxxxxx).";
        return null;
    }

    /**
     * Liste d'identifiants entiers positifs (cases à cocher name="tags[]").
     *
     * @return list<int>
     */
    public function ids(string $field): array
    {
        $values = $this->data[$field] ?? [];
        if (!is_array($values)) {
            return [];
        }
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value)) {
                $ids[] = (int) $value;
            }
        }
        return array_values(array_unique($ids));
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function raw(string $field): string
    {
        $value = $this->data[$field] ?? '';
        return is_string($value) ? $value : '';
    }

    private function checkLength(string $field, string $label, string $value, bool $required, int $max, int $min): ?string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $this->errors[$field] = "« {$label} » contient des caractères invalides.";
            return null;
        }
        if ($value === '') {
            if ($required) {
                $this->errors[$field] = "« {$label} » est obligatoire.";
            }
            return null;
        }
        $length = mb_strlen($value);
        if ($length > $max) {
            $this->errors[$field] = "« {$label} » ne doit pas dépasser {$max} caractères.";
            return null;
        }
        if ($length < $min) {
            $this->errors[$field] = "« {$label} » doit contenir au moins {$min} caractères.";
            return null;
        }
        return $value;
    }
}
