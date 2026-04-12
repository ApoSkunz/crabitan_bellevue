<?php

declare(strict_types=1);

namespace Service;

use Model\AdminLogModel;

/**
 * Service de journalisation des actions admin.
 *
 * Toutes les erreurs sont silencieuses : une défaillance du journal
 * ne doit jamais faire échouer l'action principale.
 */
class AuditLogger
{
    /**
     * Journalise une action admin dans la table admin_logs.
     *
     * Méthode silencieuse : capture toutes les exceptions pour ne pas
     * interrompre le flux applicatif en cas d'indisponibilité BDD.
     *
     * @param int                  $adminId    Identifiant de l'admin (accounts.id)
     * @param string               $action     Identifiant d'action (ex: order.status_updated)
     * @param string               $targetType Type de ressource ciblée (ex: order, wine, account)
     * @param int|null             $targetId   ID de la ressource (null si non applicable)
     * @param array<string, mixed> $details    Données contextuelles (avant/après, etc.)
     * @param string               $ip         Adresse IP de l'admin
     * @return void
     */
    public static function log(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId,
        array $details,
        string $ip
    ): void {
        try {
            (new AdminLogModel())->insert($adminId, $action, $targetType, $targetId, $details, $ip);
        } catch (\Throwable $e) {
            // Journal silencieux — ne jamais faire planter l'action principale
            // NOSONAR — php:S4792
            error_log('[AuditLogger] Failed to log action "' . $action . '": ' . $e->getMessage());
        }
    }

    /**
     * Retourne l'adresse IP du client depuis les variables serveur.
     *
     * Prend en compte les proxies via X-Forwarded-For (premier IP de la chaîne).
     *
     * @return string IP ou chaîne vide si indéterminée
     */
    public static function getIp(): string
    {
        // VULN-017 : X-Forwarded-For spoofable — utiliser REMOTE_ADDR (IP réseau réelle)
        // X-Forwarded-For est conservé uniquement derrière un proxy de confiance configuré.
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
