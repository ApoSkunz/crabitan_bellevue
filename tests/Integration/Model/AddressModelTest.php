<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AddressModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AddressModel.
 */
class AddressModelTest extends IntegrationTestCase
{
    private AddressModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AddressModel();

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['address.model.' . bin2hex(random_bytes(4)) . '@test.local']
        );
    }

    private function insertAddress(string $type = 'billing'): int
    {
        return (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, ?, 'Jean', 'Dupont', 'M', '1 rue de la Paix', 'Paris', '75001', 'France', '0600000001', 1)",
            [$this->userId, $type]
        );
    }

    // ----------------------------------------------------------------
    // getByUser
    // ----------------------------------------------------------------

    /**
     * getByUser retourne les adresses sauvegardées de l'utilisateur.
     */
    public function testGetByUserReturnsSavedAddresses(): void
    {
        $this->insertAddress('billing');
        $this->insertAddress('delivery');

        $result = $this->model->getByUser($this->userId);

        $this->assertCount(2, $result);
    }

    /**
     * getByUser retourne tableau vide si aucune adresse sauvegardée.
     */
    public function testGetByUserReturnsEmptyForNoAddresses(): void
    {
        $result = $this->model->getByUser($this->userId);

        $this->assertSame([], $result);
    }

    /**
     * getByUser n'inclut pas les adresses non sauvegardées (saved=0).
     */
    public function testGetByUserExcludesUnsavedAddresses(): void
    {
        self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, 'billing', 'Jean', 'Dupont', 'M', '1 rue Test', 'Paris', '75001', 'France', '0600000001', 0)",
            [$this->userId]
        );

        $result = $this->model->getByUser($this->userId);

        $this->assertCount(0, $result);
    }

    // ----------------------------------------------------------------
    // findByIdForUser
    // ----------------------------------------------------------------

    /**
     * findByIdForUser retourne l'adresse si elle appartient à l'utilisateur.
     */
    public function testFindByIdForUserReturnsAddress(): void
    {
        $id = $this->insertAddress('billing');

        $result = $this->model->findByIdForUser($id, $this->userId);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
    }

    /**
     * findByIdForUser retourne null si l'adresse n'appartient pas à l'utilisateur.
     */
    public function testFindByIdForUserReturnsNullForWrongUser(): void
    {
        $id = $this->insertAddress('billing');

        $result = $this->model->findByIdForUser($id, 999999);

        $this->assertNull($result);
    }

    /**
     * findByIdForUser retourne null si l'identifiant n'existe pas.
     */
    public function testFindByIdForUserReturnsNullForUnknownId(): void
    {
        $result = $this->model->findByIdForUser(999999, $this->userId);

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    /**
     * create insère une adresse de facturation et la retrouve avec getByUser.
     */
    public function testCreateInsertsBillingAddress(): void
    {
        $this->model->create(
            $this->userId,
            'billing',
            'Jean',
            'Dupont',
            'M',
            '12 rue de la Paix',
            'Paris',
            '75001',
            'France',
            '0600000001'
        );

        $result = $this->model->getByUser($this->userId);
        $this->assertCount(1, $result);
        $this->assertSame('billing', $result[0]['type']);
    }

    /**
     * create avec un type invalide n'insère rien.
     */
    public function testCreateWithInvalidTypeDoesNotInsert(): void
    {
        $this->model->create(
            $this->userId,
            'invalid_type',
            'Jean',
            'Dupont',
            'M',
            '12 rue de la Paix',
            'Paris',
            '75001',
            'France',
            '0600000001'
        );

        $result = $this->model->getByUser($this->userId);
        $this->assertCount(0, $result);
    }

    // ----------------------------------------------------------------
    // update
    // ----------------------------------------------------------------

    /**
     * update modifie les données de l'adresse existante.
     */
    public function testUpdateModifiesAddress(): void
    {
        $id = $this->insertAddress('billing');

        $this->model->update(
            $id,
            $this->userId,
            'Marie',
            'Martin',
            'F',
            '99 avenue Victor Hugo',
            'Lyon',
            '69001',
            'France',
            '0611111111'
        );

        $result = $this->model->findByIdForUser($id, $this->userId);
        $this->assertNotNull($result);
        $this->assertSame('Marie', $result['firstname']);
        $this->assertSame('Lyon', $result['city']);
    }

    // ----------------------------------------------------------------
    // deleteForUser
    // ----------------------------------------------------------------

    /**
     * deleteForUser supprime physiquement une adresse jamais utilisée dans une commande.
     */
    public function testDeleteForUserHardDeletesUnsnapshotedAddress(): void
    {
        $id = $this->insertAddress('billing');

        $this->model->deleteForUser($id, $this->userId);

        $row = self::$db->fetchOne(
            "SELECT id FROM addresses WHERE id = ?",
            [$id]
        );
        $this->assertFalse($row);
    }

    /**
     * deleteForUser soft-delete (deleted_at) une adresse qui a un snapshot de commande.
     */
    public function testDeleteForUserSoftDeletesAddressWithOrderSnapshot(): void
    {
        $id = $this->insertAddress('billing');

        // Créer un snapshot liant cette adresse à une commande passée
        self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             VALUES (?, 'M', 'Jean', 'Dupont', '1 rue de la Paix', 'Paris', '75001', 'France', '0600000001')",
            [$id]
        );

        $this->model->deleteForUser($id, $this->userId);

        // L'adresse existe encore en BDD (non supprimée physiquement)
        $row = self::$db->fetchOne(
            "SELECT deleted_at, saved FROM addresses WHERE id = ?",
            [$id]
        );
        $this->assertNotFalse($row, "L'adresse doit exister en BDD après soft-delete");
        $this->assertNotNull($row['deleted_at'], 'deleted_at doit être renseigné');
        $this->assertSame(0, (int) $row['saved'], 'saved doit être à 0');
    }

    /**
     * getByUser exclut les adresses soft-deleted (deleted_at IS NOT NULL).
     */
    public function testGetByUserExcludesSoftDeletedAddresses(): void
    {
        $id = $this->insertAddress('billing');

        // Simuler un soft-delete manuel
        self::$db->execute(
            "UPDATE addresses SET deleted_at = NOW(), saved = 0 WHERE id = ?",
            [$id]
        );

        $result = $this->model->getByUser($this->userId);
        $this->assertCount(0, $result);
    }

    /**
     * findByIdForUser retourne null pour une adresse soft-deleted.
     */
    public function testFindByIdForUserReturnsNullForSoftDeletedAddress(): void
    {
        $id = $this->insertAddress('billing');

        self::$db->execute(
            "UPDATE addresses SET deleted_at = NOW(), saved = 0 WHERE id = ?",
            [$id]
        );

        $result = $this->model->findByIdForUser($id, $this->userId);
        $this->assertNull($result);
    }

    /**
     * deleteForUser ne touche pas les adresses d'un autre utilisateur.
     */
    public function testDeleteForUserDoesNotAffectOtherUsers(): void
    {
        $otherUserId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['other.addr.' . bin2hex(random_bytes(4)) . '@test.local']
        );
        $otherAddrId = (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, 'billing', 'Autre', 'User', 'M', '1 rue X', 'Nice', '06000', 'France', '0600000099', 1)",
            [$otherUserId]
        );

        // Tentative de suppression avec le mauvais userId — ne doit rien faire
        $this->model->deleteForUser($otherAddrId, $this->userId);

        // L'adresse de l'autre user doit rester intacte
        $row = self::$db->fetchOne(
            "SELECT saved FROM addresses WHERE id = ?",
            [$otherAddrId]
        );
        $this->assertSame(1, (int) ($row['saved'] ?? 0));
    }

    // ----------------------------------------------------------------
    // hasOrderSnapshot
    // ----------------------------------------------------------------

    /**
     * hasOrderSnapshot retourne false pour une adresse sans snapshot.
     */
    public function testHasOrderSnapshotReturnsFalseWhenNoSnapshot(): void
    {
        $id = $this->insertAddress('billing');

        $this->assertFalse($this->model->hasOrderSnapshot($id));
    }

    /**
     * hasOrderSnapshot retourne true quand un snapshot existe pour cette adresse.
     */
    public function testHasOrderSnapshotReturnsTrueWhenSnapshotExists(): void
    {
        $id = $this->insertAddress('delivery');

        self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             VALUES (?, 'M', 'Jean', 'Dupont', '1 rue de la Paix', 'Paris', '75001', 'France', '0600000001')",
            [$id]
        );

        $this->assertTrue($this->model->hasOrderSnapshot($id));
    }
}
