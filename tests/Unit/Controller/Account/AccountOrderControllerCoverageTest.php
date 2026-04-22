<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountOrderController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;

/**
 * Tests de couverture complémentaires pour AccountOrderController.
 * Cible les branches non couvertes :
 *   - cancelOrder() catch Exception de sendReturnEmails (lines 251-252)
 *   - sendReturnEmails() early return clientEmail vide (line 390)
 *   - buildReturnSlipPdf() items foreach loop avec is_cuvee_speciale (lines 446-457)
 */
#[AllowMockObjectsWithoutExpectations]
class AccountOrderControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'test-csrf'];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/commandes';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un TestableAccountOrderController.
     *
     * @param UserRepository|null $userRepo
     * @param string              $method
     * @param string              $uri
     * @return TestableAccountOrderController
     */
    private function makeController(
        ?UserRepository $userRepo = null,
        string $method = 'GET',
        string $uri = '/fr/mon-compte/commandes'
    ): TestableAccountOrderController {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        return new TestableAccountOrderController(new Request(), $userRepo);
    }

    /**
     * Injecte un mock OrderModel dans le controller via réflexion.
     *
     * @param TestableAccountOrderController $ctrl
     * @param OrderModel                     $orders
     */
    private function injectOrders(TestableAccountOrderController $ctrl, OrderModel $orders): void
    {
        $ref = new \ReflectionProperty(AccountOrderController::class, 'orders');
        $ref->setValue($ctrl, $orders);
    }

    /**
     * Crée un stub UserRepository retournant un compte individual avec email.
     *
     * @param string $email
     * @return UserRepository
     */
    private function makeIndividualUserRepo(string $email = 'jean@example.com'): UserRepository
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findById')->willReturn([
            'id'           => 1,
            'email'        => $email,
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'lang'         => 'fr',
        ]);
        return $repo;
    }

    // ----------------------------------------------------------------
    // cancelOrder() — catch Exception de sendReturnEmails (lines 251-252)
    // ----------------------------------------------------------------

    /**
     * cancelOrder() quand requestReturnForUser réussit et sendReturnEmails lève une exception
     * (MailService non disponible en test) → exception catchée silencieusement, flash success posé.
     *
     * sendReturnEmails appelle buildReturnSlipPdf (TCPDF) puis MailService → SMTP indisponible → throw.
     * L'exception est catchée à la ligne 251 et loggée (ligne 252).
     */
    public function testCancelOrderSendReturnEmailsExceptionIsCaughtSilently(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];

        $userRepo = $this->makeIndividualUserRepo('jean@example.com');

        $order = [
            'payment_method'  => 'transfer',
            'status'          => 'delivered',
            'delivered_at'    => date('Y-m-d H:i:s', strtotime('-1 day')),
            'content'         => '[]',
            'order_reference' => 'REF-TEST-001',
            'price'           => '50.00',
            'bill_civility'   => 'M',
            'bill_firstname'  => 'Jean',
            'bill_lastname'   => 'Dupont',
            'bill_street'     => '1 rue de la Paix',
            'bill_zip'        => '75001',
            'bill_city'       => 'Paris',
            'bill_country'    => 'France',
            'ordered_at'      => date('Y-m-d H:i:s', strtotime('-5 days')),
        ];

        $ordersMock = $this->createMock(OrderModel::class);
        // Premier findDetailForUser : pour cancelOrder
        // Deuxième findDetailForUser : pour sendReturnEmails
        $ordersMock->method('findDetailForUser')->willReturn($order);
        $ordersMock->method('cancelForUser')->willReturn(false);
        $ordersMock->method('requestReturnForUser')->willReturn(true);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        // Flash success doit être posé malgré l'exception de sendReturnEmails
        $this->assertSame(__('account.order_return_requested'), $_SESSION['flash']['order_success'] ?? null);
    }

    // ----------------------------------------------------------------
    // sendReturnEmails() — early return clientEmail vide (line 390)
    // ----------------------------------------------------------------

    /**
     * sendReturnEmails() avec compte dont l'email est vide → early return sans envoi mail.
     *
     * Invoque la méthode privée via ReflectionMethod directement.
     */
    public function testSendReturnEmailsWithEmptyClientEmailReturnsEarly(): void
    {
        $order = [
            'status'          => 'return_requested',
            'content'         => '[]',
            'order_reference' => 'REF-001',
            'bill_civility'   => 'M',
            'bill_firstname'  => 'Jean',
            'bill_lastname'   => 'Dupont',
            'bill_street'     => '1 rue',
            'bill_zip'        => '75001',
            'bill_city'       => 'Paris',
            'bill_country'    => 'France',
            'ordered_at'      => date('Y-m-d H:i:s', strtotime('-5 days')),
        ];

        // UserRepo : premier appel (requireIndividual via orders()) → individual
        // Dans sendReturnEmails : findById retourne compte avec email vide
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn([
            'id'           => 1,
            'email'        => '', // email vide → early return line 390
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'lang'         => 'fr',
        ]);

        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo);
        $this->injectOrders($ctrl, $ordersMock);

        $ref = new \ReflectionMethod(AccountOrderController::class, 'sendReturnEmails');
        // Doit retourner sans throw (early return)
        $ref->invoke($ctrl, 1, 1, 'fr');

        $this->assertTrue(true); // No exception = early return path covered
    }

    // ----------------------------------------------------------------
    // buildReturnSlipPdf() — items foreach avec is_cuvee_speciale (lines 446-457)
    // ----------------------------------------------------------------

    /**
     * buildReturnSlipPdf() avec items contenant des vins normaux et un Cuvée Spéciale
     * → foreach loop exécutée, branche is_cuvee_speciale couverte.
     *
     * Invoque la méthode privée via ReflectionMethod.
     */
    public function testBuildReturnSlipPdfWithItemsCoversForEachLoop(): void
    {
        $items = [
            [
                'label_name'       => 'Château Crabitan Blanc',
                'qty'              => 2,
                'price'            => '15.50',
                'vintage'          => 2021,
                'is_cuvee_speciale' => false,
            ],
            [
                'label_name'       => 'Château Crabitan Rouge',
                'qty'              => 1,
                'price'            => '18.00',
                'vintage'          => 0, // pas de millésime → vintageCell vide
                'is_cuvee_speciale' => true, // branche is_cuvee_speciale couverte
            ],
        ];

        $order = [
            'content'         => json_encode($items),
            'order_reference' => 'REF-SLIP-001',
            'bill_civility'   => 'M',
            'bill_firstname'  => 'Jean',
            'bill_lastname'   => 'Dupont',
            'bill_street'     => '1 rue de la Paix',
            'bill_zip'        => '75001',
            'bill_city'       => 'Paris',
            'bill_country'    => 'France',
            'ordered_at'      => '2026-01-15 10:00:00',
        ];

        $ctrl = $this->makeController();

        $ref    = new \ReflectionMethod(AccountOrderController::class, 'buildReturnSlipPdf');
        $result = $ref->invoke($ctrl, $order);

        // Le résultat est un PDF binaire — on vérifie juste que c'est non vide et commence par %PDF
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    /**
     * buildReturnSlipPdf() avec items vides → foreach non exécuté, PDF généré quand même.
     */
    public function testBuildReturnSlipPdfWithEmptyItemsGeneratesPdf(): void
    {
        $order = [
            'content'         => '[]',
            'order_reference' => 'REF-EMPTY-001',
            'bill_civility'   => '',
            'bill_firstname'  => '',
            'bill_lastname'   => '',
            'bill_street'     => '',
            'bill_zip'        => '',
            'bill_city'       => '',
            'bill_country'    => '',
            'ordered_at'      => '2026-01-15 10:00:00',
        ];

        $ctrl = $this->makeController();

        $ref    = new \ReflectionMethod(AccountOrderController::class, 'buildReturnSlipPdf');
        $result = $ref->invoke($ctrl, $order);

        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('%PDF', $result);
    }
}
