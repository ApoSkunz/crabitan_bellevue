<?php

/**
 * Version des Conditions Générales de Vente.
 *
 * À METTRE À JOUR à chaque modification du contenu des CGV
 * (src/View/cgv/index.php ou lang/fr.php clé cgv.*).
 *
 * Convention : MAJEUR.MINEUR
 *   - MAJEUR : modification substantielle (droits client, prix, délais, responsabilité)
 *   - MINEUR : correction rédactionnelle ou mise en forme sans impact juridique
 *
 * La valeur est stockée dans la colonne `orders.cgv_version` à chaque commande,
 * permettant de prouver quelle version le client a acceptée en cas de litige.
 */
return [
    'version'    => '1.0',
    'updated_at' => '2026-04-04',
];
