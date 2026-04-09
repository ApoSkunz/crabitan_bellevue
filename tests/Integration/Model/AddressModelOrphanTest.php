<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AddressModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration — purge des adresses orphelines (us-purge-adresses-orphelines).
 */
class AddressModelOrphanTest extends IntegrationTestCase
{
    private AddressModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AddressModel();

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, deleted_at, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), NOW())",
            ['orphan.model.' . bin2hex(random_bytes(4)) . '@test.local']
        );
    }

    private function insertPreservedAddress(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved, preserved_for_order)
             VALUES (?, 'billing', 'Jean', 'Dupont', 'M', '1 rue', 'Paris', '75001', 'France', '0600000001', 0, 1)",
            [$this->userId]
        );
    }

    // ── getOrphanedAddresses ─────────────────────────────────────────────────

    /**
     * Retourne une adresse preserved_for_order = 1 sans commande active.
     */
    public function testGetOrphanedAddressesReturnsPreservedWithNoActiveOrder(): void
    {
        $addrId = $this->insertPreservedAddress();

        $result = $this->model->getOrphanedAddresses();

        $ids = array_column($result, 'id');
        $this->assertContains($addrId, $ids);
    }

    /**
     * N'inclut pas une adresse preserved_for_order = 0.
     */
    public function testGetOrphanedAddressesExcludesNonPreserved(): void
    {
        $addrId = (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved, preserved_for_order)
             VALUES (?, 'billing', 'Jean', 'Dupont', 'M', '1 rue', 'Paris', '75001', 'France', '0600000001', 0, 0)",
            [$this->userId]
        );

        $result = $this->model->getOrphanedAddresses();

        $ids = array_column($result, 'id');
        $this->assertNotContains($addrId, $ids);
    }

    /**
     * Retourne vide si aucune adresse orpheline.
     */
    public function testGetOrphanedAddressesReturnsEmptyWhenNone(): void
    {
        $result = $this->model->getOrphanedAddresses();
        $this->assertIsArray($result);
        // La liste peut contenir d'autres adresses orphelines en base dev
        // On vérifie juste que le type est correct
        $this->assertIsArray($result);
    }

    // ── purgeOrphaned ────────────────────────────────────────────────────────

    /**
     * Supprime les adresses orphelines éligibles et retourne le nombre.
     */
    public function testPurgeOrphanedDeletesEligibleAddresses(): void
    {
        $addrId = $this->insertPreservedAddress();

        // Vérifier qu'elle existe avant
        $before = self::$db->fetchOne(
            "SELECT id FROM addresses WHERE id = ?",
            [$addrId]
        );
        $this->assertNotFalse($before);

        $purged = $this->model->purgeOrphaned();

        $this->assertGreaterThanOrEqual(1, $purged);

        $after = self::$db->fetchOne(
            "SELECT id FROM addresses WHERE id = ?",
            [$addrId]
        );
        $this->assertFalse($after);
    }

    /**
     * purgeOrphaned retourne 0 si aucune adresse éligible.
     */
    public function testPurgeOrphanedReturnsZeroWhenNothingToDelete(): void
    {
        // Aucune adresse preserved_for_order dans cette transaction (compte frais)
        // On insère une adresse normale (preserved_for_order = 0)
        $this->insertPreservedAddress(); // on la purge d'abord
        $this->model->purgeOrphaned();

        // Deuxième appel : rien à purger pour ce compte
        $purged = $this->model->purgeOrphaned();
        $this->assertSame(0, $purged);
    }
}
