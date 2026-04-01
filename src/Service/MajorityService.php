<?php

declare(strict_types=1);

namespace Service;

/**
 * Vérifie la majorité légale d'un utilisateur à partir de sa date de naissance.
 *
 * Base légale : Art. L3342-1 CSP — vente d'alcool aux mineurs interdite.
 * Sanctions pénales : Art. L3353-3 CSP.
 *
 * La date doit être au format ISO 8601 (YYYY-MM-DD).
 */
class MajorityService
{
    /** Âge légal de la majorité en France (Art. L3342-1 CSP). */
    private const LEGAL_AGE = 18;

    /**
     * Vérifie que la date de naissance correspond à un individu majeur (>= 18 ans).
     *
     * Retourne false si la date est invalide, dans le futur, ou si l'individu est mineur.
     * Retourne true si l'individu a exactement 18 ans ou plus aujourd'hui.
     *
     * @param string $birthDate Date de naissance au format YYYY-MM-DD
     * @return bool True si l'individu est majeur, false sinon
     */
    public static function isEligible(string $birthDate): bool
    {
        if ($birthDate === '') {
            return false;
        }

        // Validation stricte du format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            return false;
        }

        // Création de la date — checkdate garantit que le calendrier est cohérent
        $parts = explode('-', $birthDate);
        $year  = (int) $parts[0];
        $month = (int) $parts[1];
        $day   = (int) $parts[2];

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        // Forcer minuit pour éviter que l'heure courante fausse le calcul d'âge
        $birth = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $birthDate . ' 00:00:00');
        if ($birth === false) {
            return false; // NOSONAR — sécurité défensive malgré checkdate
        }

        $today = new \DateTimeImmutable('today');

        // Rejet des dates dans le futur
        if ($birth > $today) {
            return false;
        }

        $age = $today->diff($birth)->y;

        return $age >= self::LEGAL_AGE;
    }
}
