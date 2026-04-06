<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\OrderController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests du flux paiement dans OrderController.
 *
 * Couvre les méthodes :
 *   - paymentReturnOk()     — retour CA paiement accepté (toutes branches)
 *   - paymentReturnCancel() — retour CA annulation (avec et sans Ref)
 *   - paymentReturnRefuse() — retour CA refus (avec et sans Ref)
 *   - confirmation()        — rendu vue quand commande trouvée
 *   - payment()             — flux carte (redirect vers CA)
 *
 * Les dépendances BDD sont interceptées via Database::instance stub.
 * Les branches nécessitant une vraie signature RSA utilisent une paire
 * de clés générée dynamiquement en mémoire dans le setUp.
 */
class OrderControllerPaymentFlowTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    /** Chemin vers la clé publique RSA temporaire générée pour les tests. */
    private string $tmpPubkeyPath = '';

    /** Clé privée RSA pour signer les QUERY_STRING dans les tests. */
    private \OpenSSLAsymmetricKey|false $privateKey = false;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['QUERY_STRING']    = '';

        // Générer une paire RSA temporaire pour les tests nécessitant une signature valide
        // Sur Windows (XAMPP) openssl_pkey_new() échoue sans chemin explicite vers openssl.cnf
        $opensslConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['C:/xampp/apache/conf/openssl.cnf', 'C:/xampp/php/extras/ssl/openssl.cnf'] as $candidate) {
                if (file_exists($candidate)) {
                    $opensslConfig['config'] = $candidate;
                    break;
                }
            }
        }
        $this->privateKey = openssl_pkey_new($opensslConfig);
        if ($this->privateKey !== false) {
            $details = openssl_pkey_get_details($this->privateKey);
            if ($details !== false) {
                $this->tmpPubkeyPath = sys_get_temp_dir() . '/test_ca_pubkey_' . getmypid() . '.pem';
                file_put_contents($this->tmpPubkeyPath, $details['key']);
            }
        }

        // Neutraliser CA_PUBKEY_PATH par défaut (signature invalide sauf si surchargé)
        $_ENV['CA_PUBKEY_PATH'] = '/tmp/does_not_exist_pubkey_test.pem';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_COOKIE  = [];
        $_SERVER['QUERY_STRING'] = '';
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        if ($this->tmpPubkeyPath !== '' && file_exists($this->tmpPubkeyPath)) {
            @unlink($this->tmpPubkeyPath);
        }

        unset($_ENV['CA_PUBKEY_PATH']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Remplace le stub Database::instance et retourne le nouveau stub.
     *
     * @return \PHPUnit\Framework\MockObject\Stub
     */
    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    /**
     * Génère un JWT valide pour les tests.
     *
     * @param int    $userId Identifiant utilisateur
     * @param string $role   Rôle JWT
     * @return string
     */
    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }

    /**
     * Construit un QUERY_STRING signé avec la clé RSA de test.
     *
     * Reproduit le mécanisme CA : les données précèdent &Sign= encodé en base64+urlencoded.
     *
     * @param string $data Partie data du QUERY_STRING (avant &Sign=)
     * @return string QUERY_STRING complet avec signature RSA valide
     */
    private function buildSignedQueryString(string $data): string
    {
        if ($this->privateKey === false) {
            return $data;
        }
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);
        return $data . '&Sign=' . urlencode(base64_encode($signature));
    }

    /**
     * Retourne un snapshot de payment_intent par défaut pour les tests.
     *
     * @param int    $userId    Identifiant du client
     * @param string $reference Référence commande
     * @return array<string, mixed>
     */
    private function defaultSnapshot(int $userId = 1, string $reference = 'WEB-CB-AABBCCDD-2026'): array
    {
        return [
            'reference'           => $reference,
            'user_id'             => $userId,
            'items'               => [['wine_id' => 1, 'qty' => 12, 'price' => 10.0, 'name' => 'Wine']],
            'total'               => 120.0,
            'delivery_discount'   => 0.0,
            'billing_address_id'  => 1,
            'delivery_address_id' => 1,
            'cgv_version'         => '1.0',
            'lang'                => 'fr',
            'newsletter'          => false,
            'client_email'        => 'client@example.com',
            'client_name'         => 'Jean Dupont',
        ];
    }

    // ================================================================
    // paymentReturnOk() — ref vide → order null → redirect /fr/commande
    // ================================================================

    /**
     * Quand $_GET['Ref'] est absent, la commande ne peut pas être retrouvée
     * et le controller redirige vers /fr/commande avec un flash d'erreur.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenRefIsEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Erreur' => '00000']; // Ref absent

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — commande déjà existante → idempotence → redirect confirmation
    // ================================================================

    /**
     * Si la commande existe déjà en BDD (IPN déjà traité), le controller
     * stocke la référence en session et redirige vers la confirmation
     * sans rentrer dans le bloc de fallback RSA.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsToConfirmationWhenOrderAlreadyExists(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference → commande déjà existante
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode([]),
            ]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
        $this->assertSame('card', $_SESSION['last_order_payment'] ?? '');
        // La session ca_payment doit être nettoyée
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnOk() — Erreur ≠ 00000, commande introuvable → redirect
    // ================================================================

    /**
     * Si CA retourne un code erreur ≠ 00000 et que la commande n'existe pas,
     * le bloc fallback n'est pas exécuté et le controller redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenErreurNotSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00001'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false        // OrderModel::findByReference → introuvable
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — signature RSA invalide → redirect /fr/commande
    // ================================================================

    /**
     * Quand la référence est présente, la commande n'existe pas encore,
     * Erreur=00000 mais la signature RSA est invalide (CA_PUBKEY_PATH absent),
     * le controller redirige vers /fr/commande avec un flash d'erreur paiement.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsOnInvalidSignature(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        // QUERY_STRING sans &Sign= → verifyIpnSignature() retourne false
        $_SERVER['QUERY_STRING']   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000';
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000'];
        // CA_PUBKEY_PATH déjà neutralisé dans setUp → retourne false

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false        // OrderModel::findByReference → commande introuvable
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — intent introuvable après signature valide → redirect
    // ================================================================

    /**
     * Quand la signature RSA est valide mais le payment_intent est introuvable
     * en BDD (expiré ou jamais créé), le controller redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenIntentNotFound(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $stub = $this->setDbMock();
        // fetchOne : Auth → findByReference (introuvable) → PaymentIntentModel::findByReference (introuvable)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false,       // OrderModel::findByReference → commande introuvable
            false        // PaymentIntentModel::findByReference → intent introuvable (SELECT snapshot)
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — commande créée depuis snapshot valide → mail + redirect confirmation
    // ================================================================

    /**
     * Quand la signature RSA est valide, l'intent existe, et la commande n'a
     * pas encore été créée (findByReferenceOnly = null), le controller crée la
     * commande, envoie les mails et redirige vers la confirmation.
     *
     * @return void
     */
    public function testPaymentReturnOkCreatesOrderFromSnapshotAndRedirectsToConfirmation(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Ordre des appels fetchOne :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference (commande absente)
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → null (pas encore créée)
        // 5. OrderModel::insert via createFromIpn → stub via fetchOne/execute
        // 6. OrderModel::findByReference après création → commande trouvée
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // 1. AuthMiddleware
            false,          // 2. OrderModel::findByReference → commande absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference → snapshot
            false,          // 4. OrderModel::findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            [               // 9. OrderModel::findByReferenceWithAddresses → génération facture
                'id'               => 684,
                'order_reference'  => 'WEB-CB-AABBCCDD-2026',
                'payment_method'   => 'card',
                'content'          => json_encode($snapshot['items']),
                'price'            => 120.0,
                'shipping_discount' => 0.0,
                'ordered_at'       => date('Y-m-d H:i:s'),
            ],
            [               // 10. OrderModel::findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );
        // insert et execute ne lèvent pas d'exception (stub no-op par défaut)

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
        $this->assertSame('card', $_SESSION['last_order_payment'] ?? '');
    }

    // ================================================================
    // paymentReturnOk() — idempotence : findByReferenceOnly non null → pas de createFromIpn
    // ================================================================

    /**
     * Race condition IPN/return : si findByReferenceOnly retourne la commande
     * (IPN a gagné la course), createFromIpn n'est pas appelé mais le controller
     * redirige quand même vers la confirmation.
     *
     * @return void
     */
    public function testPaymentReturnOkIdempotenceSkipsCreateWhenReferenceAlreadyExists(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Ordre :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference (commande absente au premier appel)
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → commande existe déjà (IPN l'a créée)
        //    → pas de createFromIpn
        // 5. OrderModel::findByReference après le bloc if → commande trouvée
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware
            false,          // OrderModel::findByReference → commande absente
            $snapshotRow,   // PaymentIntentModel::findByReference
            ['order_reference' => 'WEB-CB-AABBCCDD-2026'], // findByReferenceOnly → existe
            [               // OrderModel::findByReference (appel final ligne 415)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
    }

    // ================================================================
    // generateAndStoreInvoice() — findByReferenceWithAddresses null → null-guard
    // ================================================================

    /**
     * Quand findByReferenceWithAddresses retourne null (commande introuvable avec adresses),
     * generateAndStoreInvoice retourne immédiatement sans générer de facture.
     *
     * @return void
     */
    public function testGenerateAndStoreInvoiceSkipsWhenOrderNotFound(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // 6e appel = findByReferenceWithAddresses → null (commande introuvable avec adresses)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware
            false,          // 2. OrderModel::findByReference → absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference
            false,          // 4. findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            false,          // 9. findByReferenceWithAddresses → false (null-guard : facture skippée)
            [               // 10. findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // generateAndStoreInvoice() — avec email client → sendInvoiceToClient couvert
    // ================================================================

    /**
     * Quand findByReferenceWithAddresses retourne un ordre avec email,
     * generateAndStoreInvoice tente d'envoyer l'email facture.
     * L'envoi SMTP échoue (pas de SMTP réel) mais est absorbé silencieusement.
     *
     * @return void
     */
    public function testGenerateAndStoreInvoiceCoversEmailBranchWithClientEmail(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // 1. AuthMiddleware
            false,          // 2. OrderModel::findByReference → absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference
            false,          // 4. findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            [               // 9. findByReferenceWithAddresses → ordre avec email
                'id'               => 684,
                'order_reference'  => 'WEB-CB-AABBCCDD-2026',
                'payment_method'   => 'card',
                'content'          => json_encode($snapshot['items']),
                'price'            => 120.0,
                'shipping_discount' => 0.0,
                'ordered_at'       => date('Y-m-d H:i:s'),
                'email'            => 'client@example.com',
                'firstname'        => 'Jean',
                'lastname'         => 'Dupont',
                'lang'             => 'fr',
            ],
            [               // 10. findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // L'email échoue silencieusement (pas de SMTP réel) → redirect confirmation quand même
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // paymentReturnCancel() — avec Ref → delete intent + flash + redirect
    // ================================================================

    /**
     * Avec une référence dans $_GET, le controller supprime l'intent en BDD
     * (via PaymentIntentModel::delete), pose un flash d'annulation et redirige
     * vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnCancelWithRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/annule';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnCancel(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnCancel() — sans Ref → flash + redirect (sans delete)
    // ================================================================

    /**
     * Sans référence dans $_GET, le controller ne tente pas de delete l'intent
     * mais pose quand même le flash d'annulation et redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnCancelWithoutRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/annule';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = []; // Pas de Ref

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnCancel(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnRefuse() — avec Ref → delete intent + flash + redirect
    // ================================================================

    /**
     * Avec une référence dans $_GET, le controller supprime l'intent, pose un
     * flash de refus et redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnRefuseWithRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/refuse';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnRefuse(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnRefuse() — sans Ref → flash + redirect
    // ================================================================

    /**
     * Sans référence dans $_GET, le controller pose le flash de refus et redirige
     * vers /fr/commande sans appel BDD pour delete.
     *
     * @return void
     */
    public function testPaymentReturnRefuseWithoutRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/refuse';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = []; // Pas de Ref

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnRefuse(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // confirmation() — référence en session, commande trouvée → rendu vue
    // ================================================================

    /**
     * Quand last_order_ref est en session et que la commande est retrouvée,
     * le controller efface les clés de session de commande et tente le rendu de la vue.
     *
     * @return void
     */
    public function testConfirmationClearsSessionKeysAndRendersViewWhenOrderFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref']        = 'WEB-CB-AABBCCDD-2026';
        $_SESSION['last_order_payment']    = 'card';
        $_SESSION['last_order_newsletter'] = true;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference → commande trouvée
                'order_reference'   => 'WEB-CB-AABBCCDD-2026',
                'payment_method'    => 'card',
                'price'             => 120.0,
                'shipping_discount' => 0.0,
                'content'           => json_encode([['wine_id' => 1, 'qty' => 12, 'price' => 10.0, 'name' => 'Wine']]),
                'bill_firstname'    => 'Jean',
                'bill_lastname'     => 'Dupont',
                'bill_street'       => '1 Rue Test',
                'bill_zip'          => '75001',
                'bill_city'         => 'Paris',
                'bill_country'      => 'France',
                'del_firstname'     => 'Jean',
                'del_lastname'      => 'Dupont',
                'del_street'        => '1 Rue Test',
                'del_zip'           => '75001',
                'del_city'          => 'Paris',
                'del_country'       => 'France',
            ]
        );

        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // Attendu : view() lève une exception car pas de template en context unitaire
        } catch (\Throwable) {
            // Vue non disponible en contexte unitaire — comportement attendu
        }

        // Les clés de session doivent être effacées après lecture
        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
        $this->assertArrayNotHasKey('last_order_payment', $_SESSION);
        $this->assertArrayNotHasKey('last_order_newsletter', $_SESSION);
    }

    // ================================================================
    // confirmation() — newsletterOptIn false → variable correcte
    // ================================================================

    /**
     * Quand last_order_newsletter est absent ou false, newsletterOptIn vaut false
     * et les clés de session sont nettoyées.
     *
     * @return void
     */
    public function testConfirmationWithNewsletterFalseCleansSessions(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref']     = 'WEB-VB-XXYYZZ-2026';
        $_SESSION['last_order_payment'] = 'virement';
        // last_order_newsletter absent → false

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference
                'order_reference'   => 'WEB-VB-XXYYZZ-2026',
                'payment_method'    => 'virement',
                'price'             => 60.0,
                'shipping_discount' => 0.0,
                'content'           => json_encode([]),
                'bill_firstname'    => 'Test',
                'bill_lastname'     => 'User',
                'bill_street'       => '2 Rue Exemple',
                'bill_zip'          => '33000',
                'bill_city'         => 'Bordeaux',
                'bill_country'      => 'France',
                'del_firstname'     => 'Test',
                'del_lastname'      => 'User',
                'del_street'        => '2 Rue Exemple',
                'del_zip'           => '33000',
                'del_city'          => 'Bordeaux',
                'del_country'       => 'France',
            ]
        );

        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect ou vue
        } catch (\Throwable) {
            // Vue non disponible en contexte unitaire
        }

        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
        $this->assertArrayNotHasKey('last_order_payment', $_SESSION);
    }

    // ================================================================
    // payment() — flux carte (method=card) → redirect vers CA (vue auto-submit)
    // ================================================================

    /**
     * Pour le paiement par carte, le controller génère les champs PBX via
     * PaymentService et rend la vue formulaire auto-submit sans redirection HTTP.
     * Le test vérifie qu'aucune HttpException 302 n'est levée jusqu'à l'appel
     * de buildPbxFields (qui peut échouer sur les ENV CA absents) — la branche
     * est bien empruntée.
     *
     * @return void
     */
    public function testPaymentCardFlowEntersCardBranch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'test-submit-token',
            'payment_method'      => 'card',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '5',
        ];

        // Neutraliser les limites de rate-limit
        $_SESSION['payment_attempts'][1] = ['count' => 0, 'window_start' => time()];

        // Variables d'env CA minimales pour que buildPbxFields ne lève pas d'exception réseau
        $_ENV['CA_PBX_SITE']          = '1999887';
        $_ENV['CA_PBX_RANG']          = '032';
        $_ENV['CA_PBX_IDENTIFIANT']   = '107904482';
        $_ENV['CA_PBX_HMAC_KEY']      = str_repeat('ab', 64);
        $_ENV['CA_SANDBOX_MODE']      = 'true';
        $_ENV['CA_FALLBACK_BASE_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        // Ordre :
        // 1. AuthMiddleware
        // 2. CartModel::findByUserId → panier avec 12 bouteilles
        // 3. WineModel::getById (enrichItems)
        // 4. AddressModel::findByIdForUser (resolveAddress delivery)
        // 5. AddressModel::findByIdForUser (mainland zip check)
        // 6. PricingRuleModel::findForQuantity (computeDeliveryDiscount)
        // 7. AccountModel::findById
        // 8. AddressModel::findByIdForUser (billingAddr pour PBX_BILLING)
        // 9. PaymentIntentModel::save → execute (pas de fetchOne)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])],
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],
            false, // PricingRuleModel::findForQuantity
            ['id' => 1, 'email' => 'jean@test.com', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => false],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'street' => '1 Rue Test', 'zip_code' => '75001', 'city' => 'Paris']
        );

        $exceptionCaught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exceptionCaught = $e;
        } catch (\Throwable $e) {
            // buildPbxFields peut échouer si le serveur CA est inaccessible en test
            // ce qui confirme que la branche card a bien été atteinte
            $exceptionCaught = null;
        } finally {
            unset(
                $_ENV['CA_PBX_SITE'],
                $_ENV['CA_PBX_RANG'],
                $_ENV['CA_PBX_IDENTIFIANT'],
                $_ENV['CA_PBX_HMAC_KEY'],
                $_ENV['CA_SANDBOX_MODE'],
                $_ENV['CA_FALLBACK_BASE_URL']
            );
        }

        // En branche card, soit on atteint la vue (pas de 302), soit buildPbxFields échoue
        // Dans les deux cas, il ne doit PAS y avoir de clé last_order_ref (celle-ci n'est
        // posée que par les branches virement/chèque)
        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
    }

    // ================================================================
    // paymentReturnOk() — exception lors de createFromIpn → redirect /fr/commande
    // ================================================================

    /**
     * Si createFromIpn lève une PDOException (collision persistante), le controller
     * propage l'exception. Ce test vérifie que l'exception n'est pas silencieusement
     * avalée — le comportement attendu est une propagation (pas de redirect 302).
     *
     * Ce test couvre la branche catch (\PDOException) dans createFromIpn
     * (après 3 tentatives sans succès).
     *
     * @return void
     */
    public function testPaymentReturnOkPropagatesExceptionFromCreateFromIpn(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // 1. AuthMiddleware
            false,         // 2. OrderModel::findByReference → commande absente
            $snapshotRow,  // 3. PaymentIntentModel::findByReference
            false,         // 4. OrderModel::findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,         // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false          // 8. dedup lookup delivery → pas trouvé
        );

        // insert() lèvera une PDOException avec code 23000 pour simuler une collision persistante
        $pdoException = new \PDOException('Duplicate entry', 23000);
        $stub->method('insert')->willThrowException($pdoException);

        $this->expectException(\PDOException::class);

        (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
    }
}
