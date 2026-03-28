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
        $_GET = [];
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
            'civility'   => 'Mme',
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
            'civility'   => 'Mme',
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
            'civility'   => 'Mme',
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
            'civility'   => 'Mme',
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
}
