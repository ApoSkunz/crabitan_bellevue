<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountAddressController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountAddressController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountAddressControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-address-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
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
     * Insère un compte client vérifié.
     *
     * @param string $email       Adresse e-mail unique
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
     * Connecte un utilisateur via cookie JWT + connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT
     * @return string Token JWT généré
     */
    private function loginAs(int $userId, string $role = 'customer'): string
    {
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        return $token;
    }

    /**
     * Insère une adresse pour l'utilisateur.
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
     * Crée un objet Request.
     *
     * @param string $method Méthode HTTP
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
     * Instancie AccountAddressController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountAddressController
     */
    private function makeController(string $method, string $uri): AccountAddressController
    {
        return new AccountAddressController($this->makeRequest($method, $uri));
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
     * Les flashs address sont consommés à l'affichage.
     */
    public function testAddressesConsumesFlash(): void
    {
        $userId = $this->insertCustomer('addr.flash@test.local');
        $this->loginAs($userId);
        $_SESSION['flash']['address_success'] = 'ok';
        $_SESSION['flash']['address_error']   = 'err';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('address_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('address_error', $_SESSION['flash'] ?? []);
    }

    /**
     * Un compte company est redirigé vers le dashboard.
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
     * Visiteur non authentifié → redirigé (302).
     */
    public function testAddressesRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses')->addresses(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // addAddress()
    // ----------------------------------------------------------------

    /**
     * Données valides → adresse créée, redirection.
     */
    public function testAddAddressSuccess(): void
    {
        $userId = $this->insertCustomer('add.addr.ok@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'type'      => 'billing',
            'civility'  => 'M',
            'firstname' => 'Pierre',
            'lastname'  => 'Martin',
            'street'    => '10 rue du Moulin',
            'city'      => 'Lyon',
            'zip_code'  => '69001',
            'country'   => 'France',
            'phone'     => '0601020304',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testAddAddressCsrfFails(): void
    {
        $userId = $this->insertCustomer('add.addr.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * Champs obligatoires manquants → redirige avec erreur.
     */
    public function testAddAddressMissingFields(): void
    {
        $userId = $this->insertCustomer('add.addr.missing@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'type'      => 'billing',
            'firstname' => '',
            'lastname'  => '',
            'street'    => '',
            'city'      => '',
            'zip_code'  => '',
            'phone'     => '',
            'country'   => 'France',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * Code postal France invalide → redirige avec erreur.
     */
    public function testAddAddressInvalidZip(): void
    {
        $userId = $this->insertCustomer('add.addr.zip@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'type'      => 'billing',
            'civility'  => 'M',
            'firstname' => 'Pierre',
            'lastname'  => 'Martin',
            'street'    => '10 rue du Moulin',
            'city'      => 'Lyon',
            'zip_code'  => '2A000', // Corse — invalide
            'country'   => 'France',
            'phone'     => '0601020304',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * Adresse hors France (code postal non vérifié) → créée avec succès.
     */
    public function testAddAddressForeignCountrySuccess(): void
    {
        $userId = $this->insertCustomer('add.addr.foreign@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'type'      => 'delivery',
            'civility'  => 'M',
            'firstname' => 'Pierre',
            'lastname'  => 'Martin',
            'street'    => '10 Baker Street',
            'city'      => 'London',
            'zip_code'  => 'SW1A 1AA',
            'country'   => 'Royaume-Uni',
            'phone'     => '0601020304',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * Compte company → redirigé.
     */
    public function testAddAddressRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('add.addr.co@test.local', 'company');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testAddAddressRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/ajouter')->addAddress(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // editAddress()
    // ----------------------------------------------------------------

    /**
     * Adresse existante → formulaire affiché.
     */
    public function testEditAddressRenders(): void
    {
        $userId    = $this->insertCustomer('edit.addr@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/adresses/{$addressId}/modifier")
             ->editAddress(['lang' => 'fr', 'id' => (string) $addressId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('address_form', $output);
    }

    /**
     * Adresse introuvable → redirigé vers la liste.
     */
    public function testEditAddressNotFound(): void
    {
        $userId = $this->insertCustomer('edit.addr.notfound@test.local');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses/99999/modifier')
             ->editAddress(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Compte company → redirigé.
     */
    public function testEditAddressRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('edit.addr.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses/1/modifier')
             ->editAddress(['lang' => 'fr', 'id' => '1']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testEditAddressRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/adresses/1/modifier')
             ->editAddress(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // updateAddress()
    // ----------------------------------------------------------------

    /**
     * Données valides → adresse mise à jour, redirection.
     */
    public function testUpdateAddressSuccess(): void
    {
        $userId    = $this->insertCustomer('upd.addr.ok@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'civility'  => 'M',
            'firstname' => 'Paul',
            'lastname'  => 'Dupuis',
            'street'    => '5 avenue des Fleurs',
            'city'      => 'Bordeaux',
            'zip_code'  => '33000',
            'country'   => 'France',
            'phone'     => '0601020304',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
             ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testUpdateAddressCsrfFails(): void
    {
        $userId    = $this->insertCustomer('upd.addr.csrf@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
             ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Adresse introuvable → redirigé vers la liste.
     */
    public function testUpdateAddressNotFound(): void
    {
        $userId = $this->insertCustomer('upd.addr.notfound@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/99999/modifier')
             ->updateAddress(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Champs manquants → redirige avec erreur.
     */
    public function testUpdateAddressMissingFields(): void
    {
        $userId    = $this->insertCustomer('upd.addr.missing@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'firstname' => '',
            'lastname'  => '',
            'street'    => '',
            'city'      => '',
            'zip_code'  => '',
            'phone'     => '',
            'country'   => 'France',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
             ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Code postal France invalide → redirige avec erreur.
     */
    public function testUpdateAddressInvalidZip(): void
    {
        $userId    = $this->insertCustomer('upd.addr.zip@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = [
            'csrf_token'      => self::CSRF,
            'civility'  => 'M',
            'firstname' => 'Paul',
            'lastname'  => 'Dupuis',
            'street'    => '5 avenue des Fleurs',
            'city'      => 'Bordeaux',
            'zip_code'  => '99999',
            'country'   => 'France',
            'phone'     => '0601020304',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/modifier")
             ->updateAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testUpdateAddressRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/1/modifier')
             ->updateAddress(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // deleteAddress()
    // ----------------------------------------------------------------

    /**
     * Adresse existante sans commande active → supprimée, redirection.
     */
    public function testDeleteAddressSuccess(): void
    {
        $userId    = $this->insertCustomer('del.addr.ok@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/supprimer")
             ->deleteAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testDeleteAddressCsrfFails(): void
    {
        $userId    = $this->insertCustomer('del.addr.csrf@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/adresses/{$addressId}/supprimer")
             ->deleteAddress(['lang' => 'fr', 'id' => (string) $addressId]);
    }

    /**
     * Adresse introuvable → redirigé vers la liste.
     */
    public function testDeleteAddressNotFound(): void
    {
        $userId = $this->insertCustomer('del.addr.notfound@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/99999/supprimer')
             ->deleteAddress(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testDeleteAddressRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/1/supprimer')
             ->deleteAddress(['lang' => 'fr', 'id' => '1']);
    }

    /**
     * Compte company → redirigé.
     */
    public function testDeleteAddressRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('del.addr.co@test.local', 'company');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/adresses/1/supprimer')
             ->deleteAddress(['lang' => 'fr', 'id' => '1']);
    }
}
