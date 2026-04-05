<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\OrderAddressSnapshotModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour OrderAddressSnapshotModel.
 *
 * Vérifie la création de snapshots d'adresses indépendants des comptes.
 */
class OrderAddressSnapshotModelTest extends IntegrationTestCase
{
    private OrderAddressSnapshotModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrderAddressSnapshotModel();
    }

    // ----------------------------------------------------------------
    // createFromAddress — données complètes
    // ----------------------------------------------------------------

    /**
     * Vérifie que createFromAddress insère un snapshot et retourne un ID > 0.
     *
     * @return void
     */
    public function testCreateFromAddressReturnsPositiveId(): void
    {
        $id = $this->model->createFromAddress([
            'civility'  => 'M',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
            'street'    => '12 rue de la Vigne',
            'city'      => 'Bordeaux',
            'zip_code'  => '33000',
            'country'   => 'France',
            'phone'     => '0600000000',
        ]);

        $this->assertGreaterThan(0, $id);
    }

    /**
     * Vérifie que les données sont correctement persistées en BDD.
     *
     * @return void
     */
    public function testCreateFromAddressPersistsAllFields(): void
    {
        $id = $this->model->createFromAddress([
            'civility'  => 'F',
            'firstname' => 'Marie',
            'lastname'  => 'Martin',
            'street'    => '5 avenue du Vin',
            'city'      => 'Lyon',
            'zip_code'  => '69001',
            'country'   => 'France',
            'phone'     => '0700000001',
        ]);

        $row = self::$db->fetchOne(
            "SELECT * FROM order_address_snapshots WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($row);
        $this->assertSame('F', $row['civility']);
        $this->assertSame('Marie', $row['firstname']);
        $this->assertSame('Martin', $row['lastname']);
        $this->assertSame('5 avenue du Vin', $row['street']);
        $this->assertSame('Lyon', $row['city']);
        $this->assertSame('69001', $row['zip']);
        $this->assertSame('France', $row['country']);
        $this->assertSame('0700000001', $row['phone']);
    }

    // ----------------------------------------------------------------
    // createFromAddress — clé zip alternative
    // ----------------------------------------------------------------

    /**
     * Vérifie que la clé `zip` (sans _code) est également acceptée.
     *
     * @return void
     */
    public function testCreateFromAddressAcceptsZipKey(): void
    {
        $id = $this->model->createFromAddress([
            'firstname' => 'Paul',
            'lastname'  => 'Bernard',
            'street'    => '3 rue du Château',
            'city'      => 'Sainte-Croix-du-Mont',
            'zip'       => '33410',
            'country'   => 'France',
            'phone'     => '0500000002',
        ]);

        $row = self::$db->fetchOne(
            "SELECT zip FROM order_address_snapshots WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($row);
        $this->assertSame('33410', $row['zip']);
    }

    // ----------------------------------------------------------------
    // createFromAddress — civilité null
    // ----------------------------------------------------------------

    /**
     * Vérifie qu'un snapshot sans civilité (null) est accepté.
     *
     * @return void
     */
    public function testCreateFromAddressWithNullCivility(): void
    {
        $id = $this->model->createFromAddress([
            'civility'  => null,
            'firstname' => 'Alex',
            'lastname'  => 'Dupuis',
            'street'    => '1 Crabitan',
            'city'      => 'Sainte-Croix-du-Mont',
            'zip_code'  => '33410',
            'country'   => 'France',
            'phone'     => '',
        ]);

        $row = self::$db->fetchOne(
            "SELECT civility FROM order_address_snapshots WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($row);
        $this->assertNull($row['civility']);
    }

    // ----------------------------------------------------------------
    // Indépendance — pas de FK vers accounts
    // ----------------------------------------------------------------

    /**
     * Vérifie que les snapshots persistent après suppression d'un compte.
     * L'indépendance totale vis-à-vis de `accounts` garantit la conservation
     * des données de commande (Art. L123-22 Code de commerce).
     *
     * @return void
     */
    public function testSnapshotPersistsAfterAccountDeletion(): void
    {
        // Insérer un compte temporaire
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            ['snapshot_del@example.com', password_hash('pass', PASSWORD_BCRYPT)]
        );

        // Créer un snapshot (sans lien vers le compte)
        $snapshotId = $this->model->createFromAddress([
            'firstname' => 'Temp',
            'lastname'  => 'User',
            'street'    => '10 rue Test',
            'city'      => 'Paris',
            'zip_code'  => '75000',
            'country'   => 'France',
            'phone'     => '',
        ]);

        // Supprimer le compte — ne doit pas affecter le snapshot
        self::$db->execute("DELETE FROM accounts WHERE id = ?", [$accountId]);

        $row = self::$db->fetchOne(
            "SELECT id FROM order_address_snapshots WHERE id = ?",
            [$snapshotId]
        );

        $this->assertNotFalse($row);
        $this->assertSame($snapshotId, (int) $row['id']);
    }

    // ----------------------------------------------------------------
    // Déduplication — find-or-create par source_address_id
    // ----------------------------------------------------------------

    /**
     * Deux commandes avec la même adresse non modifiée → même snapshot_id.
     *
     * @return void
     */
    public function testCreateFromAddressReusesSameSnapshotWhenAddressUnchanged(): void
    {
        $addr = [
            'firstname' => 'Marie',
            'lastname'  => 'Dupont',
            'street'    => '3 rue du Vin',
            'city'      => 'Bordeaux',
            'zip_code'  => '33000',
            'country'   => 'France',
            'phone'     => '0600000001',
        ];

        $id1 = $this->model->createFromAddress($addr, 42);
        $id2 = $this->model->createFromAddress($addr, 42);

        $this->assertSame($id1, $id2);
    }

    /**
     * Même source_address_id mais adresse modifiée → nouveau snapshot créé.
     *
     * @return void
     */
    public function testCreateFromAddressCreatesNewSnapshotWhenAddressChanged(): void
    {
        $addr = [
            'firstname' => 'Paul',
            'lastname'  => 'Martin',
            'street'    => '10 rue Ancienne',
            'city'      => 'Lyon',
            'zip_code'  => '69001',
            'country'   => 'France',
            'phone'     => '',
        ];
        $addrModified = array_merge($addr, ['street' => '10 rue Nouvelle']);

        $id1 = $this->model->createFromAddress($addr, 99);
        $id2 = $this->model->createFromAddress($addrModified, 99);

        $this->assertNotSame($id1, $id2);
    }

    /**
     * Sans source_address_id (null) → toujours un nouveau snapshot (pas de déduplication).
     *
     * @return void
     */
    public function testCreateFromAddressWithoutSourceIdAlwaysCreatesNew(): void
    {
        $addr = [
            'firstname' => 'Alex',
            'lastname'  => 'Bernard',
            'street'    => '1 Crabitan',
            'city'      => 'Sainte-Croix-du-Mont',
            'zip_code'  => '33410',
            'country'   => 'France',
            'phone'     => '',
        ];

        $id1 = $this->model->createFromAddress($addr, null);
        $id2 = $this->model->createFromAddress($addr, null);

        $this->assertNotSame($id1, $id2);
    }

    /**
     * Vérifie que source_address_id est bien stocké dans le snapshot.
     *
     * @return void
     */
    public function testCreateFromAddressStoresSourceAddressId(): void
    {
        $id = $this->model->createFromAddress([
            'firstname' => 'Sophie',
            'lastname'  => 'Leblanc',
            'street'    => '7 allée des Vignes',
            'city'      => 'Bordeaux',
            'zip_code'  => '33000',
            'country'   => 'France',
            'phone'     => '',
        ], 77);

        $row = self::$db->fetchOne(
            "SELECT source_address_id FROM order_address_snapshots WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($row);
        $this->assertSame(77, (int) $row['source_address_id']);
    }
}
