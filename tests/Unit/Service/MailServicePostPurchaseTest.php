<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendPostPurchase() et sendAbandonedCart().
 *
 * PHPMailer est remplacé par un stub pour éviter tout envoi SMTP réel.
 * RatingModel est stubbé via seam newRatingModel() pour isoler MailService de la BDD.
 */
class MailServicePostPurchaseTest extends TestCase
{
    /**
     * Injecte un stub PHPMailer dans le service pour éviter les appels SMTP réels.
     *
     * @param MailService $service Instance de MailService à patcher
     * @return void
     */
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    /**
     * Articles de panier pour les tests sendAbandonedCart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleCartItems(): array
    {
        return [
            [
                'label_name' => 'Crabitan Rouge',
                'vintage'    => 2020,
                'image_path' => 'crabitan-rouge-2020.jpg',
                'price'      => 12.50,
                'qty'        => 3,
            ],
            [
                'label_name' => 'Cuvée Prestige',
                'vintage'    => 2018,
                'image_path' => '',
                'price'      => 24.00,
                'qty'        => 1,
            ],
        ];
    }

    // ----------------------------------------------------------------
    // sendAbandonedCart — FR
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendAbandonedCart ne lève pas d'exception en français.
     */
    public function testSendAbandonedCartFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendAbandonedCart(
            'jean@test.com',
            'Jean',
            $this->sampleCartItems(),
            'fr',
            'https://crabitanbellevue.fr/fr/panier'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendAbandonedCart — EN
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendAbandonedCart ne lève pas d'exception en anglais.
     */
    public function testSendAbandonedCartEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendAbandonedCart(
            'john@test.com',
            'John',
            $this->sampleCartItems(),
            'en',
            'https://crabitanbellevue.fr/en/cart'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendAbandonedCart — panier vide (liste vide)
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendAbandonedCart fonctionne avec une liste d'articles vide.
     */
    public function testSendAbandonedCartEmptyItemsDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendAbandonedCart(
            'jean@test.com',
            'Jean',
            [],
            'fr',
            'https://crabitanbellevue.fr/fr/panier'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendPostPurchase — FR
    // ----------------------------------------------------------------

    /**
     * Crée une instance de MailService avec RatingModel stubbé via seam.
     *
     * @return MailService
     */
    private function makeServiceWithRatingStub(): MailService
    {
        $ratingStub = $this->createStub(\Model\RatingModel::class);
        $ratingStub->method('generateEmailToken')->willReturn(str_pad('', 64, 'a'));

        $service = new class ($ratingStub) extends MailService {
            public function __construct(private \Model\RatingModel $ratingMock)
            {
                parent::__construct();
            }
            protected function newRatingModel(): \Model\RatingModel
            {
                return $this->ratingMock;
            }
        };

        return $service;
    }

    /**
     * Vérifie que sendPostPurchase ne lève pas d'exception en français.
     * RatingModel est stubbé via seam newRatingModel().
     */
    public function testSendPostPurchaseFrDoesNotThrow(): void
    {
        $service = $this->makeServiceWithRatingStub();
        $this->injectMockMailer($service);

        $items = [
            ['wine_id' => 1, 'label_name' => 'Crabitan Rouge', 'vintage' => 2020, 'image_path' => 'img.jpg'],
            ['wine_id' => 2, 'label_name' => 'Cuvée Prestige', 'vintage' => 2018, 'image_path' => ''],
        ];

        $service->sendPostPurchase(
            'jean@test.com',
            'Jean',
            42,
            100,
            $items,
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendPostPurchase — EN
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendPostPurchase ne lève pas d'exception en anglais.
     * RatingModel est stubbé via seam newRatingModel().
     */
    public function testSendPostPurchaseEnDoesNotThrow(): void
    {
        $service = $this->makeServiceWithRatingStub();
        $this->injectMockMailer($service);

        $items = [
            ['wine_id' => 3, 'label_name' => 'Crabitan Blanc', 'vintage' => 2021, 'image_path' => 'blanc.jpg'],
        ];

        $service->sendPostPurchase(
            'john@test.com',
            'John',
            43,
            101,
            $items,
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendPostPurchase — liste vide d'items
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendPostPurchase fonctionne avec une liste d'articles vide.
     */
    public function testSendPostPurchaseEmptyItemsDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendPostPurchase(
            'jean@test.com',
            'Jean',
            42,
            100,
            [],
            'fr'
        );

        $this->assertTrue(true);
    }
}
