<?php

declare(strict_types=1);

namespace ValueObject;

/**
 * Value Object regroupant les filtres de la liste admin des comptes.
 *
 * Utilisé par AccountModel::getForAdmin() et AccountModel::countForAdmin() pour
 * réduire le nombre de paramètres positionnels (cf. Sonar php:S107).
 *
 * Immutable via readonly properties (PHP 8.1+).
 */
final class AccountAdminFilters
{
    /**
     * @param string|null $role       Filtre rôle (customer|admin|super_admin)
     * @param string|null $search     Recherche texte libre (email, nom, prénom…)
     * @param string|null $type       Filtre type (individual|company)
     * @param string|null $sort       Clé de tri (created_at_asc, last_connection_desc, …)
     * @param string|null $hasOrdered '1' = a commandé, '0' = jamais, null = tous
     * @param string|null $activity   'active', 'inactive', 'deleted' ou null
     */
    public function __construct(
        public readonly ?string $role = null,
        public readonly ?string $search = null,
        public readonly ?string $type = null,
        public readonly ?string $sort = null,
        public readonly ?string $hasOrdered = null,
        public readonly ?string $activity = null
    ) {
    }
}
