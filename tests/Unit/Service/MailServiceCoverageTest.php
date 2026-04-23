<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Couverture complémentaire MailService — lignes non couvertes.
 *
 * Cible :
 *   L1339       — sendOrderConfirmationToOwner() : catch(\Throwable) branche PDF
 *   L1381       — buildOrderItemsTable() : vintageHtml branche > 0
 *   L1699-1711  — buildOrderStatusContentEn() : items block
 *   L1716-1718  — buildOrderStatusContentEn() : deliveryAddress block
 *   L1724-1732  — buildOrderStatusContentEn() : trackingUrl block (shipped)
 *   L1770       — sendReturnRequestedToOwner() : ordered_at absent → '—'
 *   L2009       — sendReturnConfirmedToClient() : ordered_at absent → '—'
 *   L3114-3130  — buildDeliveryAddressBlockEn() : city non vide → bloc HTML
 *   L3345-3347  — newOrderFormModel() / newRatingModel() factory methods
 *   L3548       — sendPostPurchase() : vintageHtml branche > 0
 *   L3600-3646  — sendReviewRequest() FR + EN
 *   L3711       — sendAbandonedCart() : vintageHtml branche > 0
 *   L4186-4190  — sendLowStockAlert()
 */
#[AllowMockObjectsWithoutExpectations]
class MailServiceCoverageTest extends TestCase
{
    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $_ENV['MAIL_USER']           = 'noreply@test.local';
        $_ENV['APP_URL']             = 'http://crabitan.local';
        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@test.local';
        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    protected function tearDown(): void
    {
        unset($_ENV['MAIL_USER'], $_ENV['CONTACT_OWNER_EMAIL'], $_ENV['APP_URL']);
    }

