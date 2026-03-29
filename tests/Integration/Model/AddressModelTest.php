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
     * deleteForUser marque l'adresse comme saved=0 (soft-delete).
     */
    public function testDeleteForUserSoftDeletesAddress(): void
    {
        $id = $this->insertAddress('billing');

        $this->model->deleteForUser($id, $this->userId);

        $result = $this->model->getByUser($this->userId);
        $this->assertCount(0, $result);
    }

    /**
     * deleteForUser ne touche pas les adresses d'un autre utilisateur.
     */
    public function testDeleteForUserDoesNotAffectOtherUsers(): void
    {
        $otherId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['other.addr.' . bin2hex(random_bytes(4)) . '@test.local']
        );
        $otherId = (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, 'billing', 'Autre', 'User', 'M', '1 rue X', 'Nice', '06000', 'France', '0600000099', 1)",
            [$otherId]
        );

        // Pas d'effet sur les adresses du mauvais utilisateur
        $this->model->deleteForUser($otherId, $this->userId);

        // L'adresse de l'autre user reste visible via une requête directe
        $row = self::$db->fetchOne(
            "SELECT saved FROM addresses WHERE id = ?",
            [$otherId]
        );
        $this->assertSame(1, (int) ($row['saved'] ?? 0));
    }
}
