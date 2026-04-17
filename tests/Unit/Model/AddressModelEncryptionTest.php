<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\AddressModel;
use PHPUnit\Framework\TestCase;
use Service\EncryptionService;

/**
 * TU chiffrement AddressModel — street + phone chiffrés à l'écriture, déchiffrés à la lecture.
 */
class AddressModelEncryptionTest extends TestCase
{
    private EncryptionService $enc;
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';

        $dbStub = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbStub);

        $this->enc = new EncryptionService('test-key-address-model');
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
    }

    public function testCreateEncryptsStreetAndPhone(): void
    {
        $capturedArgs = null;

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('insert')->willReturnCallback(function (string $sql, array $params) use (&$capturedArgs): string {
            $capturedArgs = $params;
            return '1';
        });
        $this->dbInstanceProp->setValue(null, $dbMock);

        $model = new AddressModel($this->enc);
        $model->create(1, 'billing', 'Jean', 'Dupont', 'M', '12 rue de la Paix', 'Paris', '75001', 'France', '0612345678');

        $this->assertNotNull($capturedArgs);
        // Index 5 = street, index 9 = phone
        $this->assertStringStartsWith('ENC:', $capturedArgs[5], 'street doit être chiffré');
        $this->assertStringStartsWith('ENC:', $capturedArgs[9], 'phone doit être chiffré');
        $this->assertNotSame('12 rue de la Paix', $capturedArgs[5]);
        $this->assertNotSame('0612345678', $capturedArgs[9]);
    }

    public function testUpdateEncryptsStreetAndPhone(): void
    {
        $capturedArgs = null;

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('execute')->willReturnCallback(function (string $sql, array $params) use (&$capturedArgs): int {
            $capturedArgs = $params;
            return 1;
        });
        $this->dbInstanceProp->setValue(null, $dbMock);

        $model = new AddressModel($this->enc);
        $model->update(1, 1, 'Jean', 'Dupont', 'M', '15 avenue Victor Hugo', 'Lyon', '69001', 'France', '0698765432');

        $this->assertNotNull($capturedArgs);
        // Index 3 = street, index 7 = phone
        $this->assertStringStartsWith('ENC:', $capturedArgs[3], 'street doit être chiffré');
        $this->assertStringStartsWith('ENC:', $capturedArgs[7], 'phone doit être chiffré');
    }

    public function testGetByUserDecryptsStreetAndPhone(): void
    {
        $encStreet = $this->enc->encrypt('12 rue de la Paix');
        $encPhone  = $this->enc->encrypt('0612345678');

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('fetchAll')->willReturn([[
            'id' => 1, 'user_id' => 1, 'type' => 'billing',
            'firstname' => 'Jean', 'lastname' => 'Dupont', 'civility' => 'M',
            'street' => $encStreet, 'city' => 'Paris', 'zip_code' => '75001',
            'country' => 'France', 'phone' => $encPhone, 'saved' => 1,
            'deleted_at' => null, 'preserved_for_order' => 0,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => null,
        ]]);
        $this->dbInstanceProp->setValue(null, $dbStub);

        $model   = new AddressModel($this->enc);
        $results = $model->getByUser(1);

        $this->assertCount(1, $results);
        $this->assertSame('12 rue de la Paix', $results[0]['street']);
        $this->assertSame('0612345678', $results[0]['phone']);
    }

    public function testDecryptRowFallsBackOnLegacyOrPlainValue(): void
    {
        $dbStub = $this->createStub(Database::class);
        $dbStub->method('fetchOne')->willReturn([
            'id' => 2, 'user_id' => 1, 'type' => 'delivery',
            'firstname' => 'Marie', 'lastname' => 'Curie', 'civility' => 'F',
            'street' => 'valeur-en-clair-sans-prefixe', 'city' => 'Bordeaux',
            'zip_code' => '33000', 'country' => 'France',
            'phone' => 'tel-en-clair', 'saved' => 1,
            'deleted_at' => null, 'preserved_for_order' => 0,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => null,
        ]);
        $this->dbInstanceProp->setValue(null, $dbStub);

        $model = new AddressModel($this->enc);
        $row   = $model->findByIdForUser(2, 1);

        $this->assertNotNull($row);
        // decrypt() retourne null sur valeur non déchiffrable → fallback sur valeur brute
        $this->assertSame('valeur-en-clair-sans-prefixe', $row['street']);
        $this->assertSame('tel-en-clair', $row['phone']);
    }
}
