<?php

/**
 * Version et date de mise à jour des pages légales.
 *
 * À METTRE À JOUR à chaque modification du contenu des pages concernées.
 *
 * Convention : MAJEUR.MINEUR
 *   - MAJEUR : modification substantielle (droits client, traitements, sous-traitants)
 *   - MINEUR : correction rédactionnelle ou mise en forme sans impact juridique
 *
 * La valeur cgv.version est stockée dans orders.cgv_version à chaque commande
 * (preuve de la version acceptée en cas de litige — L221-11 Code de la consommation).
 */

$legalDate = '2026-04-07'; // NOSONAR — php:S1192 : date commune à tous les documents RGPD, définie une seule fois ici

return [
    'cgv' => [
        'version'    => '1.0',
        'updated_at' => '2026-04-04',
    ],
    'mentions_legales' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'politique_confidentialite' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'matrice_habilitations' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'registre_traitements' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'sous_traitants' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'procedure_violation' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
    'dpia' => [
        'version'    => '1.0',
        'updated_at' => $legalDate,
    ],
];
