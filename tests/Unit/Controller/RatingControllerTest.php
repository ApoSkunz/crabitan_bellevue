<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\RatingController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour RatingController.
 * Couvre les guards de validation (auth, CSRF, note, token) sans BDD réelle.
 */
class RatingControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        // RateLimiterService appelle fetchOne → null = aucun record = requête autorisée
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('execute')->willReturn(0);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/rating/test';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_ENV['JWT_SECRET']        = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): RatingController
    {
        return new RatingController(new Request());
    }

    private function makeTestableController(): TestableRatingController
    {
        return new TestableRatingController(new Request());
    }

    // ================================================================
    // rateViaTokenApi — validation (php://input vide → token='' → 422)
    // ================================================================

    /**
     * rateViaTokenApi() retourne 422 quand token et note sont absents (corps vide).
     */
    public function testRateViaTokenApiReturns422WhenBodyEmpty(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaTokenApi([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : non authentifié → 401
    // ================================================================

    /**
     * rateViaAccount() retourne 404 quand aucun cookie auth_token
     * (AuthMiddleware masque les routes protégées — design sécurité).
     */
    public function testRateViaAccountReturns404WhenNotAuthenticated(): void
    {
        $_COOKIE = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 404 quand le JWT est invalide
     * (AuthMiddleware masque les routes protégées — design sécurité).
     */
    public function testRateViaAccountReturns404WhenTokenInvalid(): void
    {
        $_COOKIE = ['auth_token' => 'invalid.jwt.token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : CSRF invalide → 403
    // ================================================================

    /**
     * rateViaAccount() retourne 403 quand le CSRF ne correspond pas.
     */
    public function testRateViaAccountReturns403WhenCsrfInvalid(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'WRONG-CSRF', 'note' => '4'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : note invalide → 422
    // ================================================================

    /**
     * rateViaAccount() retourne 422 quand la note est hors bornes (0).
     */
    public function testRateViaAccountReturns422WhenNoteIsZero(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '0'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 422 quand la note dépasse 5.
     */
    public function testRateViaAccountReturns422WhenNoteExceedsFive(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '6'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaEmail — note invalide (via TestableRatingController)
    // ================================================================

    /**
     * rateViaEmail() affiche la vue d'erreur quand la note est invalide (0).
     */
    public function testRateViaEmailRendersErrorViewWhenNoteInvalid(): void
    {
        $ctrl = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'anytoken', 'note' => '0']);

        $this->assertSame('rating/error', $ctrl->lastView);
        $this->assertSame('invalid_note', $ctrl->lastViewData['reason'] ?? null);
    }

    /**
     * rateViaEmail() affiche la vue d'erreur quand la note dépasse 5.
     */
    public function testRateViaEmailRendersErrorViewWhenNoteAbove5(): void
    {
        $ctrl = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'anytoken', 'note' => '6']);

        $this->assertSame('rating/error', $ctrl->lastView);
        $this->assertSame('invalid_note', $ctrl->lastViewData['reason'] ?? null);
    }

    /**
     * rateViaEmail() affiche la vue d'erreur quand le token est invalide (introuvable en BDD).
     */
    public function testRateViaEmailRendersErrorViewWhenTokenInvalid(): void
    {
        // DB retourne false → findByToken = null → token invalide
        $ctrl = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'unknown-token', 'note' => '3']);

        $this->assertSame('rating/error', $ctrl->lastView);
        $this->assertSame('invalid_token', $ctrl->lastViewData['reason'] ?? null);
    }

    /**
     * rateViaEmail() affiche la vue de confirmation quand token et note sont valides.
     */
    public function testRateViaEmailRendersConfirmViewWhenValid(): void
    {
        // DB stub : fetchOne retourne un row valide pour findByToken, puis false pour les suivants
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['account_id' => 1, 'wine_id' => 2, 'order_id' => 3], // findByToken
            false                                                    // autres appels
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'valid-token', 'note' => '4']);

        $this->assertSame('rating/confirm', $ctrl->lastView);
        $this->assertSame(4, $ctrl->lastViewData['note'] ?? null);
    }

    /**
     * rateViaEmail() utilise la langue du cookie quand valide ('en').
     */
    public function testRateViaEmailUsesLangCookieWhenValid(): void
    {
        $_COOKIE = ['lang' => 'en'];
        $ctrl    = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'anytoken', 'note' => '0']);

        $this->assertSame('en', $ctrl->lastViewData['lang'] ?? null);
    }

    /**
     * rateViaEmail() bascule sur 'fr' pour une langue de cookie inconnue.
     */
    public function testRateViaEmailFallsBackToFrForUnknownLangCookie(): void
    {
        $_COOKIE = ['lang' => 'de'];
        $ctrl    = $this->makeTestableController();
        $ctrl->rateViaEmail(['token' => 'anytoken', 'note' => '0']);

        $this->assertSame('fr', $ctrl->lastViewData['lang'] ?? null);
    }

    // ================================================================
    // rateOrder — toutes les branches
    // ================================================================

    /**
     * rateOrder() affiche la vue d'erreur quand le orderToken est invalide.
     */
    public function testRateOrderRendersErrorViewWhenOrderTokenInvalid(): void
    {
        // DB retourne false → resolveOrderToken = null
        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'unknown-order-token']);

        $this->assertSame('rating/error', $ctrl->lastView);
        $this->assertSame('invalid_token', $ctrl->lastViewData['reason'] ?? null);
    }

    /**
     * rateOrder() affiche la vue d'erreur quand la commande est introuvable en BDD.
     */
    public function testRateOrderRendersErrorViewWhenOrderRowNotFound(): void
    {
        // fetchOne : 1er = resolveOrderToken (valide), 2e = orders fetchOne (false)
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['order_id' => 10, 'account_id' => 1], // resolveOrderToken
            false                                    // orders SELECT → introuvable
        );
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'valid-order-token']);

        $this->assertSame('rating/error', $ctrl->lastView);
        $this->assertSame('invalid_token', $ctrl->lastViewData['reason'] ?? null);
    }

    /**
     * rateOrder() affiche la vue rating/order avec des vins valides.
     * Couvre le chemin nominal complet incluant wine_id = 0 (ignoré) et wine_id > 0.
     */
    public function testRateOrderRendersOrderViewWithWines(): void
    {
        $content = json_encode([
            ['wine_id' => 0],                // ignoré (wine_id = 0)
            ['wine_id' => 5, 'label_name' => 'Chablis', 'vintage' => 2020, 'image_path' => ''],
        ]);

        // Séquence fetchOne :
        // 1. resolveOrderToken  → row valide
        // 2. orders SELECT      → row avec content JSON
        // 3. getById wine       → row vin
        // 4. getByAccountAndWine → null (pas de note existante)
        // 5. generateEmailToken fetchOne → false (pas de token existant)
        // + execute pour insert token
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['order_id' => 10, 'account_id' => 1],                                           // resolveOrderToken
            ['id' => 10, 'content' => $content],                                              // orders
            ['id' => 5, 'label_name' => 'Chablis', 'vintage' => 2020, 'image_path' => ''],   // WineModel::getById
            false,                                                                             // getByAccountAndWine → pas de note
            false                                                                              // generateEmailToken existing
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'valid-order-token']);

        $this->assertSame('rating/order', $ctrl->lastView);
        $wines = $ctrl->lastViewData['wines'] ?? [];
        $this->assertCount(1, $wines); // wine_id=0 ignoré
        $this->assertSame(5, $wines[0]['wine_id']);
    }

    /**
     * rateOrder() marque noted=true quand $_GET['noted'] === '1'.
     */
    public function testRateOrderSetsNotedTrueWhenGetParamPresent(): void
    {
        $_GET = ['noted' => '1'];

        $content = json_encode([]);
        $dbMock  = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['order_id' => 10, 'account_id' => 1],
            ['id' => 10, 'content' => $content]
        );
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'valid-order-token']);

        $this->assertTrue($ctrl->lastViewData['noted'] ?? false);
    }

    /**
     * rateOrder() génère un pageTitle en anglais quand lang='en'.
     */
    public function testRateOrderGeneratesEnglishPageTitleForEnLang(): void
    {
        $_COOKIE = ['lang' => 'en'];

        $content = json_encode([]);
        $dbMock  = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['order_id' => 42, 'account_id' => 1],
            ['id' => 42, 'content' => $content]
        );
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'valid-order-token']);

        $pageTitle = $ctrl->lastViewData['pageTitle'] ?? '';
        $this->assertStringContainsString('Rate your wines', $pageTitle);
    }

    /**
     * rateOrder() affecte current_note depuis la note existante quand elle existe.
     */
    public function testRateOrderUsesExistingNoteForCurrentNote(): void
    {
        $content = json_encode([
            ['wine_id' => 7, 'label_name' => 'Meursault', 'vintage' => 2019, 'image_path' => ''],
        ]);

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['order_id' => 20, 'account_id' => 1],
            ['id' => 20, 'content' => $content],
            ['id' => 7, 'label_name' => 'Meursault', 'vintage' => 2019, 'image_path' => ''],
            ['rating' => 5, 'avg' => 4.5, 'count' => 2],   // getByAccountAndWine → note existante
            false                                            // generateEmailToken existing
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = $this->makeTestableController();
        $ctrl->rateOrder(['orderToken' => 'valid-order-token']);

        $wines = $ctrl->lastViewData['wines'] ?? [];
        $this->assertSame(5, $wines[0]['current_note'] ?? 0);
    }

    // ================================================================
    // rateViaTokenApi — token valide, note valide → ligne 171 + 181
    // ================================================================

    /**
     * rateViaTokenApi() avec token valide et note valide retourne 200 success=true (L171+L181).
     * Utilise TestableRatingControllerWithJsonBody pour injecter le body JSON.
     */
    public function testRateViaTokenApiReturns200WithValidTokenAndNote(): void
    {
        $dbMock = $this->createStub(Database::class);
        // Séquence fetchOne : (1) checkLimit :until → false, (2) checkLimit :count → false,
        // (3) recordAttempt :count → false, (4) findByToken → row valide
        $calls = 0;
        $dbMock->method('fetchOne')->willReturnCallback(
            function () use (&$calls): array|false {
                $calls++;
                return $calls === 4
                    ? ['account_id' => 1, 'wine_id' => 2, 'order_id' => 3]
                    : false;
            }
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = new TestableRatingControllerWithJsonBody(new Request());
        $ctrl->setBody((string) json_encode(['token' => 'valid-tok', 'note' => 3]));

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(200);

        $ctrl->rateViaTokenApi([]);
    }

    /**
     * rateViaTokenApi() retourne 404 quand le token n'est pas trouvé en BDD (L171-175).
     * Utilise TestableRatingControllerWithJsonBody pour injecter le body JSON.
     */
    public function testRateViaTokenApiReturns404WhenTokenNotFoundInDb(): void
    {
        // DB retourne false pour tous les fetchOne → findByToken = null
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('execute')->willReturn(0);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = new TestableRatingControllerWithJsonBody(new Request());
        $ctrl->setBody((string) json_encode(['token' => 'unknown-tok', 'note' => 4]));

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(404);

        $ctrl->rateViaTokenApi([]);
    }

    // ================================================================
    // rateViaTokenApi — rate-limit 429
    // ================================================================

    /**
     * rateViaTokenApi() retourne 429 quand le rate-limit est dépassé.
     */
    public function testRateViaTokenApiReturns429WhenRateLimited(): void
    {
        // Simuler un lockout actif : la clé :until contient un timestamp futur
        $futureTs = time() + 3600;
        $dbMock   = $this->createStub(Database::class);
        // fetchOne pour dbGet(:until) → retourne la valeur JSON du timestamp futur
        $dbMock->method('fetchOne')->willReturn(['value' => json_encode($futureTs)]);
        $this->instanceProp->setValue(null, $dbMock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(429);

        $this->makeController()->rateViaTokenApi([]);
    }

    // ================================================================
    // rateViaTokenApi — token invalide 404
    // ================================================================

    /**
     * rateViaTokenApi() retourne 404 quand le token est inconnu (note valide, token absent BDD).
     */
    public function testRateViaTokenApiReturns404WhenTokenUnknown(): void
    {
        // DB : pas de lockout (false pour :until et :count), pas de token trouvé (false pour findByToken)
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('execute')->willReturn(0);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = new TestableRatingControllerWithUnknownToken(new Request());

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $ctrl->rateViaTokenApi([]);
    }

    /**
     * rateViaTokenApi() retourne 200 avec success=true quand token valide et note valide.
     */
    public function testRateViaTokenApiReturns200WhenValid(): void
    {
        // DB : pas de lockout → row valide pour findByToken au 4e fetchOne
        // (appels 1=:until, 2=:count checkLimit, 3=:count recordAttempt, 4=findByToken)
        $calls  = 0;
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnCallback(
            function () use (&$calls): array|false {
                $calls++;
                return $calls === 4
                    ? ['account_id' => 1, 'wine_id' => 2, 'order_id' => 3]
                    : false;
            }
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $ctrl = new TestableRatingControllerWithValidToken(new Request());

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        $ctrl->rateViaTokenApi([]);
    }

    // ================================================================
    // rateViaAccount — happy path (hasOrdered = true)
    // ================================================================

    /**
     * rateViaAccount() retourne 200 avec success=true quand toutes les conditions sont remplies.
     */
    public function testRateViaAccountReturns200WhenAllConditionsValid(): void
    {
        // DB : fetchOne retourne un compte valide pour AuthMiddleware + hasOrdered + getByAccountAndWine
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'status' => 'active'],    // AuthMiddleware connection check
            ['id' => 1],                           // hasOrdered / getFirstOrderId
            ['rating' => 4, 'avg' => 4.0, 'count' => 3]  // getByAccountAndWine
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '4'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '5']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 403 quand l'achat n'est pas trouvé (hasOrdered = false).
     */
    public function testRateViaAccountReturns403WhenNotOrdered(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'status' => 'active'],  // AuthMiddleware
            false                                 // hasOrdered → false
        );
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '4'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '5']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 200 avec avg=null quand getByAccountAndWine retourne null.
     */
    public function testRateViaAccountReturns200WithNullAvgWhenNoExistingRating(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'status' => 'active'],   // AuthMiddleware
            ['id' => 1],                          // hasOrdered
            false                                 // getByAccountAndWine → pas de note existante (false → null dans le model)
        );
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '3'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '7']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // Helper
    // ================================================================

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }
}
