<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\WineModel;

class WineAdminController extends AdminController
{
    /**
     * Appellation → wine_color (couleur déterminée par l'appellation, pas d'autre choix).
     *
     * @var array<string, string>
     */
    private const APPELLATIONS = [
        'Sainte-Croix-du-Mont'              => 'sweet',
        'Premières Côtes de Bordeaux Blanc' => 'sweet',
        "Vin de Pays de l'Atlantique"       => 'rosé',
        'Bordeaux Blanc'                    => 'white',
        'Côtes de Bordeaux Rouge'           => 'red',
        'Bordeaux Rouge'                    => 'red',
    ];

    /** @var string[] */
    private const CERT_LABELS = ['AOC', 'IGP', 'STG', 'AOP'];

    private const ADMIN_BASE       = '/admin';
    private const ADMIN_URL        = '/admin/vins';
    private const FORM_VIEW        = 'admin/wines/form';
    private const ALLOWED_PER_PAGE = [10, 25, 50];
    private const DEFAULT_PER_PAGE = 10;

    /** @var string[] */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var array<string, string> */
    private const MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @var string[] */
    private const JSON_REQUIRED = ['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'];

    /** @var array<string, string> */
    private const JSON_LABELS = [
        'oenological_comment' => 'Commentaire œnologique',
        'soil'                => 'Sol',
        'pruning'             => 'Taille',
        'harvest'             => 'Vendanges',
        'vinification'        => 'Vinification',
        'barrel_fermentation' => 'Élevage / Barrique',
    ];

    private WineModel $wines;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->wines = new WineModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/vins
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser  = $this->requireAdmin();
        $page       = max(1, (int) $this->request->get('page', 1));
        $color      = $this->request->get('color') ?: null;
        $available  = $this->request->get('available') ?: null;
        $perPageReq = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage    = in_array($perPageReq, self::ALLOWED_PER_PAGE, true) ? $perPageReq : self::DEFAULT_PER_PAGE;

        $total = $this->wines->countForAdmin($color, $available);
        $wines = $this->wines->getForAdmin($color, $available, $perPage, ($page - 1) * $perPage);

