<?php

declare(strict_types=1);

namespace Service;

/**
 * Client réel de l'API GAE Crédit Agricole e-Transactions.
 *
 * Délègue à PaymentService::callGaeCancelOrRefund() qui implémente
 * déjà le protocole CA (TYPE=00014, HMAC-SHA512, etc.).
 */
class GaeApiClient implements GaeApiInterface
{
    private PaymentService $paymentService;

    /**
     * Initialise le client GAE avec le service de paiement CA.
     *
     * @param PaymentService|null $paymentService Instance optionnelle (injection de dépendance)
     */
    public function __construct(?PaymentService $paymentService = null)
    {
        $this->paymentService = $paymentService ?? new PaymentService();
    }

    /**
     * Déclenche un remboursement GAE via PaymentService.
     *
     * @param string $orderReference Référence de la commande
     * @param string $numappel       Numéro d'appel CA (NUMAPPEL PBX_RETOUR)
     * @param string $numtrans       Numéro de transaction CA (NUMTRANS PBX_RETOUR)
     * @param int    $amountCents    Montant en centimes d'euros
     * @return bool True si le remboursement a réussi
     */
    public function refund(string $orderReference, string $numappel, string $numtrans, int $amountCents): bool
    {
        return $this->paymentService->callGaeCancelOrRefund($orderReference, $numappel, $numtrans, $amountCents);
    }
}
