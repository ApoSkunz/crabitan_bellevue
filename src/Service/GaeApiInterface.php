<?php

declare(strict_types=1);

namespace Service;

/**
 * Contrat d'appel API GAE Crédit Agricole e-Transactions.
 *
 * Permet l'injection de dépendance (mock en test, client réel en prod).
 */
interface GaeApiInterface
{
    /**
     * Déclenche un remboursement GAE pour la commande donnée.
     *
     * @param string $orderReference Référence de la commande
     * @param string $numappel       Numéro d'appel CA (NUMAPPEL PBX_RETOUR)
     * @param string $numtrans       Numéro de transaction CA (NUMTRANS PBX_RETOUR)
     * @param int    $amountCents    Montant en centimes d'euros
     * @return bool True si le remboursement a réussi
     */
    public function refund(string $orderReference, string $numappel, string $numtrans, int $amountCents): bool;
}
