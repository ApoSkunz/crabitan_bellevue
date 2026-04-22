<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Database;
use Core\Request;
use Model\RatingModel;
use Model\ReviewModel;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour AccountRatingsController.
 * Utilise une sous-classe de test pour isoler auth/CSRF/redirect/view.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountRatingsControllerTest extends TestCase
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
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/mes-notes';
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
     * Crée un controller testable avec un UserRepository mocké.
     *
     * @param UserRepository|null $userRepo
     * @return TestableAccountRatingsController
     */
    private function makeController(?UserRepository $userRepo = null): TestableAccountRatingsController
    {
        return new TestableAccountRatingsController(new Request(), $userRepo);
    }

    /**
     * Injecte des mocks RatingModel et ReviewModel dans le controller via réflexion.
     *
     * @param TestableAccountRatingsController $ctrl
     * @param RatingModel|null                 $ratings
     * @param ReviewModel|null                 $reviews
     */
    private function injectMocks(
        TestableAccountRatingsController $ctrl,
        ?RatingModel $ratings = null,
        ?ReviewModel $reviews = null
    ): void {
        if ($ratings !== null) {
            $ref = new \ReflectionProperty(\Controller\Account\AccountRatingsController::class, 'ratings');
            $ref->setValue($ctrl, $ratings);
        }
        if ($reviews !== null) {
            $ref = new \ReflectionProperty(\Controller\Account\AccountRatingsController::class, 'reviews');
            $ref->setValue($ctrl, $reviews);
        }
    }

    // ----------------------------------------------------------------
    // ratings()
    // ----------------------------------------------------------------

    /**
     * ratings() appelle getOrderedWinesWithRating et findById → view account/ratings.
     */
    public function testRatingsRendersViewWithWinesAndAccount(): void
    {
        $wines = [
            ['wine_id' => 1, 'label_name' => 'Vin A', 'rating' => 4],
        ];
        $account = ['id' => 1, 'email' => 'test@example.com'];

        $ratingsMock = $this->createStub(RatingModel::class);
        $ratingsMock->method('getOrderedWinesWithRating')->willReturn($wines);

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, ratings: $ratingsMock);
        $ctrl->ratings(['lang' => 'fr']);

        $this->assertSame('account/ratings', $ctrl->lastView);
        $this->assertSame($wines, $ctrl->lastViewData['wines'] ?? null);
        $this->assertSame($account, $ctrl->lastViewData['account'] ?? null);
    }

    // ----------------------------------------------------------------
    // updateRating()
    // ----------------------------------------------------------------

    /**
     * updateRating() avec CSRF invalide → redirect sans upsert.
     */
    public function testUpdateRatingWithInvalidCsrfRedirectsWithoutUpsert(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['wine_id' => '1', 'rating' => '4'];

        $ratingsMock = $this->createMock(RatingModel::class);
        $ratingsMock->expects($this->never())->method('upsert');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $this->injectMocks($ctrl, ratings: $ratingsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mes-notes/');

        $ctrl->updateRating(['lang' => 'fr']);
    }

    /**
     * updateRating() avec wineId = 0 → redirect sans upsert.
     */
    public function testUpdateRatingWithWineIdZeroRedirectsWithoutUpsert(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['wine_id' => '0', 'rating' => '4'];

        $ratingsMock = $this->createMock(RatingModel::class);
        $ratingsMock->expects($this->never())->method('upsert');

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratings: $ratingsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mes-notes/');

        $ctrl->updateRating(['lang' => 'fr']);
    }

    /**
     * updateRating() avec orderId null → redirect sans upsert.
     */
    public function testUpdateRatingWithNullOrderIdRedirectsWithoutUpsert(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['wine_id' => '5', 'rating' => '3'];

        $ratingsMock = $this->createMock(RatingModel::class);
        $ratingsMock->method('getFirstOrderId')->willReturn(null);
        $ratingsMock->expects($this->never())->method('upsert');

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratings: $ratingsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mes-notes/');

        $ctrl->updateRating(['lang' => 'fr']);
    }

    /**
     * updateRating() avec rating = 0 → redirect sans upsert.
     */
    public function testUpdateRatingWithInvalidRatingRedirectsWithoutUpsert(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['wine_id' => '5', 'rating' => '0'];

        $ratingsMock = $this->createMock(RatingModel::class);
        $ratingsMock->method('getFirstOrderId')->willReturn(10);
        $ratingsMock->expects($this->never())->method('upsert');

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratings: $ratingsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mes-notes/');

        $ctrl->updateRating(['lang' => 'fr']);
    }

    /**
     * updateRating() avec données valides → upsert appelé + redirect.
     */
    public function testUpdateRatingWithValidDataCallsUpsertAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['wine_id' => '5', 'rating' => '4'];

        $ratingsMock = $this->createMock(RatingModel::class);
        $ratingsMock->method('getFirstOrderId')->willReturn(10);
        $ratingsMock->expects($this->once())->method('upsert')->with(1, 5, 10, 4);

        $ctrl = $this->makeController();
        $this->injectMocks($ctrl, ratings: $ratingsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mes-notes/');

        $ctrl->updateRating(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // review()
    // ----------------------------------------------------------------

    /**
     * review() appelle findByAccountId et findById → view account/review.
     */
    public function testReviewRendersViewWithReviewAndAccount(): void
    {
        $review  = ['id' => 1, 'rating' => 5, 'comment' => 'Super'];
        $account = ['id' => 1, 'email' => 'test@example.com'];

        $reviewsMock = $this->createStub(ReviewModel::class);
        $reviewsMock->method('findByAccountId')->willReturn($review);

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);
        $ctrl->review(['lang' => 'fr']);

        $this->assertSame('account/review', $ctrl->lastView);
        $this->assertSame($review, $ctrl->lastViewData['review'] ?? null);
        $this->assertSame($account, $ctrl->lastViewData['account'] ?? null);
    }

    /**
     * review() sans avis existant → view account/review avec review=null.
     */
    public function testReviewRendersViewWithNullReviewWhenNoneExists(): void
    {
        $reviewsMock = $this->createStub(ReviewModel::class);
        $reviewsMock->method('findByAccountId')->willReturn(null);

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['id' => 1]);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);
        $ctrl->review(['lang' => 'fr']);

        $this->assertSame('account/review', $ctrl->lastView);
        $this->assertArrayHasKey('review', $ctrl->lastViewData ?? []);
        $this->assertNull($ctrl->lastViewData['review']);
    }

    // ----------------------------------------------------------------
    // updateReview()
    // ----------------------------------------------------------------

    /**
     * updateReview() avec CSRF invalide → redirect sans upsert.
     */
    public function testUpdateReviewWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '4', 'comment' => 'Bien', 'location' => 'Paris'];

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->never())->method('upsert');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mon-avis/');

        $ctrl->updateReview(['lang' => 'fr']);
    }

    /**
     * updateReview() avec rating < 1 → redirect sans upsert.
     */
    public function testUpdateReviewWithRatingBelowOneRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '0', 'comment' => 'Bof', 'location' => 'Lyon'];

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->never())->method('upsert');

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => 'Marc', 'lastname' => 'Leroy']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mon-avis/');

        $ctrl->updateReview(['lang' => 'fr']);
    }

    /**
     * updateReview() avec rating > 5 → redirect sans upsert.
     */
    public function testUpdateReviewWithRatingAboveFiveRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '6', 'comment' => 'Excellent', 'location' => 'Bordeaux'];

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->never())->method('upsert');

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => 'Jean', 'lastname' => 'Martin']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+mon-avis/');

        $ctrl->updateReview(['lang' => 'fr']);
    }

    /**
     * updateReview() avec données valides → upsert appelé + redirect avec ?saved=1.
     */
    public function testUpdateReviewWithValidDataCallsUpsertAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '5', 'comment' => 'Excellent', 'location' => 'Paris'];

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->once())->method('upsert');

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => 'Sophie', 'lastname' => 'Dupont']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/redirect:.+saved=1/');

        $ctrl->updateReview(['lang' => 'fr']);
    }

    /**
     * updateReview() avec firstname et lastname → displayName 'Prénom N.'.
     */
    public function testUpdateReviewDisplayNameFormattedWithBothNames(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '4', 'comment' => 'Bon', 'location' => 'Nantes'];

        $capturedDisplayName = null;

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->once())
            ->method('upsert')
            ->with(
                1,
                4,
                'Bon',
                $this->callback(function (string $name) use (&$capturedDisplayName): bool {
                    $capturedDisplayName = $name;
                    return true;
                }),
                'Nantes'
            );

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => 'Sophie', 'lastname' => 'Dupont']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        try {
            $ctrl->updateReview(['lang' => 'fr']);
        } catch (\RuntimeException $e) {
            // redirect attendu
        }

        $this->assertSame('Sophie D.', $capturedDisplayName);
    }

    /**
     * updateReview() avec firstname seul → displayName = firstname.
     */
    public function testUpdateReviewDisplayNameWithOnlyFirstname(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '3', 'comment' => '', 'location' => ''];

        $capturedDisplayName = null;

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->once())
            ->method('upsert')
            ->with(
                1,
                3,
                '',
                $this->callback(function (string $name) use (&$capturedDisplayName): bool {
                    $capturedDisplayName = $name;
                    return true;
                }),
                ''
            );

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => 'Marc', 'lastname' => '']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        try {
            $ctrl->updateReview(['lang' => 'fr']);
        } catch (\RuntimeException $e) {
            // redirect attendu
        }

        $this->assertSame('Marc', $capturedDisplayName);
    }

    /**
     * updateReview() sans firstname ni lastname → displayName = 'Client'.
     */
    public function testUpdateReviewDisplayNameFallsBackToClient(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['rating' => '2', 'comment' => '', 'location' => ''];

        $capturedDisplayName = null;

        $reviewsMock = $this->createMock(ReviewModel::class);
        $reviewsMock->expects($this->once())
            ->method('upsert')
            ->with(
                1,
                2,
                '',
                $this->callback(function (string $name) use (&$capturedDisplayName): bool {
                    $capturedDisplayName = $name;
                    return true;
                }),
                ''
            );

        $userRepoMock = $this->createStub(UserRepository::class);
        $userRepoMock->method('findById')->willReturn(['firstname' => '', 'lastname' => '']);

        $ctrl = $this->makeController($userRepoMock);
        $this->injectMocks($ctrl, reviews: $reviewsMock);

        try {
            $ctrl->updateReview(['lang' => 'fr']);
        } catch (\RuntimeException $e) {
            // redirect attendu
        }

        $this->assertSame('Client', $capturedDisplayName);
    }
}
