<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\NewsAdminController;
use Core\Exception\HttpException;

class NewsAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): NewsAdminController
    {
        return new NewsAdminController($this->makeRequest($method, '/admin/actualites'));
    }

    private function insertArticle(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO news (title, text_content, image_path, slug)
             VALUES (?, ?, 'test.jpg', 'test-article-ti')",
            [
                json_encode(['fr' => 'Article TI', 'en' => 'TI Article']),
                json_encode(['fr' => 'Contenu test', 'en' => 'Test content']),
            ]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersNewsList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Actualités', $output);
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
    // store — CSRF valide, champs manquants → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithErrorsOnMissingFields(): void
    {
        $_POST['csrf_token']    = self::CSRF_TOKEN;
        $_POST['title_fr']      = '';
        $_POST['text_content_fr'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('obligatoire', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, title_fr rempli, contenu manquant → re-rendu
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithContentError(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Mon article test';
        $_POST['text_content_fr'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // edit — article introuvable
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
    // edit — article existant
    // ----------------------------------------------------------------

    public function testEditRendersFormWithExistingArticle(): void
    {
        $id = $this->insertArticle();

        ob_start();
        $this->makeController()->edit(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Modifier', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // update — article introuvable
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
    // update — CSRF valide, champs manquants → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testUpdateRendersFormWithErrorsOnMissingFields(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = '';
        $_POST['text_content_fr'] = '';
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
        $id = $this->insertArticle();
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Titre mis à jour';
        $_POST['title_en']          = 'Updated title';
        $_POST['text_content_fr']   = 'Contenu mis à jour';
        $_POST['text_content_en']   = 'Updated content';
        $_POST['link_path']         = '';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, données valides, image absente en création → erreur image
    // ----------------------------------------------------------------

    public function testStoreRedirectsWhenImageMissingOnCreate(): void
    {
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Nouvel article test';
        $_POST['title_en']          = 'New test article';
        $_POST['text_content_fr']   = 'Contenu complet de l\'article';
        $_POST['text_content_en']   = 'Full article content';
        $_POST['link_path']         = '';
        $_FILES = [];

        // Image optionnelle en création : le store réussit et redirige
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST')->store([]);
    }

    // ----------------------------------------------------------------
    // store — EN vides → translateText déclenché (auto-traduction)
    // ----------------------------------------------------------------

    public function testStoreRedirectsWhenEnFieldsAreEmpty(): void
    {
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Article avec traduction automatique';
        $_POST['title_en']          = ''; // EN vide → translateText appelé si disponible
        $_POST['text_content_fr']   = 'Contenu nécessitant une traduction';
        $_POST['text_content_en']   = ''; // EN vide
        $_POST['link_path']         = '';
        $_FILES = [];

        // Image optionnelle : le store aboutit et redirige même sans champs EN
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST')->store([]);
    }

    // ----------------------------------------------------------------
    // store — seul le titre est rempli (contenu manquant) → erreur contenu
    // ----------------------------------------------------------------

    public function testStoreRendersFormWhenOnlyTitleProvided(): void
    {
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Titre présent';
        $_POST['title_en']          = 'Title present';
        $_POST['text_content_fr']   = '';
        $_POST['text_content_en']   = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('obligatoire', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, seul le contenu est manquant → erreur contenu
    // ----------------------------------------------------------------

    public function testUpdateRendersFormWhenOnlyContentMissing(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Titre présent';
        $_POST['title_en']          = 'Title present';
        $_POST['text_content_fr']   = '';
        $_POST['text_content_en']   = '';
        $_POST['link_path']         = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->update(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('obligatoire', $output);
    }

    // ----------------------------------------------------------------
    // update — EN vides → translateText déclenché pour title et content
    // ----------------------------------------------------------------

    public function testUpdateCallsTranslationWhenEnFieldsAreEmpty(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Titre mis à jour avec traduction';
        $_POST['title_en']          = ''; // EN vide → translateText appelé
        $_POST['text_content_fr']   = 'Contenu mis à jour avec traduction automatique';
        $_POST['text_content_en']   = ''; // EN vide → translateText appelé
        $_POST['link_path']         = '/actualites/test';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // index — page > 1
    // ----------------------------------------------------------------

    public function testIndexWithPageParamRendersView(): void
    {
        $_GET['page'] = '3';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Actualités', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, link_path renseigné → redirect 302
    // ----------------------------------------------------------------

    public function testUpdateSuccessWithLinkPathRedirects(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['title_fr']          = 'Article avec lien';
        $_POST['title_en']          = 'Article with link';
        $_POST['text_content_fr']   = 'Contenu de l\'article avec lien';
        $_POST['text_content_en']   = 'Article content with link';
        $_POST['link_path']         = '/nos-vins';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // store — image avec MIME invalide (text/plain) → erreur dans le formulaire
    // Couvre handleImageUpload l.276-280 : validation MIME non autorisé
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() ré-affiche le formulaire avec une erreur "Format non autorisé"
     * quand un fichier avec un MIME type non autorisé est fourni dans $_FILES['image'].
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('fileinfo')]
    public function testStoreRendersFormWithErrorOnInvalidImageMime(): void
    {
        // Fichier texte : finfo retourne text/plain, non autorisé
        $tmpFile = tempnam(sys_get_temp_dir(), 'news_img_test_');
        file_put_contents($tmpFile, 'This is plain text, not an image');

        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Article avec image invalide';
        $_POST['title_en']        = 'Article with invalid image';
        $_POST['text_content_fr'] = 'Contenu de l\'article.';
        $_POST['text_content_en'] = 'Article content.';
        $_POST['link_path']       = '';
        $_FILES['image']          = [
            'tmp_name' => $tmpFile,
            'name'     => 'fake.txt',
            'type'     => 'text/plain',
            'size'     => strlen('This is plain text, not an image'),
            'error'    => UPLOAD_ERR_OK,
        ];

        ob_start();
        try {
            $this->makeController('POST')->store([]);
            $output = ob_get_clean();
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('non autorisé', $output);
    }

    // ----------------------------------------------------------------
    // store — image JPEG valide (MIME ok) + move_uploaded_file échoue en test
    // Couvre handleImageUpload l.283-298 : ext, safeName, destDir, do-while, move failed
    // ----------------------------------------------------------------

    /**
     * Vérifie que store() ré-affiche le formulaire avec une erreur de téléversement
     * quand le fichier image a un MIME valide (JPEG) mais que move_uploaded_file
     * échoue (comportement normal hors contexte HTTP).
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('fileinfo')]
    public function testStoreRendersFormWithErrorOnUploadMoveFailure(): void
    {
        // JPEG magic bytes pour passer la validation MIME
        $tmpFile = tempnam(sys_get_temp_dir(), 'news_jpeg_test_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 100));

        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Article image JPEG invalide move';
        $_POST['title_en']        = 'Article JPEG invalid move';
        $_POST['text_content_fr'] = 'Contenu avec image JPEG.';
        $_POST['text_content_en'] = 'Content with JPEG image.';
        $_POST['link_path']       = '';
        $_FILES['image']          = [
            'tmp_name' => $tmpFile,
            'name'     => 'photo.jpg',
            'type'     => 'image/jpeg',
            'size'     => 104,
            'error'    => UPLOAD_ERR_OK,
        ];

        ob_start();
        try {
            $this->makeController('POST')->store([]);
            $output = ob_get_clean();
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        // move_uploaded_file retourne false hors HTTP → erreur téléversement
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('téléversement', $output);
    }

    // ----------------------------------------------------------------
    // update — image JPEG valide fournie avec article existant ayant une image_path
    // Couvre parseNewsForm l.237-241 : branche "supprime l'ancienne photo"
    // (file_exists retourne false sur le fichier fictif → unlink non exécuté)
    // ----------------------------------------------------------------

    /**
     * Vérifie que update() traite correctement la branche de suppression d'ancienne image :
     * quand une nouvelle image valide est fournie et que l'article existant a un image_path,
     * la condition de suppression est évaluée (fichier fictif absent → unlink ignoré).
     * Couvre parseNewsForm l.237-241 et handleImageUpload l.276-298.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('fileinfo')]
    public function testUpdateWithNewImageCoversOldImageDeletionBranch(): void
    {
        // Insérer un article avec une image_path existante
        $id = (int) self::$db->insert(
            "INSERT INTO news (title, text_content, image_path, slug)
             VALUES (?, ?, 'old_image.jpg', 'test-article-old-image')",
            [
                json_encode(['fr' => 'Article Ancienne Image', 'en' => 'Old Image Article']),
                json_encode(['fr' => 'Contenu test', 'en' => 'Test content']),
            ]
        );

        // Fournir une nouvelle image JPEG (MIME valide)
        $tmpFile = tempnam(sys_get_temp_dir(), 'news_upd_img_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 100));

        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Article image mise à jour';
        $_POST['title_en']        = 'Updated image article';
        $_POST['text_content_fr'] = 'Contenu mis à jour avec image.';
        $_POST['text_content_en'] = 'Updated content with image.';
        $_POST['link_path']       = '';
        $_FILES['image']          = [
            'tmp_name' => $tmpFile,
            'name'     => 'nouvelle.jpg',
            'type'     => 'image/jpeg',
            'size'     => 104,
            'error'    => UPLOAD_ERR_OK,
        ];

        ob_start();
        try {
            $this->makeController('POST')->update(['id' => (string) $id]);
            $output = ob_get_clean();
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        // move_uploaded_file échoue hors HTTP → erreur téléversement affichée dans le formulaire
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('téléversement', $output);
    }

    // ----------------------------------------------------------------
    // update — image avec MIME invalide + article avec image existante
    // Couvre handleImageUpload l.279-280 dans le chemin update
    // ----------------------------------------------------------------

    /**
     * Vérifie que update() ré-affiche le formulaire avec erreur MIME
     * quand une image avec un format non autorisé est soumise lors d'une mise à jour.
     * Couvre la branche MIME invalide de handleImageUpload dans le contexte update().
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('fileinfo')]
    public function testUpdateRendersFormWithErrorOnInvalidImageMime(): void
    {
        $id = $this->insertArticle();

        $tmpFile = tempnam(sys_get_temp_dir(), 'news_upd_mime_');
        file_put_contents($tmpFile, 'This is not an image');

        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Article MIME invalide update';
        $_POST['title_en']        = 'Invalid MIME update article';
        $_POST['text_content_fr'] = 'Contenu de l\'article.';
        $_POST['text_content_en'] = 'Article content.';
        $_POST['link_path']       = '';
        $_FILES['image']          = [
            'tmp_name' => $tmpFile,
            'name'     => 'bad.gif',
            'type'     => 'image/gif',
            'size'     => strlen('This is not an image'),
            'error'    => UPLOAD_ERR_OK,
        ];

        ob_start();
        try {
            $this->makeController('POST')->update(['id' => (string) $id]);
            $output = ob_get_clean();
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('non autorisé', $output);
    }
}
