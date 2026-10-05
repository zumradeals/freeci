<?php

namespace App\Modules\Catalog\Support;

/** Détection simple de coordonnées privées dans un texte public (adresse e-mail, numéro de téléphone, lien de messagerie). */
final class PrivateContact
{
    public static function found(string $text): bool
    {
        if (preg_match('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}/u', $text)) {
            return true;
        }
        if (preg_match('/(?:\+|00)?\d(?:[\s.\-()]?\d){7,}/', $text)) {
            return true;
        }

        return (bool) preg_match('/\b(?:wa\.me|t\.me|whatsapp\.com|telegram\.me)\b/i', $text);
    }
}
