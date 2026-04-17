<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AddressModel;
use Model\OrderAddressSnapshotModel;
use Model\OrderModel;
use Service\EncryptionService;
use Tests\Integration\IntegrationTestCase;

/**
 * TI chiffrement adresses dans le flux commande.
 *
 * Vérifie le round-trip : adresse chiffrée en BDD → déchiffrement par OrderModel::fetchAddressById()
 * → re-chiffrement par OrderAddressSnapshotModel::createFromAddress() → déchiffrement à la lecture.
 */
class OrderModelAddressEncryptionTest extends IntegrationTestCase
{
    private AddressModel $addressModel;
    private OrderModel $orderModel;
    private OrderAddressSnapshotModel $snapshotModel;
    private EncryptionService $enc;

    protected function setUp(): void
    {
        parent::setUp();

        // DB_ENCRYPT_KEY peut être unset par EncryptionServiceTest — recharger depuis .env
        if (($key = $_ENV['DB_ENCRYPT_KEY'] ?? '') === '') {
            $dotenv = is_file(ROOT_PATH . '/.env') ? parse_ini_file(ROOT_PATH . '/.env') : [];
            $_ENV['DB_ENCRYPT_KEY'] = $dotenv['DB_ENCRYPT_KEY'] ?? '';
            unset($key, $dotenv);
        }

        $this->enc           = EncryptionService::fromEnv();
        $this->addressModel  = new AddressModel($this->enc);
        $this->orderModel    = new OrderModel($this->enc);
        $this->snapshotModel = new OrderAddressSnapshotModel($this->enc);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte minimal et retourne son ID.
     *
     * @return int
     */
    private function insertAccount(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            ['enc_test_' . uniqid() . '@example.com', password_hash('pass', PASSWORD_BCRYPT)]
        );
    }

    /**
     * Insère un vin minimal et retourne son ID.
     *
     * @return int
     */
    private function insertWine(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              image_path, slug)
             VALUES ('Vin Enc Test','red','bottle',2022,20.00,100,1,
                     5.00,'Test','Merlot',15,'C FR','C EN','C FR','C EN','C FR','C EN',
                     'C FR','C EN','C FR','C EN','C FR','C EN',
                     '','','','','test-enc.png','vin-enc-test-" . uniqid() . "')",
            []
        );
    }

    // ----------------------------------------------------------------
    // Test principal : round-trip chiffrement via flux commande
    // ----------------------------------------------------------------

    /**
     * Vérifie que le snapshot stocké contient des valeurs chiffrées (ENC:)
     * et que la lecture via getForUserExport() retourne les valeurs en clair.
     *
     * @return void
     */
    public function testOrderCreationStoresEncryptedSnapshotAndDecryptsOnRead(): void
    {
        $userId  = $this->insertAccount();
        $wineId  = $this->insertWine();

        // Insère une adresse via AddressModel — street/phone auto-chiffrés
        $addressId = (int) $this->addressModel->create(
            $userId,
            'billing',
            'Jean',
            'Dupont',
            'M',
            '12 rue de la Vigne',
            'Bordeaux',
            '33000',
            'France',
            '0600000000'
        );
        $this->assertGreaterThan(0, $addressId);

        // Vérifie que la valeur stockée en BDD est bien chiffrée
        $raw = self::$db->fetchOne(
            "SELECT street, phone FROM addresses WHERE id = ?",
            [$addressId]
        );
        $this->assertNotFalse($raw);
        $this->assertStringStartsWith('ENC:', $raw['street'], 'street doit être chiffré en BDD');
        $this->assertStringStartsWith('ENC:', $raw['phone'], 'phone doit être chiffré en BDD');

        // Crée une commande — OrderModel déchiffre l'adresse puis la passe au snapshot
        $reference = $this->orderModel->create(
            $userId,
            [['id' => $wineId, 'qty' => 1, 'price' => 20.00]],
            20.00,
            'card',
            0.0,
            $addressId,
            null,
            '1.0'
        );
        $this->assertNotEmpty($reference);

        // Récupère le snapshot créé par la commande
        $order = self::$db->fetchOne(
            "SELECT id_billing_snapshot FROM orders WHERE order_reference = ?",
            [$reference]
        );
        $this->assertNotFalse($order);
        $snapshotId = (int) $order['id_billing_snapshot'];
        $this->assertGreaterThan(0, $snapshotId);

        // Le snapshot raw en BDD doit contenir des valeurs chiffrées
        $snapRaw = self::$db->fetchOne(
            "SELECT street, phone FROM order_address_snapshots WHERE id = ?",
            [$snapshotId]
        );
        $this->assertNotFalse($snapRaw);
        $this->assertStringStartsWith('ENC:', $snapRaw['street'], 'snapshot.street doit être chiffré en BDD');
        $this->assertStringStartsWith('ENC:', $snapRaw['phone'], 'snapshot.phone doit être chiffré en BDD');

        // La lecture via getForUserExport() doit retourner les valeurs en clair
        $exports = $this->snapshotModel->getForUserExport($userId);
        $this->assertNotEmpty($exports);

        $found = null;
        foreach ($exports as $row) {
            if ($row['street'] === '12 rue de la Vigne') {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull($found, 'getForUserExport() doit retourner la ligne avec le street déchiffré');
        $this->assertSame('12 rue de la Vigne', $found['street']);
        $this->assertSame('0600000000', $found['phone']);
    }

    /**
     * Vérifie que AddressModel retourne les valeurs déchiffrées à la lecture (getByUser).
     *
     * @return void
     */
    public function testAddressModelGetByUserReturnsDecryptedValues(): void
    {
        $userId    = $this->insertAccount();
        $addressId = (int) $this->addressModel->create(
            $userId,
            'delivery',
            'Marie',
            'Martin',
            'F',
            '5 allée des Roses',
            'Lyon',
            '69001',
            'France',
            '0478000001'
        );

        $addresses = $this->addressModel->getByUser($userId);
        $this->assertCount(1, $addresses);
        $this->assertSame('5 allée des Roses', $addresses[0]['street']);
        $this->assertSame('0478000001', $addresses[0]['phone']);
    }
}
