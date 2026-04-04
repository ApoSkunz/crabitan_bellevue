<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\PaymentIntentModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour PaymentIntentModel.
 *
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 * Couvre : save, findByReference (valide / expiré / absent), delete, purgeExpired.
 */
class PaymentIntentModelTest extends IntegrationTestCase
{
    private PaymentIntentModel $model;

    /** @var int ID d'un compte de test */
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PaymentIntentModel();

        // Insérer un compte minimal pour satisfaire la FK user_id
        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            ['intent_test@example.com', password_hash('secret', PASSWORD_BCRYPT)]
        );
    }

    // ----------------------------------------------------------------
    // save()
    // ----------------------------------------------------------------

    /**
     * Vérifie qu'un intent est bien persisté et retrouvable.
     */
    public function testSavePersistsSnapshot(): void
    {
        $ref      = 'WEB-CB-TEST0001-2026';
        $snapshot = ['total' => 49.90, 'items' => [['sku' => 'ABC', 'qty' => 12]]];

        $this->model->save($ref, $this->userId, $snapshot);

        $found = $this->model->findByReference($ref);

        $this->assertNotNull($found);
        $this->assertSame(49.90, $found['total']);
    }

    /**
     * Vérifie que save() est idempotent : un second appel met à jour le snapshot.
     */
    public function testSaveUpdatesExistingIntent(): void
    {
        $ref = 'WEB-CB-TEST0002-2026';

        $this->model->save($ref, $this->userId, ['total' => 10.00]);
        $this->model->save($ref, $this->userId, ['total' => 99.99]);

        $found = $this->model->findByReference($ref);

        $this->assertNotNull($found);
        $this->assertSame(99.99, $found['total']);
    }

    // ----------------------------------------------------------------
    // findByReference()
    // ----------------------------------------------------------------

    /**
     * Vérifie que findByReference() retourne null pour une référence inconnue.
     */
    public function testFindByReferenceReturnsNullForUnknownRef(): void
    {
        $this->assertNull($this->model->findByReference('WEB-CB-UNKNOWN-0000'));
    }

    /**
     * Vérifie que findByReference() retourne null pour un intent expiré.
     */
    public function testFindByReferenceReturnsNullForExpiredIntent(): void
    {
        $ref = 'WEB-CB-EXPIRED-2026';

        // Insérer directement avec expires_at dans le passé
        self::$db->execute(
            "INSERT INTO payment_intents (reference, user_id, snapshot, expires_at)
             VALUES (:ref, :uid, :snap, DATE_SUB(NOW(), INTERVAL 2 HOUR))",
            [
                ':ref'  => $ref,
                ':uid'  => $this->userId,
                ':snap' => json_encode(['total' => 5.00]),
            ]
        );

        $this->assertNull($this->model->findByReference($ref));
    }

    /**
     * Vérifie que findByReference() retourne null si le JSON snapshot est invalide.
     */
    public function testFindByReferenceReturnsNullForInvalidJson(): void
    {
        $ref = 'WEB-CB-BADJSON-2026';

        self::$db->execute(
            "INSERT INTO payment_intents (reference, user_id, snapshot, expires_at)
             VALUES (:ref, :uid, :snap, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [
                ':ref'  => $ref,
                ':uid'  => $this->userId,
                ':snap' => 'not-valid-json{{',
            ]
        );

        $this->assertNull($this->model->findByReference($ref));
    }

    // ----------------------------------------------------------------
    // delete()
    // ----------------------------------------------------------------

    /**
     * Vérifie que delete() supprime bien l'intent.
     */
    public function testDeleteRemovesIntent(): void
    {
        $ref = 'WEB-CB-DELETE01-2026';

        $this->model->save($ref, $this->userId, ['total' => 20.00]);
        $this->assertNotNull($this->model->findByReference($ref));

        $this->model->delete($ref);

        $this->assertNull($this->model->findByReference($ref));
    }

    /**
     * Vérifie que delete() sur une référence inconnue ne lève pas d'exception.
     */
    public function testDeleteOnUnknownRefIsIdempotent(): void
    {
        // Ne doit pas lever d'exception
        $this->model->delete('WEB-CB-NOTEXIST-2026');
        $this->assertTrue(true); // si on arrive ici, c'est OK
    }

    // ----------------------------------------------------------------
    // purgeExpired()
    // ----------------------------------------------------------------

    /**
     * Vérifie que purgeExpired() supprime les intents expirés sans toucher les valides.
     */
    public function testPurgeExpiredRemovesOnlyExpiredIntents(): void
    {
        $refExpired = 'WEB-CB-PURGEXP-2026';
        $refValid   = 'WEB-CB-PURGVLD-2026';

        // Insérer un intent expiré
        self::$db->execute(
            "INSERT INTO payment_intents (reference, user_id, snapshot, expires_at)
             VALUES (:ref, :uid, :snap, DATE_SUB(NOW(), INTERVAL 3 HOUR))",
            [':ref' => $refExpired, ':uid' => $this->userId, ':snap' => json_encode(['total' => 1.00])]
        );

        // Insérer un intent valide
        $this->model->save($refValid, $this->userId, ['total' => 2.00]);

        $this->model->purgeExpired();

        // L'expiré doit avoir été supprimé
        $row = self::$db->fetchOne(
            'SELECT reference FROM payment_intents WHERE reference = :ref',
            [':ref' => $refExpired]
        );
        $this->assertFalse($row);

        // Le valide doit être intact
        $this->assertNotNull($this->model->findByReference($refValid));
    }
}
