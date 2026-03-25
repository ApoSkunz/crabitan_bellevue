<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\WineModel;

class WineAdminController extends AdminController
{
    private const PER_PAGE = 20;

    private WineModel $wines;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->wines = new WineModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/vins
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $page   = max(1, (int) $this->request->get('page', 1));
        $color  = $this->request->get('color') ?: null;
        $total  = $this->wines->countForAdmin($color);
        $wines  = $this->wines->getForAdmin($color, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->view('admin/wines/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Gestion des vins',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Vins']],
            'wines'        => $wines,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'color'        => $color,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/vins/ajouter
    // ----------------------------------------------------------------

    public function create(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/wines/form', [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Ajouter un vin',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Vins', 'url' => '/admin/vins'],
                ['label' => 'Ajouter'],
            ],
            'wine'         => null,
            'errors'       => [],
            'csrfToken'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/vins/ajouter
    // ----------------------------------------------------------------

    public function store(array $params): void
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/vins/ajouter');
        }

        [$data, $errors] = $this->parseWineForm(null);

        if ($errors !== []) {
            $this->view('admin/wines/form', [
                'adminUser'    => $adminUser,
                'adminSection' => 'wines',
                'pageTitle'    => 'Ajouter un vin',
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => '/admin'],
                    ['label' => 'Vins', 'url' => '/admin/vins'],
                    ['label' => 'Ajouter'],
                ],
                'wine'      => $data,
                'errors'    => $errors,
                'csrfToken' => $_SESSION['csrf'] ?? '',
            ]);
            return;
        }

        $this->wines->create($data);
        $this->flash('success', 'Vin ajouté avec succès.');
        Response::redirect('/admin/vins');
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

        $this->view('admin/wines/form', [
            'adminUser'    => $adminUser,
            'adminSection' => 'wines',
            'pageTitle'    => 'Modifier — ' . $wine['label_name'],
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Vins', 'url' => '/admin/vins'],
                ['label' => 'Modifier'],
            ],
            'wine'      => $wine,
            'errors'    => [],
            'csrfToken' => $_SESSION['csrf'] ?? '',
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
            $this->view('admin/wines/form', [
                'adminUser'    => $adminUser,
                'adminSection' => 'wines',
                'pageTitle'    => 'Modifier — ' . $wine['label_name'],
                'breadcrumbs'  => [
                    ['label' => 'Admin', 'url' => '/admin'],
                    ['label' => 'Vins', 'url' => '/admin/vins'],
                    ['label' => 'Modifier'],
                ],
                'wine'      => array_merge($wine, $data),
                'errors'    => $errors,
                'csrfToken' => $_SESSION['csrf'] ?? '',
            ]);
            return;
        }

        $this->wines->update($id, $data);
        $this->flash('success', 'Vin mis à jour avec succès.');
        Response::redirect('/admin/vins');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Valide et construit le tableau de données depuis la requête POST.
     *
     * @param array<string, mixed>|null $existing  Données actuelles (pour conserver l'image si non modifiée)
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function parseWineForm(?array $existing): array
    {
        $r      = $this->request;
        $errors = [];

        $labelName   = trim($r->post('label_name', ''));
        $wineColor   = $r->post('wine_color', '');
        $format      = $r->post('format', 'bottle');
        $vintage     = (int) $r->post('vintage', 0);
        $price       = (float) str_replace(',', '.', $r->post('price', '0'));
        $quantity    = (int) $r->post('quantity', 0);
        $available   = $r->post('available', '0') === '1' ? 1 : 0;
        $certLabel   = trim($r->post('certification_label', ''));
        $area        = (float) str_replace(',', '.', $r->post('area', '0'));
        $city        = trim($r->post('city', ''));
        $variety     = trim($r->post('variety_of_vine', ''));
        $age         = (int) $r->post('age_of_vineyard', 0);
        $isCuvee     = $r->post('is_cuvee_speciale', '0') === '1' ? 1 : 0;
        $imagePath   = trim($r->post('image_path', ''));
        $slug        = trim($r->post('slug', ''));

        if ($labelName === '') {
            $errors['label_name'] = 'Le nom est obligatoire.';
        }
        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if (!in_array($wineColor, $validColors, true)) {
            $errors['wine_color'] = 'Couleur invalide.';
        }
        if ($vintage < 1900 || $vintage > (int) date('Y') + 2) {
            $errors['vintage'] = 'Millésime invalide.';
        }
        if ($price <= 0) {
            $errors['price'] = 'Prix invalide.';
        }
        if ($slug === '') {
            $slug = $this->generateSlug($labelName, $vintage);
        }

        // Conserver l'image existante si le champ est vide
        if ($imagePath === '' && $existing !== null) {
            $imagePath = $existing['image_path'] ?? '';
        }

        // JSON bilingues
        $jsonFields = [
            'oenological_comment', 'soil', 'pruning', 'harvest',
            'vinification', 'barrel_fermentation', 'award', 'extra_comment',
        ];
        $jsonData = [];
        foreach ($jsonFields as $field) {
            $fr = trim($r->post($field . '_fr', ''));
            $en = trim($r->post($field . '_en', ''));
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

    private function generateSlug(string $name, int $vintage): string
    {
        $slug = strtolower($name . '-' . $vintage);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }
}
