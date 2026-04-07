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

return [
    'cgv' => [
        'version'    => '1.0',
        'updated_at' => '2026-04-04',
    ],
    'mentions_legales' => [
        'version'    => '1.0',
        'updated_at' => '2026-04-07',
    ],
    'politique_confidentialite' => [
        'version'    => '1.0',
        'updated_at' => '2026-04-07',
    ],
    'matrice_habilitations' => [
        'version'    => '1.1',
        'updated_at' => '2026-04-07',
    ],
];
