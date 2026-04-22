<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AddressModel;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;

/**
 * Tests unitaires pour AccountAddressController.
 * Utilise TestableAccountAddressController pour isoler view/requireCustomer/verifyCsrf.
 * Les redirections passant par Response::redirect() lèvent HttpException — on les capture.
 */
class AccountAddressControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'tok'];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_ENV['APP_URL']           = 'https://example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/adresses';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un contrôleur testable avec dépendances mockées.
     *
     * @param UserRepository|null $userRepo
     * @param AddressModel|null   $addresses
     * @param OrderModel|null     $orders
     * @return TestableAccountAddressController
     */
    private function makeController(
        ?UserRepository $userRepo = null,
        ?AddressModel $addresses = null,
        ?OrderModel $orders = null
    ): TestableAccountAddressController {
        $ctrl = new TestableAccountAddressController(new Request(), $userRepo);

        if ($addresses !== null) {
            $ref = new \ReflectionProperty(\Controller\Account\AccountAddressController::class, 'addresses');
            $ref->setValue($ctrl, $addresses);
        }
        if ($orders !== null) {
            $ref = new \ReflectionProperty(\Controller\Account\AccountAddressController::class, 'orders');
            $ref->setValue($ctrl, $orders);
        }

        return $ctrl;
    }

    /** @return array<string, mixed> */
    private function makeAddress(): array
    {
        return [
            'id'        => 1,
            'user_id'   => 1,
            'type'      => 'shipping',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
            'street'    => '1 rue de la Paix',
            'city'      => 'Paris',
            'zip_code'  => '75001',
            'country'   => 'France',
            'phone'     => '0601020304',
        ];
    }

    // ----------------------------------------------------------------
    // addresses() — GET list
    // ----------------------------------------------------------------

    public function testAddressesRendersViewWithAddressList(): void
    {
        $address = $this->makeAddress();

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([$address]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('getAddressIdsWithActiveOrders')->willReturn([]);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);
        $ctrl->addresses(['lang' => 'fr']);

        $this->assertSame('account/addresses', $ctrl->lastView);
        $this->assertCount(1, $ctrl->lastViewData['addresses'] ?? []);
        $this->assertSame([], $ctrl->lastViewData['lockedIds'] ?? null);
    }

    public function testAddressesRendersViewWithFlashMessages(): void
    {
        $_SESSION['flash']['address_success'] = 'Adresse ajoutée';
        $_SESSION['flash']['address_error']   = 'Erreur';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('getAddressIdsWithActiveOrders')->willReturn([]);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);
        $ctrl->addresses(['lang' => 'fr']);

        $this->assertSame('Adresse ajoutée', $ctrl->lastViewData['success'] ?? null);
        $this->assertSame('Erreur', $ctrl->lastViewData['error'] ?? null);
        $this->assertArrayNotHasKey('address_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('address_error', $_SESSION['flash'] ?? []);
    }

    public function testAddressesRedirectsForCompanyAccount(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'company']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('getAddressIdsWithActiveOrders')->willReturn([]);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addresses(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — CSRF invalide
    // ----------------------------------------------------------------

    public function testAddAddressWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — validation errors
    // ----------------------------------------------------------------

    public function testAddAddressWithEmptyFirstnameRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => '', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddAddressWithInvalidFranceZipRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '99999', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — happy path
    // ----------------------------------------------------------------

    public function testAddAddressSuccessfullyCreatesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue de la Paix', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())->method('create');

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('address_success', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // addAddress() — non-France country skips zip validation
    // ----------------------------------------------------------------

    public function testAddAddressWithForeignCountrySkipsZipValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Hans', 'lastname' => 'Mueller',
            'street' => 'Hauptstrasse 1', 'city' => 'Berlin',
            'zip_code' => 'ABCDE', 'country' => 'Allemagne', 'phone' => '+49123456',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())->method('create');

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // editAddress() — address not found
    // ----------------------------------------------------------------

    public function testEditAddressRedirectsWhenAddressNotFound(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->editAddress(['lang' => 'fr', 'id' => '99']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // editAddress() — happy path : found address
    // ----------------------------------------------------------------

    public function testEditAddressRendersFormWhenFound(): void
    {
        $address = $this->makeAddress();

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($address);

        $ctrl = $this->makeController($userRepo, $addresses);
        $ctrl->editAddress(['lang' => 'fr', 'id' => '1']);

        $this->assertSame('account/address_form', $ctrl->lastView);
        $this->assertSame($address, $ctrl->lastViewData['address'] ?? null);
    }

    // ----------------------------------------------------------------
    // updateAddress() — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateAddressWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateAddress() — address not found
    // ----------------------------------------------------------------

    public function testUpdateAddressRedirectsWhenAddressNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '99']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateAddress() — blocked by active order
    // ----------------------------------------------------------------

    public function testUpdateAddressBlockedByActiveOrderRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($this->makeAddress());

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(true);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('address_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // updateAddress() — happy path
    // ----------------------------------------------------------------

    public function testUpdateAddressSuccessfullyUpdatesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '2 rue de la Paix', 'city' => 'Lyon',
            'zip_code' => '69001', 'country' => 'France', 'phone' => '0601020305',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($this->makeAddress());
        $addresses->expects($this->once())->method('update');

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(false);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateAddress() — validation error (empty street)
    // ----------------------------------------------------------------

    public function testUpdateAddressWithEmptyStreetRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '', 'city' => 'Lyon',
            'zip_code' => '69001', 'country' => 'France', 'phone' => '0601020305',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($this->makeAddress());

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAddress() — CSRF invalide
    // ----------------------------------------------------------------

    public function testDeleteAddressWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAddress() — address not found
    // ----------------------------------------------------------------

    public function testDeleteAddressRedirectsWhenNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController($userRepo, $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '99']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAddress() — blocked by active order
    // ----------------------------------------------------------------

    public function testDeleteAddressBlockedByActiveOrderRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($this->makeAddress());

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(true);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('address_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // deleteAddress() — happy path
    // ----------------------------------------------------------------

    public function testDeleteAddressSuccessfullyDeletesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($this->makeAddress());
        $addresses->expects($this->once())->method('deleteForUser');

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(false);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // isValidFranceMetroZip — via private reflection
    // ----------------------------------------------------------------

    public function testIsValidFranceMetroZipAcceptsValidZip(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'isValidFranceMetroZip');

        $this->assertTrue($method->invoke($ctrl, '75001'));
        $this->assertTrue($method->invoke($ctrl, '13001'));
        $this->assertTrue($method->invoke($ctrl, '69001'));
    }

    public function testIsValidFranceMetroZipRejectsInvalidFormats(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'isValidFranceMetroZip');

        $this->assertFalse($method->invoke($ctrl, '7500'));      // too short
        $this->assertFalse($method->invoke($ctrl, '750011'));     // too long
        $this->assertFalse($method->invoke($ctrl, 'ABCDE'));      // non-numeric
        $this->assertFalse($method->invoke($ctrl, '20000'));      // Corse prefix 20
        $this->assertFalse($method->invoke($ctrl, '00999'));      // < 01000
        $this->assertFalse($method->invoke($ctrl, '96000'));      // > 95999
    }

    public function testIsValidFranceMetroZipRejectsCorse(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'isValidFranceMetroZip');

        $this->assertFalse($method->invoke($ctrl, '20001'));
    }

    // ----------------------------------------------------------------
    // normalizePhone — double espaces
    // ----------------------------------------------------------------

    public function testNormalizePhoneCollapseMultipleSpaces(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'normalizePhone');

        $result = $method->invoke($ctrl, '06  01  02  03');

        $this->assertSame('06 01 02 03', $result);
    }

    // ----------------------------------------------------------------
    // requireIndividual — company redirects (lines 324, 326-329)
    // ----------------------------------------------------------------

    public function testRequireIndividualRedirectsCompanyAccount(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'company']);

        $ctrl   = $this->makeController($userRepo);
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'requireIndividual');

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $method->invoke($ctrl, 1, 'fr');
        } finally {
            ob_end_clean();
        }
    }

    public function testRequireIndividualAllowsIndividualAccount(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl   = $this->makeController($userRepo);
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'requireIndividual');

        // Must not throw
        $method->invoke($ctrl, 1, 'fr');
        $this->assertTrue(true);
    }

    public function testRequireIndividualAllowsFalseAccount(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(false);

        $ctrl   = $this->makeController($userRepo);
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'requireIndividual');

        // false account → no redirect (guard only blocks company)
        $method->invoke($ctrl, 1, 'fr');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // validateAddressInput — all required fields empty
    // ----------------------------------------------------------------

    public function testValidateAddressInputRedirectsWhenAllEmpty(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\Account\AccountAddressController::class, 'validateAddressInput');
        $fields = [
            'firstname' => '', 'lastname' => '', 'street' => '',
            'city' => '', 'zipCode' => '', 'phone' => '',
            'civility' => '', 'country' => 'France',
        ];

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $method->invoke($ctrl, $fields, '/fr/mon-compte/adresses');
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — empty lastname redirects
    // ----------------------------------------------------------------

    public function testAddAddressWithEmptyLastnameRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => '',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — empty city redirects
    // ----------------------------------------------------------------

    public function testAddAddressWithEmptyCityRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => '',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addAddress() — empty phone redirects
    // ----------------------------------------------------------------

    public function testAddAddressWithEmptyPhoneRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // addresses() — locked ids passed correctly
    // ----------------------------------------------------------------

    public function testAddressesPassesLockedIdsToView(): void
    {
        $address1 = array_merge($this->makeAddress(), ['id' => 1]);
        $address2 = array_merge($this->makeAddress(), ['id' => 2]);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([$address1, $address2]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('getAddressIdsWithActiveOrders')->willReturn([1]);

        $ctrl = $this->makeController($userRepo, $addresses, $orders);
        $ctrl->addresses(['lang' => 'fr']);

        $this->assertContains(1, $ctrl->lastViewData['lockedIds'] ?? []);
    }

    // ----------------------------------------------------------------
    // addAddress() — company account redirects
    // ----------------------------------------------------------------

    public function testAddAddressCompanyAccountRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type' => 'shipping', 'civility' => 'M',
            'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue X', 'city' => 'Paris',
            'zip_code' => '75001', 'country' => 'France', 'phone' => '0601020304',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(['id' => 1, 'account_type' => 'company']);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }
}
