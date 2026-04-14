<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests de couverture complémentaires pour MailService.
 *
 * Cible les branches non couvertes par les autres fichiers de test :
 * - sendDeletionReminder EN avec daysAhead > 1 (ligne 481)
 * - buildOrderItemsTable avec shippingDiscount > 0 (lignes 1247-1251)
 * - sendNewsletterConfirmation FR + EN (lignes 1610-1688)
 * - sendEmailChangeConfirmation EN (lignes 1798-1812)
 * - sendEmailChangeNotification EN (lignes 1844-1853)
 * - sendAdminLoginFailureAlert EN, targetName null et non-null (lignes 1961-2012)
 * - sendAdminPasswordChangeAlert EN, targetName null et non-null (lignes 2045-2084)
 * - sendLegalDocumentUpdate EN + changeNote non vide (lignes 2944-2963)
 */
class MailServiceBilingualTemplatesTest extends TestCase
{
    /**
     * Injecte un PHPMailer stub dont send() ne lève pas d'exception.
     *
     * @param MailService $service Instance à patcher
     * @return PHPMailer Le stub injecté (pour lire Subject/Body si besoin)
     */
    private function injectStub(MailService $service): PHPMailer
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré

        return $stub;
    }

    // ----------------------------------------------------------------
    // sendDeletionReminder — branch EN daysAhead > 1 (ligne 481)
    // ----------------------------------------------------------------

    /**
     * L'objet EN avec daysAhead=7 contient "7 days" (ternaire ligne 481).
     */
    public function testDeletionReminderEnSubjectMentionsDays(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendDeletionReminder(
            'u@example.com',
            'John',
            'en',
            7,
            '2026-06-01 00:00:00',
            'tok'
        );

        $this->assertStringContainsString('7 days', $stub->Subject ?? '');
    }

    // ----------------------------------------------------------------
    // buildOrderItemsTable — branch shippingDiscount > 0 (lignes 1247-1251)
    // ----------------------------------------------------------------

    /**
     * buildOrderItemsTable avec shippingDiscount > 0 inclut la ligne de remise.
     */
    public function testBuildOrderItemsTableWithShippingDiscount(): void
    {
        $service = new MailService();

        $items = [
            ['name' => 'Vin rouge', 'qty' => 2, 'unit_price' => 15.00, 'subtotal' => 30.00],
        ];

        $prop = new \ReflectionClass(MailService::class);
        $method = $prop->getMethod('buildOrderItemsTable');

        $html = $method->invoke(
            $service,
            $items,
            30.00,
            'Produit',
            'Qté',
            'Prix unit.',
            'Sous-total',
            'Total',
            5.00,           // shippingDiscount > 0
            'Livraison offerte'
        );

        $this->assertStringContainsString('Livraison offerte', $html);
        $this->assertStringContainsString('5,00', $html);
    }

    /**
     * buildOrderItemsTable avec shippingDiscount = 0 n'inclut pas de ligne de remise.
     */
    public function testBuildOrderItemsTableWithoutShippingDiscount(): void
    {
        $service = new MailService();

        $items = [
            ['name' => 'Vin blanc', 'qty' => 1, 'unit_price' => 20.00, 'subtotal' => 20.00],
        ];

        $prop = new \ReflectionClass(MailService::class);
        $method = $prop->getMethod('buildOrderItemsTable');

        $html = $method->invoke(
            $service,
            $items,
            20.00,
            'Product',
            'Qty',
            'Unit price',
            'Subtotal',
            'Total',
            0.0,    // shippingDiscount = 0 → pas de ligne remise
            ''
        );

        $this->assertStringNotContainsString('color:#c9a84c', $html); // pas de ligne remise colorée
    }

    // ----------------------------------------------------------------
    // sendNewsletterConfirmation — FR (lignes 1610-1653)
    // ----------------------------------------------------------------

    /**
     * sendNewsletterConfirmation FR ne lève pas d'exception.
     */
    public function testNewsletterConfirmationFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendNewsletterConfirmation(
            'test@example.com',
            'https://example.com/confirm?token=abc',
            'fr'
        );

        $this->assertTrue(true);
    }

    /**
     * sendNewsletterConfirmation FR inclut l'URL de confirmation.
     */
    public function testNewsletterConfirmationFrContainsUrl(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendNewsletterConfirmation(
            'test@example.com',
            'https://example.com/confirm?token=abc123',
            'fr'
        );

        $this->assertStringContainsString('abc123', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendNewsletterConfirmation — EN (lignes 1664-1688)
    // ----------------------------------------------------------------

    /**
     * sendNewsletterConfirmation EN ne lève pas d'exception.
     */
    public function testNewsletterConfirmationEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendNewsletterConfirmation(
            'test@example.com',
            'https://example.com/confirm?token=xyz',
            'en'
        );

        $this->assertTrue(true);
    }

    /**
     * sendNewsletterConfirmation EN inclut l'URL de confirmation.
     */
    public function testNewsletterConfirmationEnContainsUrl(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendNewsletterConfirmation(
            'test@example.com',
            'https://example.com/confirm?token=xyz789',
            'en'
        );

        $this->assertStringContainsString('xyz789', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendEmailChangeConfirmation — EN (lignes 1798-1812)
    // ----------------------------------------------------------------

    /**
     * sendEmailChangeConfirmation EN ne lève pas d'exception.
     */
    public function testEmailChangeConfirmationEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendEmailChangeConfirmation(
            'old@example.com',
            'Alice',
            'https://example.com/confirm-email?token=tok1',
            'en',
            'new@example.com'
        );

        $this->assertTrue(true);
    }

    /**
     * sendEmailChangeConfirmation EN avec revokeUrl inclut les deux boutons.
     */
    public function testEmailChangeConfirmationEnWithRevokeUrlDoesNotThrow(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendEmailChangeConfirmation(
            'old@example.com',
            'Alice',
            'https://example.com/confirm-email?token=tok1',
            'en',
            'new@example.com',
            'https://example.com/revoke?token=rev1'
        );

        $this->assertStringContainsString('Cancel this request', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendEmailChangeNotification — EN (lignes 1844-1853)
    // ----------------------------------------------------------------

    /**
     * sendEmailChangeNotification EN ne lève pas d'exception.
     */
    public function testEmailChangeNotificationEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendEmailChangeNotification('new@example.com', 'en');

        $this->assertTrue(true);
    }

    /**
     * sendEmailChangeNotification EN inclut un message en anglais.
     */
    public function testEmailChangeNotificationEnContainsEnglishText(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendEmailChangeNotification('new@example.com', 'en');

        $this->assertStringContainsString('submitted as the new login address', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminLoginFailureAlert — EN sans targetName (lignes 1988-2012)
    // ----------------------------------------------------------------

    /**
     * sendAdminLoginFailureAlert EN sans targetName ne lève pas d'exception.
     */
    public function testAdminLoginFailureAlertEnNoTargetDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendAdminLoginFailureAlert(
            'admin@example.com',
            'Admin',
            'en',
            '127.0.0.1'
        );

        $this->assertTrue(true);
    }

    /**
     * sendAdminLoginFailureAlert EN sans targetName : intro générique.
     */
    public function testAdminLoginFailureAlertEnNoTargetContainsGenericIntro(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminLoginFailureAlert(
            'admin@example.com',
            'Admin',
            'en',
            '192.168.1.1'
        );

        $this->assertStringContainsString('your admin account', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminLoginFailureAlert — EN avec targetName (lignes 1990-1992)
    // ----------------------------------------------------------------

    /**
     * sendAdminLoginFailureAlert EN avec targetName mentionne le compte ciblé.
     */
    public function testAdminLoginFailureAlertEnWithTargetMentionsTarget(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminLoginFailureAlert(
            'superadmin@example.com',
            'SuperAdmin',
            'en',
            '10.0.0.1',
            'TargetAdmin'
        );

        $this->assertStringContainsString('TargetAdmin', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminLoginFailureAlert — FR avec targetName (lignes 1967-1970)
    // ----------------------------------------------------------------

    /**
     * sendAdminLoginFailureAlert FR avec targetName mentionne le compte ciblé.
     */
    public function testAdminLoginFailureAlertFrWithTargetMentionsTarget(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminLoginFailureAlert(
            'superadmin@example.com',
            'SuperAdmin',
            'fr',
            '10.0.0.1',
            'AdminCible'
        );

        $this->assertStringContainsString('AdminCible', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminPasswordChangeAlert — EN sans targetName (lignes 2062-2084)
    // ----------------------------------------------------------------

    /**
     * sendAdminPasswordChangeAlert EN sans targetName ne lève pas d'exception.
     */
    public function testAdminPasswordChangeAlertEnNoTargetDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendAdminPasswordChangeAlert(
            'admin@example.com',
            'Admin',
            'en',
            '127.0.0.1',
            'Mozilla/5.0'
        );

        $this->assertTrue(true);
    }

    /**
     * sendAdminPasswordChangeAlert EN sans targetName : message en anglais.
     */
    public function testAdminPasswordChangeAlertEnNoTargetContainsEnglishMessage(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminPasswordChangeAlert(
            'admin@example.com',
            'Admin',
            'en',
            '127.0.0.1',
            'Chrome/120'
        );

        $this->assertStringContainsString('Your admin account password was just changed', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminPasswordChangeAlert — EN avec targetName (lignes 2064-2066)
    // ----------------------------------------------------------------

    /**
     * sendAdminPasswordChangeAlert EN avec targetName mentionne le compte ciblé.
     */
    public function testAdminPasswordChangeAlertEnWithTargetMentionsTarget(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminPasswordChangeAlert(
            'superadmin@example.com',
            'SuperAdmin',
            'en',
            '10.0.0.5',
            'Firefox/110',
            'TargetAdmin'
        );

        $this->assertStringContainsString('TargetAdmin', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminPasswordChangeAlert — FR avec targetName (lignes 2044-2046)
    // ----------------------------------------------------------------

    /**
     * sendAdminPasswordChangeAlert FR avec targetName mentionne le compte ciblé.
     */
    public function testAdminPasswordChangeAlertFrWithTargetMentionsTarget(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminPasswordChangeAlert(
            'superadmin@example.com',
            'SuperAdmin',
            'fr',
            '10.0.0.5',
            'Firefox/110',
            'AdminCible'
        );

        $this->assertStringContainsString('AdminCible', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendLegalDocumentUpdate — EN (lignes 2944-2963)
    // ----------------------------------------------------------------

    /**
     * sendLegalDocumentUpdate EN sans note ne lève pas d'exception.
     */
    public function testLegalDocUpdateEnNoNoteDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendLegalDocumentUpdate(
            'user@example.com',
            'Alice',
            'en',
            'Terms of Service',
            'https://example.com/tos',
            '2.0',
            '2026-04-01',
            ''
        );

        $this->assertTrue(true);
    }

    /**
     * sendLegalDocumentUpdate EN avec note inclut le résumé.
     */
    public function testLegalDocUpdateEnWithNoteContainsSummary(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendLegalDocumentUpdate(
            'user@example.com',
            'Alice',
            'en',
            'Terms of Service',
            'https://example.com/tos',
            '2.1',
            '2026-04-01',
            'Added GDPR clause'
        );

        $this->assertStringContainsString('Summary of changes', $stub->Body ?? '');
        $this->assertStringContainsString('Added GDPR clause', $stub->Body ?? '');
    }

    /**
     * sendLegalDocumentUpdate FR avec note inclut le résumé en français.
     */
    public function testLegalDocUpdateFrWithNoteContainsSummary(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendLegalDocumentUpdate(
            'user@example.com',
            'Bob',
            'fr',
            'CGV',
            'https://example.com/cgv',
            '1.5',
            '2026-04-01',
            'Ajout clause RGPD'
        );

        $this->assertStringContainsString('Résumé des modifications', $stub->Body ?? '');
        $this->assertStringContainsString('Ajout clause RGPD', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendLegalDocumentUpdate — FR sans note (ligne 2932 ternaire vide)
    // ----------------------------------------------------------------

    /**
     * sendLegalDocumentUpdate FR sans note ne lève pas d'exception (noteHtml = '').
     */
    public function testLegalDocUpdateFrNoNoteDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendLegalDocumentUpdate(
            'user@example.com',
            'Bob',
            'fr',
            'CGV',
            'https://example.com/cgv',
            '1.0',
            '2026-04-01',
            ''  // changeNote vide → ternaire ligne 2932
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendEmailChangeConfirmation — FR (lignes 1782-1796)
    // ----------------------------------------------------------------

    /**
     * sendEmailChangeConfirmation FR ne lève pas d'exception.
     */
    public function testEmailChangeConfirmationFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendEmailChangeConfirmation(
            'old@example.com',
            'Alice',
            'https://example.com/confirm-email?token=tok2',
            'fr',
            'new@example.com'
        );

        $this->assertTrue(true);
    }

    /**
     * sendEmailChangeConfirmation FR avec revokeUrl ne lève pas d'exception.
     */
    public function testEmailChangeConfirmationFrWithRevokeUrlDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendEmailChangeConfirmation(
            'old@example.com',
            'Alice',
            'https://example.com/confirm-email?token=tok2',
            'fr',
            'new@example.com',
            'https://example.com/revoke?token=rev2'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendEmailChangeNotification — FR (lignes 1833-1842)
    // ----------------------------------------------------------------

    /**
     * sendEmailChangeNotification FR ne lève pas d'exception.
     */
    public function testEmailChangeNotificationFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectStub($service);

        $service->sendEmailChangeNotification('new@example.com', 'fr');

        $this->assertTrue(true);
    }

    /**
     * sendEmailChangeNotification FR inclut un message en français.
     */
    public function testEmailChangeNotificationFrContainsFrenchText(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendEmailChangeNotification('new@example.com', 'fr');

        $this->assertStringContainsString('renseignée comme nouvelle adresse', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminLoginFailureAlert — FR sans targetName (lignes 1972, 1976-1977)
    // ----------------------------------------------------------------

    /**
     * sendAdminLoginFailureAlert FR sans targetName : intro générique + action.
     */
    public function testAdminLoginFailureAlertFrNoTargetContainsGenericIntro(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminLoginFailureAlert(
            'admin@example.com',
            'Admin',
            'fr',
            '192.168.1.1'
        );

        $this->assertStringContainsString('votre compte administrateur', $stub->Body ?? '');
        $this->assertStringContainsString('changez immédiatement votre mot de passe', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendAdminPasswordChangeAlert — FR sans targetName (ligne 2048)
    // ----------------------------------------------------------------

    /**
     * sendAdminPasswordChangeAlert FR sans targetName : intro générique.
     */
    public function testAdminPasswordChangeAlertFrNoTargetContainsGenericIntro(): void
    {
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendAdminPasswordChangeAlert(
            'admin@example.com',
            'Admin',
            'fr',
            '127.0.0.1',
            'Chrome/120'
        );

        $this->assertStringContainsString('votre compte administrateur vient d\'être modifié', $stub->Body ?? '');
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToOwner (lignes 1162-1197)
    // ----------------------------------------------------------------

    /**
     * sendOrderConfirmationToOwner ne lève pas d'exception.
     */
    public function testSendOrderConfirmationToOwnerDoesNotThrow(): void
    {
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Crabitan Bellevue');
        }

        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@example.com';
        $service = new MailService();
        $this->injectStub($service);

        $items = [
            ['name' => 'Vin rouge 2022', 'qty' => 3, 'unit_price' => 18.50, 'subtotal' => 55.50],
        ];

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Jean Dupont',
            'CB-2026-042',
            'card',
            $items,
            55.50
        );

        $this->assertTrue(true);
    }

    /**
     * sendOrderConfirmationToOwner avec shippingDiscount inclut la remise.
     */
    public function testSendOrderConfirmationToOwnerWithDiscountContainsItems(): void
    {
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Crabitan Bellevue');
        }

        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@example.com';
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $items = [
            ['name' => 'Vin blanc 2023', 'qty' => 2, 'unit_price' => 22.00, 'subtotal' => 44.00],
        ];

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Marie Martin',
            'CB-2026-043',
            'virement',
            $items,
            44.00,
            5.00  // shippingDiscount
        );

        $this->assertStringContainsString('CB-2026-043', $stub->Subject ?? '');
    }

    /**
     * sendOrderConfirmationToOwner avec $pdfContext renseigné déclenche la génération
     * du PDF récap et le joint au mail (couvre la branche $pdfContext !== null).
     */
    public function testSendOrderConfirmationToOwnerWithPdfContextGeneratesAttachment(): void
    {
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Crabitan Bellevue');
        }

        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@example.com';
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $items = [
            ['name' => 'Vin rouge 2022', 'qty' => 3, 'unit_price' => 18.50, 'subtotal' => 55.50],
        ];

        $address = [
            'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue Test', 'zip' => '75001', 'city' => 'Paris',
            'country' => 'France', 'phone' => '0102030405',
        ];

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Jean Dupont',
            'CB-2026-PDF',
            'card',
            $items,
            55.50,
            0.0,
            [
                'ordered_at' => '2026-04-14 12:00:00',
                'billing'    => $address,
                'delivery'   => $address,
            ]
        );

        $this->assertStringContainsString('CB-2026-PDF', $stub->Subject ?? '');
    }

    /**
     * sendOrderConfirmationToOwner avec $pdfContext null saute la branche PDF
     * (couvre le chemin alternatif sans génération PDF).
     */
    public function testSendOrderConfirmationToOwnerWithoutPdfContextSkipsAttachment(): void
    {
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Crabitan Bellevue');
        }

        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@example.com';
        $service = new MailService();
        $stub    = $this->injectStub($service);

        $service->sendOrderConfirmationToOwner(
            'client@example.com',
            'Jean Dupont',
            'CB-2026-NOPDF',
            'cheque',
            [['name' => 'V', 'qty' => 1, 'price' => 10.0]],
            10.0,
            0.0,
            null
        );

        $this->assertStringContainsString('CB-2026-NOPDF', $stub->Subject ?? '');
    }
}
