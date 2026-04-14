<?php

declare(strict_types=1);

namespace Service;

use Model\AddressModel;

/**
 * Construit le contexte adresses + date à passer à OrderSummaryPdfService
 * pour le PDF récap envoyé au gérant.
 *
 * Centralise la logique partagée entre OrderController (retour paiement CA)
 * et IpnController (notification IPN CA) — duplication détectée par Sonar PR#141.
 */
class OrderPdfContextBuilder
{
    public function __construct(private ?AddressModel $addresses = null)
    {
        $this->addresses ??= new AddressModel();
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{ordered_at: string, billing: array<string, mixed>, delivery: array<string, mixed>|null}|null
     */
    public function build(array $snapshot): ?array
    {
        $userId     = (int) ($snapshot['user_id'] ?? 0);
        $billingId  = (int) ($snapshot['billing_address_id']  ?? 0);
        $deliveryId = (int) ($snapshot['delivery_address_id'] ?? 0);
        try {
            $billing = ($userId > 0 && $billingId > 0)
                ? $this->addresses->findByIdForUser($billingId, $userId)
                : null;
            if ($billing === null) {
                return null;
            }
            $delivery = match (true) {
                $deliveryId === $billingId => $billing,
                $deliveryId > 0            => $this->addresses->findByIdForUser($deliveryId, $userId),
                default                    => null,
            };
            return [
                'ordered_at' => date('Y-m-d H:i:s'),
                'billing'    => $billing,
                'delivery'   => $delivery,
            ];
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[PDF_CONTEXT]');
            return null;
        }
    }
}
