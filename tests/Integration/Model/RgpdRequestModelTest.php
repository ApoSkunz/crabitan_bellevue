<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\RgpdRequestModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour RgpdRequestModel.
 *
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 * Couvre : CRUD complet + filtres + méthodes métier overdue.
 */
class RgpdRequestModelTest extends IntegrationTestCase
{
    private RgpdRequestModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RgpdRequestModel();
    }

    /**
     * Insère un compte admin minimal et retourne son ID.
     *
     * @param string $email Email unique pour ce test
     * @return int ID du compte inséré
     */
    private function insertAdmin(string $email = 'rgpd_admin@example.com'): int
    {
        return (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'admin', 'fr', NOW())",
            [$email, password_hash('pass', PASSWORD_BCRYPT)]
        );
    }

    /**
     * Insère une demande RGPD de test et retourne son ID.
     *
     * @param string $type       Type RGPD
     * @param string $email      Email demandeur
     * @param string $name       Nom demandeur
     * @param string $receivedAt Date au format Y-m-d H:i:s
     * @return int ID de la demande insérée
     */
    private function insertRequest(
        string $type = 'acces',
        string $email = 'demandeur@example.com',
        string $name = 'Jean Dupont',
        string $receivedAt = ''
    ): int {
        $received = $receivedAt ?: date('Y-m-d H:i:s');
        return $this->model->create($type, $email, $name, $received);
    }

    public function testCreateReturnsPositiveId(): void
    {
        $id = $this->insertRequest();
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateDefaultStatusIsNouvelle(): void
    {
        $id  = $this->insertRequest();
        $row = $this->model->findById($id);
        $this->assertNotFalse($row);
        $this->assertSame('nouvelle', $row['status']);
    }

    public function testCreatePersistsAllFields(): void
    {
        $receivedAt = '2026-01-10 09:00:00';
        $id         = $this->insertRequest('effacement', 'alice@example.com', 'Alice Martin', $receivedAt);
        $row        = $this->model->findById($id);
        $this->assertNotFalse($row);
        $this->assertSame('effacement', $row['type']);
        $this->assertSame('alice@example.com', $row['requester_email']);
        $this->assertStringStartsWith('2026-01-10', $row['received_at']);
    }

    public function testCreateThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->create('type_invalide', 'x@x.fr', 'X');
    }

    public function testFindByIdReturnsFalseForUnknownId(): void
    {
        $this->assertFalse($this->model->findById(999999));
    }

    public function testFindByIdReturnsPreviouslyInsertedRow(): void
    {
        $id  = $this->insertRequest('portabilite', 'bob@example.com', 'Bob Leblanc');
        $row = $this->model->findById($id);
        $this->assertNotFalse($row);
        $this->assertSame('portabilite', $row['type']);
    }

    public function testCountIncludesInsertedRows(): void
    {
        $before = $this->model->count();
        $this->insertRequest();
        $this->insertRequest('rectification', 'rect@example.com', 'Rect User');
        $this->assertSame($before + 2, $this->model->count());
    }

    public function testUpdateStatusChangesStatusAndTreatedBy(): void
    {
        $id      = $this->insertRequest();
        $adminId = $this->insertAdmin('upd_admin@example.com');
        $ok      = $this->model->updateStatus($id, 'en_cours', $adminId);
        $row     = $this->model->findById($id);
        $this->assertTrue($ok);
        $this->assertSame('en_cours', $row['status']);
    }

    public function testUpdateStatusWithTrateeSetsTeatedAt(): void
    {
        $id      = $this->insertRequest();
        $adminId = $this->insertAdmin('adm_treated@example.com');
        $this->model->updateStatus($id, 'traitee', $adminId, 'Note.');
        $row = $this->model->findById($id);
        $this->assertNotNull($row['treated_at']);
    }

    public function testUpdateStatusReturnsFalseForInvalidStatus(): void
    {
        $id = $this->insertRequest();
        $this->assertFalse($this->model->updateStatus($id, 'invalide', 1));
    }

    public function testDeleteRemovesRequest(): void
    {
        $id = $this->insertRequest();
        $this->assertSame(1, $this->model->delete($id));
        $this->assertFalse($this->model->findById($id));
    }

    public function testOldRequestAppearsInOverdue(): void
    {
        $receivedAt = date('Y-m-d H:i:s', strtotime('-26 days'));
        $id         = $this->insertRequest('acces', 'old_req@example.com', 'Old Req', $receivedAt);
        $overdue    = $this->model->getOverdueRequests();
        $found      = array_filter($overdue, fn($r) => (int) $r['id'] === $id);
        $this->assertNotEmpty($found);
    }

    public function testTreatedRequestDoesNotAppearInOverdue(): void
    {
        $receivedAt = date('Y-m-d H:i:s', strtotime('-30 days'));
        $id         = $this->insertRequest('acces', 'treated_old@example.com', 'Treated Old', $receivedAt);
        $adminId    = $this->insertAdmin('adm_old@example.com');
        $this->model->updateStatus($id, 'traitee', $adminId);
        $overdue = $this->model->getOverdueRequests();
        $found   = array_filter($overdue, fn($r) => (int) $r['id'] === $id);
        $this->assertEmpty($found);
    }
}
