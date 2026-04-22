<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountProfileController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\FavoriteModel;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour AccountProfileController.
 * Utilise TestableAccountProfileController pour isoler view/requireCustomer/verifyCsrf.
 * Les redirections passant par Response::redirect() lèvent HttpException — on les capture.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountProfileControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbStub);

        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_ENV['APP_URL'] = 'https://example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/profil';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un contrôleur testable avec un UserRepository mocké.
     *
     * @param UserRepository|null $userRepo
     * @return TestableAccountProfileController
     */
    private function makeController(?UserRepository $userRepo = null): TestableAccountProfileController
    {
        return new TestableAccountProfileController(new Request(), $userRepo);
    }

    /**
     * Injecte des mocks FavoriteModel / OrderModel via réflexion.
     *
     * @param TestableAccountProfileController $ctrl
     * @param FavoriteModel|null               $favorites
     * @param OrderModel|null                  $orders
     */
    private function injectModelMocks(
        TestableAccountProfileController $ctrl,
        ?FavoriteModel $favorites = null,
        ?OrderModel $orders = null
    ): void {
        if ($favorites !== null) {
            $ref = new \ReflectionProperty(AccountProfileController::class, 'favorites');
            $ref->setValue($ctrl, $favorites);
        }
        if ($orders !== null) {
            $ref = new \ReflectionProperty(AccountProfileController::class, 'orders');
            $ref->setValue($ctrl, $orders);
        }
    }

    // ----------------------------------------------------------------
    // maskEmail() — méthode statique pure
    // ----------------------------------------------------------------

    /**
     * Email normal → masque la partie locale sauf première lettre.
     */
    public function testMaskEmailNormal(): void
    {
        $this->assertSame('t***@example.com', AccountProfileController::maskEmail('test@example.com'));
    }

    /**
     * Email sans @ → tout remplacé par des étoiles.
     */
    public function testMaskEmailWithoutAt(): void
    {
        $result = AccountProfileController::maskEmail('notemail');
        $this->assertSame('********', $result);
    }

    /**
     * Email vide → chaîne vide.
     */
    public function testMaskEmailEmptyString(): void
    {
        $this->assertSame('', AccountProfileController::maskEmail(''));
    }

    /**
     * @ en position 0 → tout remplacé par des étoiles.
     */
    public function testMaskEmailAtPositionZero(): void
    {
        $result = AccountProfileController::maskEmail('@example.com');
        $this->assertSame('************', $result);
    }

    /**
     * Email d'une seule lettre + @ → masque complet.
     */
    public function testMaskEmailSingleCharBeforeAt(): void
    {
        $result = AccountProfileController::maskEmail('a@b.com');
        $this->assertSame('a***@b.com', $result);
    }

    // ----------------------------------------------------------------
    // index() — tableau de bord
    // ----------------------------------------------------------------

    /**
     * index() avec compte individual rend la vue account/index.
     */
    public function testIndexRendersViewForIndividualAccount(): void
    {
        $account = ['id' => 1, 'account_type' => 'individual', 'email' => 'u@example.com'];

        $userRepo  = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(3);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(2);

        $ctrl = $this->makeController($userRepo);
        $this->injectModelMocks($ctrl, $favorites, $orders);

        $ctrl->index(['lang' => 'fr']);

        $this->assertSame('account/index', $ctrl->lastView);
        $this->assertSame(3, $ctrl->lastViewData['favoriteCount'] ?? null);
        $this->assertSame(2, $ctrl->lastViewData['orderCount'] ?? null);
        $this->assertFalse($ctrl->lastViewData['isCompany'] ?? true);
    }

    /**
     * index() avec compte company → orderCount = 0 et addressCount = 0.
     */
    public function testIndexRendersViewForCompanyAccount(): void
    {
        $account = ['id' => 2, 'account_type' => 'company', 'email' => 'company@example.com'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(1);

        $ctrl = $this->makeController($userRepo);
        $this->injectModelMocks($ctrl, $favorites);

        $ctrl->index(['lang' => 'fr']);

        $this->assertSame('account/index', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['isCompany'] ?? false);
        $this->assertSame(0, $ctrl->lastViewData['orderCount'] ?? -1);
        $this->assertSame(0, $ctrl->lastViewData['addressCount'] ?? -1);
    }

    /**
     * index() lit et efface le flash 'info'.
     */
    public function testIndexPassesFlashInfoToViewAndClearsIt(): void
    {
        $_SESSION['flash']['info'] = 'Profile saved';

        $account = ['id' => 1, 'account_type' => 'individual', 'email' => 'u@example.com'];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(0);
        $orders    = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(0);

        $ctrl = $this->makeController($userRepo);
        $this->injectModelMocks($ctrl, $favorites, $orders);

        $ctrl->index(['lang' => 'fr']);

        $this->assertSame('Profile saved', $ctrl->lastViewData['info'] ?? null);
        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // profile() — affichage
    // ----------------------------------------------------------------

    /**
     * profile() rend la vue account/profile avec les flashes lus et effacés.
     */
    public function testProfileRendersViewAndClearsFlash(): void
    {
        $_SESSION['flash']['profile_success']   = 'Saved!';
        $_SESSION['flash']['profile_errors']    = ['firstname' => 'Required'];
        $_SESSION['flash']['email_change_sent'] = true;
        $_SESSION['csrf'] = 'tok123';

        $account = ['id' => 1, 'email' => 'u@example.com'];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);
        $ctrl->profile(['lang' => 'fr']);

        $this->assertSame('account/profile', $ctrl->lastView);
        $this->assertSame('Saved!', $ctrl->lastViewData['success'] ?? null);
        $this->assertSame(['firstname' => 'Required'], $ctrl->lastViewData['errors'] ?? null);
        $this->assertTrue($ctrl->lastViewData['emailChangeSent'] ?? false);

        $this->assertArrayNotHasKey('profile_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('profile_errors', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('email_change_sent', $_SESSION['flash'] ?? []);
    }

    /**
     * profile() sans flash → defaults corrects.
     */
    public function testProfileRendersViewWithDefaultsWhenNoFlash(): void
    {
        $account = ['id' => 1, 'email' => 'u@example.com'];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);
        $ctrl->profile(['lang' => 'fr']);

        $this->assertSame('account/profile', $ctrl->lastView);
        $this->assertNull($ctrl->lastViewData['success'] ?? null);
        $this->assertSame([], $ctrl->lastViewData['errors'] ?? null);
        $this->assertFalse($ctrl->lastViewData['emailChangeSent'] ?? true);
    }

    // ----------------------------------------------------------------
    // updateProfile() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * updateProfile() avec CSRF invalide → redirect avec flash erreur csrf.
     */
    public function testUpdateProfileWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateProfile() — individual validation errors
    // ----------------------------------------------------------------

    /**
     * updateProfile() individual : firstname vide → flash erreur + redirect.
     */
    public function testUpdateProfileIndividualWithEmptyFirstnameRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['firstname' => '', 'lastname' => 'Dupont', 'newsletter' => '0'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => false];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('firstname', $_SESSION['flash']['profile_errors'] ?? []);
    }

    /**
     * updateProfile() individual : lastname vide → flash erreur + redirect.
     */
    public function testUpdateProfileIndividualWithEmptyLastnameRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['firstname' => 'Jean', 'lastname' => '', 'newsletter' => '0'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => false];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateProfile() — company validation errors
    // ----------------------------------------------------------------

    /**
     * updateProfile() company : company_name vide → flash erreur + redirect.
     */
    public function testUpdateProfileCompanyWithEmptyNameRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['company_name' => '', 'siret' => '', 'newsletter' => '0'];

        $account  = ['id' => 2, 'account_type' => 'company', 'newsletter' => false];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() company valide → updateCompanyProfile appelé + redirect succès.
     */
    public function testUpdateProfileCompanyValidCallsUpdateAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['company_name' => 'ACME', 'siret' => '12345678901234', 'newsletter' => '0'];

        $account  = ['id' => 2, 'account_type' => 'company', 'newsletter' => false, 'lang' => 'fr'];

        // createStub pour findById (pas d'expects), puis expectations séparées via createMock
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account); // stub-style call
        $userRepo->expects($this->once())->method('updateCompanyProfile'); // only assertion

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() individual valide → updateIndividualProfile appelé + redirect succès.
     */
    public function testUpdateProfileIndividualValidCallsUpdateAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => '0'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => false, 'lang' => 'fr'];
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->expects($this->once())->method('updateIndividualProfile');

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() newsletter 1→0 → updateNewsletter(false) appelé.
     */
    public function testUpdateProfileNewsletterToggleOffCallsUpdateNewsletter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => '0'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => true, 'lang' => 'fr'];
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->method('updateIndividualProfile');
        $userRepo->expects($this->once())->method('updateNewsletter')->with(1, false);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateLangPreference() — AJAX
    // ----------------------------------------------------------------

    /**
     * updateLangPreference() avec CSRF invalide → 403 JSON.
     */
    public function testUpdateLangPreferenceWithInvalidCsrfReturns403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'en'];

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertFalse($decoded['success'] ?? true);
        $this->assertSame('csrf', $decoded['error'] ?? null);
    }

    /**
     * updateLangPreference() avec langue invalide → 422 JSON.
     */
    public function testUpdateLangPreferenceWithInvalidLangReturns422(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'de'];

        $ctrl = $this->makeController();

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertFalse($decoded['success'] ?? true);
        $this->assertSame('invalid_lang', $decoded['error'] ?? null);
    }

    /**
     * updateLangPreference() valide sans redirect_to → JSON success.
     */
    public function testUpdateLangPreferenceValidWithoutRedirectReturnsJsonSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'en'];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('updateLang')->with(1, 'en');

        $ctrl = $this->makeController($userRepo);

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success'] ?? false);
    }

    /**
     * updateLangPreference() valide avec redirect_to valide → redirect.
     */
    public function testUpdateLangPreferenceValidWithRedirectToRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'en', 'redirect_to' => '/en/vins'];

        $userRepo = $this->createStub(UserRepository::class);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateLangPreference(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateLangPreference() avec redirect_to non valide → JSON success (pas de redirect).
     */
    public function testUpdateLangPreferenceWithInvalidRedirectToReturnsJson(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'fr', 'redirect_to' => 'http://evil.com/phishing'];

        $userRepo = $this->createStub(UserRepository::class);

        $ctrl = $this->makeController($userRepo);

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // confirmEmailChange()
    // ----------------------------------------------------------------

    /**
     * confirmEmailChange() sans token → vue avec success=false et erreur token_invalid.
     */
    public function testConfirmEmailChangeWithEmptyTokenRendersError(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->confirmEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
        $this->assertNotEmpty($ctrl->lastViewData['error'] ?? '');
    }

    /**
     * confirmEmailChange() avec token absent → vue avec success=false.
     */
    public function testConfirmEmailChangeWithMissingTokenRendersError(): void
    {
        $_GET = [];

        $ctrl = $this->makeController();
        $ctrl->confirmEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    // ----------------------------------------------------------------
    // revokeEmailChange()
    // ----------------------------------------------------------------

    /**
     * revokeEmailChange() sans token → vue avec success=false.
     */
    public function testRevokeEmailChangeWithEmptyTokenRendersError(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * revokeEmailChange() avec token introuvable → vue avec success=false.
     */
    public function testRevokeEmailChangeWithUnknownTokenRendersError(): void
    {
        $_GET = ['token' => 'unknowntokenvalue'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findByEmailChangeToken')->willReturn(false);

        $ctrl = $this->makeController($userRepo);
        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * revokeEmailChange() avec token valide → clearEmailChangeToken appelé + vue success.
     */
    public function testRevokeEmailChangeWithValidTokenClearsAndRendersSuccess(): void
    {
        $_GET = ['token' => 'validrawtoken'];

        $account  = ['id' => 42, 'email' => 'u@example.com'];
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmailChangeToken')->willReturn($account);
        $userRepo->expects($this->once())->method('clearEmailChangeToken')->with(42);

        $ctrl = $this->makeController($userRepo);
        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
        $this->assertTrue($ctrl->lastViewData['revoked'] ?? false);
    }

    // ----------------------------------------------------------------
    // cancelEmailChange()
    // ----------------------------------------------------------------

    /**
     * cancelEmailChange() avec CSRF invalide → redirect avec flash erreur.
     */
    public function testCancelEmailChangeWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelEmailChange() valide → clearEmailChangeToken appelé + redirect.
     */
    public function testCancelEmailChangeCallsClearTokenAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('clearEmailChangeToken')->with(1);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — branches sans MailService (CSRF/validation)
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() avec CSRF invalide → redirect avec flash erreur.
     */
    public function testRequestEmailChangeWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_email' => 'new@example.com', 'current_password' => 'pass'];

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestEmailChange() avec email invalide → redirect avec flash erreur email.
     */
    public function testRequestEmailChangeWithInvalidEmailRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_email' => 'not-an-email', 'current_password' => 'pass'];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }
}
