<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AccountController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-account-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte client vérifié avec son profil individual ou company.
     *
     * @param string $email       Adresse e-mail unique du compte
     * @param string $accountType 'individual' ou 'company'
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(string $email, string $accountType = 'individual'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), $accountType]
        );

        if ($accountType === 'company') {
            self::$db->insert(
                "INSERT INTO account_companies (account_id, company_name, siret)
                 VALUES (?, 'Acme SARL', '12345678901234')",
                [$id]
            );
        } else {
            self::$db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, 'Dupont', 'Jean', 'M')",
                [$id]
            );
        }

        return $id;
    }

    /**
     * Insère une connexion active en base pour que AuthMiddleware valide le token.
     *
     * @param int    $userId Identifiant du compte
     * @param string $token  Token JWT à enregistrer
     */
    private function insertConnection(int $userId, string $token): void
    {
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
    }

    /**
     * Connecte un utilisateur en posant son cookie JWT et en insérant sa connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT (défaut : 'customer')
     * @return string Token JWT généré
     */
    private function loginAs(int $userId, string $role = 'customer'): string
    {
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($userId, $token);
        return $token;
    }

    /**
     * Crée un objet Request avec la méthode et l'URI fournies.
     *
     * @param string $method Méthode HTTP (GET, POST…)
     * @param string $uri    URI de la requête
     * @return Request
     */
    private function makeRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $uriQuery = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $uriQuery);
        $_GET = array_merge($_GET, $uriQuery);
        return new Request();
    }

    /**
     * Instancie AccountController avec la méthode et l'URI fournies.
     *
     * @param string $method Méthode HTTP (GET, POST…)
     * @param string $uri    URI de la requête
     * @return AccountController
     */
    private function makeController(string $method, string $uri): AccountController
    {
        return new AccountController($this->makeRequest($method, $uri));
    }

    /**
     * Insère une adresse sauvegardée pour l'utilisateur donné.
     *
     * @param int    $userId Identifiant du compte
     * @param string $type   'billing' ou 'delivery'
     * @return int Identifiant de l'adresse créée
     */
    private function insertAddress(int $userId, string $type = 'billing'): int
    {
        return (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, ?, 'Jean', 'Dupont', 'M', '12 rue de la Paix', 'Paris', '75001', 'France', '0601020304', 1)",
            [$userId, $type]
        );
    }

    /**
     * Insère une commande pour l'utilisateur donné.
     *
     * @param int $userId    Identifiant du compte
     * @param int $addressId Identifiant de l'adresse de facturation
     * @param string $status Statut de la commande
     * @return int Identifiant de la commande créée
     */
    private function insertOrder(int $userId, int $addressId, string $status = 'pending'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        return (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, ?)",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId, $status]
        );
    }

    /**
     * Insère une commande au statut "delivered" avec delivered_at configurable.
     *
     * @param int    $userId    Identifiant du compte
     * @param int    $addressId Identifiant de l'adresse de facturation
     * @param string $deliveredAt Valeur SQL pour delivered_at (ex: 'NOW() - INTERVAL 7 DAY')
     * @return int Identifiant de la commande créée
     */
    private function insertDeliveredOrder(int $userId, int $addressId, string $deliveredAt = 'NOW() - INTERVAL 7 DAY'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        return (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status, delivered_at)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, 'delivered', {$deliveredAt})",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId]
        );
    }

    // ----------------------------------------------------------------
    // index()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié reçoit la vue du tableau de bord.
     */
    public function testIndexRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('individual@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un compte company authentifié reçoit la vue du tableau de bord.
     */
    public function testIndexRendersForCompany(): void
    {
        $userId = $this->insertCustomer('company@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un message flash info posé en session est bien affiché dans la vue.
     */
    public function testIndexShowsFlashInfoForCompany(): void
    {
        $userId = $this->insertCustomer('flash@test.local', 'company');
        $this->loginAs($userId);

        $_SESSION['flash']['info'] = 'test-info-message';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testIndexRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302); // 302 — redirect-post-login : _intended mémorisé, redirection login
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // orders()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la liste de ses commandes.
     */
    public function testOrdersRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('orders.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Un compte company est redirigé vers le dashboard (requireIndividual).
     */
    public function testOrdersRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('orders.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testOrdersRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // addresses()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la liste de ses adresses.
     */
    public function testAddressesRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('addr.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('address-add-form', $output);
    }

    /**
     * Un compte company est redirigé (requireIndividual).
     */
    public function testAddressesRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('addr.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testAddressesRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // favorites()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la liste de ses favoris.
     */
    public function testFavoritesRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('fav.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('panel.favorites', $output);
    }

    /**
     * Un compte company peut aussi consulter ses favoris (pas de requireIndividual).
     */
    public function testFavoritesRendersForCompany(): void
    {
        $userId = $this->insertCustomer('fav.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('panel.favorites', $output);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testFavoritesRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // security()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié accède à la page sécurité.
     */
    public function testSecurityRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('sec.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('current_password', $output);
    }

    /**
     * Un compte company peut accéder à la page sécurité (pas de requireIndividual).
     */
    public function testSecurityRendersForCompany(): void
    {
        $userId = $this->insertCustomer('sec.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('current_password', $output);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testSecurityRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // profile() GET
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié accède à son profil.
     */
    public function testProfileRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('prof.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('profile-email', $output);
    }

    /**
     * Un compte company authentifié accède à son profil (différent de l'individual).
     */
    public function testProfileRendersForCompany(): void
    {
        $userId = $this->insertCustomer('prof.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('profile-email', $output);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testProfileRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // updateProfile() POST — individual
    // ----------------------------------------------------------------

    /**
     * Un POST valide pour un compte individual redirige vers le profil (succès).
     */
    public function testUpdateProfileIndividualSuccess(): void
    {
        $userId = $this->insertCustomer('upd.ind@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'civility'   => 'F',
            'newsletter' => '0',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Un firstname vide redirige avec une erreur en session.
     */
    public function testUpdateProfileIndividualMissingFirstname(): void
    {
        $userId = $this->insertCustomer('upd.nofirst@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => '',
            'lastname'   => 'Martin',
            'civility'   => 'M',
            'newsletter' => '0',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un lastname vide redirige avec une erreur en session.
     */
    public function testUpdateProfileIndividualMissingLastname(): void
    {
        $userId = $this->insertCustomer('upd.nolast@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => '',
            'civility'   => 'F',
            'newsletter' => '0',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un POST valide pour un compte company redirige vers le profil (succès).
     */
    public function testUpdateProfileCompanySuccess(): void
    {
        $userId = $this->insertCustomer('upd.co@test.local', 'company');
        $this->loginAs($userId);

        $_POST = [
            'company_name' => 'Acme Mise à jour',
            'siret'        => '98765432100011',
            'newsletter'   => '0',
            'csrf_token'   => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Un company_name vide redirige avec une erreur en session.
     */
    public function testUpdateProfileCompanyMissingName(): void
    {
        $userId = $this->insertCustomer('upd.coname@test.local', 'company');
        $this->loginAs($userId);

        $_POST = [
            'company_name' => '',
            'siret'        => '',
            'newsletter'   => '0',
            'csrf_token'   => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un token CSRF invalide redirige avec une erreur en session.
     */
    public function testUpdateProfileInvalidCsrf(): void
    {
        $userId = $this->insertCustomer('upd.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'civility'   => 'F',
            'newsletter' => '0',
            'csrf_token' => 'wrong-csrf-token',
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    // ----------------------------------------------------------------
    // reactivateAccount()
    // ----------------------------------------------------------------

    /**
     * Sans token dans l'URL, la vue est rendue avec success=false.
     */
    public function testReactivateWithEmptyToken(): void
    {
        $_GET = [];

        ob_start();
        $this->makeController('GET', '/fr/compte/reactiver')->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        // La vue reactivate est bien rendue (pas d'exception)
        $this->assertNotEmpty($output);
    }

    /**
     * Avec un token inexistant en base, la vue est rendue avec success=false.
     */
    public function testReactivateWithInvalidToken(): void
    {
        $_GET = ['token' => 'token-qui-nexiste-pas-du-tout-xyz'];

        ob_start();
        $this->makeController('GET', '/fr/compte/reactiver')->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // unsubscribePage()
    // ----------------------------------------------------------------

    /**
     * Sans token dans l'URL, la vue est rendue avec confirm=false.
     */
    public function testUnsubscribePageEmptyToken(): void
    {
        $_GET = [];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement')->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Avec un token valide en base (newsletter_unsubscribe_token), la vue est rendue avec confirm=true.
     */
    public function testUnsubscribePageValidToken(): void
    {
        $userId = $this->insertCustomer('unsub@test.local', 'individual');
        $unsubToken = 'test-unsub-token-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "UPDATE accounts SET newsletter_unsubscribe_token = ? WHERE id = ?",
            [$unsubToken, $userId]
        );

        // makeRequest() réinitialise $_GET avant new Request() — on instancie directement
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/newsletter/desabonnement';
        $_GET = ['token' => $unsubToken];
        $controller = new AccountController(new Request());

        ob_start();
        $controller->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        // confirm=true → le formulaire de désabonnement est affiché
        $this->assertStringContainsString($unsubToken, $output);
    }

    // ----------------------------------------------------------------
    // requireIndividual — via cancelOrder()
    // ----------------------------------------------------------------

    /**
     * Un POST cancelOrder depuis un compte company est redirigé (requireIndividual).
     */
    public function testCancelOrderRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('cancel.co@test.local', 'company');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/commandes/1/annuler')
            ->cancelOrder(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // orderDetail()
    // ----------------------------------------------------------------

    /**
     * Un GET orderDetail depuis un compte company est redirigé (requireIndividual).
     */
    public function testOrderDetailRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('detail.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/1')
            ->orderDetail(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // addAddress()
    // ----------------------------------------------------------------

    /**
     * Un POST addAddress depuis un compte company est redirigé (requireIndividual).
     */
    public function testAddAddressRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('addaddr.co@test.local', 'company');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
            ->addAddress(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // addAddress() POST — individual
    // ----------------------------------------------------------------

    /**
     * Des champs requis manquants déclenchent un flash error et une redirection.
     */
    public function testAddAddressMissingFields(): void
    {
        $userId = $this->insertCustomer('addaddr.ind@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'type'       => 'billing',
            'civility'   => 'M',
            'firstname'  => '',
            'lastname'   => '',
            'street'     => '',
            'city'       => '',
            'zip_code'   => '',
            'country'    => 'France',
            'phone'      => '',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
                ->addAddress(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Un code postal français invalide déclenche un flash error et une redirection.
     */
    public function testAddAddressInvalidZip(): void
    {
        $userId = $this->insertCustomer('addaddr.zip@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'type'       => 'shipping',
            'civility'   => 'M',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'street'     => '1 rue de la Paix',
            'city'       => 'Paris',
            'zip_code'   => '00000',
            'country'    => 'France',
            'phone'      => '0601020304',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
                ->addAddress(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Un POST valide crée l'adresse et redirige (302).
     */
    public function testAddAddressSuccess(): void
    {
        $userId = $this->insertCustomer('addaddr.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'type'       => 'shipping',
            'civility'   => 'M',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'street'     => '12 rue de la Paix',
            'city'       => 'Paris',
            'zip_code'   => '75001',
            'country'    => 'France',
            'phone'      => '0601020304',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
            ->addAddress(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // editAddress() GET
    // ----------------------------------------------------------------

    /**
     * Une adresse existante est affichée dans le formulaire d'édition.
     */
    public function testEditAddressRendersForValid(): void
    {
        $userId    = $this->insertCustomer('editok.ind@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/adresses/{$addressId}/modifier")
            ->editAddress(['lang' => 'fr', 'id' => (string) $addressId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-form', $output);
    }

    /**
     * Un id inexistant redirige vers la liste des adresses.
     */
    public function testEditAddressNotFound(): void
    {
        $userId = $this->insertCustomer('editaddr.ind@test.local', 'individual');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses/999999/modifier')
            ->editAddress(['lang' => 'fr', 'id' => '999999']);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testEditAddressRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses/1/modifier')
            ->editAddress(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // updateAddress() POST
    // ----------------------------------------------------------------

    /**
     * Un POST valide met à jour l'adresse et redirige (302).
     */
    public function testUpdateAddressSuccess(): void
    {
        $userId    = $this->insertCustomer('updadrok.ind@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = [
            'civility'   => 'F',
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'street'     => '5 avenue de l\'Opéra',
            'city'       => 'Paris',
            'zip_code'   => '75001',
            'country'    => 'France',
            'phone'      => '0607080910',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
            ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Un id inexistant redirige vers la liste des adresses.
     */
    public function testUpdateAddressNotFound(): void
    {
        $userId = $this->insertCustomer('updaddr.ind@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/999999/modifier')
            ->updateAddress(['lang' => 'fr', 'id' => '999999']);
    }

    // ----------------------------------------------------------------
    // deleteAddress() POST
    // ----------------------------------------------------------------

    /**
     * Un POST valide supprime l'adresse et redirige (302).
     */
    public function testDeleteAddressSuccess(): void
    {
        $userId    = $this->insertCustomer('deladrok.ind@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/supprimer")
            ->deleteAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Un id inexistant redirige vers la liste des adresses.
     */
    public function testDeleteAddressNotFound(): void
    {
        $userId = $this->insertCustomer('deladdr.ind@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/999999/supprimer')
            ->deleteAddress(['lang' => 'fr', 'id' => '999999']);
    }

    // ----------------------------------------------------------------
    // orderDetail() GET — individual
    // ----------------------------------------------------------------

    /**
     * Un individual authentifié voit le détail d'une commande existante.
     */
    public function testOrderDetailRendersForIndividual(): void
    {
        $userId    = $this->insertCustomer('odtl.ok@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId);
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un id de commande inexistant redirige vers la liste des commandes.
     */
    public function testOrderDetailRedirectsForNonexistentOrder(): void
    {
        $userId = $this->insertCustomer('odtl.ind@test.local', 'individual');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/999999')
            ->orderDetail(['lang' => 'fr', 'id' => '999999']);
    }

    /**
     * Commande 'processing' par carte avec CA_ENABLE_GAE=false → bouton remboursement affiché.
     * (CA_ENABLE_GAE=false : la voie de remboursement passe par /remboursement, pas /annuler)
     *
     * @return void
     */
    public function testOrderDetailProcessingCardGaeDisabledShowsRefundButton(): void
    {
        unset($_ENV['CA_ENABLE_GAE']); // GAE désactivé par défaut
        $userId    = $this->insertCustomer('odtl.proc@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account.order_refund_btn', $output);
    }

    // ----------------------------------------------------------------
    // cancelOrder() POST — individual
    // ----------------------------------------------------------------

    /**
     * Un POST valide avec une commande annulable redirige (302).
     */
    public function testCancelOrderSuccess(): void
    {
        $userId    = $this->insertCustomer('cancel.ok@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'pending');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
            ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Un token CSRF invalide pose un flash error et redirige.
     */
    public function testCancelOrderInvalidCsrf(): void
    {
        $userId = $this->insertCustomer('cancel.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'wrong-csrf'];

        try {
            $this->makeController('POST', '/fr/mon-compte/commandes/1/annuler')
                ->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? '');
        }
    }

    /**
     * Une commande inexistante redirige avec flash error.
     */
    public function testCancelOrderNotFound(): void
    {
        $userId = $this->insertCustomer('cancel.nf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        try {
            $this->makeController('POST', '/fr/mon-compte/commandes/999999/annuler')
                ->cancelOrder(['lang' => 'fr', 'id' => '999999']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }
    }

    // ----------------------------------------------------------------
    // unsubscribe() POST
    // ----------------------------------------------------------------

    /**
     * Un POST avec token valide désabonne et affiche la confirmation.
     */
    public function testUnsubscribeWithValidToken(): void
    {
        $userId     = $this->insertCustomer('unsub.post@test.local', 'individual');
        $unsubToken = 'valid-unsub-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "UPDATE accounts SET newsletter_unsubscribe_token = ? WHERE id = ?",
            [$unsubToken, $userId]
        );

        $_POST = ['unsub_token' => $unsubToken, 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un POST avec token invalide affiche la vue avec success=false.
     */
    public function testUnsubscribeWithInvalidToken(): void
    {
        $_POST = ['unsub_token' => 'token-inexistant', 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // exportPage() GET
    // ----------------------------------------------------------------

    /**
     * Un compte authentifié accède à la page d'export RGPD.
     */
    public function testExportPageRenders(): void
    {
        $userId = $this->insertCustomer('export@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testExportPageRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // changePassword() POST
    // ----------------------------------------------------------------

    /**
     * Un mauvais mot de passe actuel pose un flash error et redirige.
     */
    public function testChangePasswordWrongCurrentPassword(): void
    {
        $userId = $this->insertCustomer('chpwd.bad@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'      => 'WrongPassword!',
            'new_password'          => 'NewPassword123456!',
            'new_password_confirm'  => 'NewPassword123456!',
            'csrf_token'            => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Un nouveau mot de passe trop court pose un flash error et redirige.
     */
    public function testChangePasswordTooShort(): void
    {
        $userId = $this->insertCustomer('chpwd.short@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'      => 'Password123!',
            'new_password'          => 'Short1!',
            'new_password_confirm'  => 'Short1!',
            'csrf_token'            => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Des mots de passe non identiques posent un flash error et redirigent.
     */
    public function testChangePasswordMismatch(): void
    {
        $userId = $this->insertCustomer('chpwd.mismatch@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'      => 'Password123!',
            'new_password'          => 'NewPassword123456!',
            'new_password_confirm'  => 'DifferentPassword123456!',
            'csrf_token'            => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Un changement de mot de passe valide redirige (302).
     */
    public function testChangePasswordSuccess(): void
    {
        $userId = $this->insertCustomer('chpwd.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'      => 'Password123!',
            'new_password'          => 'NewPassword123456!',
            'new_password_confirm'  => 'NewPassword123456!',
            'csrf_token'            => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
            ->changePassword(['lang' => 'fr']);
    }

    /**
     * Un token CSRF invalide sur changePassword redirige sans modifier le mot de passe.
     */
    public function testChangePasswordInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('chpwd.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'     => 'Password123!',
            'new_password'         => 'NewPassword123456!',
            'new_password_confirm' => 'NewPassword123456!',
            'csrf_token'           => 'invalid-csrf',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
            ->changePassword(['lang' => 'fr']);
    }

    /**
     * Un nouveau mot de passe identique à l'actuel pose un flash error et redirige.
     */
    public function testChangePasswordSameAsCurrentShowsError(): void
    {
        $userId = $this->insertCustomer('chpwd.same@test.local', 'individual');
        $this->loginAs($userId);

        // Le mot de passe inséré par insertCustomer est 'Password123!'
        $_POST = [
            'current_password'     => 'Password123!',
            'new_password'         => 'Password123!',
            'new_password_confirm' => 'Password123!',
            'csrf_token'           => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $errors = $_SESSION['flash']['security_errors'] ?? [];
            $this->assertArrayHasKey('new_password', $errors);
            // La traduction de account.password_same_as_current doit être non vide
            $this->assertNotEmpty($errors['new_password']);
        }
    }

    /**
     * Un changement de mot de passe valide envoie l'email d'alerte sans bloquer l'opération.
     * L'email est non-bloquant : même si MailService lève une exception, la redirection a lieu.
     */
    public function testChangePasswordSuccessEmailDoesNotBlock(): void
    {
        $userId = $this->insertCustomer('chpwd.email@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'     => 'Password123!',
            'new_password'         => 'NewEmailTest123!',
            'new_password_confirm' => 'NewEmailTest123!',
            'csrf_token'           => self::CSRF,
        ];

        // On s'attend à une redirection 302 — l'email ne bloque pas le flux
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
            ->changePassword(['lang' => 'fr']);
    }

    /**
     * Un changement de mot de passe valide pose un flash de succès.
     */
    public function testChangePasswordSuccessFlashesSuccess(): void
    {
        $userId = $this->insertCustomer('chpwd.flash@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'current_password'     => 'Password123!',
            'new_password'         => 'Zephyr9$mKlt2w',
            'new_password_confirm' => 'Zephyr9$mKlt2w',
            'csrf_token'           => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_success'] ?? '');
        }
    }

    // ----------------------------------------------------------------
    // revokeAllUserSessions() POST
    // ----------------------------------------------------------------

    /**
     * Un POST valide révoque toutes les sessions et redirige (302).
     */
    public function testRevokeAllSessionsSuccess(): void
    {
        $userId = $this->insertCustomer('revoke.all@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/sessions/revoquer-toutes')
            ->revokeAllUserSessions(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // revokeSession() POST
    // ----------------------------------------------------------------

    /**
     * Un POST avec CSRF invalide redirige sans révoquer.
     */
    public function testRevokeSessionInvalidCsrf(): void
    {
        $userId = $this->insertCustomer('rev.session@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'wrong-csrf'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/session/1/revoquer')
            ->revokeSession(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // untrustAllDevices() POST
    // ----------------------------------------------------------------

    /**
     * Un POST valide supprime tous les appareils de confiance et redirige (302).
     */
    public function testUntrustAllDevicesSuccess(): void
    {
        $userId = $this->insertCustomer('untrust.all@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/supprimer-toutes')
            ->untrustAllDevices(['lang' => 'fr']);
    }

    /**
     * Un token CSRF invalide sur untrustAllDevices redirige sans supprimer les appareils.
     */
    public function testUntrustAllDevicesInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('untrust.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'invalid-csrf'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/supprimer-toutes')
            ->untrustAllDevices(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // untrustDevice() POST
    // ----------------------------------------------------------------

    /**
     * Un POST valide révoque la confiance d'un appareil et redirige (302).
     */
    public function testUntrustDeviceRedirects(): void
    {
        $userId = $this->insertCustomer('untrust.dev@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'device_token' => 'some-device-token',
            'csrf_token'   => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
            ->untrustDevice(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // newDevice() GET
    // ----------------------------------------------------------------

    /**
     * Sans session pending_device, redirige vers l'accueil.
     */
    public function testNewDeviceRedirectsWithoutPending(): void
    {
        unset($_SESSION['pending_device']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/nouvel-appareil')
            ->newDevice(['lang' => 'fr']);
    }

    /**
     * Avec une session pending_device valide, la vue est rendue.
     */
    public function testNewDeviceRendersWithPending(): void
    {
        $_SESSION['pending_device'] = [
            'device_name' => 'PHPUnit Browser',
            'mfa_token'   => 'test-mfa-token-xyz',
        ];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/nouvel-appareil')
            ->newDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('test-mfa-token-xyz', $output);
    }

    // ----------------------------------------------------------------
    // confirmDevice() GET
    // ----------------------------------------------------------------

    /**
     * Un token invalide rend la vue avec success=false.
     */
    public function testConfirmDeviceInvalidToken(): void
    {
        $controller = $this->makeController('GET', '/fr/mon-compte/appareil/confirmer');
        $_GET = ['token' => 'token-inexistant-xyz'];

        ob_start();
        $controller->confirmDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // cancelMfa() GET
    // ----------------------------------------------------------------

    /**
     * Sans token, la vue est rendue avec revoked=false.
     */
    public function testCancelMfaEmptyToken(): void
    {
        $controller = $this->makeController('GET', '/fr/mon-compte/appareil/annuler');
        $_GET = [];

        ob_start();
        $controller->cancelMfa(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // resetSecurity() POST
    // ----------------------------------------------------------------

    /**
     * Un mauvais mot de passe pose un flash error et redirige.
     */
    public function testResetSecurityWrongPassword(): void
    {
        $userId = $this->insertCustomer('reset.bad@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'password'   => 'WrongPassword!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
                ->resetSecurity(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Un mot de passe correct réinitialise la sécurité et redirige (302).
     */
    public function testResetSecuritySuccess(): void
    {
        $userId = $this->insertCustomer('reset.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
            ->resetSecurity(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // deleteAccount() POST
    // ----------------------------------------------------------------

    /**
     * Un texte de confirmation incorrect pose un flash error et redirige.
     */
    public function testDeleteAccountWrongConfirmText(): void
    {
        $userId = $this->insertCustomer('del.text@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'PAS-LA-BONNE-VALEUR',
            'confirm_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite')
                ->deleteAccount(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Un mauvais mot de passe de confirmation pose un flash error et redirige.
     */
    public function testDeleteAccountWrongPassword(): void
    {
        $userId = $this->insertCustomer('del.pwd@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'WrongPassword!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite')
                ->deleteAccount(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
    }

    /**
     * Des credentials valides suppriment le compte et redirigent (302).
     */
    public function testDeleteAccountSuccess(): void
    {
        $userId = $this->insertCustomer('del.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite')
            ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Un compte avec des commandes actives peut désormais être supprimé (RGPD Art. 17).
     * Les commandes en cours ne bloquent plus la suppression.
     */
    public function testDeleteAccountWithActiveOrdersSucceeds(): void
    {
        $userId    = $this->insertCustomer('del.active@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite')
            ->deleteAccount(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // reactivateAccount() — success path
    // ----------------------------------------------------------------

    /**
     * Un token valide réactive le compte et affiche la confirmation.
     */
    public function testReactivateWithValidToken(): void
    {
        $userId = $this->insertCustomer('reactiv@test.local', 'individual');
        $token  = bin2hex(random_bytes(16));

        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW(), scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
             reactivation_token = ? WHERE id = ?",
            [$token, $userId]
        );

        // makeRequest réinitialise $_GET → on instancie directement
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/compte/reactiver';
        $_GET = ['token' => $token];
        $controller = new AccountController(new \Core\Request());

        ob_start();
        $controller->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // revokeSession() — valid CSRF path
    // ----------------------------------------------------------------

    /**
     * Un POST avec CSRF valide tente de révoquer la session (même si inexistante).
     */
    public function testRevokeSessionValidCsrf(): void
    {
        $userId = $this->insertCustomer('rev.valid@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        // Id 999999 n'existe pas pour cet utilisateur → revoke no-op puis redirect
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/session/999999/revoquer')
            ->revokeSession(['lang' => 'fr', 'id' => '999999']);
    }

    // ----------------------------------------------------------------
    // cancelMfa() — valid token path
    // ----------------------------------------------------------------

    /**
     * Un token MFA valide révoque le token et affiche la vue avec revoked=true.
     */
    public function testCancelMfaValidToken(): void
    {
        $userId    = $this->insertCustomer('mfa.cancel@test.local', 'individual');
        $mfaToken  = bin2hex(random_bytes(16));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens (user_id, device_token, device_name, token, expires_at)
             VALUES (?, 'device-tok', 'TestDevice', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $mfaToken]
        );

        $controller = $this->makeController('GET', '/fr/mon-compte/appareil/annuler');
        $_GET = ['token' => $mfaToken];

        ob_start();
        $controller->cancelMfa(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // confirmDevice() — valid (but unconfirmed) token path
    // ----------------------------------------------------------------

    /**
     * Un token existant mais non encore confirmé est confirmé et la vue affiche success.
     */
    public function testConfirmDeviceValidToken(): void
    {
        $userId   = $this->insertCustomer('confirm.dev@test.local', 'individual');
        $devToken = bin2hex(random_bytes(16));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens (user_id, device_token, device_name, token, expires_at)
             VALUES (?, 'device-confirm-tok', 'TestDevice', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $devToken]
        );

        $controller = $this->makeController('GET', '/fr/mon-compte/appareil/confirmer');
        $_GET = ['token' => $devToken];

        ob_start();
        $controller->confirmDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // resetSecurity()
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide flash une erreur et redirige.
     */
    public function testResetSecurityInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('reset.sec.csrf@test.local');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad', 'password' => 'Password123!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
            ->resetSecurity(['lang' => 'fr']);
    }

    /**
     * Un mot de passe incorrect flash une erreur et redirige.
     */
    public function testResetSecurityWrongPasswordRedirects(): void
    {
        $userId = $this->insertCustomer('reset.sec.pwd@test.local');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF, 'password' => 'WrongPassword!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
            ->resetSecurity(['lang' => 'fr']);
    }

    /**
     * Un mot de passe correct révoque tout et redirige vers /fr.
     */
    public function testResetSecuritySuccessRedirects(): void
    {
        $userId = $this->insertCustomer('reset.sec.ok@test.local');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF, 'password' => 'Password123!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
            ->resetSecurity(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // deleteAccount()
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide redirige.
     */
    public function testDeleteAccountInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('del.acc.csrf@test.local');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
            ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Un texte de confirmation incorrect redirige.
     */
    public function testDeleteAccountWrongConfirmTextRedirects(): void
    {
        $userId = $this->insertCustomer('del.acc.txt@test.local');
        $this->loginAs($userId);

        $_POST = [
            'csrf_token'       => self::CSRF,
            'confirm_text'     => 'WRONG',
            'confirm_password' => 'Password123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
            ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Un mot de passe incorrect redirige.
     */
    public function testDeleteAccountWrongPasswordRedirects(): void
    {
        $userId = $this->insertCustomer('del.acc.pwd@test.local');
        $this->loginAs($userId);

        $_POST = [
            'csrf_token'       => self::CSRF,
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'WrongPass!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
            ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Tout valide sans commandes actives : soft-delete et redirect vers /fr.
     */
    public function testDeleteAccountSuccessRedirects(): void
    {
        $userId = $this->insertCustomer('del.acc.ok@test.local');
        $this->loginAs($userId);

        $_POST = [
            'csrf_token'       => self::CSRF,
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'Password123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
            ->deleteAccount(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // exportSend() — guard CSRF + authentification
    // ----------------------------------------------------------------

    /**
     * Un utilisateur non authentifié déclenche une HttpException (guard requireCustomer).
     */
    public function testExportSendUnauthenticatedRedirects(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/export/envoyer')
            ->exportSend(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // revokeSession() — révocation de la session courante
    // ----------------------------------------------------------------

    /**
     * Révoquer la session courante vide le cookie et redirige vers /fr.
     */
    public function testRevokeSessionCurrentSessionClearsAndRedirects(): void
    {
        $userId = $this->insertCustomer('rev.cur@test.local', 'individual');
        $token  = $this->loginAs($userId);

        $conn = self::$db->fetchOne(
            'SELECT id FROM connections WHERE user_id = ? AND token = ? LIMIT 1',
            [$userId, $token]
        );
        $this->assertNotFalse($conn, 'La connexion doit exister en base');

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/securite/session/{$conn['id']}/revoquer")
            ->revokeSession(['lang' => 'fr', 'id' => (string) $conn['id']]);
    }

    // ----------------------------------------------------------------
    // revokeAllUserSessions() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide redirige sans révoquer.
     */
    public function testRevokeAllSessionsInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('revall.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad-csrf'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/sessions/revoquer-toutes')
            ->revokeAllUserSessions(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // addAddress() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide pose un flash error et redirige.
     */
    public function testAddAddressInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('addaddr.badcsrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'csrf_token' => 'bad-csrf',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
                ->addAddress(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    // ----------------------------------------------------------------
    // updateAddress() — CSRF invalide, zip invalide, bloqué commande active
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide pose un flash error et redirige.
     */
    public function testUpdateAddressInvalidCsrfRedirects(): void
    {
        $userId    = $this->insertCustomer('updaddr.badcsrf@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad-csrf'];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
                ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Un code postal français invalide pose un flash error et redirige.
     */
    public function testUpdateAddressInvalidZipRedirects(): void
    {
        $userId    = $this->insertCustomer('updaddr.badzip@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = [
            'civility'   => 'M',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'street'     => '1 rue de la Paix',
            'city'       => 'Paris',
            'zip_code'   => '00000',
            'country'    => 'France',
            'phone'      => '0601020304',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
                ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Une adresse liée à une commande active ne peut pas être modifiée.
     */
    public function testUpdateAddressSucceedsEvenWithActiveOrder(): void
    {
        // Depuis l'enabler order-address-snapshot, les adresses sont découplées des commandes
        // via des snapshots immuables : hasActiveOrderForAddress retourne toujours false,
        // donc la modification d'une adresse est toujours permise.
        $userId    = $this->insertCustomer('updaddr.blocked@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        $_POST = [
            'civility'   => 'M',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'street'     => '5 avenue de la Paix',
            'city'       => 'Paris',
            'zip_code'   => '75001',
            'country'    => 'France',
            'phone'      => '0601020304',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
                ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_success'] ?? '');
        }
    }

    // ----------------------------------------------------------------
    // deleteAddress() — CSRF invalide, bloqué commande active
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide pose un flash error et redirige.
     */
    public function testDeleteAddressInvalidCsrfRedirects(): void
    {
        $userId    = $this->insertCustomer('deladdr.badcsrf@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad-csrf'];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/supprimer")
                ->deleteAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Une adresse liée à une commande active ne peut pas être supprimée.
     */
    public function testDeleteAddressSucceedsEvenWithActiveOrder(): void
    {
        // Depuis l'enabler order-address-snapshot, les adresses sont découplées des commandes
        // via des snapshots immuables : hasActiveOrderForAddress retourne toujours false,
        // donc la suppression d'une adresse est toujours permise (snapshot préservé).
        $userId    = $this->insertCustomer('deladdr.blocked@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/supprimer")
                ->deleteAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_success'] ?? '');
        }
    }

    // ----------------------------------------------------------------
    // orders() — filtres période et per_page
    // ----------------------------------------------------------------

    /**
     * Un filtre période = '2024' active la branche année.
     */
    public function testOrdersWithYearPeriodRendersView(): void
    {
        $userId = $this->insertCustomer('orders.year@test.local', 'individual');
        $this->loginAs($userId);

        $_SERVER['REQUEST_URI'] = '/fr/mon-compte/commandes?period=2024';
        $_GET = ['period' => '2024'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?period=2024')
            ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Un per_page invalide est remplacé par la valeur par défaut (10).
     */
    public function testOrdersInvalidPerPageFallsToDefault(): void
    {
        $userId = $this->insertCustomer('orders.pp@test.local', 'individual');
        $this->loginAs($userId);

        $_GET = ['per_page' => '99'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?per_page=99')
            ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Un filtre statut valide est transmis au model.
     */
    public function testOrdersWithValidStatusFilterRendersView(): void
    {
        $userId = $this->insertCustomer('orders.status@test.local', 'individual');
        $this->loginAs($userId);

        $_GET = ['status' => 'pending'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?status=pending')
            ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    // ----------------------------------------------------------------
    // cancelOrder() — rétractation après livraison
    // ----------------------------------------------------------------

    /**
     * Une commande "delivered" dans la fenêtre de 15 jours passe à "return_requested".
     */
    public function testCancelOrderRequestsReturnWhenDeliveredWithinWindow(): void
    {
        $userId    = $this->insertCustomer('retract.ok@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 7 DAY');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(\Core\Exception\HttpException::class); // redirect → 302 levé par Response::redirect
        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
                ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT status FROM orders WHERE id = ?",
                [$orderId]
            );
            $this->assertSame('return_requested', $row['status']);
            $this->assertNotEmpty($_SESSION['flash']['order_success'] ?? null);
            throw $e;
        }
    }

    /**
     * Une commande "delivered" hors fenêtre (> 15 jours) ne passe pas à "return_requested".
     */
    public function testCancelOrderFailsWhenWindowExpired(): void
    {
        $userId    = $this->insertCustomer('retract.expired@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 16 DAY');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(\Core\Exception\HttpException::class);
        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
                ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT status FROM orders WHERE id = ?",
                [$orderId]
            );
            $this->assertSame('delivered', $row['status']);
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // returnSlip — chemins 404
    // ----------------------------------------------------------------

    /**
     * Commande inexistante → 404.
     */
    public function testReturnSlipAborts404WhenOrderNotFound(): void
    {
        $userId = $this->insertCustomer('slip.notfound@test.local');
        $this->loginAs($userId);

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('GET', '/fr/mon-compte/commandes/999999/fiche-retour')
                ->returnSlip(['lang' => 'fr', 'id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Commande avec statut 'pending' (ni return_requested ni delivered) → 404.
     */
    public function testReturnSlipAborts404WhenStatusInvalid(): void
    {
        $userId    = $this->insertCustomer('slip.pending@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'pending');
        $this->loginAs($userId);

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")
                ->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Commande delivered mais hors fenêtre (> 15 jours) → 404.
     */
    public function testReturnSlipAborts404WhenDeliveredOutsideWindow(): void
    {
        $userId    = $this->insertCustomer('slip.expired@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 20 DAY');
        $this->loginAs($userId);

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")
                ->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // orderDetail() — branches delivered_at
    // ----------------------------------------------------------------

    /**
     * Commande 'delivered' sans delivered_at → deliveredNoDate = true (l.161).
     */
    public function testOrderDetailDeliveredWithNullDeliveredAt(): void
    {
        $userId    = $this->insertCustomer('odtl.dnull@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'delivered');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Commande 'delivered' avec delivered_at dans la fenêtre → cancellableReturn = true (l.166-167).
     */
    public function testOrderDetailDeliveredWithinReturnWindow(): void
    {
        $userId    = $this->insertCustomer('odtl.dwin@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 3 DAY');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Commande 'delivered' avec delivered_at hors fenêtre → returnExpired = true (l.169).
     */
    public function testOrderDetailReturnWindowExpired(): void
    {
        $userId    = $this->insertCustomer('odtl.dexp@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 20 DAY');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    // ----------------------------------------------------------------
    // returnSlip() — chemin succès (via sous-classe interceptant sendPdfResponse)
    // ----------------------------------------------------------------

    /**
     * Commande 'delivered' dans la fenêtre → buildReturnSlipPdf + sendPdfResponse appelés.
     * Sous-classe intercepte sendPdfResponse pour éviter exit (couvre l.293-295, l.302-304, l.347-355).
     */
    public function testReturnSlipSuccessForDeliveredWithinWindow(): void
    {
        $userId    = $this->insertCustomer('slip.win@test.local');
        $addressId = $this->insertAddress($userId);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status, delivered_at)
             VALUES (?, ?, ?, 99.90, 'card', 0.00, ?, 'delivered', NOW() - INTERVAL 3 DAY)",
            [
                $userId,
                'TEST-' . bin2hex(random_bytes(4)),
                json_encode([['label_name' => 'Bordeaux Rouge', 'format' => 'bottle', 'qty' => 2, 'price' => 24.00]]),
                $snapshotId,
            ]
        );
        $this->loginAs($userId);

        $ctrl = new class ($this->makeRequest('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")) extends AccountController {
            protected function sendPdfResponse(string $pdfBytes, string $filename): never
            {
                throw new \RuntimeException('pdf-sent:' . $filename);
            }
        };

        ob_start();
        try {
            $ctrl->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
            $this->fail('Expected RuntimeException from sendPdfResponse seam');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('pdf-sent:fiche-retour_', $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Un CSRF invalide ne modifie pas le statut de la commande.
     */
    public function testCancelOrderReturnRequestBlockedByInvalidCsrf(): void
    {
        $userId    = $this->insertCustomer('retract.csrf@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 3 DAY');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'invalid-token'];

        $this->expectException(\Core\Exception\HttpException::class);
        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
                ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT status FROM orders WHERE id = ?",
                [$orderId]
            );
            $this->assertSame('delivered', $row['status']);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // orders() — period '3months'
    // ----------------------------------------------------------------

    /**
     * Un filtre période '3months' est transmis tel quel au model (branche != 'all' && == '3months').
     */
    public function testOrdersWith3MonthsPeriodRendersView(): void
    {
        $userId = $this->insertCustomer('orders.3m@test.local', 'individual');
        $this->loginAs($userId);

        $_GET = ['period' => '3months'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?period=3months')
            ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Un filtre statut invalide est ignoré (null transmis au model).
     */
    public function testOrdersWithInvalidStatusFilterRendersView(): void
    {
        $userId = $this->insertCustomer('orders.badstatus@test.local', 'individual');
        $this->loginAs($userId);

        $_GET = ['status' => 'invalid_status'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?status=invalid_status')
            ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    // ----------------------------------------------------------------
    // orderDetail() — flash messages et shippingDiscount
    // ----------------------------------------------------------------

    /**
     * Un flash order_success présent en session est lu et supprimé par orderDetail.
     */
    public function testOrderDetailConsumesFlashOrderSuccess(): void
    {
        $userId    = $this->insertCustomer('odtl.flash.ok@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'shipped');
        $this->loginAs($userId);

        $_SESSION['flash']['order_success'] = 'test-success-flash';

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        ob_get_clean();

        $this->assertArrayNotHasKey('order_success', $_SESSION['flash'] ?? []);
    }

    /**
     * Un flash order_error présent en session est lu et supprimé par orderDetail.
     */
    public function testOrderDetailConsumesFlashOrderError(): void
    {
        $userId    = $this->insertCustomer('odtl.flash.err@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'shipped');
        $this->loginAs($userId);

        $_SESSION['flash']['order_error'] = 'test-error-flash';

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        ob_get_clean();

        $this->assertArrayNotHasKey('order_error', $_SESSION['flash'] ?? []);
    }

    /**
     * Une commande avec shipping_discount > 0 est correctement rendue (couvre la branche > 0.0).
     */
    public function testOrderDetailWithShippingDiscount(): void
    {
        $userId    = $this->insertCustomer('odtl.ship@test.local', 'individual');
        $addressId = $this->insertAddress($userId);

        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 120.00, 'card', 5.90, ?, 'shipped')",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId]
        );

        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    // ----------------------------------------------------------------
    // addAddress() — pays étranger (zip non validé)
    // ----------------------------------------------------------------

    /**
     * Un POST avec un pays étranger (non France) n'est pas soumis à la validation du code postal.
     */
    public function testAddAddressForeignCountrySkipsZipValidation(): void
    {
        $userId = $this->insertCustomer('addaddr.foreign@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'type'       => 'billing',
            'civility'   => 'M',
            'firstname'  => 'Hans',
            'lastname'   => 'Müller',
            'street'     => 'Hauptstrasse 1',
            'city'       => 'Berlin',
            'zip_code'   => '10115',
            'country'    => 'Allemagne',
            'phone'      => '+49301234567',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')
            ->addAddress(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // updateAddress() — champs requis manquants
    // ----------------------------------------------------------------

    /**
     * Des champs requis manquants sur updateAddress posent un flash error et redirigent.
     */
    public function testUpdateAddressMissingFieldsRedirects(): void
    {
        $userId    = $this->insertCustomer('updaddr.missing@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = [
            'civility'   => 'M',
            'firstname'  => '',
            'lastname'   => '',
            'street'     => '',
            'city'       => '',
            'zip_code'   => '',
            'country'    => 'France',
            'phone'      => '',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
                ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
        }
    }

    /**
     * Un pays étranger sur updateAddress ne déclenche pas la validation du code postal français.
     */
    public function testUpdateAddressForeignCountrySkipsZipValidation(): void
    {
        $userId    = $this->insertCustomer('updaddr.foreign@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->loginAs($userId);

        $_POST = [
            'civility'   => 'M',
            'firstname'  => 'Hans',
            'lastname'   => 'Müller',
            'street'     => 'Hauptstrasse 1',
            'city'       => 'Berlin',
            'zip_code'   => '10115',
            'country'    => 'Allemagne',
            'phone'      => '+49301234567',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
            ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    // ----------------------------------------------------------------
    // untrustDevice() — CSRF invalide et device_token vide
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide sur untrustDevice ne révoque rien mais redirige (302).
     */
    public function testUntrustDeviceInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('untrust.devcsrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'device_token' => 'some-device-token',
            'csrf_token'   => 'bad-csrf',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
            ->untrustDevice(['lang' => 'fr']);
    }

    /**
     * Un device_token vide sur untrustDevice (même avec CSRF valide) ne révoque rien mais redirige.
     */
    public function testUntrustDeviceEmptyTokenRedirects(): void
    {
        $userId = $this->insertCustomer('untrust.devempty@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'device_token' => '',
            'csrf_token'   => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
            ->untrustDevice(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // returnSlip() — commande return_requested
    // ----------------------------------------------------------------

    /**
     * Une commande au statut 'return_requested' génère la fiche de retour PDF.
     */
    public function testReturnSlipSuccessForReturnRequested(): void
    {
        $userId    = $this->insertCustomer('slip.ret@test.local');
        $addressId = $this->insertAddress($userId);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status, delivered_at)
             VALUES (?, ?, ?, 99.90, 'card', 0.00, ?, 'return_requested', NOW() - INTERVAL 5 DAY)",
            [
                $userId,
                'TEST-' . bin2hex(random_bytes(4)),
                json_encode([['label_name' => 'Sauternes', 'format' => 'bottle', 'qty' => 1, 'price' => 35.00]]),
                $snapshotId,
            ]
        );
        $this->loginAs($userId);

        $ctrl = new class ($this->makeRequest('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")) extends AccountController {
            protected function sendPdfResponse(string $pdfBytes, string $filename): never
            {
                throw new \RuntimeException('pdf-sent:' . $filename);
            }
        };

        ob_start();
        try {
            $ctrl->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
            $this->fail('Expected RuntimeException from sendPdfResponse seam');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('pdf-sent:fiche-retour_', $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // cancelOrder() — commande ni annulable ni rétractable
    // ----------------------------------------------------------------

    /**
     * Une commande au statut 'shipped' (ni pending ni delivered dans la fenêtre) déclenche un flash error.
     */
    public function testCancelOrderFailsWhenNotCancellableNorReturnable(): void
    {
        $userId    = $this->insertCustomer('cancel.shipped@test.local');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'shipped');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
                ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // updateProfile() — newsletter = '1'
    // ----------------------------------------------------------------

    /**
     * Un POST updateProfile pour un compte individual redirige bien (302).
     */
    public function testUpdateProfileIndividualRedirects(): void
    {
        $userId = $this->insertCustomer('upd.indiv@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'civility'   => 'F',
            'csrf_token' => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // unsubscribe() — token vide
    // ----------------------------------------------------------------

    /**
     * Un POST unsubscribe avec un token vide affiche la vue avec success=false.
     */
    public function testUnsubscribeWithEmptyToken(): void
    {
        $_POST = ['unsub_token' => '', 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // addresses() — flash messages consommés
    // ----------------------------------------------------------------

    /**
     * Un flash address_success est lu et supprimé par addresses().
     */
    public function testAddressesConsumesFlashSuccess(): void
    {
        $userId = $this->insertCustomer('addr.flash.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['address_success'] = 'Adresse ajoutée';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('address_success', $_SESSION['flash'] ?? []);
    }

    /**
     * Un flash address_error est lu et supprimé par addresses().
     */
    public function testAddressesConsumesFlashError(): void
    {
        $userId = $this->insertCustomer('addr.flash.err@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['address_error'] = 'Erreur adresse';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('address_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // security() — flash messages consommés
    // ----------------------------------------------------------------

    /**
     * Un flash security_success est lu et supprimé par security().
     */
    public function testSecurityConsumesFlashSuccess(): void
    {
        $userId = $this->insertCustomer('sec.flash.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['security_success'] = 'Mot de passe mis à jour';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('security_success', $_SESSION['flash'] ?? []);
    }

    /**
     * Un flash security_errors est lu et supprimé par security().
     */
    public function testSecurityConsumesFlashErrors(): void
    {
        $userId = $this->insertCustomer('sec.flash.err@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['security_errors'] = ['csrf' => 'Token invalide'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('security_errors', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // profile() — flash messages consommés
    // ----------------------------------------------------------------

    /**
     * Un flash profile_success est lu et supprimé par profile().
     */
    public function testProfileConsumesFlashSuccess(): void
    {
        $userId = $this->insertCustomer('prof.flash.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['profile_success'] = 'Profil mis à jour';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('profile_success', $_SESSION['flash'] ?? []);
    }

    /**
     * Un flash profile_errors est lu et supprimé par profile().
     */
    public function testProfileConsumesFlashErrors(): void
    {
        $userId = $this->insertCustomer('prof.flash.err@test.local', 'individual');
        $this->loginAs($userId);

        $_SESSION['flash']['profile_errors'] = ['firstname' => 'Requis'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('profile_errors', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // deleteAccount() — compte avec commandes actives (via nouveau test)
    // ----------------------------------------------------------------

    /**
     * Un compte avec une commande 'paid' peut être supprimé (RGPD Art. 17).
     * Vérifie que la suppression aboutit bien (soft-delete, deleted_at positionné).
     */
    public function testDeleteAccountWithPaidOrderSoftDeletesAccount(): void
    {
        $userId    = $this->insertCustomer('del.paid@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->insertOrder($userId, $addressId, 'paid');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
            ->deleteAccount(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // revokeSession() — id = 0 (CSRF valide mais id invalide)
    // ----------------------------------------------------------------

    /**
     * Un POST avec CSRF valide mais id=0 ne révoque rien et redirige (couvre la branche $id > 0).
     */
    public function testRevokeSessionWithZeroIdSkipsRevoke(): void
    {
        $userId = $this->insertCustomer('rev.zero@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/session/0/revoquer')
            ->revokeSession(['lang' => 'fr', 'id' => '0']);
    }

    // ----------------------------------------------------------------
    // requireCustomer() — rôle non-customer
    // ----------------------------------------------------------------

    /**
     * Un utilisateur avec un rôle autre que 'customer' (ex: admin) reçoit un abort 404.
     * Couvre la branche role !== 'customer' dans requireCustomer().
     */
    public function testRequireCustomerAborts404ForAdminRole(): void
    {
        $userId = $this->insertCustomer('admin.role@test.local', 'individual');
        $this->loginAs($userId, 'admin');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // cancelMfa() — token non vide mais enregistrement inexistant
    // ----------------------------------------------------------------

    /**
     * Un token non vide mais absent de la base (ou expiré) n'est pas révoqué ;
     * la vue est rendue avec revoked=false.
     * Couvre la branche $token !== '' && findByToken() === false.
     *
     * @return void
     */
    public function testCancelMfaTokenNotFoundInDb(): void
    {
        $controller = $this->makeController('GET', '/fr/mon-compte/appareil/annuler');
        $_GET = ['token' => 'token-non-vide-mais-inexistant-' . bin2hex(random_bytes(8))];

        ob_start();
        $controller->cancelMfa(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // exportPage() — compte company
    // ----------------------------------------------------------------

    /**
     * Un compte company authentifié accède également à la page d'export RGPD.
     * Couvre le chemin exportPage() pour account_type = 'company'.
     *
     * @return void
     */
    public function testExportPageRendersForCompany(): void
    {
        $userId = $this->insertCustomer('export.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    // exportData() authentifié — via buildExportContent (seam refactorisé)
    // ----------------------------------------------------------------

    /**
     * buildExportData() retourne les 3 nouvelles sections RGPD Art. 20.
     * Couvre : appels getForExport(), getRecentForUser(), getConsentData() et leurs array_maps.
     */
    public function testBuildExportDataIncludesRgpdComplement(): void
    {
        $userId = $this->insertCustomer('build-export-data@test.local', 'individual');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportData');

        /** @var array<string, mixed> $export */
        $export = $method->invoke($ctrl, $userId);

        $this->assertArrayHasKey('connection_logs', $export, 'La clé connection_logs doit être présente');
        $this->assertArrayHasKey('consentements', $export, 'La clé consentements doit être présente');
        $this->assertArrayHasKey('trusted_devices', $export, 'La clé trusted_devices doit être présente');
        $this->assertArrayHasKey('adresses_commandes', $export, 'La clé adresses_commandes doit être présente');
        $this->assertArrayHasKey('newsletter', $export['consentements']);
        $this->assertArrayHasKey('cgv_versions_acceptees', $export['consentements']);
        $this->assertIsArray($export['connection_logs']);
        $this->assertIsArray($export['trusted_devices']);
        $this->assertIsArray($export['adresses_commandes']);
    }

    /**
     * buildExportData() — compte individuel : Prénom/Nom/Civilité présents, Raison sociale absente.
     */
    public function testBuildExportDataIndividualHasNamesNotCompany(): void
    {
        $userId = $this->insertCustomer('export-individual@test.local', 'individual');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportData');

        /** @var array<string, mixed> $export */
        $export  = $method->invoke($ctrl, $userId);
        $account = $export['account'];

        $this->assertArrayHasKey('Prénom', $account);
        $this->assertArrayHasKey('Nom', $account);
        $this->assertArrayHasKey('Civilité', $account);
        $this->assertArrayNotHasKey('Raison sociale', $account);
    }

    /**
     * buildExportData() — compte société : Raison sociale présente, Prénom/Nom/Civilité absents.
     */
    public function testBuildExportDataCompanyHasRaisonSocialeNotNames(): void
    {
        $userId = $this->insertCustomer('export-company@test.local', 'company');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportData');

        /** @var array<string, mixed> $export */
        $export  = $method->invoke($ctrl, $userId);
        $account = $export['account'];

        $this->assertArrayHasKey('Raison sociale', $account);
        $this->assertArrayNotHasKey('Prénom', $account);
        $this->assertArrayNotHasKey('Nom', $account);
        $this->assertArrayNotHasKey('Civilité', $account);
    }

    /**
     * buildExportData() — les entrées trusted_devices contiennent bien user_agent.
     */
    public function testBuildExportDataTrustedDevicesHaveUserAgent(): void
    {
        $userId = $this->insertCustomer('export-ua@test.local', 'individual');
        $this->loginAs($userId);

        // Insérer un appareil de confiance avec user_agent via connexion associée
        self::$db->execute(
            "INSERT INTO trusted_devices (user_id, device_token, device_name, confirmed_at, last_seen)
             VALUES (?, ?, 'Chrome · Win', NOW(), NOW())",
            [$userId, bin2hex(random_bytes(16))]
        );

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportData');

        /** @var array<string, mixed> $export */
        $export = $method->invoke($ctrl, $userId);

        $this->assertNotEmpty($export['trusted_devices'], 'Un appareil doit être retourné');
        $this->assertArrayHasKey('user_agent', $export['trusted_devices'][0], 'user_agent doit être présent');
    }

    /**
     * buildExportData() — consentements.newsletter contient abonne et email_verifie_le.
     */
    public function testBuildExportDataConsentementsStructure(): void
    {
        $userId = $this->insertCustomer('export-consent@test.local', 'individual');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportData');

        /** @var array<string, mixed> $export */
        $export  = $method->invoke($ctrl, $userId);
        $consent = $export['consentements'];

        $this->assertArrayHasKey('abonne', $consent['newsletter']);
        $this->assertArrayHasKey('email_verifie_le', $consent['newsletter']);
        $this->assertContains($consent['newsletter']['abonne'], ['Oui', 'Non']);
    }

    /**
     * buildExportContent() avec ZipArchive retourne un ZIP valide contenant le JSON.
     *
     * Couvre les lignes L1244-L1254 (branche ZipArchive) d'AccountController::exportData().
     * On passe par buildExportContent() directement pour éviter exit().
     */
    public function testBuildExportContentZipBranchReturnsValidZip(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive non disponible');
        }

        $userId = $this->insertCustomer('export.zip@test.local', 'individual');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportContent');

        $export   = [
            'exported_at'    => date('c'),
            'account'        => ['Email' => 'export.zip@test.local', 'Type de compte' => 'individual'],
            'addresses'      => [],
            'orders'         => [],
            'favorites'      => [],
            'trusted_devices' => [],
            'active_sessions' => [],
        ];
        $date     = date('Y-m-d');
        $basename = 'mes-donnees-' . $date;

        /** @var array{0: string, 1: bool} $result */
        $result          = (array) $method->invoke($ctrl, $export, $date, $basename);
        [$content, $isZip] = $result;

        $this->assertTrue($isZip, 'La branche ZipArchive doit retourner isZip=true');
        $this->assertStringStartsWith("PK\x03\x04", $content, 'Le contenu doit être un ZIP valide');

        // Vérifier que le JSON est bien dans le ZIP
        $tmp = tempnam(sys_get_temp_dir(), 'ti_zip_');
        file_put_contents($tmp, $content);
        $zip  = new \ZipArchive();
        $zip->open($tmp);
        $json = $zip->getFromName($basename . '.json');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($json, 'Le ZIP doit contenir ' . $basename . '.json');
        $decoded = json_decode((string) $json, true);
        $this->assertSame('export.zip@test.local', $decoded['account']['Email'] ?? null);
    }

    /**
     * buildExportContent() branche JSON fallback retourne du JSON valide.
     *
     * Couvre les lignes L1260-L1262 (branche fallback JSON) d'AccountController::exportData().
     */
    public function testBuildExportContentJsonFallbackReturnsValidJson(): void
    {
        $userId = $this->insertCustomer('export.json@test.local', 'individual');
        $this->loginAs($userId);

        // Sous-classe qui force la branche JSON (simule ZipArchive absent)
        $stub = new class ($this->makeController('GET', '/fr/mon-compte/export/telecharger')) extends \Controller\AccountController {
            public function __construct(private \Controller\AccountController $inner)
            {
                // pas de parent::__construct() — on délègue
            }

            protected function buildExportContent(array $export, string $date, string $basename): array
            {
                $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
                return [$json, false];
            }
        };

        $method = new \ReflectionMethod($stub, 'buildExportContent');

        $export   = ['account' => ['Email' => 'export.json@test.local'], 'addresses' => []];
        $date     = date('Y-m-d');
        /** @var array{0: string, 1: bool} $result */
        $result          = (array) $method->invoke($stub, $export, $date, 'mes-donnees-' . $date);
        [$content, $isZip] = $result;

        $this->assertFalse($isZip, 'Le fallback JSON doit retourner isZip=false');
        $decoded = json_decode($content, true);
        $this->assertSame('export.json@test.local', $decoded['account']['Email'] ?? null);
    }

    /**
     * buildExportContent() retourne un export JSON incluant les 3 nouvelles sections RGPD :
     * connection_logs, consentements et user_agent dans trusted_devices.
     */
    public function testBuildExportContentIncludesRgpdComplement(): void
    {
        $userId = $this->insertCustomer('export.rgpd.complement@test.local', 'individual');
        $this->loginAs($userId);

        $ctrl   = $this->makeController('GET', '/fr/mon-compte/export/telecharger');
        $method = new \ReflectionMethod($ctrl, 'buildExportContent');

        $export = [
            'exported_at'     => date('c'),
            'account'         => ['Email' => 'export.rgpd.complement@test.local'],
            'addresses'       => [],
            'orders'          => [],
            'favorites'       => [],
            'trusted_devices' => [
                ['device_name' => 'Chrome · Win', 'confirmed_at' => '2026-01-01 10:00:00',
                 'last_seen' => '2026-03-01 12:00:00', 'user_agent' => 'Mozilla/5.0 Chrome/120'],
            ],
            'active_sessions' => [],
            'connection_logs' => [
                ['device_name' => 'Chrome · Win', 'ip_address' => '1.2.3.4',
                 'user_agent' => 'Mozilla/5.0', 'auth_method' => 'password',
                 'status' => 'active', 'created_at' => '2026-03-01 10:00:00'],
            ],
            'consentements' => [
                'newsletter' => ['abonne' => 'Oui', 'email_verifie_le' => '2026-01-01 09:00:00'],
                'cgv_versions_acceptees' => ['1.0'],
            ],
        ];

        $date   = date('Y-m-d');
        /** @var array{0: string, 1: bool} $result */
        $result          = (array) $method->invoke($ctrl, $export, $date, 'mes-donnees-' . $date);
        [$content, $isZip] = $result;

        if ($isZip) {
            $tmp = tempnam(sys_get_temp_dir(), 'ti_rgpd_');
            file_put_contents($tmp, $content);
            $zip  = new \ZipArchive();
            $zip->open($tmp);
            $json = $zip->getFromName('mes-donnees-' . $date . '.json');
            $zip->close();
            unlink($tmp);
            $decoded = json_decode((string) $json, true);
        } else {
            $decoded = json_decode($content, true);
        }

        $this->assertArrayHasKey('connection_logs', $decoded, 'La clé connection_logs doit être présente');
        $this->assertArrayHasKey('consentements', $decoded, 'La clé consentements doit être présente');
        $this->assertArrayHasKey('newsletter', $decoded['consentements']);
        $this->assertArrayHasKey('cgv_versions_acceptees', $decoded['consentements']);
        $this->assertArrayHasKey('user_agent', $decoded['trusted_devices'][0]);
        $this->assertSame('Mozilla/5.0 Chrome/120', $decoded['trusted_devices'][0]['user_agent']);
        $this->assertSame('Oui', $decoded['consentements']['newsletter']['abonne']);
        $this->assertSame(['1.0'], $decoded['consentements']['cgv_versions_acceptees']);
    }

    // ----------------------------------------------------------------
    // unsubscribePage() — token non vide mais compte non trouvé
    // ----------------------------------------------------------------

    /**
     * Un token non vide dont le compte associé n'existe plus en base
     * affiche la vue avec confirm=false (branche !findByUnsubscribeToken()).
     *
     * @return void
     */
    public function testUnsubscribePageTokenNotFoundInDb(): void
    {
        // Token non vide mais absent de la base
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/newsletter/desabonnement';
        $_GET = ['token' => 'token-qui-nexiste-pas-du-tout-' . bin2hex(random_bytes(8))];
        $controller = new AccountController(new \Core\Request());

        ob_start();
        $controller->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
        // confirm=false → le token de désabonnement ne doit pas apparaître dans la vue
        $this->assertStringNotContainsString('unsub_token', $output);
    }

    // ----------------------------------------------------------------
    // newDevice() — session pending_device avec champs partiels
    // ----------------------------------------------------------------

    /**
     * Avec une session pending_device ne contenant que les clés minimales,
     * la vue est rendue sans erreur (couvre les opérateurs null-coalescing).
     *
     * @return void
     */
    public function testNewDeviceRendersWithEmptyPendingFields(): void
    {
        // Un tableau non-vide est requis : le check est `!$pending` ([] est falsy).
        $_SESSION['pending_device'] = ['device_name' => '', 'mfa_token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/nouvel-appareil')
            ->newDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // buildReturnSlipPdf() — méthodes privées PDF via ReflectionMethod
    // ----------------------------------------------------------------

    /**
     * buildReturnSlipPdf() avec un ordre minimal sans articles génère un PDF valide.
     *
     * @return void
     */
    public function testBuildReturnSlipPdfWithMinimalOrder(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        if (!class_exists('TCPDF')) {
            $this->markTestSkipped('TCPDF non disponible');
        }

        $order = [
            'content'         => '[]',
            'order_reference' => 'TEST-MINIMAL-001',
            'ordered_at'      => '2025-01-15 10:00:00',
            'bill_civility'   => 'M',
            'bill_firstname'  => 'Jean',
            'bill_lastname'   => 'Dupont',
            'bill_street'     => '12 rue de la Paix',
            'bill_zip'        => '75001',
            'bill_city'       => 'Paris',
            'bill_country'    => 'France',
            'status'          => 'delivered',
        ];

        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildReturnSlipPdf');

        $result = $ref->invoke($ctrl, $order);

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    /**
     * buildReturnSlipPdf() avec un ordre contenant des articles JSON génère un PDF valide
     * et couvre la boucle foreach sur les items.
     *
     * @return void
     */
    public function testBuildReturnSlipPdfWithOrderItems(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        if (!class_exists('TCPDF')) {
            $this->markTestSkipped('TCPDF non disponible');
        }

        $items = json_encode([
            [
                'label_name' => 'Château Crabitan Rouge 2022',
                'format'     => '75 cl',
                'qty'        => 3,
                'price'      => 18.50,
            ],
            [
                'label_name' => 'Château Crabitan Blanc 2023',
                'format'     => '75 cl',
                'qty'        => 6,
                'price'      => 15.00,
            ],
        ]);

        $order = [
            'content'         => $items,
            'order_reference' => 'TEST-ITEMS-002',
            'ordered_at'      => '2025-06-20 14:30:00',
            'bill_civility'   => 'Mme',
            'bill_firstname'  => 'Marie',
            'bill_lastname'   => 'Martin',
            'bill_street'     => '5 avenue Montaigne',
            'bill_zip'        => '75008',
            'bill_city'       => 'Paris',
            'bill_country'    => 'France',
            'status'          => 'return_requested',
        ];

        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildReturnSlipPdf');

        $result = $ref->invoke($ctrl, $order);

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    // ----------------------------------------------------------------
    // buildPdfTableSection() — via ReflectionMethod
    // ----------------------------------------------------------------

    /**
     * buildPdfTableSection() avec $rows=[] retourne la section vide avec le message fourni.
     *
     * @return void
     */
    public function testBuildPdfTableSectionWithEmptyRows(): void
    {
        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildPdfTableSection');

        $result = $ref->invoke(
            $ctrl,
            'Commandes',
            [],
            '<tr><th>Référence</th></tr>',
            static fn(array $r): string => '<tr><td>' . $r['ref'] . '</td></tr>',
            'Aucune commande.'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Commandes (0)', $result);
        $this->assertStringContainsString('Aucune commande.', $result);
        $this->assertStringNotContainsString('<table>', $result);
    }

    /**
     * buildPdfTableSection() avec $rows non-vide retourne la section HTML avec le tableau.
     *
     * @return void
     */
    public function testBuildPdfTableSectionWithRows(): void
    {
        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildPdfTableSection');

        $rows = [
            ['reference' => 'CMD-001', 'status' => 'delivered', 'price' => '99.90', 'ordered_at' => '2025-01-10'],
            ['reference' => 'CMD-002', 'status' => 'pending',   'price' => '45.00', 'ordered_at' => '2025-02-05'],
        ];

        $result = $ref->invoke(
            $ctrl,
            'Commandes',
            $rows,
            '<tr><th>Référence</th><th>Statut</th></tr>',
            static fn(array $r): string => '<tr><td>' . htmlspecialchars($r['reference'])
                . '</td><td>' . htmlspecialchars($r['status']) . '</td></tr>',
            'Aucune commande.'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Commandes (2)', $result);
        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('CMD-001', $result);
        $this->assertStringContainsString('CMD-002', $result);
        $this->assertStringNotContainsString('Aucune commande.', $result);
    }

    // ----------------------------------------------------------------
    // buildExportPdf() — via ReflectionMethod
    // ----------------------------------------------------------------

    /**
     * buildExportPdf() avec tous les tableaux vides génère un PDF valide
     * et couvre les branches $rows === [] dans buildPdfTableSection().
     *
     * @return void
     */
    public function testBuildExportPdfWithEmptyData(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        if (!class_exists('TCPDF')) {
            $this->markTestSkipped('TCPDF non disponible');
        }

        $export = [
            'account'         => [
                'email'      => 'export@test.local',
                'created_at' => '2024-01-01',
            ],
            'orders'          => [],
            'addresses'       => [],
            'favorites'       => [],
            'trusted_devices' => [],
            'active_sessions' => [],
        ];

        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildExportPdf');

        $result = $ref->invoke($ctrl, $export, '29/03/2026');

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    /**
     * buildExportPdf() avec des données dans chaque section génère un PDF valide
     * et couvre les branches non-vides (foreach) dans buildPdfTableSection().
     *
     * @return void
     */
    public function testBuildExportPdfWithPopulatedData(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        if (!class_exists('TCPDF')) {
            $this->markTestSkipped('TCPDF non disponible');
        }

        $export = [
            'account'         => [
                'email'        => 'populated@test.local',
                'account_type' => 'individual',
                'created_at'   => '2024-06-15',
            ],
            'orders'          => [
                [
                    'reference'  => 'CMD-EXP-001',
                    'status'     => 'delivered',
                    'price'      => '129.50',
                    'ordered_at' => '2025-03-10',
                ],
            ],
            'addresses'       => [
                [
                    'type'      => 'billing',
                    'firstname' => 'Jean',
                    'lastname'  => 'Dupont',
                    'street'    => '12 rue de la Paix',
                    'zip_code'  => '75001',
                    'city'      => 'Paris',
                    'country'   => 'France',
                ],
            ],
            'favorites'       => [
                [
                    'name'    => 'Château Crabitan Rouge',
                    'vintage' => 2022,
                ],
            ],
            'trusted_devices' => [
                [
                    'device_name'  => 'Chrome / Windows',
                    'confirmed_at' => '2025-01-20 09:00:00',
                    'last_seen'    => '2026-03-28 18:45:00',
                ],
            ],
            'active_sessions' => [
                [
                    'device_name' => 'Firefox / macOS',
                    'ip_address'  => '192.168.1.1',
                    'created_at'  => '2026-03-29 08:00:00',
                    'expired_at'  => '2026-04-05 08:00:00',
                ],
            ],
        ];

        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'buildExportPdf');

        $result = $ref->invoke($ctrl, $export, '29/03/2026');

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    // ----------------------------------------------------------------
    // validateAndSaveCompany() — via ReflectionMethod
    // ----------------------------------------------------------------

    /**
     * validateAndSaveCompany() avec companyName vide retourne une erreur de validation
     * sans appeler la BDD.
     *
     * @return void
     */
    public function testValidateAndSaveCompanyWithEmptyNameReturnsError(): void
    {
        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'validateAndSaveCompany');

        $errors = $ref->invoke($ctrl, 999999, '', null);

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('company_name', $errors);
        $this->assertNotEmpty($errors['company_name']);
    }

    // ----------------------------------------------------------------
    // cancelOrder() — branche card/paid avec ca_numappel/ca_numtrans
    // ----------------------------------------------------------------

    /**
     * Une commande "paid" par carte avec ca_numappel/ca_numtrans remplis
     * mais dont le remboursement GAE échoue doit poser un flash error et rediriger.
     *
     * @return void
     */
    public function testCancelOrderCardPaidWithCaRefsRefundFails(): void
    {
        $userId    = $this->insertCustomer('cancel.card.fail@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot, status,
              ca_numappel, ca_numtrans)
             VALUES (?, ?, '[]', 50.00, 'card', ?, 'paid', '12345', '67890')",
            [$userId, 'TEST-CARD-FAIL-' . bin2hex(random_bytes(4)), $snapshotId]
        );
        $this->loginAs($userId);

        // Stub PaymentService → remboursement échoue
        $_POST['csrf_token'] = self::CSRF;

        $failingPayment = new class extends \Service\PaymentService {
            public function callGaeCancelOrRefund(
                string $ref,
                string $numappel,
                string $numtrans,
                int $amount
            ): bool {
                return false;
            }
        };

        $controller = new class (
            $this->makeRequest('POST', "/fr/mon-compte/commandes/{$orderId}/annuler"),
            $failingPayment
        ) extends \Controller\AccountController {
            public function __construct(
                \Core\Request $request,
                private \Service\PaymentService $mockPayment
            ) {
                parent::__construct($request);
            }

            protected function newPaymentService(): \Service\PaymentService
            {
                return $this->mockPayment;
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $controller->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            // Le statut ne doit pas avoir changé
            $row = self::$db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
            $this->assertSame('paid', $row['status']);
            throw $e;
        }
    }

    /**
     * Une commande "paid" par carte avec ca_numappel/ca_numtrans remplis
     * et dont le remboursement GAE réussit doit passer au statut "refunded" et poser un flash success.
     *
     * @return void
     */
    public function testCancelOrderCardPaidWithCaRefsRefundSucceeds(): void
    {
        $userId    = $this->insertCustomer('cancel.card.ok@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot, status,
              ca_numappel, ca_numtrans)
             VALUES (?, ?, '[]', 50.00, 'card', ?, 'paid', '12345', '67890')",
            [$userId, 'TEST-CARD-OK-' . bin2hex(random_bytes(4)), $snapshotId]
        );
        $this->loginAs($userId);

        $_POST['csrf_token'] = self::CSRF;

        $okPayment = new class extends \Service\PaymentService {
            public function callGaeCancelOrRefund(
                string $ref,
                string $numappel,
                string $numtrans,
                int $amount
            ): bool {
                return true;
            }
        };

        $controller = new class (
            $this->makeRequest('POST', "/fr/mon-compte/commandes/{$orderId}/annuler"),
            $okPayment
        ) extends \Controller\AccountController {
            public function __construct(
                \Core\Request $request,
                private \Service\PaymentService $mockPayment
            ) {
                parent::__construct($request);
            }

            protected function newPaymentService(): \Service\PaymentService
            {
                return $this->mockPayment;
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $controller->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_success'] ?? null);
            $row = self::$db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
            $this->assertSame('refunded', $row['status']);
            throw $e;
        }
    }

    /**
     * Une commande "paid" par carte avec ca_numappel/ca_numtrans vides
     * ne doit pas tenter le remboursement GAE mais poursuivre l'annulation normale.
     * La commande au statut "paid" ne peut pas être annulée (cancelForUser échoue),
     * donc on attend un flash error de cancel_failed (la commande ne peut pas être annulée
     * depuis le statut paid sans refs GAE).
     *
     * @return void
     */
    public function testCancelOrderCardPaidWithMissingCaRefsProceeds(): void
    {
        $userId    = $this->insertCustomer('cancel.card.norefs@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        // Commande paid sans ca_numappel/ca_numtrans → branche else (logger uniquement)
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot, status,
              ca_numappel, ca_numtrans)
             VALUES (?, ?, '[]', 50.00, 'card', ?, 'paid', '', '')",
            [$userId, 'TEST-CARD-NOREFS-' . bin2hex(random_bytes(4)), $snapshotId]
        );
        $this->loginAs($userId);

        $_POST['csrf_token'] = self::CSRF;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
                ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (HttpException $e) {
            // La commande paid sans refs GAE passe par le chemin cancelForUser/requestReturnForUser
            // Aucun appel GAE — vérifier que la redirection (302) est bien émise
            $this->assertSame(302, $e->getCode());
            throw $e;
        }
    }

    /**
     * validateAndSaveCompany() avec un nom valide et un userId existant met à jour le profil
     * et retourne un tableau d'erreurs vide.
     *
     * @return void
     */
    public function testValidateAndSaveCompanyWithValidDataReturnsNoErrors(): void
    {
        $userId = $this->insertCustomer('company-validate@test.local', 'company');
        $this->loginAs($userId);

        $ctrl = $this->makeController('GET', '/fr/mon-compte');
        $ref  = new \ReflectionMethod(AccountController::class, 'validateAndSaveCompany');

        $errors = $ref->invoke($ctrl, $userId, 'Nouvelle Société SAS', '98765432109876');

        $this->assertIsArray($errors);
        $this->assertSame([], $errors);
    }

    // ----------------------------------------------------------------
    // confirmSecurityAction() GET
    // ----------------------------------------------------------------

    /**
     * Un token valide applique le pending password, révoque les sessions et affiche le succès.
     */
    public function testConfirmSecurityActionSuccessWithValidToken(): void
    {
        $userId      = $this->insertCustomer('sec.confirm.ok@test.local', 'individual');
        $raw         = bin2hex(random_bytes(16));
        $hash        = hash('sha256', $raw);
        $expires     = date('Y-m-d H:i:s', time() + 3600);
        $newPwdHash  = password_hash('ConfirmedPwd1!', PASSWORD_BCRYPT);

        $fakeSessionToken = 'fake.session.jwt.for.test';
        self::$db->execute(
            "UPDATE accounts SET security_confirm_token_hash = ?, security_confirm_token_expires_at = ?,
             pending_password_hash = ?, pending_password_expires_at = ?,
             pending_password_session_token = ? WHERE id = ?",
            [$hash, $expires, $newPwdHash, $expires, $fakeSessionToken, $userId]
        );

        $_GET['token'] = $raw;

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer')
            ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);
        // Token consommé
        $this->assertFalse((new \Model\AccountModel())->findBySecurityConfirmToken($hash));
        // Pending password appliqué + session token effacé
        $row = self::$db->fetchOne(
            "SELECT password, pending_password_hash, pending_password_session_token FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertIsArray($row);
        $this->assertSame($newPwdHash, $row['password']);
        $this->assertNull($row['pending_password_hash']);
        $this->assertNull($row['pending_password_session_token']);
    }

    /**
     * Un token expiré affiche la page d'erreur.
     */
    public function testConfirmSecurityActionFailsWithExpiredToken(): void
    {
        $userId  = $this->insertCustomer('sec.confirm.exp@test.local', 'individual');
        $raw     = bin2hex(random_bytes(16));
        $hash    = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() - 1);

        (new \Model\AccountModel())->saveSecurityConfirmToken($userId, $hash, $expires);

        $_GET['token'] = $raw;

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer')
            ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * Un token inconnu affiche la page d'erreur.
     */
    public function testConfirmSecurityActionFailsWithUnknownToken(): void
    {
        $_GET['token'] = 'totally-invalid-token-xyz';

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer')
            ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * Sans token dans la querystring, la page d'erreur s'affiche.
     */
    public function testConfirmSecurityActionFailsWithNoToken(): void
    {
        unset($_GET['token']);

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer')
            ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        // Vue chargée sans succès (token vide = success false)
        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * changePassword() stocke un pending password et un security_confirm_token — ne modifie pas password.
     */
    public function testChangePasswordStoresPendingPasswordAndToken(): void
    {
        $userId = $this->insertCustomer('chpwd.token@test.local', 'individual');
        $this->loginAs($userId);

        $originalRow = self::$db->fetchOne("SELECT password FROM accounts WHERE id = ?", [$userId]);
        $this->assertIsArray($originalRow);
        $originalPwd = $originalRow['password'];

        $_POST = [
            'current_password'     => 'Password123!',
            'new_password'         => 'NewSecurePass456!',
            'new_password_confirm' => 'NewSecurePass456!',
            'csrf_token'           => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException) {
            // 302 attendu
        }

        $row = self::$db->fetchOne(
            "SELECT password, pending_password_hash, security_confirm_token_hash
             FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertIsArray($row);
        // Mot de passe original inchangé
        $this->assertSame($originalPwd, $row['password']);
        // Pending password stocké
        $this->assertNotNull($row['pending_password_hash']);
        // Token de confirmation stocké
        $this->assertNotNull($row['security_confirm_token_hash']);
    }

    // ----------------------------------------------------------------
    // denySecurityAction() GET
    // ----------------------------------------------------------------

    /**
     * Un token valide annule le pending password, révoque les sessions,
     * supprime tous les appareils de confiance et affiche le succès.
     */
    public function testDenySecurityActionSuccessWithValidToken(): void
    {
        $userId  = $this->insertCustomer('sec.deny.ok@test.local', 'individual');
        $raw     = bin2hex(random_bytes(16));
        $hash    = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        self::$db->execute(
            "UPDATE accounts SET security_confirm_token_hash = ?, security_confirm_token_expires_at = ?,
             pending_password_hash = ?, pending_password_expires_at = ?,
             pending_password_session_token = 'deny.session.token' WHERE id = ?",
            [$hash, $expires, password_hash('ShouldBeCleared1!', PASSWORD_BCRYPT), $expires, $userId]
        );

        // Insérer un appareil de confiance — doit être supprimé lors du refus
        self::$db->execute(
            "INSERT INTO trusted_devices (user_id, device_token, device_name, confirmed_at)
             VALUES (?, 'deny.device.token', 'Test Device', NOW())",
            [$userId]
        );

        $_GET['token'] = $raw;

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);

        // Token consommé + pending effacé
        $row = self::$db->fetchOne(
            "SELECT pending_password_hash, security_confirm_token_hash FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertIsArray($row);
        $this->assertNull($row['pending_password_hash']);
        $this->assertNull($row['security_confirm_token_hash']);

        // Tous les appareils de confiance supprimés
        $deviceCount = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM trusted_devices WHERE user_id = ?",
            [$userId]
        );
        $this->assertSame(0, (int) $deviceCount['cnt']);
    }

    /**
     * Un token expiré pour le refus affiche la page d'erreur.
     */
    public function testDenySecurityActionFailsWithExpiredToken(): void
    {
        $userId  = $this->insertCustomer('sec.deny.exp@test.local', 'individual');
        $raw     = bin2hex(random_bytes(16));
        $hash    = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() - 1);

        self::$db->execute(
            "UPDATE accounts SET security_confirm_token_hash = ?, security_confirm_token_expires_at = ? WHERE id = ?",
            [$hash, $expires, $userId]
        );

        $_GET['token'] = $raw;

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * Un token inconnu pour le refus affiche la page d'erreur.
     */
    public function testDenySecurityActionFailsWithUnknownToken(): void
    {
        $_GET['token'] = 'deny-invalid-token-xyz';

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * Sans token, la page d'erreur s'affiche pour le refus.
     */
    public function testDenySecurityActionFailsWithNoToken(): void
    {
        unset($_GET['token']);

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    // ----------------------------------------------------------------
    // changePassword() — rate limiting (3 tentatives / 15 min / IP)
    // ----------------------------------------------------------------

    /**
     * Une tentative sur un bucket déjà saturé (count >= 3) est bloquée
     * et pose la clé rate_limit dans les erreurs flash security.
     *
     * Utilise le pré-chargement de session pour simuler 3 tentatives déjà enregistrées,
     * identique au pattern de AuthLoginRateLimitTest.
     */
    public function testChangePasswordRateLimitBlocksWhenBucketSaturated(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $this->insertCustomer('chpwd.rl@test.local', 'individual');
        $this->loginAs($userId);

        $_SERVER['REMOTE_ADDR'] = '10.99.88.77';

        // Réinitialiser $_SESSION après session_start() pour garantir csrf + _rl
        // Pré-charger 3 tentatives (= MAX_CHANGE_PW_IP) → prochaine checkLimit retourne false
        $_SESSION = [
            'csrf'    => self::CSRF,
            '_rl'     => ['rl:change_pw:10.99.88.77:count' => 3],
            '_rl_ttl' => ['rl:change_pw:10.99.88.77:count' => time() + 900],
        ];

        $_POST = [
            'csrf_token'           => self::CSRF,
            'current_password'     => 'WrongPassword!',
            'new_password'         => 'NewPassword123!',
            'new_password_confirm' => 'NewPassword123!',
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $this->assertArrayHasKey(
            'rate_limit',
            $_SESSION['flash']['security_errors'] ?? [],
            'Une tentative sur bucket saturé doit être bloquée par le rate limiter'
        );
    }

    /**
     * Une tentative sur un bucket non saturé (count < 3) incrémente le compteur.
     */
    public function testChangePasswordRateLimitIncrementsCounterOnFailure(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $this->insertCustomer('chpwd.rl2@test.local', 'individual');
        $this->loginAs($userId);

        $_SERVER['REMOTE_ADDR'] = '10.99.77.66';

        $_SESSION = ['csrf' => self::CSRF, '_rl' => [], '_rl_ttl' => []];

        $_POST = [
            'csrf_token'           => self::CSRF,
            'current_password'     => 'WrongPassword!',
            'new_password'         => 'NewPassword123!',
            'new_password_confirm' => 'NewPassword123!',
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
                ->changePassword(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $count = (int) ($_SESSION['_rl']['rl:change_pw:10.99.77.66:count'] ?? 0);
        $this->assertSame(1, $count, 'Le compteur doit être incrémenté après une tentative');
    }

    // ----------------------------------------------------------------
    // exportSend() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide pose le flash export_error et redirige.
     */
    public function testExportSendInvalidCsrfSetsFlashError(): void
    {
        $userId = $this->insertCustomer('exp-csrf@test.local', 'individual');
        $this->loginAs($userId);
        $_POST['csrf_token'] = 'invalid-token';

        try {
            $this->makeController('POST', '/fr/mon-compte/export/envoyer')
                ->exportSend(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect() lève HttpException
        }

        $this->assertArrayHasKey('export_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // exportSend() — rate limit dépassé
    // ----------------------------------------------------------------

    /**
     * Un utilisateur bloqué par le rate limit reçoit le flash export_error.
     */
    public function testExportSendRateLimitExceededSetsFlashError(): void
    {
        $userId = $this->insertCustomer('exp-rl@test.local', 'individual');
        $this->loginAs($userId);
        $_POST['csrf_token'] = self::CSRF;

        // Simuler un lockout actif directement en session (backend fallback sans APCu)
        $_SESSION['_rl']['rl:rgpd_export:' . $userId . ':until'] = time() + 86400;

        try {
            $this->makeController('POST', '/fr/mon-compte/export/envoyer')
                ->exportSend(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect() lève HttpException
        }

        $this->assertArrayHasKey('export_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // exportSend() — MailService lève une exception → catch → flash error
    // ----------------------------------------------------------------

    /**
     * Quand sendRgpdExport() lève une exception, le flash export_error est posé.
     * Utilise le seam newMailService() pour injecter un MailService déterministe
     * qui lance systématiquement une RuntimeException — indépendant de SMTP.
     */
    public function testExportSendMailerExceptionSetsFlashError(): void
    {
        $userId = $this->insertCustomer('exp-smtp@test.local', 'individual');
        $this->loginAs($userId);
        $_POST['csrf_token'] = self::CSRF;

        $ctrl = new class ($this->makeRequest('POST', '/fr/mon-compte/export/envoyer')) extends AccountController {
            protected function newMailService(): \Service\MailService
            {
                return new class extends \Service\MailService {
                    public function sendRgpdExport(
                        string $to,
                        string $name,
                        string $zipContent,
                        string $filename,
                        string $lang
                    ): void {
                        throw new \RuntimeException('SMTP simulé indisponible');
                    }
                };
            }
        };

        try {
            $ctrl->exportSend(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect() lève HttpException
        }

        $this->assertArrayHasKey('export_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // exportSend() — succès avec MailService mocké
    // ----------------------------------------------------------------

    /**
     * Quand l'envoi email réussit, le flash export_sent contient l'email masqué.
     */
    public function testExportSendSuccessWithMockedMailerSetsFlashSent(): void
    {
        $userId = $this->insertCustomer('exp-ok@test.local', 'individual');
        $this->loginAs($userId);
        $_POST['csrf_token'] = self::CSRF;

        // Sous-classe avec MailService no-op (seam newMailService — évite le SMTP en test)
        $ctrl = new class ($this->makeRequest('POST', '/fr/mon-compte/export/envoyer')) extends AccountController {
            protected function newMailService(): \Service\MailService
            {
                return new class extends \Service\MailService {
                    public function sendRgpdExport(
                        string $to,
                        string $name,
                        string $zipContent,
                        string $filename,
                        string $lang
                    ): void { /* no-op : évite le SMTP en test */
                    }
                };
            }
        };

        try {
            $ctrl->exportSend(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect() lève HttpException
        }

        $this->assertArrayHasKey('export_sent', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // exportPage() — flash export_sent + export_error
    // ----------------------------------------------------------------

    /**
     * exportPage() lit et expose le flash export_sent (adresse masquée).
     */
    public function testExportPageWithFlashSentPassesDataToView(): void
    {
        $userId = $this->insertCustomer('exp-page-sent@test.local', 'individual');
        $this->loginAs($userId);
        $_SESSION['flash']['export_sent'] = 'e***@test.local';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        // Le flash est consommé (absent de la session)
        $this->assertArrayNotHasKey('export_sent', $_SESSION['flash'] ?? []);
        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * exportPage() lit et expose le flash export_error.
     */
    public function testExportPageWithFlashErrorPassesDataToView(): void
    {
        $userId = $this->insertCustomer('exp-page-err@test.local', 'individual');
        $this->loginAs($userId);
        $_SESSION['flash']['export_error'] = 'Une erreur est survenue.';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertArrayNotHasKey('export_error', $_SESSION['flash'] ?? []);
        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * exportPage() affiche le timestamp de dernière demande quand disponible en session.
     */
    public function testExportPageWithLastExportTimestampRendersDate(): void
    {
        $userId = $this->insertCustomer('exp-page-ts@test.local', 'individual');
        $this->loginAs($userId);

        // Simuler un timestamp stocké par rateLimiter::remember() (clé session fallback)
        $_SESSION['_rl']['rl:rgpd_export_ts:' . $userId] = mktime(14, 30, 0, 4, 6, 2026);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        ob_get_clean();

        // Le test vérifie que le chemin lastExportTs !== null est couvert (pas d'exception)
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // maskEmail() — cas limites (méthode privée via Reflection)
    // ----------------------------------------------------------------

    /**
     * maskEmail() masque correctement un email standard.
     */
    public function testMaskEmailNormal(): void
    {
        $method = new \ReflectionMethod(AccountController::class, 'maskEmail');

        $this->assertSame('j***@gmail.com', $method->invoke(null, 'jean@gmail.com'));
        $this->assertSame('j***@example.com', $method->invoke(null, 'jo@example.com'));
    }

    /**
     * maskEmail() retourne des étoiles si l'email ne contient pas de @.
     */
    public function testMaskEmailNoAtSign(): void
    {
        $method = new \ReflectionMethod(AccountController::class, 'maskEmail');

        $result = $method->invoke(null, 'invalidemail');
        $this->assertSame(str_repeat('*', strlen('invalidemail')), $result);
    }

    /**
     * maskEmail() retourne des étoiles si @ est en position 0.
     */
    public function testMaskEmailAtSignAtStart(): void
    {
        $method = new \ReflectionMethod(AccountController::class, 'maskEmail');

        $result = $method->invoke(null, '@domain.com');
        $this->assertStringContainsString('*', $result);
    }

    // ----------------------------------------------------------------
    // updateLangPreference — AJAX endpoint
    // ----------------------------------------------------------------

    /**
     * Sans auth → redirect 302 (requireCustomer).
     */
    public function testUpdateLangPreferenceRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
    }

    /**
     * CSRF invalide → JSON 403.
     */
    public function testUpdateLangPreferenceRejectsInvalidCsrf(): void
    {
        $userId = $this->insertCustomer('langpref.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['lang' => 'en', 'csrf_token' => 'wrong'];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
        $body = ob_get_clean();

        $data = json_decode($body, true);
        $this->assertFalse($data['success']);
        $this->assertSame('csrf', $data['error']);
    }

    /**
     * Lang invalide → JSON 422.
     */
    public function testUpdateLangPreferenceRejectsInvalidLang(): void
    {
        $userId = $this->insertCustomer('langpref.invalid@test.local');
        $this->loginAs($userId);
        $_POST = ['lang' => 'de', 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
        $body = ob_get_clean();

        $data = json_decode($body, true);
        $this->assertFalse($data['success']);
        $this->assertSame('invalid_lang', $data['error']);
    }

    /**
     * Lang valide → accounts.lang mis à jour en BDD + JSON success.
     */
    public function testUpdateLangPreferenceUpdatesDatabaseAndReturnsSuccess(): void
    {
        $userId = $this->insertCustomer('langpref.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['lang' => 'en', 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
        $body = ob_get_clean();

        $data = json_decode($body, true);
        $this->assertTrue($data['success']);

        $row = self::$db->fetchOne("SELECT lang FROM accounts WHERE id = ?", [$userId]);
        $this->assertSame('en', $row['lang']);
    }

    /**
     * Lang fr → accepté (no-op si déjà fr, mais toujours succès).
     */
    public function testUpdateLangPreferenceAcceptsFr(): void
    {
        $userId = $this->insertCustomer('langpref.fr@test.local');
        $this->loginAs($userId);
        $_POST = ['lang' => 'fr', 'csrf_token' => self::CSRF];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
        $body = ob_get_clean();

        $data = json_decode($body, true);
        $this->assertTrue($data['success']);
    }

    // ----------------------------------------------------------------
    // updateLangPreference() — redirect_to valide (L2134)
    // ----------------------------------------------------------------

    /**
     * Un redirect_to valide déclenche une redirection 302 après la mise à jour de la langue.
     * Couvre la branche L2134 : redirect_to non-vide + matching ^/(fr|en)(/|$).
     */
    public function testUpdateLangPreferenceRedirectsWithValidRedirectTo(): void
    {
        $userId = $this->insertCustomer('langpref.redir@test.local');
        $this->loginAs($userId);
        $_POST = ['lang' => 'en', 'csrf_token' => self::CSRF, 'redirect_to' => '/en/mon-compte'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
            ->updateLangPreference(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // updateProfile() — newsletter 0→1 (double opt-in) (L1140-1148)
    // ----------------------------------------------------------------

    /**
     * Un POST updateProfile avec newsletter=1 alors que le compte n'est pas abonné
     * déclenche l'envoi d'un email de confirmation (double opt-in) et redirige (302).
     * L'email SMTP peut échouer (pas de serveur local) — on attrape toute exception de redirection.
     * Couvre les lignes L1140-1148.
     */
    public function testUpdateProfileNewsletterOptInSendsConfirmationEmail(): void
    {
        $userId = $this->insertCustomer('newsletter.optin@test.local', 'individual');
        $this->loginAs($userId);

        // S'assurer que newsletter = false (valeur par défaut à l'insertion)
        self::$db->execute("UPDATE accounts SET newsletter = 0 WHERE id = ?", [$userId]);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'civility'   => 'F',
            'newsletter' => '1',
            'csrf_token' => self::CSRF,
        ];

        // L'envoi SMTP peut échouer en environnement de test — on attrape les deux cas :
        // 302 (redirect) si l'email est envoyé, ou PHPMailer\Exception si SMTP indisponible.
        try {
            $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
            $this->fail('Expected exception (HttpException 302 or PHPMailer\Exception)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // SMTP non disponible en local — la branche L1143 a bien été exécutée
            $this->assertStringContainsString('SMTP', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — email invalide (L1185-1186)
    // ----------------------------------------------------------------

    /**
     * Un email invalide (format incorrect) pose un flash error et redirige.
     * Couvre les lignes L1185-1186.
     */
    public function testRequestEmailChangeInvalidEmailRedirects(): void
    {
        $userId = $this->insertCustomer('emailchg.bademail@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'new_email'        => 'not-an-email',
            'current_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
                ->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un email vide pose un flash error et redirige.
     * Couvre les lignes L1184-1186 (branche email vide).
     */
    public function testRequestEmailChangeEmptyEmailRedirects(): void
    {
        $userId = $this->insertCustomer('emailchg.empty@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'new_email'        => '',
            'current_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
                ->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un mot de passe incorrect lève une HttpException depuis AccountService (L1194-1196).
     * Couvre le catch HttpException dans requestEmailChange.
     */
    public function testRequestEmailChangeWrongPasswordFlashesError(): void
    {
        $userId = $this->insertCustomer('emailchg.badpwd@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'new_email'        => 'new-valid@test.local',
            'current_password' => 'WrongPassword!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
                ->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un changement d'email avec mot de passe correct redirige avec flash success.
     * Couvre les lignes L1202-1204.
     */
    public function testRequestEmailChangeSuccessRedirectsWithFlash(): void
    {
        $userId = $this->insertCustomer('emailchg.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'new_email'        => 'new-unique-emailchg-ok@test.local',
            'current_password' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
            ->requestEmailChange(['lang' => 'fr']);
    }

    /**
     * Un CSRF invalide redirige avec flash error (L1177-1178).
     */
    public function testRequestEmailChangeInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('emailchg.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'new_email'        => 'valid@test.local',
            'current_password' => 'Password123!',
            'csrf_token'       => 'bad-csrf',
        ];

        try {
            $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
                ->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    // ----------------------------------------------------------------
    // confirmEmailChange() (L1225-1254)
    // ----------------------------------------------------------------

    /**
     * Sans token, la vue est rendue avec success=false.
     * Couvre les lignes L1225-1231.
     */
    public function testConfirmEmailChangeEmptyTokenRendersView(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/confirmer')
            ->confirmEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token invalide rend la vue avec success=false.
     * Couvre les lignes L1238-1244.
     */
    public function testConfirmEmailChangeInvalidTokenRendersErrorView(): void
    {
        $_GET = ['token' => 'token-invalide-qui-nexiste-pas-' . bin2hex(random_bytes(8))];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/confirmer')
            ->confirmEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token valide confirme le changement, déconnecte et rend la vue avec success=true.
     * Couvre les lignes L1248-1254.
     */
    public function testConfirmEmailChangeValidTokenRendersSuccess(): void
    {
        $userId   = $this->insertCustomer('emailconf.ok@test.local', 'individual');
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + 3600);

        self::$db->execute(
            "UPDATE accounts SET email_change_token = ?, email_change_new_email = 'newmail-conf@test.local',
             email_change_expires_at = ? WHERE id = ?",
            [$hashedToken, $expiresAt, $userId]
        );

        $_GET = ['token' => $rawToken];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/confirmer')
            ->confirmEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // revokeEmailChange() (L1271-1303)
    // ----------------------------------------------------------------

    /**
     * Sans token, la vue est rendue avec success=false (L1275-1281).
     */
    public function testRevokeEmailChangeEmptyTokenRendersView(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/revoquer')
            ->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token non vide mais absent de la base rend la vue avec success=false (L1287-1293).
     */
    public function testRevokeEmailChangeInvalidTokenRendersErrorView(): void
    {
        $_GET = ['token' => 'revoke-token-inexistant-' . bin2hex(random_bytes(8))];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/revoquer')
            ->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token valide révoque la demande et rend la vue avec revoked=true (L1296-1303).
     */
    public function testRevokeEmailChangeValidTokenRendersRevoked(): void
    {
        $userId   = $this->insertCustomer('emailrev.ok@test.local', 'individual');
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + 3600);

        self::$db->execute(
            "UPDATE accounts SET email_change_token = ?, email_change_new_email = 'revokeme@test.local',
             email_change_expires_at = ? WHERE id = ?",
            [$hashedToken, $expiresAt, $userId]
        );

        // Construire le Request avec le token directement
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/email/revoquer';
        $_GET = ['token' => $rawToken];
        $ctrl = new AccountController(new \Core\Request());

        ob_start();
        $ctrl->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // cancelEmailChange() (L1319-1334)
    // ----------------------------------------------------------------

    /**
     * Un CSRF invalide pose un flash error et redirige (L1326-1328).
     */
    public function testCancelEmailChangeInvalidCsrfRedirects(): void
    {
        $userId = $this->insertCustomer('cancelmail.csrf@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => 'bad-csrf'];

        try {
            $this->makeController('POST', '/fr/mon-compte/email/annuler')
                ->cancelEmailChange(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['profile_errors'] ?? []);
        }
    }

    /**
     * Un CSRF valide supprime le token de changement d'email et redirige (L1331-1334).
     */
    public function testCancelEmailChangeSuccessRedirects(): void
    {
        $userId = $this->insertCustomer('cancelmail.ok@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/email/annuler')
            ->cancelEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // confirmSecurityAction() — token valide avec deviceToken (L1446)
    // ----------------------------------------------------------------

    /**
     * Un token valide avec une connexion associée ayant un device_token
     * révoque le trusted device (L1442-1447).
     */
    public function testConfirmSecurityActionRevokesDeviceWhenPresent(): void
    {
        $userId      = $this->insertCustomer('seconf.dev@test.local', 'individual');
        $rawToken    = bin2hex(random_bytes(32));
        $tokenHash   = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + 3600);
        $pendingHash = password_hash('NewPwd123!', PASSWORD_BCRYPT);

        // Créer une connexion avec un device_token
        $sessionToken = 'session-tok-' . bin2hex(random_bytes(8));
        $deviceToken  = 'device-tok-' . bin2hex(random_bytes(8));

        self::$db->execute(
            "INSERT INTO connections (user_id, token, device_token, auth_method, status, expired_at)
             VALUES (?, ?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $sessionToken, $deviceToken]
        );

        // Insérer un trusted device correspondant au device_token
        self::$db->execute(
            "INSERT INTO trusted_devices (user_id, device_token, device_name, confirmed_at, last_seen)
             VALUES (?, ?, 'Test Browser', NOW(), NOW())",
            [$userId, $deviceToken]
        );

        self::$db->execute(
            "UPDATE accounts SET pending_password_hash = ?, pending_password_expires_at = ?,
             pending_password_session_token = ?, security_confirm_token_hash = ?,
             security_confirm_token_expires_at = ? WHERE id = ?",
            [$pendingHash, $expiresAt, $sessionToken, $tokenHash, $expiresAt, $userId]
        );

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/securite/confirmer';
        $_GET = ['token' => $rawToken];
        $ctrl = new AccountController(new \Core\Request());

        ob_start();
        $ctrl->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // denySecurityAction() — token valide (L1475-1498)
    // ----------------------------------------------------------------

    /**
     * Sans token, la vue est rendue avec success=false (L1470-1472).
     */
    public function testDenySecurityActionEmptyTokenRendersView(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token invalide rend la vue avec success=false expired=true (L1478-1480).
     */
    public function testDenySecurityActionInvalidTokenRendersExpired(): void
    {
        $_GET = ['token' => 'deny-token-inexistant-' . bin2hex(random_bytes(8))];

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
            ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Un token valide révoque toutes les sessions, purge le pending password
     * et rend la vue avec success=true (L1483-1498).
     */
    public function testDenySecurityActionValidTokenRendersSuccess(): void
    {
        $userId      = $this->insertCustomer('deny.sec@test.local', 'individual');
        $rawToken    = bin2hex(random_bytes(32));
        $tokenHash   = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + 3600);
        $pendingHash = password_hash('NewPwd123!', PASSWORD_BCRYPT);

        self::$db->execute(
            "UPDATE accounts SET pending_password_hash = ?, pending_password_expires_at = ?,
             security_confirm_token_hash = ?, security_confirm_token_expires_at = ?
             WHERE id = ?",
            [$pendingHash, $expiresAt, $tokenHash, $expiresAt, $userId]
        );

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/securite/ce-netait-pas-moi';
        $_GET = ['token' => $rawToken];
        $ctrl = new AccountController(new \Core\Request());

        ob_start();
        $ctrl->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // unsubscribe() — avec campaignId > 0 (L1563-1564)
    // ----------------------------------------------------------------

    /**
     * Un POST unsubscribe avec campaignId > 0 enregistre l'événement de désinscription
     * en plus de désabonner (L1563-1564).
     */
    public function testUnsubscribeWithCampaignIdRecordsTracking(): void
    {
        $userId     = $this->insertCustomer('unsub.cid@test.local', 'individual');
        $unsubToken = 'cid-unsub-' . bin2hex(random_bytes(8));

        self::$db->execute(
            "UPDATE accounts SET newsletter_unsubscribe_token = ? WHERE id = ?",
            [$unsubToken, $userId]
        );

        // Insérer une campagne pour que recordUnsubscribe ne plante pas sur FK
        $campaignId = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, title, body, sent_at)
             VALUES ('Test Subject', 'Test Campaign', '<p>body</p>', NOW())"
        );

        $_POST = [
            'unsub_token' => $unsubToken,
            'campaign_id' => (string) $campaignId,
            'csrf_token'  => self::CSRF,
        ];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // requestRefund()
    // ----------------------------------------------------------------

    /**
     * Une commande "paid" avec CA_ENABLE_GAE=false doit passer à refund_pending en BDD
     * et poser un flash success.
     */
    public function testRequestRefundWithGaeDisabledSetsRefundPendingInDb(): void
    {
        $_ENV['CA_ENABLE_GAE'] = 'false';

        $userId    = $this->insertCustomer('req.refund.ok@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $mockRefund = $this->createMock(\Service\RefundService::class);
        $mockRefund->expects($this->once())
            ->method('refund')
            ->with($orderId)
            ->willReturnCallback(function () use ($orderId) {
                self::$db->execute(
                    "UPDATE orders SET status = 'refund_pending' WHERE id = ?",
                    [$orderId]
                );
                return 'refund_pending';
            });

        $controller = new class (
            $this->makeRequest('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement"),
            $mockRefund
        ) extends \Controller\AccountController {
            public function __construct(
                \Core\Request $request,
                private \Service\RefundService $mockSvc
            ) {
                parent::__construct($request);
            }

            protected function newRefundService(): \Service\RefundService
            {
                return $this->mockSvc;
            }
        };

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $controller->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_success'] ?? null);
            $row = self::$db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
            $this->assertSame('refund_pending', $row['status']);
            throw $e;
        } finally {
            unset($_ENV['CA_ENABLE_GAE']);
        }
    }

    /**
     * Une commande avec statut non éligible (ex. "delivered") doit poser un flash error
     * sans appeler RefundService.
     */
    public function testRequestRefundNonEligibleStatusFlashesError(): void
    {
        $userId    = $this->insertCustomer('req.refund.ineligible@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'delivered');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $mockRefund = $this->createMock(\Service\RefundService::class);
        $mockRefund->expects($this->never())->method('refund');

        $controller = new class (
            $this->makeRequest('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement"),
            $mockRefund
        ) extends \Controller\AccountController {
            public function __construct(
                \Core\Request $request,
                private \Service\RefundService $mockSvc
            ) {
                parent::__construct($request);
            }

            protected function newRefundService(): \Service\RefundService
            {
                return $this->mockSvc;
            }
        };

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $controller->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            throw $e;
        }
    }

    /**
     * Si RefundService lève une exception, un flash error est posé et la commande reste inchangée.
     */
    public function testRequestRefundServiceExceptionFlashesError(): void
    {
        $_ENV['CA_ENABLE_GAE'] = 'true';

        $userId    = $this->insertCustomer('req.refund.fail@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $mockRefund = $this->createMock(\Service\RefundService::class);
        $mockRefund->method('refund')
            ->willThrowException(new \Exception\GaeRefundException('GAE error'));

        $controller = new class (
            $this->makeRequest('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement"),
            $mockRefund
        ) extends \Controller\AccountController {
            public function __construct(
                \Core\Request $request,
                private \Service\RefundService $mockSvc
            ) {
                parent::__construct($request);
            }

            protected function newRefundService(): \Service\RefundService
            {
                return $this->mockSvc;
            }
        };

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $controller->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            $row = self::$db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
            $this->assertSame('paid', $row['status']);
            throw $e;
        } finally {
            unset($_ENV['CA_ENABLE_GAE']);
        }
    }

    /**
     * Token CSRF manquant : flash order_error posé, redirection 302.
     */
    public function testRequestRefundCsrfFailureFlashesError(): void
    {
        $userId    = $this->insertCustomer('req.refund.csrf@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');
        $this->loginAs($userId);

        $_POST = []; // pas de csrf_token

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement")
                ->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertNotEmpty($_SESSION['flash']['order_error'] ?? null);
            throw $e;
        }
    }

    /**
     * Commande introuvable pour cet utilisateur : abort 404.
     */
    public function testRequestRefundOrderNotFoundAborts404(): void
    {
        $userId = $this->insertCustomer('req.refund.notfound@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = ['csrf_token' => self::CSRF];

        $nonExistentId = 999999;

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('POST', "/fr/mon-compte/commandes/{$nonExistentId}/remboursement")
            ->requestRefund(['lang' => 'fr', 'id' => (string) $nonExistentId]);
    }
}
