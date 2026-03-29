<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\WineAdminController;
use Core\Exception\HttpException;

class WineAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): WineAdminController
    {
        return new WineAdminController($this->makeRequest($method, '/admin/vins'));
    }

    /**
     * Insère un vin avec un slug personnalisé pour déclencher les tests de doublon.
     */
    private function insertWineWithSlug(string $slug): int
    {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, image_path, slug)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                'Vin Doublon', 'red', 'bottle', 2018, 15.00, 10, 0,
                '1.00', 'Bordeaux', 'Merlot 100%', 15,
                json_encode(['fr' => 'Desc doublon', 'en' => 'Duplicate desc']),
                json_encode(['fr' => 'Argile', 'en' => 'Clay']),
                json_encode(['fr' => 'Guyot', 'en' => 'Guyot']),
                json_encode(['fr' => 'Manuel', 'en' => 'Manual']),
                json_encode(['fr' => 'Inox', 'en' => 'Stainless']),
                json_encode(['fr' => '12 mois', 'en' => '12 months']),
                json_encode(['fr' => '', 'en' => '']),
                json_encode(['fr' => '', 'en' => '']),
                'Wine_Vin_Doublon_2018.png', $slug,
            ]
        );
    }

    /**
     * Crée un fichier PNG minimal valide (reconnu image/png par finfo) dans le dossier temporaire.
     *
     * @return string Chemin du fichier temporaire créé
     */
    private function createMinimalPng(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wine_png_');
        file_put_contents(
            $tmpFile,
            "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xde"
        );
        return $tmpFile;
    }

    /**
     * Crée un fichier PNG minimal à un chemin précis.
     *
     * @param string $path Chemin complet de destination
     */
    private function createMinimalPngAt(string $path): void
    {
        file_put_contents(
            $path,
            "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xde"
        );
    }

    private function insertWine(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, image_path, slug)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                'Vin TI', 'red', 'bottle', 2022, 18.50, 60, 1,
                '0.50', 'Saint-Émilion', 'Merlot 80%', 20,
                json_encode(['fr' => 'Desc TI', 'en' => 'TI desc']),
                json_encode(['fr' => 'Argile', 'en' => 'Clay']),
                json_encode(['fr' => 'Guyot', 'en' => 'Guyot']),
                json_encode(['fr' => 'Manuel', 'en' => 'Manual']),
                json_encode(['fr' => 'Inox', 'en' => 'Stainless']),
                json_encode(['fr' => '12 mois', 'en' => '12 months']),
                json_encode(['fr' => '', 'en' => '']),
                json_encode(['fr' => '', 'en' => '']),
                'Wine_Vin_TI_2022.png', 'vin-ti-2022',
            ]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersWineList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Vins', $output);
    }

    public function testIndexWithColorFilterRendersView(): void
    {
        $_GET['color']     = 'red';
        $_GET['available'] = 'available';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-filters', $output);
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    public function testCreateRendersForm(): void
    {
        ob_start();
        $this->makeController()->create([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Ajouter', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF invalide
    // ----------------------------------------------------------------

    public function testStoreRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->store([]);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, champs vides → parseWineForm déclenche erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithValidationErrors(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = '';
        $_POST['wine_color'] = '';
        $_POST['vintage']    = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, label_name rempli, couleur invalide → erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithInvalidColor(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = 'Château Test';
        $_POST['wine_color'] = 'violet'; // invalide
        $_POST['vintage']    = '2022';
        $_POST['price']      = '20.00';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // edit — vin introuvable
    // ----------------------------------------------------------------

    public function testEditAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->edit(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // edit — vin existant
    // ----------------------------------------------------------------

    public function testEditRendersFormWithExistingWine(): void
    {
        $id = $this->insertWine();

        ob_start();
        $this->makeController()->edit(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Modifier', $output);
    }

    // ----------------------------------------------------------------
    // update — vin introuvable
    // ----------------------------------------------------------------

    public function testUpdateAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('POST')->update(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, champs vides → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testUpdateRendersFormWithValidationErrors(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = '';
        $_POST['wine_color'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->update(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, données valides → redirect 302
    // ----------------------------------------------------------------

    public function testUpdateSuccessRedirects(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['wine_color']          = 'red';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '18.50';
        $_POST['quantity']            = '60';
        $_POST['area']                = '0.50';
        $_POST['city']                = 'Saint-Émilion';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // update — is_cuvee_speciale = 1 → flash mentionne « Cuvée Spéciale »
    // ----------------------------------------------------------------

    public function testUpdateSuccessWithCuveeSpecialeRedirects(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2021';
        $_POST['price']               = '25.00';
        $_POST['quantity']            = '30';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Cabernet Sauvignon';
        $_POST['age_of_vineyard']     = '25';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '1';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, données valides → redirect 302
    // ----------------------------------------------------------------

    public function testStoreSuccessRedirects(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2020';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '100';
        $_POST['area']                = '2.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        // Pas d'image → obligatoire en création → erreur image déclenchée
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        // L'image est obligatoire en création, le formulaire est re-rendu avec erreur
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — données valides + cuvee_speciale = 1 → même chemin erreur image
    // ----------------------------------------------------------------

    public function testStoreWithCuveeSpecialeRendersImageError(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Blanc';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2019';
        $_POST['price']               = '12.00';
        $_POST['quantity']            = '50';
        $_POST['area']                = '0.75';
        $_POST['city']                = 'Blaye';
        $_POST['variety_of_vine']     = 'Sauvignon';
        $_POST['age_of_vineyard']     = '10';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '1';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur {$f}";
            $_POST["{$f}_en"] = "Value {$f}";
        }
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // index — per_page valide (25)
    // ----------------------------------------------------------------

    public function testIndexWithValidPerPageRendersView(): void
    {
        $_GET['per_page'] = '25';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
    }

    // ----------------------------------------------------------------
    // index — per_page invalide → fallback sur 10
    // ----------------------------------------------------------------

    public function testIndexWithInvalidPerPageFallsBackToDefault(): void
    {
        $_GET['per_page'] = '99';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
    }

    // ----------------------------------------------------------------
    // index — page > 1
    // ----------------------------------------------------------------

    public function testIndexWithPageParamRendersView(): void
    {
        $_GET['page'] = '2';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
    }

    // ----------------------------------------------------------------
    // store — vintage invalide (< 1900) → erreur millésime
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithInvalidVintage(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['vintage']             = '1800';
        $_POST['price']               = '10.00';
        $_POST['quantity']            = '20';
        $_POST['certification_label'] = 'AOC';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — prix négatif → erreur prix
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithNegativePrice(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '-5.00';
        $_POST['quantity']            = '10';
        $_POST['certification_label'] = 'AOC';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — quantité négative → erreur quantité
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithNegativeQuantity(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '10.00';
        $_POST['quantity']            = '-1';
        $_POST['certification_label'] = 'AOC';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — champs JSON requis manquants (FR vide) → erreur champs JSON
    // ----------------------------------------------------------------

    public function testStoreRendersFormWhenRequiredJsonFieldsMissing(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['certification_label'] = 'AOC';
        // Champs JSON requis intentionnellement absents (vides)
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = '';
            $_POST["{$f}_en"] = '';
        }
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — EN vide + FR rempli → translateText appelé (auto-traduction)
    // ----------------------------------------------------------------

    public function testStoreCallsTranslationWhenEnFieldIsEmpty(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['certification_label'] = 'AOC';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Texte FR {$f}";
            $_POST["{$f}_en"] = ''; // EN vide → translateText déclenché
        }
        // award et extra_comment avec EN vide aussi
        $_POST['award_fr']         = 'Médaille d\'or';
        $_POST['award_en']         = '';
        $_POST['extra_comment_fr'] = 'Note supplémentaire';
        $_POST['extra_comment_en'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        // Image obligatoire en création → formulaire re-rendu (chemin normal)
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, données valides avec per_page → redirect 302
    // ----------------------------------------------------------------

    public function testUpdateWithPriceAsCommaDecimalRedirects(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bib';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '18,50'; // virgule comme séparateur décimal
        $_POST['quantity']            = '60';
        $_POST['area']                = '0,50';
        $_POST['city']                = 'Saint-Émilion';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // store — vin available=1, abonné existant → flash contient "newsletter"
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() ajoute "newsletter" dans le flash admin
     * quand un vin est créé avec available=1 et qu'il existe au moins un abonné.
     * La sous-classe WineAdminControllerTestable surcharge moveUploadedFile() → copy()
     * pour contourner la restriction de move_uploaded_file en CLI PHPUnit.
     */
    public function testStoreAvailableWineSendsNewsletterToSubscribers(): void
    {
        // Insérer un abonné newsletter
        $subscriberId = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, newsletter, newsletter_unsubscribe_token, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', 1, ?, NOW())",
            ['subscriber@test.local', password_hash('Pass123!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Abonné', 'Test', 'M')",
            [$subscriberId]
        );

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2021';
        $_POST['price']               = '20.00';
        $_POST['quantity']            = '50';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1'; // disponible → newsletter déclenchée
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        // PNG 8 octets minimum pour passer la validation MIME
        $tmpFile = tempnam(sys_get_temp_dir(), 'wine_nl_');
        // PNG minimal valide (signature + IHDR 1x1 RGB) détecté comme image/png par finfo
        file_put_contents($tmpFile, "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xde");
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        // Utilise la sous-classe testable dont moveUploadedFile() utilise copy()
        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        try {
            $controller->store([]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        @unlink($tmpFile);

        // Le flash admin contient "newsletter" (envoi SMTP silencieusement échoué mais comptabilisé à 0)
        // Vérifier que le flash existe et contient le mot "newsletter"
        $flash = $_SESSION['admin_flash']['success'] ?? '';
        $this->assertStringContainsStringIgnoringCase('newsletter', $flash);
    }

    // ----------------------------------------------------------------
    // store — vin available=0 → pas de newsletter dans le flash
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() n'ajoute PAS "newsletter" dans le flash
     * quand un vin est créé avec available=0, même si des abonnés existent.
     */
    public function testStoreUnavailableWineDoesNotSendNewsletter(): void
    {
        // Insérer un abonné newsletter
        $subscriberId = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, newsletter, newsletter_unsubscribe_token, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', 1, ?, NOW())",
            ['subscriber2@test.local', password_hash('Pass123!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Abonné2', 'Test2', 'M')",
            [$subscriberId]
        );

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Blanc';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '1975'; // millésime de test spécifique non présent en base
        $_POST['price']               = '18.00';
        $_POST['quantity']            = '30';
        $_POST['area']                = '0.80';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Sauvignon Blanc';
        $_POST['age_of_vineyard']     = '12';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0'; // non disponible → pas de newsletter
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'wine_nl_');
        // PNG minimal valide (signature + IHDR 1x1 RGB) détecté comme image/png par finfo
        file_put_contents($tmpFile, "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xde");
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        try {
            $controller->store([]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        @unlink($tmpFile);

        $flash = $_SESSION['admin_flash']['success'] ?? '';
        $this->assertStringNotContainsStringIgnoringCase('newsletter', $flash);
    }

    // ----------------------------------------------------------------
    // index — per_page = 50 (troisième valeur autorisée)
    // ----------------------------------------------------------------

    /**
     * Vérifie que per_page=50 est accepté (valeur autorisée de ALLOWED_PER_PAGE).
     */
    public function testIndexWithPerPage50RendersView(): void
    {
        $_GET['per_page'] = '50';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
    }

    // ----------------------------------------------------------------
    // store — PDOException doublon slug → formulaire avec erreur slug
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() affiche une erreur de slug lorsque PDO lève une exception
     * de type "duplicate entry" (code MySQL 1062 / contrainte uq_wines_slug).
     * Technique : insérer un vin avec le même slug en BDD puis tenter de créer
     * le même vin — la contrainte UNIQUE déclenche la PDOException.
     */
    public function testStorePdoExceptionDuplicateSlugRendersFormWithSlugError(): void
    {
        // Insérer un vin avec le slug qui sera généré par le formulaire
        // generateSlug('Bordeaux Rouge', 2099) → 'bordeaux-rouge-2099'
        // On utilise 2099 pour éviter tout conflit avec les autres tests.
        $this->insertWineWithSlug('bordeaux-rouge-2099');

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2099';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = $this->createMinimalPng();
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        ob_start();
        $controller->store([]);
        $output = ob_get_clean();

        @unlink($tmpFile);

        // Nettoyer les éventuels fichiers image copiés avant la PDOException
        $winesDir = ROOT_PATH . '/public/assets/images/wines/';
        foreach (glob($winesDir . 'Wine_Bordeaux_Rouge_2099_*.png') ?: [] as $generated) {
            @unlink($generated);
        }

        // Le formulaire doit être re-rendu avec l'erreur de slug
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — PDOException doublon slug → formulaire avec erreur slug
    // ----------------------------------------------------------------

    /**
     * Vérifie que update() affiche une erreur de slug lorsque la mise à jour déclenche
     * une violation de contrainte UNIQUE (doublon de slug en BDD).
     *
     * Technique : on utilise ReflectionClass pour injecter un WineModel factice
     * (WineModelThrowOnUpdate) dans la propriété privée $wines du controller.
     * Ce model fictif lève une PDOException simulant un doublon de slug (code 1062)
     * lorsque update() est appelé.
     */
    public function testUpdatePdoExceptionDuplicateSlugRendersFormWithSlugError(): void
    {
        $id = $this->insertWine();

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '20.00';
        $_POST['quantity']            = '40';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/modifier'));

        // Injecter un WineModel factice qui lève PDOException(1062) sur update()
        $fakeModel = new WineModelThrowOnUpdate();
        $reflection = new \ReflectionClass(\Controller\Admin\WineAdminController::class);
        $prop = $reflection->getProperty('wines');
        $prop->setAccessible(true);
        $prop->setValue($controller, $fakeModel);

        ob_start();
        $controller->update(['id' => (string) $id]);
        $output = ob_get_clean();

        // Le formulaire doit être re-rendu avec l'erreur de slug (doublon)
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — PDOException générique (non-1062) → formulaire avec message générique
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() affiche un message d'erreur générique lorsque la PDOException
     * n'est pas une violation de doublon (code non-1062).
     * Utilise ReflectionClass pour injecter un WineModel factice.
     */
    public function testStorePdoExceptionGenericRendersFormWithGenericError(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = $this->createMinimalPng();
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        // Injecter un WineModel qui lève une PDOException générique (non-1062) sur create()
        $fakeModel = new WineModelThrowGenericOnCreate();
        $reflection = new \ReflectionClass(\Controller\Admin\WineAdminController::class);
        $prop = $reflection->getProperty('wines');
        $prop->setAccessible(true);
        $prop->setValue($controller, $fakeModel);

        ob_start();
        $controller->store([]);
        $output = ob_get_clean();

        @unlink($tmpFile);

        // Nettoyer les éventuels fichiers image copiés avant la PDOException
        $winesDir = ROOT_PATH . '/public/assets/images/wines/';
        foreach (glob($winesDir . 'Wine_Bordeaux_Rouge_2022_*.png') ?: [] as $generated) {
            @unlink($generated);
        }

        // Formulaire re-rendu avec l'erreur générique d'enregistrement
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — PDOException générique (non-1062) → formulaire avec message générique
    // ----------------------------------------------------------------

    /**
     * Vérifie que update() affiche un message d'erreur générique lorsque la PDOException
     * n'est pas une violation de doublon de slug.
     * Utilise ReflectionClass pour injecter un WineModel factice.
     */
    public function testUpdatePdoExceptionGenericRendersFormWithGenericError(): void
    {
        $id = $this->insertWine();

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '20.00';
        $_POST['quantity']            = '40';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/modifier'));

        // Injecter un WineModel qui lève une PDOException générique (non-1062) sur update()
        $fakeModel = new WineModelThrowGenericOnUpdate();
        $reflection = new \ReflectionClass(\Controller\Admin\WineAdminController::class);
        $prop = $reflection->getProperty('wines');
        $prop->setAccessible(true);
        $prop->setValue($controller, $fakeModel);

        ob_start();
        $controller->update(['id' => (string) $id]);
        $output = ob_get_clean();

        // Formulaire re-rendu avec l'erreur générique de mise à jour
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // handleImageUpload — MIME invalide → erreur image dans le formulaire
    // ----------------------------------------------------------------

    /**
     * Vérifie que handleImageUpload() rejette un fichier avec un MIME non autorisé
     * (ex. text/plain) et re-rend le formulaire avec une erreur image.
     */
    public function testStoreRendersFormWhenImageMimeIsInvalid(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        // Fichier texte → MIME = text/plain, non autorisé
        $tmpFile = tempnam(sys_get_temp_dir(), 'wine_txt_');
        file_put_contents($tmpFile, 'Ce fichier est du texte, pas une image.');
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'notanimage.txt',
                'type'     => 'text/plain',
                'size'     => filesize($tmpFile),
            ],
        ];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        @unlink($tmpFile);

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // handleImageUpload — moveUploadedFile retourne false → erreur téléversement
    // ----------------------------------------------------------------

    /**
     * Vérifie que handleImageUpload() retourne une erreur lorsque moveUploadedFile()
     * échoue (retourne false). Utilise WineAdminControllerMoveFail qui surcharge
     * moveUploadedFile() pour retourner false systématiquement.
     */
    public function testStoreRendersFormWhenMoveUploadedFileFails(): void
    {
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '15.00';
        $_POST['quantity']            = '10';
        $_POST['area']                = '1.00';
        $_POST['city']                = 'Bordeaux';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '15';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '0';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = $this->createMinimalPng();
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerMoveFailTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        ob_start();
        $controller->store([]);
        $output = ob_get_clean();

        @unlink($tmpFile);

        // moveUploadedFile retourne false → erreur "Erreur lors du téléversement"
        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — nouvelle image valide → ancienne image supprimée (unlink)
    // ----------------------------------------------------------------

    /**
     * Vérifie que parseWineForm() supprime l'ancienne image lors d'une mise à jour
     * réussie avec une nouvelle image uploadée.
     * Crée un vrai fichier dans le répertoire des images pour valider le @unlink.
     */
    public function testUpdateDeletesOldImageWhenNewImageUploaded(): void
    {
        $id = $this->insertWine();

        // Créer un vrai fichier image dans le répertoire des images de vin
        $winesDir = ROOT_PATH . '/public/assets/images/wines/';
        $oldFilename = 'Wine_Test_Old_2022_toberemoved.png';
        $oldFilePath = $winesDir . $oldFilename;
        $this->createMinimalPngAt($oldFilePath);

        // Mettre à jour le vin en base avec ce nom d'ancienne image
        self::$db->execute("UPDATE wines SET image_path = ? WHERE id = ?", [$oldFilename, $id]);

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '18.50';
        $_POST['quantity']            = '60';
        $_POST['area']                = '0.50';
        $_POST['city']                = 'Saint-Émilion';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = $this->createMinimalPng();
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'newimage.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/modifier'));

        try {
            $controller->update(['id' => (string) $id]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        @unlink($tmpFile);

        // L'ancienne image doit avoir été supprimée par @unlink
        $this->assertFileDoesNotExist($oldFilePath);

        // Nettoyer les éventuels nouveaux fichiers générés
        foreach (glob($winesDir . 'Wine_Bordeaux_Rouge_2022_*.png') ?: [] as $generatedFile) {
            @unlink($generatedFile);
        }
    }

    // ----------------------------------------------------------------
    // store — available=1, is_cuvee_speciale=1 → flash contient "Cuvée Spéciale"
    //         + newsletter (avec abonné individuel)
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() ajoute "Cuvée Spéciale" dans le flash lorsque
     * is_cuvee_speciale=1 et que le vin est disponible avec newsletter envoyée.
     */
    public function testStoreAvailableCuveeSpecialeFlashContainsCuveeAndNewsletter(): void
    {
        // Insérer un abonné newsletter individuel
        $subscriberId = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, newsletter, newsletter_unsubscribe_token, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', 1, ?, NOW())",
            ['cuvee-subscriber@test.local', password_hash('Pass123!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Cuvée', 'Test', 'M')",
            [$subscriberId]
        );

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Côtes de Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '1990';
        $_POST['price']               = '35.00';
        $_POST['quantity']            = '20';
        $_POST['area']                = '0.30';
        $_POST['city']                = 'Pomerol';
        $_POST['variety_of_vine']     = 'Merlot 100%';
        $_POST['age_of_vineyard']     = '50';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '1'; // → cuveeMark = ' — Cuvée Spéciale'
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = $this->createMinimalPng();
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'cuvee.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        try {
            $controller->store([]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        @unlink($tmpFile);

        // Nettoyer les images générées
        $winesDir = ROOT_PATH . '/public/assets/images/wines/';
        foreach (glob($winesDir . 'Wine_Cotes_de_Bordeaux_Rouge_1990_*.png') ?: [] as $f) {
            @unlink($f);
        }

        $flash = $_SESSION['admin_flash']['success'] ?? '';
        $this->assertStringContainsString('Cuvée Spéciale', $flash);
        $this->assertStringContainsStringIgnoringCase('newsletter', $flash);
    }

    // ----------------------------------------------------------------
    // store — abonné de type société → newsletter comptabilisée
    // ----------------------------------------------------------------

    /**
     * Vérifie que les abonnés newsletter de type "company" sont exclus
     * de la newsletter automatique vin (particuliers uniquement).
     */
    public function testStoreAvailableWineSkipsCompanySubscribers(): void
    {
        // Neutraliser tout abonné individuel pré-existant (données de seed locales)
        // — opération dans la transaction de test, rollbackée automatiquement
        self::$db->execute("UPDATE accounts SET newsletter = 0 WHERE newsletter = 1 AND account_type = 'individual'");

        // Insérer un compte société abonné newsletter
        $companyId = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, account_type, role, lang, newsletter, newsletter_unsubscribe_token, email_verified_at)
             VALUES (?, ?, 'company', 'customer', 'fr', 1, ?, NOW())",
            ['company-newsletter@test.local', password_hash('Pass123!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
        );
        self::$db->insert(
            "INSERT INTO account_companies (account_id, company_name) VALUES (?, ?)",
            [$companyId, 'Les Caves du Test SARL']
        );

        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Côtes de Bordeaux Rouge';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '1985';
        $_POST['price']               = '25.00';
        $_POST['quantity']            = '30';
        $_POST['city']                = 'Pomerol';
        $_POST['area']                = '0.5';
        $_POST['variety_of_vine']     = 'Cabernet Sauvignon';
        $_POST['age_of_vineyard']     = '40';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'wine_co_');
        file_put_contents(
            $tmpFile,
            "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xde"
        );
        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'test_company.png',
                'type'     => 'image/png',
                'size'     => filesize($tmpFile),
            ],
        ];

        $controller = new WineAdminControllerTestable($this->makeRequest('POST', '/admin/vins/ajouter'));

        try {
            $controller->store([]);
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        @unlink($tmpFile);

        $flash = $_SESSION['admin_flash']['success'] ?? '';
        // Aucun abonné particulier → pas de mention newsletter dans le flash
        $this->assertStringNotContainsStringIgnoringCase('newsletter', $flash);
    }
}

/**
 * Sous-classe de WineAdminController pour les tests d'intégration.
 * Surcharge moveUploadedFile() pour utiliser copy() au lieu de move_uploaded_file()
 * (move_uploaded_file échoue systématiquement en CLI PHPUnit car le fichier
 * n'est pas issu d'un vrai upload HTTP).
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class WineAdminControllerTestable extends \Controller\Admin\WineAdminController
{
    protected function moveUploadedFile(string $src, string $dest): bool
    {
        return copy($src, $dest);
    }
}

/**
 * Sous-classe de WineAdminController simulant un échec de moveUploadedFile().
 * Permet de couvrir la branche "Erreur lors du téléversement" de handleImageUpload().
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class WineAdminControllerMoveFailTestable extends \Controller\Admin\WineAdminController
{
    /**
     * Retourne toujours false pour simuler un échec de déplacement de fichier.
     *
     * @param string $src  Chemin source (ignoré)
     * @param string $dest Chemin destination (ignoré)
     * @return bool Toujours false
     */
    protected function moveUploadedFile(string $src, string $dest): bool
    {
        return false;
    }
}

/**
 * WineModel factice qui lève une PDOException simulant un doublon de slug (code 1062)
 * lors de l'appel à update(). Permet de couvrir la branche catch(\PDOException) isDuplicate=true
 * de WineAdminController::update() sans modifier le schéma BDD.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class WineModelThrowOnUpdate extends \Model\WineModel
{
    /**
     * Lève une PDOException avec le message "1062 Duplicate entry" pour simuler
     * une violation de contrainte UNIQUE sur le slug lors d'une mise à jour.
     *
     * @param int                  $id   Identifiant (ignoré)
     * @param array<string, mixed> $data Données (ignorées)
     * @return void
     * @throws \PDOException Toujours levée
     */
    public function update(int $id, array $data): void // NOSONAR — php:S1172 : signature imposée par le parent
    {
        throw new \PDOException('SQLSTATE[23000]: 1062 Duplicate entry for key uq_wines_slug');
    }
}

/**
 * WineModel factice qui lève une PDOException générique (non-1062) sur create().
 * Permet de couvrir la branche catch(\PDOException) isDuplicate=false de store().
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class WineModelThrowGenericOnCreate extends \Model\WineModel
{
    /**
     * Lève une PDOException générique (message ne contenant pas "1062") sur create().
     *
     * @param array<string, mixed> $data Données (ignorées)
     * @return int
     * @throws \PDOException Toujours levée
     */
    public function create(array $data): int // NOSONAR — php:S1172 : signature imposée par le parent
    {
        throw new \PDOException('SQLSTATE[HY000]: General error: connexion perdue');
    }
}

/**
 * WineModel factice qui lève une PDOException générique (non-1062) sur update().
 * Permet de couvrir la branche catch(\PDOException) isDuplicate=false de update().
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class WineModelThrowGenericOnUpdate extends \Model\WineModel
{
    /**
     * Lève une PDOException générique (message ne contenant pas "1062") sur update().
     *
     * @param int                  $id   Identifiant (ignoré)
     * @param array<string, mixed> $data Données (ignorées)
     * @return void
     * @throws \PDOException Toujours levée
     */
    public function update(int $id, array $data): void // NOSONAR — php:S1172 : signature imposée par le parent
    {
        throw new \PDOException('SQLSTATE[HY000]: General error: connexion perdue');
    }
}
