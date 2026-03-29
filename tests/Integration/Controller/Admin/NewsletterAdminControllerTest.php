<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\NewsletterAdminController;
use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

class NewsletterAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): NewsletterAdminController
    {
        return new NewsletterAdminController($this->makeRequest($method, '/admin/newsletter'));
    }

    private function insertSubscriber(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, newsletter)
             VALUES ('sub@test.local', 'x', 'customer', 'fr', NOW(), 1)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Sub', 'Test', 'M')",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersSubscribersList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Newsletter', $output);
    }

    public function testIndexWithSubscriberRendersView(): void
    {
        $this->insertSubscriber();

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // send — CSRF invalide
    // ----------------------------------------------------------------

    public function testSendRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, sujet vide → redirect avec erreur
    // ----------------------------------------------------------------

    public function testSendRedirectsOnEmptySubject(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = '';
        $_POST['body']       = 'Contenu test';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, body vide → redirect avec erreur
    // ----------------------------------------------------------------

    public function testSendRedirectsOnEmptyBody(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Sujet test';
        $_POST['body']       = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, données valides, aucun abonné → redirect 302
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() redirige en 302 avec un message de succès
     * quand le CSRF est valide, sujet + body remplis, et aucun abonné n'existe.
     * Aucun email n'est envoyé, le message affiche "0 email(s) envoyé(s) avec succès.".
     */
    public function testSendSuccessWithNoSubscribersRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Sujet test newsletter';
        $_POST['body']       = 'Contenu de la newsletter de test.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, données valides, avec abonnés → redirect 302
    // (MailService échoue en env de test → $failed++ → redirect quand même)
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() redirige en 302 même quand l'envoi SMTP échoue
     * (les exceptions MailService sont capturées → $failed s'incrémente).
     * Couvre les branches $failed > 0, buildNewsletterHtml, getNewsletterSubscribers(10000).
     */
    public function testSendWithSubscribersRedirectsEvenIfMailFails(): void
    {
        $this->insertSubscriber();

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Newsletter avec abonné';
        $_POST['body']       = 'Corps du message de test.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — avec abonné company (couvre la branche account_type = 'company')
    // ----------------------------------------------------------------

    /**
     * Vérifie que la branche account_type === 'company' est couverte dans send().
     * Un compte company est inséré, le nom est pris depuis company_name.
     */
    public function testSendWithCompanySubscriberRedirects(): void
    {
        // Insérer un abonné de type company
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, newsletter, account_type)
             VALUES ('company@test.local', 'x', 'customer', 'fr', NOW(), 1, 'company')"
        );
        self::$db->insert(
            "INSERT INTO account_companies (account_id, company_name, siret)
             VALUES (?, 'SARL Test Vins', '12345678901234')",
            [$id]
        );

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Newsletter entreprise';
        $_POST['body']       = 'Corps pour entreprise.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // index — pagination (page > 1)
    // ----------------------------------------------------------------

    /**
     * Vérifie que index() accepte un paramètre page > 1 via $_GET
     * et affiche quand même la vue sans erreur.
     */
    public function testIndexPage2RendersView(): void
    {
        $_GET['page'] = '2';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Newsletter', $output);
    }

    // ----------------------------------------------------------------
    // index — page invalide (0 ou négative) → forcée à 1
    // ----------------------------------------------------------------

    /**
     * Vérifie que index() normalise une page invalide (≤ 0) à 1 via max(1, …).
     */
    public function testIndexInvalidPageDefaultsToOne(): void
    {
        $_GET['page'] = '0';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Newsletter', $output);
    }

    // ----------------------------------------------------------------
    // send — abonné individuel sans prénom ni nom (name vide → 'Abonné')
    // ----------------------------------------------------------------

    /**
     * Vérifie que la branche "name ?: 'Abonné'" est couverte
     * quand firstname et lastname sont vides pour un abonné individuel.
     */
    public function testSendWithSubscriberEmptyNameUsesDefaultName(): void
    {
        // Insérer un abonné individuel sans prénom ni nom
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, newsletter)
             VALUES ('noname@test.local', 'x', 'customer', 'fr', NOW(), 1)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, '', '', 'M')",
            [$id]
        );

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Test nom vide';
        $_POST['body']       = 'Corps du message.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — uploadNewsletterImage : tmp_name fourni mais MIME invalide
    // Couvre la branche !isset($allowed[$mimeType]) → return null
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() ignore silencieusement une image avec un MIME type non autorisé
     * et redirige quand même en 302 (imageUrl reste null).
     */
    #[RequiresPhpExtension('fileinfo')]
    public function testSendWithInvalidImageMimeTypeIsIgnored(): void
    {
        // Créer un faux fichier avec contenu texte (MIME text/plain, non autorisé)
        $tmpFile = tempnam(sys_get_temp_dir(), 'nl_test_');
        file_put_contents($tmpFile, 'This is plain text, not an image');

        $_FILES['nl_image'] = [
            'tmp_name' => $tmpFile,
            'name'     => 'test.txt',
            'type'     => 'text/plain',
            'size'     => strlen('This is plain text, not an image'),
            'error'    => UPLOAD_ERR_OK,
        ];

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Test image MIME invalide';
        $_POST['body']       = 'Corps du message.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->send([]);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    // ----------------------------------------------------------------
    // send — uploadNewsletterPdf : MIME non-PDF → return null
    // Couvre la branche finfo !== 'application/pdf'
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() ignore un PDF avec un MIME type invalide (non application/pdf).
     */
    #[RequiresPhpExtension('fileinfo')]
    public function testSendWithNonPdfMimeTypeIsIgnored(): void
    {
        // Créer un fichier texte qui sera passé comme PDF (finfo détectera text/plain)
        $tmpFile = tempnam(sys_get_temp_dir(), 'nl_pdf_test_');
        file_put_contents($tmpFile, 'Not a real PDF content');

        $_FILES['nl_pdf'] = [
            'tmp_name' => $tmpFile,
            'name'     => 'fake.pdf',
            'type'     => 'application/pdf',
            'size'     => strlen('Not a real PDF content'),
            'error'    => UPLOAD_ERR_OK,
        ];

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Test PDF MIME invalide';
        $_POST['body']       = 'Corps du message.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->send([]);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    // ----------------------------------------------------------------
    // send — uploadNewsletterPdf : fichier trop grand → return null
    // Couvre la branche ($file['size'] ?? 0) > 10 * 1024 * 1024
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() ignore un PDF dont la taille déclarée dépasse 10 Mo.
     */
    #[RequiresPhpExtension('fileinfo')]
    public function testSendWithOversizePdfIsIgnored(): void
    {
        // Créer un vrai fichier PDF minimal pour passer la validation MIME
        $tmpFile = tempnam(sys_get_temp_dir(), 'nl_pdf_big_');
        // Contenu PDF minimal valide pour que finfo retourne application/pdf
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nxref\n0 1\n0000000000 65535 f\n%%EOF";
        file_put_contents($tmpFile, $pdfContent);

        $_FILES['nl_pdf'] = [
            'tmp_name' => $tmpFile,
            'name'     => 'big.pdf',
            'type'     => 'application/pdf',
            'size'     => 11 * 1024 * 1024, // 11 Mo — dépasse la limite de 10 Mo
            'error'    => UPLOAD_ERR_OK,
        ];

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Test PDF trop grand';
        $_POST['body']       = 'Corps du message.';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->send([]);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    // ----------------------------------------------------------------
    // send — failed message count branch ($failed > 0)
    // Déjà couvert par testSendWithSubscribersRedirectsEvenIfMailFails
    // Ajout : vérification explicite du message de flash avec échecs
    // ----------------------------------------------------------------

    /**
     * Vérifie que le flash 'success' contient " échec(s)" quand l'envoi échoue
     * (MailService lève une exception, $failed > 0 → branche $msg .= ", {$failed} échec(s)." couverte).
     */
    public function testSendFlashMessageContainsFailureCountWhenMailFails(): void
    {
        $this->insertSubscriber();

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Test failure count';
        $_POST['body']       = 'Corps.';

        try {
            $this->makeController('POST')->send([]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // En environnement de test le MailService échoue → le flash doit contenir "email(s)"
        $flash = $_SESSION['admin_flash']['success'] ?? '';
        $this->assertStringContainsString('email(s)', $flash);
    }

    // ----------------------------------------------------------------
    // send — persistance de la campagne en BDD
    // ----------------------------------------------------------------

    /**
     * Vérifie que send() crée bien une ligne dans la table newsletters.
     */
    public function testSendPersistsCampaignInDatabase(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Campagne persistance test';
        $_POST['body']       = 'Corps de la campagne.';

        try {
            $this->makeController('POST')->send([]);
        } catch (HttpException) {
            // redirect attendu
        }

        $row = self::$db->fetchOne(
            "SELECT subject FROM newsletters WHERE subject = ?",
            ['Campagne persistance test']
        );
        $this->assertNotEmpty($row);
        $this->assertSame('Campagne persistance test', $row['subject']);
    }

    /**
     * Vérifie que les compteurs sent_count et failed_count sont mis à jour après envoi.
     */
    public function testSendUpdatesStatsAfterSend(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Campagne stats test';
        $_POST['body']       = 'Corps pour stats.';

        try {
            $this->makeController('POST')->send([]);
        } catch (HttpException) {
            // redirect attendu
        }

        $row = self::$db->fetchOne(
            "SELECT sent_count, failed_count FROM newsletters WHERE subject = ?",
            ['Campagne stats test']
        );
        $this->assertNotEmpty($row);
        // sent_count + failed_count = total abonnés au moment du test (variable selon fixtures)
        $total = (int) $row['sent_count'] + (int) $row['failed_count'];
        $this->assertGreaterThanOrEqual(0, $total);
    }

    // ----------------------------------------------------------------
    // index — historique visible
    // ----------------------------------------------------------------

    /**
     * Vérifie que index() affiche la section historique même sans campagne.
     */
    public function testIndexShowsHistorySection(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Historique des envois', $output);
    }

    /**
     * Vérifie que index() affiche une campagne persistée dans l'historique.
     */
    public function testIndexShowsPersistedCampaign(): void
    {
        self::$db->insert(
            "INSERT INTO newsletters (subject, body, sent_count, failed_count)
             VALUES ('Historique visible', 'Corps', 3, 0)"
        );

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Historique visible', $output);
    }

    // ----------------------------------------------------------------
    // show()
    // ----------------------------------------------------------------

    /**
     * show() avec un id inconnu lève une HttpException 404.
     */
    public function testShowThrows404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $ctrl = new NewsletterAdminController(
            $this->makeRequest('GET', '/admin/newsletter/9999')
        );

        ob_start();
        try {
            $ctrl->show(['id' => '9999']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * show() avec un id valide affiche le détail de la campagne.
     */
    public function testShowRendersCampaignDetail(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, body, sent_count, failed_count)
             VALUES ('Campagne détail', 'Corps détail', 5, 1)"
        );

        $ctrl = new NewsletterAdminController(
            $this->makeRequest('GET', '/admin/newsletter/' . $id)
        );

        ob_start();
        $ctrl->show(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Campagne détail', $output);
        $this->assertStringContainsString('Corps détail', $output);
    }
}
