<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\NewsModel;

class NewsAdminController extends AdminController
{
    private const PER_PAGE        = 15;
    private const ALLOWED_MIME    = ['image/jpeg', 'image/png', 'image/webp'];
    private const MIME_EXT        = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private NewsModel $news;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->news = new NewsModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/actualites
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $page      = max(1, (int) $this->request->get('page', 1));
        $total     = $this->news->countForAdmin();
        $articles  = $this->news->getForAdmin(self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->view('admin/news/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'news',
            'pageTitle'    => 'Actualités',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Actualités']],
            'articles'     => $articles,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/actualites/ajouter
    // ----------------------------------------------------------------

    public function create(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/news/form', [
            'adminUser'    => $adminUser,
            'adminSection' => 'news',
            'pageTitle'    => 'Ajouter un article',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Actualités', 'url' => '/admin/actualites'],
                ['label' => 'Ajouter'],
            ],
            'article'   => null,
            'errors'    => [],
            'csrfToken' => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/actualites/ajouter
    // ----------------------------------------------------------------

    public function store(array $params): void
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/actualites/ajouter');
        }

        [$data, $errors] = $this->parseNewsForm(null);

        if ($errors !== []) {
            $this->view('admin/news/form', [
                'adminUser'    => $adminUser,
                'adminSection' => 'news',
                'pageTitle'    => 'Ajouter un article',
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => '/admin'],
                    ['label' => 'Actualités', 'url' => '/admin/actualites'],
                    ['label' => 'Ajouter'],
                ],
                'article'   => $data,
                'errors'    => $errors,
                'csrfToken' => $_SESSION['csrf'] ?? '',
            ]);
            return;
        }

        $this->news->create($data);
        $titleFr = json_decode($data['title'], true)['fr'] ?? '';
        $this->flash('success', "Article « {$titleFr} » ajouté avec succès.");
        Response::redirect('/admin/actualites');
    }

    // ----------------------------------------------------------------
    // GET /admin/actualites/{id}/modifier
    // ----------------------------------------------------------------

    public function edit(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $article   = $this->news->getById((int) $params['id']);

        if (!$article) {
            $this->abort(404, 'Article introuvable');
        }

        $this->view('admin/news/form', [
            'adminUser'    => $adminUser,
            'adminSection' => 'news',
            'pageTitle'    => 'Modifier un article',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Actualités', 'url' => '/admin/actualites'],
                ['label' => 'Modifier'],
            ],
            'article'   => $article,
            'errors'    => [],
            'csrfToken' => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/actualites/{id}/modifier
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $id        = (int) $params['id'];
        $article   = $this->news->getById($id);

        if (!$article) {
            $this->abort(404, 'Article introuvable');
        }

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect("/admin/actualites/{$id}/modifier");
        }

        [$data, $errors] = $this->parseNewsForm($article);

        if ($errors !== []) {
            $this->view('admin/news/form', [
                'adminUser'    => $adminUser,
                'adminSection' => 'news',
                'pageTitle'    => 'Modifier un article',
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => '/admin'],
                    ['label' => 'Actualités', 'url' => '/admin/actualites'],
                    ['label' => 'Modifier'],
                ],
                'article'   => array_merge($article, $data),
                'errors'    => $errors,
                'csrfToken' => $_SESSION['csrf'] ?? '',
            ]);
            return;
        }

        $this->news->update($id, $data);
        $titleFr = json_decode($data['title'], true)['fr'] ?? '';
        $this->flash('success', "Article « {$titleFr} » mis à jour avec succès.");
        Response::redirect('/admin/actualites');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $existing
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function parseNewsForm(?array $existing): array
    {
        $r      = $this->request;
        $errors = [];

        $titleFr   = trim($r->post('title_fr', ''));
        $titleEn   = trim($r->post('title_en', ''));
        $contentFr = trim($r->post('text_content_fr', ''));
        $contentEn = trim($r->post('text_content_en', ''));
        $linkPath  = trim($r->post('link_path', ''));

        if ($titleFr === '') {
            $errors['title_fr'] = 'Le titre (FR) est obligatoire.';
        }
        if ($contentFr === '') {
            $errors['text_content_fr'] = 'Le contenu (FR) est obligatoire.';
        }

        // Auto-translation EN via MyMemory if empty
        if ($titleFr !== '' && $titleEn === '') {
            $titleEn = $this->translateText($titleFr);
        }
        if ($contentFr !== '' && $contentEn === '') {
            $contentEn = $this->translateText($contentFr);
        }

        // Slug : généré à la création, conservé à la modification
        $slug = $existing !== null
            ? ($existing['slug'] ?? $this->generateSlug($titleFr))
            : $this->generateSlug($titleFr);

        // Upload image
        ['path' => $imagePath, 'error' => $imgError] = $this->handleImageUpload(
            $titleFr,
            $existing['image_path'] ?? null,
            $existing === null
        );
        if ($imgError !== null) {
            $errors['image'] = $imgError;
        }

        // Supprime l'ancienne photo si une nouvelle est uploadée
        if (
            $imgError === null
            && $imagePath !== ($existing['image_path'] ?? '')
            && !empty($existing['image_path'])
        ) {
            $oldFile = ROOT_PATH . '/public/assets/images/news/' . $existing['image_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $data = [
            'title'        => json_encode(['fr' => $titleFr, 'en' => $titleEn]),
            'text_content' => json_encode(['fr' => $contentFr, 'en' => $contentEn]),
            'image_path'   => $imagePath,
            'link_path'    => $linkPath,
            'slug'         => $slug,
        ];

        return [$data, $errors];
    }

    /**
     * @return array{path: string, error: string|null}
     */
    private function handleImageUpload(string $title, ?string $existingPath, bool $required): array
    {
        $file = $_FILES['image'] ?? [];

        if (empty($file['tmp_name'])) {
            if ($required) {
                return ['path' => '', 'error' => "L'image est obligatoire."];
            }
            return ['path' => $existingPath ?? '', 'error' => null];
        }

        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            return ['path' => $existingPath ?? '', 'error' => 'Format non autorisé (jpg, png, webp).'];
        }

        $ext      = self::MIME_EXT[$mimeType];
        $safeName = preg_replace('/[^a-zA-Z0-9]+/', '_', $title) ?? 'news';
        $safeName = trim(substr($safeName, 0, 40), '_');
        $destDir  = ROOT_PATH . '/public/assets/images/news/';

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        do {
            $token    = bin2hex(random_bytes(8));
            $filename = "News_{$safeName}_{$token}.{$ext}";
        } while (file_exists($destDir . $filename));

        if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
            return ['path' => $existingPath ?? '', 'error' => 'Erreur lors du téléversement.'];
        }

        return ['path' => $filename, 'error' => null];
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim(substr($slug, 0, 80), '-');
    }

    /**
     * Traduit un texte FR → EN via MyMemory.
     * Retourne le texte original si l'API échoue.
     */
    private function translateText(string $text): string
    {
        $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=fr|en';
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        try {
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return $text;
            }
            $data = json_decode($raw, true);
            if (
                is_array($data)
                && ($data['responseStatus'] ?? 0) === 200
                && !empty($data['responseData']['translatedText'])
            ) {
                return (string) $data['responseData']['translatedText'];
            }
        } catch (\Throwable) {
        }
        return $text;
    }
}
