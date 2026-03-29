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
        return (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_address, status)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, ?)",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $addressId, $status]
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
        return (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_address, status, delivered_at)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, 'delivered', {$deliveredAt})",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $addressId]
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
        $this->expectExceptionCode(302);
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
     * Commande 'processing' → le message droit de rétractation après livraison est affiché.
     *
     * @return void
     */
    public function testOrderDetailProcessingShowsReturnAfterDeliveryMessage(): void
    {
        $userId    = $this->insertCustomer('odtl.proc@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
            ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('account.order_return_after_delivery', $output);
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
     * Un compte avec des commandes actives ne peut pas être supprimé.
     */
    public function testDeleteAccountBlockedByActiveOrders(): void
    {
        $userId    = $this->insertCustomer('del.blocked@test.local', 'individual');
        $addressId = $this->insertAddress($userId);
        $this->insertOrder($userId, $addressId, 'processing');
        $this->loginAs($userId);

        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
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
    // exportData() — guard seulement (exit dans la branche authentifiée)
    // ----------------------------------------------------------------

    /**
     * Un utilisateur non authentifié est redirigé vers /connexion.
     */
    public function testExportDataUnauthenticatedRedirects(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('GET', '/fr/mon-compte/export/telecharger')
            ->exportData(['lang' => 'fr']);
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
    public function testUpdateAddressBlockedByActiveOrderRedirects(): void
    {
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
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
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
    public function testDeleteAddressBlockedByActiveOrderRedirects(): void
    {
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
            $this->assertNotEmpty($_SESSION['flash']['address_error'] ?? '');
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
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_address, status, delivered_at)
             VALUES (?, ?, ?, 99.90, 'card', 0.00, ?, 'delivered', NOW() - INTERVAL 3 DAY)",
            [
                $userId,
                'TEST-' . bin2hex(random_bytes(4)),
                json_encode([['label_name' => 'Bordeaux Rouge', 'format' => 'bottle', 'qty' => 2, 'price' => 24.00]]),
                $addressId,
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

        $orderId = (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_address, status)
             VALUES (?, ?, '[]', 120.00, 'card', 5.90, ?, 'shipped')",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $addressId]
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
        $orderId   = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_address, status, delivered_at)
             VALUES (?, ?, ?, 99.90, 'card', 0.00, ?, 'return_requested', NOW() - INTERVAL 5 DAY)",
            [
                $userId,
                'TEST-' . bin2hex(random_bytes(4)),
                json_encode([['label_name' => 'Sauternes', 'format' => 'bottle', 'qty' => 1, 'price' => 35.00]]),
                $addressId,
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
     * Un POST updateProfile avec newsletter='1' sauvegarde bien l'abonnement.
     */
    public function testUpdateProfileIndividualNewsletterEnabled(): void
    {
        $userId = $this->insertCustomer('upd.news@test.local', 'individual');
        $this->loginAs($userId);

        $_POST = [
            'firstname'  => 'Alice',
            'lastname'   => 'Martin',
            'civility'   => 'F',
            'newsletter' => '1',
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
     * Un compte avec des commandes 'paid' (active) ne peut pas être supprimé.
     */
    public function testDeleteAccountBlockedByPaidOrderRedirects(): void
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

        try {
            $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
                ->deleteAccount(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertNotEmpty($_SESSION['flash']['security_errors'] ?? []);
        }
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

    // Note : exportData() authentifié — non testable sans seam
    // -----------------------------------------------------------
    // exportData() se termine par `echo $content; exit;` sans méthode extractable.
    // Le guard non-authentifié est couvert par testExportDataUnauthenticatedRedirects().
    // Pour couvrir le chemin authentifié, extraire la logique dans une méthode protected
    // buildExportResponse() (ticket audit-genie-logiciel — refactoring mineur).

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

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
        $ref->setAccessible(true);

        $errors = $ref->invoke($ctrl, 999999, '', null);

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('company_name', $errors);
        $this->assertNotEmpty($errors['company_name']);
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
        $ref->setAccessible(true);

        $errors = $ref->invoke($ctrl, $userId, 'Nouvelle Société SAS', '98765432109876');

        $this->assertIsArray($errors);
        $this->assertSame([], $errors);
    }
}
