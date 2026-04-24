<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Database;
use Core\Request;
use Model\AccountModel;
use Model\RatingModel;
use Model\ReviewModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour ReviewController.
 * Utilise une sous-classe de test pour capturer redirects/views/aborts sans rendu réel.
 */
#[AllowMockObjectsWithoutExpectations]
class ReviewControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private Database $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-avis';
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

    private function makeController(): TestableReviewController
    {
        return new TestableReviewController(new Request());
    }

    /**
     * Injecte des mocks de modèle dans le controller via réflexion.
     *
     * @param TestableReviewController $ctrl
     * @param RatingModel|null         $ratingModel
     * @param ReviewModel|null         $reviewModel
     * @param AccountModel|null        $accountModel
     */
    private function injectMocks(
        TestableReviewController $ctrl,
        ?RatingModel $ratingModel = null,
        ?ReviewModel $reviewModel = null,
        ?AccountModel $accountModel = null
    ): void {
        if ($ratingModel !== null) {
            $ref = new \ReflectionProperty(\Controller\ReviewController::class, 'ratingModel');
            $ref->setValue($ctrl, $ratingModel);
        }
        if ($reviewModel !== null) {
            $ref = new \ReflectionProperty(\Controller\ReviewController::class, 'reviewModel');
            $ref->setValue($ctrl, $reviewModel);
        }
        if ($accountModel !== null) {
            $ref = new \ReflectionProperty(\Controller\ReviewController::class, 'accountModel');
            $ref->setValue($ctrl, $accountModel);
        }
    }

    // ----------------------------------------------------------------
    // show() — token vide
    // ----------------------------------------------------------------

    /**
     * show() avec token vide → vue review-form avec error et form=false.
     */
    public function testShowWithEmptyTokenRendersErrorView(): void
    {
        $_GET['token'] = '';

        $ctrl = $this->makeController();
        $ctrl->show(['lang' => 'fr']);

        $this->assertSame('review-form', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['form'] ?? true);
    }

    // ----------------------------------------------------------------
    // show() — token invalide (resolveOrderToken null)
    // ----------------------------------------------------------------

    /**
     * show() avec token inconnu → vue review-form avec form=false.
     */
    public function testShowWithInvalidTokenRendersErrorView(): void
    {
        $_GET['token'] = 'unknowntoken';

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(null);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock);
        $ctrl->show(['lang' => 'fr']);

        $this->assertSame('review-form', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['form'] ?? true);
    }

    // ----------------------------------------------------------------
    // show() — token expiré
    // ----------------------------------------------------------------

    /**
     * show() avec token expiré → vue review-form avec error expired_token.
     */
    public function testShowWithExpiredTokenRendersExpiredError(): void
    {
        $_GET['token'] = 'expiredtoken';

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 2]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 2,
            'expires_at' => '2020-01-01 00:00:00', // dans le passé
        ]);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock);
        $ctrl->show(['lang' => 'fr']);

        $this->assertSame('review-form', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['form'] ?? true);
    }

    // ----------------------------------------------------------------
    // show() — avis déjà soumis
    // ----------------------------------------------------------------

    /**
     * show() quand un avis existe déjà → vue review-already-done.
     */
    public function testShowWhenReviewAlreadyExistsRendersAlreadyDoneView(): void
    {
        $_GET['token'] = 'validtoken';

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 3]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 3,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $reviewMock = $this->createStub(ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn(['id' => 99, 'rating' => 5]);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock, reviewModel: $reviewMock);
        $ctrl->show(['lang' => 'fr']);

        $this->assertSame('review-already-done', $ctrl->lastView);
    }

    // ----------------------------------------------------------------
    // show() — token valide, pas d'avis
    // ----------------------------------------------------------------

    /**
     * show() avec token valide et aucun avis → vue review-form avec form=true.
     */
    public function testShowWithValidTokenAndNoReviewRendersForm(): void
    {
        $_GET['token'] = 'validtoken';

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 5]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 5,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $reviewMock = $this->createStub(ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn(null);

        $accountMock = $this->createStub(AccountModel::class);
        $accountMock->method('findById')->willReturn(['firstname' => 'Sophie', 'lastname' => 'Dupont']);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock, reviewModel: $reviewMock, accountModel: $accountMock);
        $ctrl->show(['lang' => 'fr']);

        $this->assertSame('review-form', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['form'] ?? false);
    }

    // ----------------------------------------------------------------
    // submit() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * submit() avec CSRF invalide → abort(403).
     */
    public function testSubmitWithInvalidCsrfAborts403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'wrongcsrf', 'token' => 'sometoken'];

        $ctrl = $this->makeController();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abort:403');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // submit() — token vide
    // ----------------------------------------------------------------

    /**
     * submit() avec token vide → abort(400).
     */
    public function testSubmitWithEmptyTokenAborts400(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'validcsrf', 'token' => ''];

        $ctrl = $this->makeController();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abort:400');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // submit() — token invalide (resolveOrderToken null)
    // ----------------------------------------------------------------

    /**
     * submit() avec token inconnu → abort(404).
     */
    public function testSubmitWithUnknownTokenAborts404(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'validcsrf', 'token' => 'unknowntoken'];

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(null);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abort:404');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // submit() — token expiré (ligne 154)
    // ----------------------------------------------------------------

    /**
     * submit() avec token valide mais expiré → abort(410) (ligne 154).
     *
     * resolveOrderToken retourne un résultat valide mais resolveOrderTokenFull
     * retourne un tokenRow avec expires_at dans le passé.
     */
    public function testSubmitWithExpiredTokenAborts410(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'validcsrf', 'token' => 'expiredtoken'];

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 2]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 2,
            'expires_at' => '2020-01-01 00:00:00', // dans le passé
        ]);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abort:410');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // submit() — déjà évalué
    // ----------------------------------------------------------------

    /**
     * submit() quand un avis existe déjà → vue review-already-done.
     */
    public function testSubmitWhenAlreadyReviewedRendersAlreadyDoneView(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'validcsrf', 'token' => 'validtoken'];

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 3]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 3,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $reviewMock = $this->createStub(ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn(['id' => 10]);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock, reviewModel: $reviewMock);
        $ctrl->submit(['lang' => 'fr']);

        $this->assertSame('review-already-done', $ctrl->lastView);
    }

    // ----------------------------------------------------------------
    // submit() — rating invalide
    // ----------------------------------------------------------------

    /**
     * submit() avec rating = 0 → abort(422).
     */
    public function testSubmitWithInvalidRatingAborts422(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = ['csrf_token' => 'validcsrf', 'token' => 'validtoken', 'rating' => '0'];

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 5]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 5,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $reviewMock = $this->createStub(ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn(null);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock, reviewModel: $reviewMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abort:422');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // submit() — nominal
    // ----------------------------------------------------------------

    /**
     * submit() avec données valides → create() appelé + redirect.
     */
    public function testSubmitWithValidDataCreatesReviewAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'validcsrf';
        $_POST = [
            'csrf_token' => 'validcsrf',
            'token'      => 'validtoken',
            'rating'     => '5',
            'comment'    => 'Excellent !',
            'location'   => 'Paris',
        ];

        $ratingMock = $this->createStub(RatingModel::class);
        $ratingMock->method('resolveOrderToken')->willReturn(['order_id' => 1, 'account_id' => 5]);
        $ratingMock->method('resolveOrderTokenFull')->willReturn([
            'order_id'   => 1,
            'account_id' => 5,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $reviewMock = $this->createMock(ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn(null);
        $reviewMock->expects($this->once())->method('create');

        $accountMock = $this->createStub(AccountModel::class);
        $accountMock->method('findById')->willReturn(['firstname' => 'Sophie', 'lastname' => 'Dupont']);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratingModel: $ratingMock, reviewModel: $reviewMock, accountModel: $accountMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+/');

        $ctrl->submit(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // success()
    // ----------------------------------------------------------------

    /**
     * success() affiche la vue review-success.
     */
    public function testSuccessRendersSuccessView(): void
    {
        $ctrl = $this->makeController();
        $ctrl->success(['lang' => 'fr']);

        $this->assertSame('review-success', $ctrl->lastView);
    }

    // ----------------------------------------------------------------
    // buildDisplayName() — via réflexion
    // ----------------------------------------------------------------

    private function callBuildDisplayName(TestableReviewController $ctrl, mixed $account): string
    {
        $ref = new \ReflectionMethod(\Controller\ReviewController::class, 'buildDisplayName');
        return $ref->invoke($ctrl, $account);
    }

    /**
     * buildDisplayName avec account = false → 'Client'.
     */
    public function testBuildDisplayNameWithFalseAccountReturnsClient(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Client', $this->callBuildDisplayName($ctrl, false));
    }

    /**
     * buildDisplayName avec firstname et lastname vides → 'Client'.
     */
    public function testBuildDisplayNameWithEmptyBothReturnsClient(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Client', $this->callBuildDisplayName($ctrl, ['firstname' => '', 'lastname' => '']));
    }

    /**
     * buildDisplayName avec firstname vide, lastname 'Dupont' → 'Dupont'.
     */
    public function testBuildDisplayNameWithOnlyLastnameReturnsLastname(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Dupont', $this->callBuildDisplayName($ctrl, ['firstname' => '', 'lastname' => 'Dupont']));
    }

    /**
     * buildDisplayName avec firstname 'Sophie', lastname vide → 'Sophie'.
     */
    public function testBuildDisplayNameWithOnlyFirstnameReturnsFirstname(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Sophie', $this->callBuildDisplayName($ctrl, ['firstname' => 'Sophie', 'lastname' => '']));
    }

    /**
     * buildDisplayName avec firstname 'Sophie', lastname 'Dupont' → 'Sophie D.'.
     */
    public function testBuildDisplayNameWithBothNamesReturnsFormattedName(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Sophie D.', $this->callBuildDisplayName($ctrl, ['firstname' => 'Sophie', 'lastname' => 'Dupont']));
    }
}
