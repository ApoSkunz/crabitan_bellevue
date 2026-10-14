<?php

declare(strict_types=1);

namespace Service;

/**
 * Zone de livraison : France métropolitaine continentale uniquement.
 *
 * Exclut la Corse (20xxx), les DOM-TOM (97xxx–98xxx) et les îles listées dans
 * {@see self::ISLAND_ZIPS}. Source unique de vérité pour le serveur (checkout),
 * la vue (adresses grisées) et la validation JS (liste injectée par la vue).
 */
final class DeliveryZoneService
{
    /**
     * Codes postaux dédiés à des îles (liaison maritime ou pont) — livraison refusée.
     *
     * Limite connue : les îles partageant leur code postal avec le continent
     * (Houat/Hoëdic 56170 = Quiberon, Porquerolles/Port-Cros/Levant 83400 = Hyères,
     * Chausey 50400 = Granville, Glénan 29170 = Fouesnant) ne peuvent pas être
     * distinguées par le seul code postal.
     *
     * @var list<string>
     */
    public const ISLAND_ZIPS = [
        // Vendée
        '85350',                                            // Île d'Yeu
        '85330', '85630', '85680', '85740',                 // Noirmoutier
        // Charente-Maritime
        '17111', '17410', '17580', '17590', '17630',        // Île de Ré
        '17670', '17740', '17880', '17940',
        '17190', '17310', '17370', '17480', '17550',        // Île d'Oléron
        '17650', '17840',
        '17123',                                            // Île d'Aix
        // Morbihan
        '56360',                                            // Belle-Île-en-Mer
        '56590',                                            // Groix
        '56780',                                            // Île-aux-Moines
        '56840',                                            // Île d'Arz
        // Finistère
        '29242',                                            // Ouessant
        '29259',                                            // Molène
        '29253',                                            // Île de Batz
        '29990',                                            // Île de Sein
        // Côtes-d'Armor
        '22870',                                            // Île de Bréhat
    ];

    /**
     * Vérifie que le pays correspond à la France (« France » ou code ISO « FR »).
     *
     * @param string $country Pays de l'adresse
     * @return bool
     */
    public static function isFranceCountry(string $country): bool
    {
        return in_array(mb_strtolower(trim($country)), ['france', 'fr'], true);
    }

    /**
     * Vérifie qu'un code postal est en France métropolitaine continentale.
     *
     * Refuse : format ≠ 5 chiffres, Corse (20), DOM-TOM (≥ 97) et îles listées.
     *
     * @param string $zip Code postal
     * @return bool
     */
    public static function isMainlandZip(string $zip): bool
    {
        if (!preg_match('/^\d{5}$/', $zip)) {
            return false;
        }
        $dept = (int) substr($zip, 0, 2);
        if ($dept === 20 || $dept >= 97) {
            return false;
        }
        return !in_array($zip, self::ISLAND_ZIPS, true);
    }

    /**
     * Adresse livrable = pays France et code postal métropole continentale.
     *
     * @param string $country Pays
     * @param string $zip     Code postal
     * @return bool
     */
    public static function isDeliverable(string $country, string $zip): bool
    {
        return self::isFranceCountry($country) && self::isMainlandZip($zip);
    }
}