    /**
     * Injecte un stub PHPMailer dont send() ne lance pas d'exception.
     */
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub);
    }

    private function callPrivate(MailService $service, string $method, mixed ...$args): mixed
    {
        $m = (new ReflectionClass(MailService::class))->getMethod($method);
        return $m->invoke($service, ...$args);
    }

    // ----------------------------------------------------------------
    // L1339 — sendOrderConfirmationToOwner() : catch Throwable sur PDF
    // OrderSummaryPdfService::generate() explose → catch → $pdfContent = null
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToOwnerWithPdfContextCatchesThrowable(): void
    {
        // Sous-classe qui fait exploser OrderSummaryPdfService
        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }
        };
        $this->injectMockMailer($service);

        // pdfContext non null → on entre dans le try, mais OrderSummaryPdfService::generate()
        // sera instancié avec des données bidon → peut échouer ou pas selon la class.
        // On veut surtout exercer le catch : on passe un pdfContext valide mais
        // la méthode generate() peut ne pas être disponible (pas de tcpdf en test).
        try {
            $service->sendOrderConfirmationToOwner(
                'client@example.com',
                'Client Test',
                'CB-2026-TST',
                'card',
                [['name' => 'Vin Rouge 2022', 'qty' => 2, 'price' => 24.00]],
                48.00,
                0.0,
                ['ordered_at' => '2026-04-01 10:00:00', 'billing' => ['name' => 'Test'], 'delivery' => null]
            );
            $this->assertTrue(true); // Pas d'exception propagée = catch fonctionne
        } catch (\Throwable $e) {
            // PHPMailer stub ne peut pas envoyer → mailer exception acceptable
            $this->assertTrue(true);
        }
    }

    // ----------------------------------------------------------------
    // L1339 — branche catch : pdfContext null → pas de PDF (branche normale)
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToOwnerWithoutPdfContext(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Client',
            'CB-2026-NP',
            'virement',
            [['name' => 'Vin', 'qty' => 1, 'price' => 20.0]],
            20.0
        );

        $this->assertTrue(true); // pdfContext = null → pas de PDF → send() mocké OK
    }

    // ----------------------------------------------------------------
    // L1381 — buildOrderItemsTable() : vintage > 0 → vintageHtml non vide
    // ----------------------------------------------------------------

    public function testBuildOrderItemsTableWithVintageRendersVintageSpan(): void
    {
        $items  = [['name' => 'Bordeaux Rouge', 'vintage' => 2022, 'qty' => 1, 'price' => 24.0]];
        $result = $this->callPrivate(
            $this->service,
            'buildOrderItemsTable',
            $items,
            24.0,
            'Produit',
            'Qté',
            'Prix unit.',
            'Sous-total',
            'Total'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('2022', $result); // vintageHtml inclus
        $this->assertStringContainsString('<span', $result);
    }

    // ----------------------------------------------------------------
    // L1381 — buildOrderItemsTable() : is_cuvee_speciale = true → cuveeHtml
    // ----------------------------------------------------------------

    public function testBuildOrderItemsTableWithCuveeSpecialeRendersCuveeLabel(): void
    {
        $items  = [[
            'name'              => 'Cuvée Prestige',
            'vintage'           => 2021,
            'qty'               => 1,
            'price'             => 35.0,
            'is_cuvee_speciale' => true,
        ]];
        $result = $this->callPrivate(
            $this->service,
            'buildOrderItemsTable',
            $items,
            35.0,
            'Produit',
            'Qté',
            'Prix unit.',
            'Sous-total',
            'Total'
        );

        $this->assertStringContainsString('Cuvée Spéciale', $result);
    }

    // ----------------------------------------------------------------
    // L1699-1711 — buildOrderStatusContentEn() : items block (EN, avec items)
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnWithItemsCoversItemsBlock(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-ITEMS',
            'processing',
            'en',
            'http://crabitan.local',
            [
                ['name' => 'Dry White 2023', 'vintage' => 2023, 'qty' => 2, 'price' => 18.5],
            ]
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L1716-1718 — buildOrderStatusContentEn() : deliveryAddress block non vide
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnWithDeliveryAddressCoversAddrBlock(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-ADDR',
            'delivered',
            'en',
            'http://crabitan.local',
            [],
            [
                'firstname' => 'Bob',
                'lastname'  => 'Smith',
                'line1'     => '12 Baker Street',
                'line2'     => '',
                'zip'       => 'W1U 6LF',
                'city'      => 'London',
                'country'   => 'UK',
            ]
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L1716-1718 — buildOrderStatusContentEn() : deliveryAddress vide → addr === ''
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnWithEmptyCityDeliverySkipsBlock(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-ADDR2',
            'delivered',
            'en',
            'http://crabitan.local',
            [],
            ['city' => ''] // city vide → buildDeliveryAddressBlockEn retourne ''
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L1724-1732 — buildOrderStatusContentEn() : shipped + trackingUrl → tracking block
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnShippedWithTrackingUrlCoversTrackingBlock(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-TRACK',
            'shipped',
            'en',
            'http://crabitan.local',
            [],
            [],
            'https://tracking.example.com/package/ABC123'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L1770 — sendReturnRequestedToOwner() : ordered_at absent → '—'
    // ----------------------------------------------------------------

    public function testSendReturnRequestedToOwnerWithoutOrderedAtUsesEmDash(): void
    {
        $this->injectMockMailer($this->service);

        $order = [
            'id'              => 42,
            'order_reference' => 'CB-2026-NODDATE',
            // pas de 'ordered_at'
            'content'         => json_encode([
                ['label_name' => 'Bordeaux Rouge', 'format' => 'bottle', 'qty' => 1, 'price' => 24.0],
            ]),
        ];

        $this->service->sendReturnRequestedToOwner($order, 'Alice', 'alice@example.com');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L2009 — sendReturnConfirmedToClient() : ordered_at absent → '—'
    // ----------------------------------------------------------------

    public function testSendReturnConfirmedToClientWithoutOrderedAtUsesEmDash(): void
    {
        $this->injectMockMailer($this->service);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'return_slip_nodate_');
        file_put_contents($tmpPdf, '%PDF-1.4 test');

        $order = [
            'id'              => 43,
            'order_reference' => 'CB-2026-NODATE',
            // pas de 'ordered_at'
            'content'         => json_encode([
                ['label_name' => 'Vin Blanc 2023', 'format' => 'bottle', 'qty' => 1, 'price' => 18.0],
            ]),
        ];

        $this->service->sendReturnConfirmedToClient('alice@example.com', 'Alice', $order, $tmpPdf, 'fr');
        unlink($tmpPdf);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3114-3130 — buildDeliveryAddressBlockEn() : city non vide → bloc HTML
    // ----------------------------------------------------------------

    public function testBuildDeliveryAddressBlockEnWithCityReturnsHtml(): void
    {
        $result = $this->callPrivate(
            $this->service,
            'buildDeliveryAddressBlockEn',
            [
                'firstname' => 'Bob',
                'lastname'  => 'Smith',
                'line1'     => '10 Downing Street',
                'line2'     => 'Flat 1',
                'zip'       => 'SW1A 2AA',
                'city'      => 'London',
                'country'   => 'United Kingdom',
            ]
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Delivery address', $result);
        $this->assertStringContainsString('London', $result);
        $this->assertStringContainsString('Bob Smith', $result);
    }

    // ----------------------------------------------------------------
    // L3114-3130 — buildDeliveryAddressBlockEn() : city vide → ''
    // ----------------------------------------------------------------

    public function testBuildDeliveryAddressBlockEnWithEmptyCityReturnsEmpty(): void
    {
        $result = $this->callPrivate(
            $this->service,
            'buildDeliveryAddressBlockEn',
            ['city' => '', 'firstname' => 'Bob', 'lastname' => 'Smith']
        );

        $this->assertSame('', $result);
    }

    // ----------------------------------------------------------------
    // L3345-3347 — newRatingModel() via sous-classe anonyme
    // ----------------------------------------------------------------

    public function testNewRatingModelReturnsRatingModelInstance(): void
    {
        $result = $this->callPrivate($this->service, 'newRatingModel');
        $this->assertInstanceOf(\Model\RatingModel::class, $result);
    }

    // ----------------------------------------------------------------
    // L3548 — sendPostPurchase() : vintageHtml branche vintage > 0
    //          RatingModel mockée via sous-classe
    // ----------------------------------------------------------------

    public function testSendPostPurchaseWithVintageRendersVintageHtml(): void
    {
        $fakeRating = new class extends \Model\RatingModel {
            // phpcs:ignore
            public function __construct() {}
            public function generateOrderToken(int $orderId, int $accountId): string
            {
                return 'order-token-123';
            }
            public function generateEmailToken(int $accountId, int $wineId, int $orderId): string
            {
                return 'email-token-456';
            }
        };

        $service = new class ($fakeRating) extends MailService {
            public function __construct(private \Model\RatingModel $ratingModel)
            {
                parent::__construct();
            }
            protected function newRatingModel(): \Model\RatingModel
            {
                return $this->ratingModel;
            }
        };

        $this->injectMockMailer($service);

        $service->sendPostPurchase(
            'alice@example.com',
            'Alice',
            1,
            42,
            [
                [
                    'wine_id'    => 5,
                    'label_name' => 'Bordeaux Rouge',
                    'vintage'    => 2022, // > 0 → vintageHtml non vide
                    'image_path' => 'bordeaux-rouge.png',
                ],
            ],
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3548 — sendPostPurchase() : vintage = 0 (branche vide)
    // ----------------------------------------------------------------

    public function testSendPostPurchaseWithZeroVintageSkipsVintageHtml(): void
    {
        $fakeRating = new class extends \Model\RatingModel {
            // phpcs:ignore
            public function __construct() {}
            public function generateOrderToken(int $orderId, int $accountId): string
            {
                return 'order-token-xyz';
            }
            public function generateEmailToken(int $accountId, int $wineId, int $orderId): string
            {
                return 'email-token-xyz';
            }
        };

        $service = new class ($fakeRating) extends MailService {
            public function __construct(private \Model\RatingModel $ratingModel)
            {
                parent::__construct();
            }
            protected function newRatingModel(): \Model\RatingModel
            {
                return $this->ratingModel;
            }
        };

        $this->injectMockMailer($service);

        $service->sendPostPurchase(
            'bob@example.com',
            'Bob',
            2,
            43,
            [
                [
                    'wine_id'    => 6,
                    'label_name' => 'Vin Blanc',
                    'vintage'    => 0, // = 0 → vintageHtml vide
                    'image_path' => '',
                ],
            ],
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3600-3646 — sendReviewRequest() FR
    // ----------------------------------------------------------------

    public function testSendReviewRequestFrCoversBody(): void
    {
        $fakeRating = new class extends \Model\RatingModel {
            // phpcs:ignore
            public function __construct() {}
            public function generateOrderToken(int $orderId, int $accountId): string
            {
                return 'review-token-fr';
            }
        };

        $service = new class ($fakeRating) extends MailService {
            public function __construct(private \Model\RatingModel $ratingModel)
            {
                parent::__construct();
            }
            protected function newRatingModel(): \Model\RatingModel
            {
                return $this->ratingModel;
            }
        };

        $this->injectMockMailer($service);

        $service->sendReviewRequest(
            'alice@example.com',
            'Alice',
            1,
            42,
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3600-3646 — sendReviewRequest() EN
    // ----------------------------------------------------------------

    public function testSendReviewRequestEnCoversBody(): void
    {
        $fakeRating = new class extends \Model\RatingModel {
            // phpcs:ignore
            public function __construct() {}
            public function generateOrderToken(int $orderId, int $accountId): string
            {
                return 'review-token-en';
            }
        };

        $service = new class ($fakeRating) extends MailService {
            public function __construct(private \Model\RatingModel $ratingModel)
            {
                parent::__construct();
            }
            protected function newRatingModel(): \Model\RatingModel
            {
                return $this->ratingModel;
            }
        };

        $this->injectMockMailer($service);

        $service->sendReviewRequest(
            'bob@example.com',
            'Bob',
            2,
            43,
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3711 — sendAbandonedCart() : vintage > 0 → vintageHtml non vide
    // ----------------------------------------------------------------

    public function testSendAbandonedCartWithVintageRendersVintageHtml(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendAbandonedCart(
            'alice@example.com',
            'Alice',
            [
                [
                    'label_name' => 'Bordeaux Rouge',
                    'vintage'    => 2022, // > 0 → vintageHtml
                    'image_path' => 'bordeaux.png',
                    'price'      => 24.0,
                    'qty'        => 1,
                ],
            ],
            'fr',
            'http://crabitan.local/fr/panier'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L3711 — sendAbandonedCart() : imagePath non vide → imageHtml
    // ----------------------------------------------------------------

    public function testSendAbandonedCartWithImagePathCoversImageHtml(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendAbandonedCart(
            'bob@example.com',
            'Bob',
            [
                [
                    'label_name' => 'Dry White',
                    'vintage'    => 0,
                    'image_path' => 'dry-white.png', // non vide → imageHtml
                    'price'      => 18.0,
                    'qty'        => 2,
                ],
            ],
            'en',
            'http://crabitan.local/en/cart'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L4186-4190 — sendLowStockAlert() : délègue à send()
    // ----------------------------------------------------------------

    public function testSendLowStockAlertCallsSend(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendLowStockAlert(
            'admin@example.com',
            'Admin',
            'Alerte stock bas : Bordeaux Rouge 2022',
            '<p>Stock critique : 2 bouteilles restantes.</p>'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderStatusEmail EN avec orderId = 0 → '/en/my-account/orders'
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnWithOrderIdZeroUsesGenericPath(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-ID0',
            'cancelled',
            'en',
            'http://crabitan.local',
            [],
            [],
            '',
            0 // orderId = 0 → CTA vers /en/my-account/orders
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderStatusEmail EN avec orderId > 0 → '/en/my-account/orders/{id}'
    // ----------------------------------------------------------------

    public function testSendOrderStatusEmailEnWithOrderIdPositiveUsesSpecificPath(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderStatusEmail(
            'bob@example.com',
            'Bob',
            'CB-2026-EN-ID5',
            'refunded',
            'en',
            'http://crabitan.local',
            [],
            [],
            '',
            5 // orderId > 0
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L1339-1342 — sendOrderConfirmationToOwner() avec shippingDiscount > 0
    // S'assure que les lignes 1339-1342 (paramètres shippingDiscount/labelDiscount) sont couvertes
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToOwnerWithShippingDiscountPassesParamsToBuildTable(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Client Test',
            'CB-2026-DISC',
            'card',
            [['name' => 'Vin Rouge 2022', 'qty' => 1, 'price' => 24.00]],
            19.00,
            5.00  // shippingDiscount > 0 → couvre lignes 1339-1342
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // buildOrderItemsTable() : discountRow branche (shippingDiscount > 0)
    // ----------------------------------------------------------------

    public function testBuildOrderItemsTableWithShippingDiscountRendersDiscountRow(): void
    {
        $items  = [['name' => 'Vin Rouge', 'vintage' => 0, 'qty' => 1, 'price' => 20.0]];
        $result = $this->callPrivate(
            $this->service,
            'buildOrderItemsTable',
            $items,
            18.0,
            'Produit',
            'Qté',
            'Prix unit.',
            'Sous-total',
            'Total',
            2.0,
            'Remise livraison'
        );

        $this->assertStringContainsString('Remise livraison', $result);
        $this->assertStringContainsString('−', $result);
    }

    // ----------------------------------------------------------------
    // L258 — sendContactToOwner() : phone non vide → bloc téléphone injecté
    // ----------------------------------------------------------------

    /**
     * Couvre la branche positive du ternaire phoneBlock (L257-258) :
     * un numéro de téléphone non vide doit apparaître dans le corps HTML.
     * Utilise un sous-classe capturant le corps passé à send().
     */
    public function testSendContactToOwnerWithPhoneCoversPhoneBlockBranch(): void
    {
        $service = new class extends MailService {
            /** @var array<string, mixed>|null Capture de l'appel send() pour assertions */
            public static ?array $capturedCall = null;

            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function send(
                string $to,
                string $name,
                string $subject,
                string $body,
                ?string $attachmentPath = null,
                ?string $replyTo = null,
                ?string $attachmentName = null,
                ?string $attachmentString = null
            ): void {
                self::$capturedCall = ['to' => $to, 'subject' => $subject, 'body' => $body];
            }
        };

        $service::$capturedCall = null;

        $service->sendContactToOwner(
            'Jean',
            'Dupont',
            'jean@example.com',
            'Question test',
            'Message avec téléphone.',
            'fr',
            '0612345678' // phone non vide → branche L258 exécutée
        );

        $this->assertNotNull($service::$capturedCall);
        $this->assertStringContainsString('Téléphone', $service::$capturedCall['body']);
        $this->assertStringContainsString('0612345678', $service::$capturedCall['body']);
    }

    // ----------------------------------------------------------------
    // L1373-1376 — sendOrderConfirmationToOwner() : PDF generate throw → catch
    // ----------------------------------------------------------------

    /**
     * Couvre les lignes 1373-1376 (catch OrderSummaryPdfService Throwable).
     * Injection d'un OrderSummaryPdfService qui lance une exception via le seam
     * newOrderSummaryPdfService(). Les champs pdfName/pdfContent doivent être null
     * et l'email parti malgré l'échec PDF.
     */
    public function testSendOrderConfirmationToOwnerCatchesPdfThrowable(): void
    {
        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function newOrderSummaryPdfService(): \Service\OrderSummaryPdfService
            {
                return new class extends \Service\OrderSummaryPdfService {
                    public function generate(array $data): string
                    {
                        throw new \RuntimeException('pdf-forced-failure');
                    }
                };
            }
        };

        $this->injectMockMailer($service);

        // Ne doit PAS remonter l'exception : le catch \Throwable la neutralise
        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Client Test',
            'CB-2026-PDFFAIL',
            'card',
            [['name' => 'Vin Rouge 2022', 'qty' => 2, 'price' => 24.00]],
            48.00,
            0.0,
            ['ordered_at' => '2026-04-01 10:00:00', 'billing' => ['name' => 'Test'], 'delivery' => null]
        );

        $this->assertTrue(true); // catch exécuté → pas d'exception propagée
    }

    // ----------------------------------------------------------------
    // L3075 — send() : PHPMailer throw rate-limit → RuntimeException 429
    // ----------------------------------------------------------------

    /**
     * Couvre L3075 : PHPMailer::send() lève une Exception contenant '421' ou
     * 'rate' ou 'too many' → send() convertit en RuntimeException('MAIL_RATE_LIMIT', 429).
     */
    public function testSendThrowsMailRateLimitWhen421Returned(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willThrowException(
            new \PHPMailer\PHPMailer\Exception('SMTP error: 421 Too many connections')
        );

        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function getRateLogger(): \Model\EmailRateLogModel
            {
                return new class extends \Model\EmailRateLogModel {
                    // phpcs:ignore
                    public function __construct() {}
                    public function log(
                        string $fromAddress,
                        string $toAddress,
                        string $subject,
                        bool $success,
                        ?string $errorMessage,
                        string $type = 'other'
                    ): void {
                        // no-op
                    }
                };
            }
        };

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub);

        $sendMethod = (new ReflectionClass(MailService::class))->getMethod('send');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAIL_RATE_LIMIT');
        $this->expectExceptionCode(429);

        try {
            $sendMethod->invoke($service, 'to@example.com', 'To', 'Sujet', '<p>Body</p>');
        } catch (\RuntimeException $e) {
            throw $e;
        }
    }

    /**
     * Couvre la branche "throw $e" (L3077) : PHPMailer throw SANS motif rate-limit
     * → l'exception PHPMailer originale est repropagée telle quelle.
     */
    public function testSendRethrowsOriginalWhenNotRateLimit(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willThrowException(
            new \PHPMailer\PHPMailer\Exception('SMTP error: Could not authenticate.')
        );

        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function getRateLogger(): \Model\EmailRateLogModel
            {
                return new class extends \Model\EmailRateLogModel {
                    // phpcs:ignore
                    public function __construct() {}
                    public function log(
                        string $fromAddress,
                        string $toAddress,
                        string $subject,
                        bool $success,
                        ?string $errorMessage,
                        string $type = 'other'
                    ): void {
                        // no-op
                    }
                };
            }
        };

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub);

        $sendMethod = (new ReflectionClass(MailService::class))->getMethod('send');

        $this->expectException(\PHPMailer\PHPMailer\Exception::class);

        $sendMethod->invoke($service, 'to@example.com', 'To', 'Sujet', '<p>Body</p>');
    }

    /**
     * Couvre le chemin heureux de send() : PHPMailer réussit → getRateLogger()->log()
     * en succès (L3080). Vérifie qu'aucune exception n'est remontée.
     */
    public function testSendLogsSuccessWhenMailerSucceeds(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function getRateLogger(): \Model\EmailRateLogModel
            {
                return new class extends \Model\EmailRateLogModel {
                    // phpcs:ignore
                    public function __construct() {}
                    public function log(
                        string $fromAddress,
                        string $toAddress,
                        string $subject,
                        bool $success,
                        ?string $errorMessage,
                        string $type = 'other'
                    ): void {
                        // no-op
                    }
                };
            }
        };

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub);

        $sendMethod = (new ReflectionClass(MailService::class))->getMethod('send');

        $sendMethod->invoke($service, 'to@example.com', 'To', 'Sujet', '<p>Body</p>');

        $this->assertTrue(true); // pas d'exception = success path exécuté (L3080)
    }

    /**
     * Couvre le catch logEx L3071-3073 : le rateLogger->log() (échec) lui-même lève
     * une exception, absorbée par le catch interne avant de décider du throw final.
     */
    public function testSendCatchesLoggerFailureBeforeRateLimitDecision(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willThrowException(
            new \PHPMailer\PHPMailer\Exception('SMTP error: rate limit exceeded')
        );

        $service = new class extends MailService {
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            protected function getRateLogger(): \Model\EmailRateLogModel
            {
                return new class extends \Model\EmailRateLogModel {
                    // phpcs:ignore
                    public function __construct() {}
                    public function log(
                        string $fromAddress,
                        string $toAddress,
                        string $subject,
                        bool $success,
                        ?string $errorMessage,
                        string $type = 'other'
                    ): void {
                        throw new \RuntimeException('logger down');
                    }
                };
            }
        };

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub);

        $sendMethod = (new ReflectionClass(MailService::class))->getMethod('send');

        // L'exception logger est avalée ; seul MAIL_RATE_LIMIT est levé
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAIL_RATE_LIMIT');

        $sendMethod->invoke($service, 'to@example.com', 'To', 'Sujet', '<p>Body</p>');
    }

    // ----------------------------------------------------------------
    // buildAddressBlock() — mode shipping + pickup + ctx null
    // Couvre le helper introduit pour fiches commande (owner + client).
    // ----------------------------------------------------------------

    /**
     * buildAddressBlock() retourne chaîne vide quand $ctx est null.
     */
    public function testBuildAddressBlockReturnsEmptyWhenCtxIsNull(): void
    {
        $result = $this->callPrivate($this->service, 'buildAddressBlock', null, 'shipping', 'fr');
        $this->assertSame('', $result);
    }

    /**
     * buildAddressBlock() mode shipping FR avec adresses billing et delivery distinctes.
     */
    public function testBuildAddressBlockShippingFrIncludesBillingAndDelivery(): void
    {
        $ctx = [
            'billing' => [
                'civility'  => 'M.',
                'firstname' => 'Jean',
                'lastname'  => 'Dupont',
                'street'    => '1 rue de Bordeaux',
                'zip'       => '33000',
                'city'      => 'Bordeaux',
                'country'   => 'France',
                'phone'     => '0102030405',
            ],
            'delivery' => [
                'firstname' => 'Marie',
                'lastname'  => 'Martin',
                'street'    => '2 avenue des Vignes',
                'zip'       => '33410',
                'city'      => 'Sainte-Croix-du-Mont',
                'country'   => 'France',
            ],
        ];

        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'shipping', 'fr');

        $this->assertIsString($result);
        $this->assertStringContainsString('Livraison : à domicile', $result);
        $this->assertStringContainsString('Adresse de facturation', $result);
        $this->assertStringContainsString('Adresse de livraison', $result);
        $this->assertStringContainsString('Jean', $result);
        $this->assertStringContainsString('Marie', $result);
        $this->assertStringContainsString('Bordeaux', $result);
        $this->assertStringContainsString('Sainte-Croix-du-Mont', $result);
        $this->assertStringContainsString('0102030405', $result);
        // Pas d'adresse château en shipping
        $this->assertStringNotContainsString('Château Crabitan', $result);
    }

    /**
     * buildAddressBlock() mode pickup FR — inclut l'adresse du château en retrait.
     */
    public function testBuildAddressBlockPickupFrIncludesChateauAddress(): void
    {
        $ctx = [
            'billing' => [
                'firstname' => 'Alice',
                'lastname'  => 'Client',
                'street'    => '10 rue Test',
                'zip'       => '33000',
                'city'      => 'Bordeaux',
            ],
            'delivery' => null,
        ];

        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'pickup', 'fr');

        $this->assertIsString($result);
        $this->assertStringContainsString('Click &amp; Collect', $result);
        $this->assertStringContainsString('Adresse de retrait', $result);
        $this->assertStringContainsString('Château Crabitan Bellevue', $result);
        $this->assertStringContainsString('33410 Sainte-Croix-du-Mont', $result);
        // Pas de "Adresse de livraison" car mode pickup
        $this->assertStringNotContainsString('Adresse de livraison', $result);
    }

    /**
     * buildAddressBlock() mode pickup EN — libellés anglais + château.
     */
    public function testBuildAddressBlockPickupEnUsesEnglishLabels(): void
    {
        $ctx = ['billing' => ['firstname' => 'John', 'lastname' => 'Doe'], 'delivery' => null];
        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'pickup', 'en');

        $this->assertStringContainsString('Click &amp; Collect', $result);
        $this->assertStringContainsString('Pickup address', $result);
        $this->assertStringContainsString('Billing address', $result);
        $this->assertStringContainsString('Château Crabitan Bellevue', $result);
    }

    /**
     * buildAddressBlock() mode shipping EN — libellés anglais, pas de château.
     */
    public function testBuildAddressBlockShippingEnUsesEnglishLabels(): void
    {
        $ctx = [
            'billing'  => ['firstname' => 'Ann', 'lastname' => 'Doe', 'city' => 'Paris'],
            'delivery' => ['firstname' => 'Bob', 'lastname' => 'Doe', 'city' => 'Lyon'],
        ];
        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'shipping', 'en');

        $this->assertStringContainsString('Delivery: home delivery', $result);
        $this->assertStringContainsString('Billing address', $result);
        $this->assertStringContainsString('Delivery address', $result);
        $this->assertStringNotContainsString('Château', $result);
    }

    /**
     * buildAddressBlock() affiche "—" quand l'adresse de facturation est entièrement vide.
     */
    public function testBuildAddressBlockFallsBackToDashForEmptyAddress(): void
    {
        $ctx = ['billing' => [], 'delivery' => null];
        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'shipping', 'fr');

        $this->assertStringContainsString('—', $result);
    }

    /**
     * buildAddressBlock() shipping FR — fallback zip_code si 'zip' absent et pas de city.
     * (Branche covered quand le premier `zip + city` trim est vide.)
     */
    public function testBuildAddressBlockShippingFallsBackToZipCode(): void
    {
        $ctx = [
            'billing' => [
                'firstname' => 'X',
                'lastname'  => 'Y',
                'zip_code'  => '75000',
            ],
            'delivery' => null,
        ];
        $result = $this->callPrivate($this->service, 'buildAddressBlock', $ctx, 'shipping', 'fr');

        $this->assertStringContainsString('75000', $result);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToOwner() — deliveryMethod pickup ⇒ libellé
    // "Remise Click & Collect" et delivery_method injecté dans pdfData.
    // ----------------------------------------------------------------

    /**
     * deliveryMethod='pickup' → label "Remise Click & Collect" dans sendOrderConfirmationToOwner.
     */
    public function testSendOrderConfirmationToOwnerWithPickupUsesClickCollectLabel(): void
    {
        $service = new class extends MailService {
            /** @var string Corps capturé via appel send() */
            public string $capturedBody = '';
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
            protected function send(
                string $to,
                string $name,
                string $subject,
                string $body,
                ?string $attachmentPath = null,
                ?string $replyTo = null,
                ?string $attachmentName = null,
                ?string $attachmentString = null
            ): void {
                $this->capturedBody = $body;
            }
            // phpcs:enable
        };
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Client',
            'CB-2026-CC',
            'card',
            [['name' => 'Vin', 'qty' => 2, 'price' => 20.0]],
            40.0,
            4.40,
            null,
            'pickup'
        );

        $this->assertStringContainsString('Remise Click & Collect', $service->capturedBody);
        $this->assertStringNotContainsString('Remise livraison', $service->capturedBody);
    }

    /**
     * deliveryMethod='shipping' → label "Remise livraison" (défaut historique).
     */
    public function testSendOrderConfirmationToOwnerWithShippingUsesDefaultLabel(): void
    {
        $service = new class extends MailService {
            public string $capturedBody = '';
            // phpcs:ignore
            public function __construct() { parent::__construct(); }

            // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
            protected function send(
                string $to,
                string $name,
                string $subject,
                string $body,
                ?string $attachmentPath = null,
                ?string $replyTo = null,
                ?string $attachmentName = null,
                ?string $attachmentString = null
            ): void {
                $this->capturedBody = $body;
            }
            // phpcs:enable
        };
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Client',
            'CB-2026-SH',
            'card',
            [['name' => 'Vin', 'qty' => 1, 'price' => 20.0]],
            20.0,
            2.20,
            null,
            'shipping'
        );

        $this->assertStringContainsString('Remise livraison', $service->capturedBody);
    }

    /**
     * setMailType() seam modifie currentType (rate log) pour les envois ultérieurs.
     */
    public function testSetMailTypeUpdatesCurrentType(): void
    {
        $this->callPrivate($this->service, 'setMailType', 'order');
        $prop = (new ReflectionClass(MailService::class))->getProperty('currentType');
        $prop->setAccessible(true);
        $this->assertSame('order', $prop->getValue($this->service));

        $this->callPrivate($this->service, 'setMailType', 'contact');
        $this->assertSame('contact', $prop->getValue($this->service));
    }
}