        $this->view('admin/wines/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Gestion des vins',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => self::ADMIN_BASE], ['label' => 'Vins']],
            'wines'        => $wines,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'color'        => $color,
            'available'    => $available,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/vins/ajouter
    // ----------------------------------------------------------------

    public function create(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $this->view(self::FORM_VIEW, [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Ajouter un vin',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => self::ADMIN_BASE],
                ['label' => 'Vins', 'url' => self::ADMIN_URL],
                ['label' => 'Ajouter'],
            ],
            'wine'         => null,
            'errors'       => [],
            'csrfToken'    => $_SESSION['csrf'] ?? '',
            'appellations' => self::APPELLATIONS,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/vins/ajouter
    // ----------------------------------------------------------------

    public function store(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/vins/ajouter');
        }

        [$data, $errors] = $this->parseWineForm(null);

        if ($errors !== []) {
            $this->view(self::FORM_VIEW, [
                'adminUser'    => $adminUser,
                'adminSection' => 'wines',
                'pageTitle'    => 'Ajouter un vin',
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => self::ADMIN_BASE],
                    ['label' => 'Vins', 'url' => self::ADMIN_URL],
                    ['label' => 'Ajouter'],
                ],
                'wine'         => $data,
                'errors'       => $errors,
                'csrfToken'    => $_SESSION['csrf'] ?? '',
                'appellations' => self::APPELLATIONS,
            ]);
            return;
        }

        $this->wines->create($data);
        $cuveeMark = $data['is_cuvee_speciale'] ? ' — Cuvée Spéciale' : '';
        $this->flash('success', "Vin « {$data['label_name']} {$data['vintage']}{$cuveeMark} » ajouté avec succès.");
        Response::redirect(self::ADMIN_URL);
    }

    // ----------------------------------------------------------------
    // GET /admin/vins/{id}/modifier
    // ----------------------------------------------------------------

    public function edit(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $wine      = $this->wines->getById((int) $params['id']);

        if (!$wine) {
            $this->abort(404, 'Vin introuvable');
        }

        $this->view(self::FORM_VIEW, [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Modifier — ' . $wine['label_name'],
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => self::ADMIN_BASE],
                ['label' => 'Vins', 'url' => self::ADMIN_URL],
                ['label' => 'Modifier'],
            ],
            'wine'         => $wine,
            'errors'       => [],
            'csrfToken'    => $_SESSION['csrf'] ?? '',
            'appellations' => self::APPELLATIONS,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/vins/{id}/modifier
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $id        = (int) $params['id'];
        $wine      = $this->wines->getById($id);

        if (!$wine) {
            $this->abort(404, 'Vin introuvable');
        }

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect("/admin/vins/{$id}/modifier");
        }

        [$data, $errors] = $this->parseWineForm($wine);

        if ($errors !== []) {
            $this->view(self::FORM_VIEW, [
                'adminUser'    => $adminUser,
                'adminSection' => 'wines',
                'pageTitle'    => 'Modifier — ' . $wine['label_name'],
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => self::ADMIN_BASE],
                    ['label' => 'Vins', 'url' => self::ADMIN_URL],
                    ['label' => 'Modifier'],
                ],
                'wine'         => array_merge($wine, $data),
                'errors'       => $errors,
                'csrfToken'    => $_SESSION['csrf'] ?? '',
                'appellations' => self::APPELLATIONS,
            ]);
            return;
        }

        $this->wines->update($id, $data);
        $cuveeMark = $data['is_cuvee_speciale'] ? ' — Cuvée Spéciale' : '';
        $this->flash('success', "Vin « {$data['label_name']} {$data['vintage']}{$cuveeMark} » mis à jour avec succès.");
        Response::redirect(self::ADMIN_URL);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Valide et construit le tableau de données depuis POST + $_FILES.
     *
     * @param array<string, mixed>|null $existing  Données actuelles en BDD (modifier)
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function parseWineForm(?array $existing): array // NOSONAR — php:S3776 : complexité cognitive nécessaire, champs formulaire vin
    {
        $r      = $this->request;
        $errors = [];

        // ---- Appellation → détermine label_name + wine_color ----
        $appellation = $r->post('appellation', '');
        if (!array_key_exists($appellation, self::APPELLATIONS)) {
            $errors['appellation'] = 'Appellation invalide.';
        }
        $labelName = $appellation;
        $wineColor = self::APPELLATIONS[$appellation] ?? '';

        // ---- Champs simples ----
        $format    = $r->post('format', 'bottle');
        $vintage   = (int) $r->post('vintage', 0);
        $price     = (float) str_replace(',', '.', $r->post('price', '0'));
        $quantity  = (int) $r->post('quantity', 0);
        $available = $r->post('available', '0') === '1' ? 1 : 0;
        $certLabel = trim($r->post('certification_label', ''));
        $area      = (float) str_replace(',', '.', $r->post('area', '0'));
        $city      = trim($r->post('city', ''));
        $variety   = trim($r->post('variety_of_vine', ''));
        $age       = (int) $r->post('age_of_vineyard', 0);
        $isCuvee   = $r->post('is_cuvee_speciale', '0') === '1' ? 1 : 0;

        if ($vintage < 1900 || $vintage > (int) date('Y')) {
            $errors['vintage'] = 'Millésime invalide (1900 – ' . date('Y') . ').';
        }
        if ($price <= 0) {
            $errors['price'] = 'Prix invalide.';
        }
        if ($quantity < 0) {
            $errors['quantity'] = 'Quantité invalide.';
        }
        if (!in_array($certLabel, self::CERT_LABELS, true)) {
            $errors['certification_label'] = 'Appellation invalide. Valeurs autorisées : ' . implode(', ', self::CERT_LABELS) . '.';
        }
        // Slug : toujours auto en création, conservé en modification
        $slug = $existing !== null
            ? ($existing['slug'] ?? $this->generateSlug($labelName, $vintage))
            : $this->generateSlug($labelName, $vintage);

        // ---- Upload image ----
        ['path' => $imagePath, 'error' => $imgError] = $this->handleImageUpload(
            $appellation,
            $vintage,
            $existing['image_path'] ?? null,
            $existing === null
        );
        if ($imgError !== null) {
            $errors['image'] = $imgError;
        }

        // Supprime l'ancienne photo si une nouvelle a été uploadée avec succès
        if (
            $imgError === null
            && $imagePath !== ($existing['image_path'] ?? '')
            && !empty($existing['image_path'])
        ) {
            $oldFile = ROOT_PATH . '/public/assets/images/wines/' . $existing['image_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        // ---- Champs JSON bilingues ----
        $jsonFields = [
            'oenological_comment', 'soil', 'pruning', 'harvest',
            'vinification', 'barrel_fermentation', 'award', 'extra_comment',
        ];
        $jsonData = [];
        foreach ($jsonFields as $field) {
            $fr = trim($r->post($field . '_fr', ''));
            $en = trim($r->post($field . '_en', ''));

            if (in_array($field, self::JSON_REQUIRED, true) && $fr === '') {
                $errors[$field . '_fr'] = self::JSON_LABELS[$field] . ' (FR) est obligatoire.';
            }

            if ($fr !== '' && $en === '') {
                $en = $this->translateText($fr);
            }

            $jsonData[$field] = json_encode(['fr' => $fr, 'en' => $en]);
        }

        $data = array_merge([
            'label_name'          => $labelName,
            'wine_color'          => $wineColor,
            'format'              => $format,
            'vintage'             => $vintage,
            'price'               => $price,
            'quantity'            => $quantity,
            'available'           => $available,
            'certification_label' => $certLabel,
            'area'                => $area,
            'city'                => $city,
            'variety_of_vine'     => $variety,
            'age_of_vineyard'     => $age,
            'is_cuvee_speciale'   => $isCuvee,
            'image_path'          => $imagePath,
            'slug'                => $slug,
        ], $jsonData);

        return [$data, $errors];
    }

    /**
     * Gère l'upload d'une image vin.
     * Valide le MIME, génère un nom unique (Wine_{appellation}_{vintage}_{token}.{ext}).
     *
     * @return array{path: string, error: string|null}
     */
    private function handleImageUpload( // NOSONAR — php:S1142 : early returns sur validation MIME/upload sont intentionnels
        string $appellation,
        int $vintage,
        ?string $existingPath,
        bool $required
    ): array {
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
            return ['path' => $existingPath ?? '', 'error' => 'Format non autorisé (jpg, png, webp uniquement).'];
        }

        $ext      = self::MIME_EXT[$mimeType];
        $safeName = preg_replace('/[^a-zA-Z0-9]+/', '_', $appellation) ?? 'wine';
        $safeName = trim($safeName, '_');
        $destDir  = ROOT_PATH . '/public/assets/images/wines/';

        // Génère un nom unique avec token
        do {
            $token    = bin2hex(random_bytes(8));
            $filename = "Wine_{$safeName}_{$vintage}_{$token}.{$ext}";
        } while (file_exists($destDir . $filename));

        if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
            return ['path' => $existingPath ?? '', 'error' => 'Erreur lors du téléversement.'];
        }

        return ['path' => $filename, 'error' => null];
    }

    private function generateSlug(string $name, int $vintage): string
    {
        $slug = strtolower($name . '-' . $vintage);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
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
            // L'API MyMemory est optionnelle — on retourne le texte original en cas d'échec
        }
        return $text;
    }
}
