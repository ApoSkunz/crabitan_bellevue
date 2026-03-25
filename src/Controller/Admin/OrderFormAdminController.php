<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\OrderFormModel;

class OrderFormAdminController extends AdminController
{
    private string $storageDir;

    private OrderFormModel $forms;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->forms      = new OrderFormModel();
        $this->storageDir = ROOT_PATH . '/storage/order_forms';
    }

    // ----------------------------------------------------------------
    // GET /admin/bons-de-commande
    // ----------------------------------------------------------------

    private const DEFAULT_PER_PAGE  = 10;
    private const ALLOWED_PER_PAGES = [10, 25, 50];

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $perPage = (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $total   = $this->forms->countAll();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min((int) ($_GET['page'] ?? 1), $pages));

        $this->view('admin/order_forms/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'order_forms',
            'pageTitle'    => 'Bons de commande',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Bons de commande'],
            ],
            'forms'        => $this->forms->getPaginated($page, $perPage),
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'perPage'      => $perPage,
            'flash'        => $this->getFlash('success'),
            'error'        => $this->getFlash('error'),
            'csrfToken'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/bons-de-commande/ajouter
    // ----------------------------------------------------------------

    public function upload(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/bons-de-commande');
        }

        $year  = (int) ($_POST['year'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $label = $label !== '' ? $label : null;

        if ($year < 2000 || $year > 2100) {
            $this->flash('error', 'Année invalide.');
            Response::redirect('/admin/bons-de-commande');
        }

        $file = $_FILES['pdf'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Aucun fichier reçu ou erreur d\'upload.');
            Response::redirect('/admin/bons-de-commande');
        }

        $mime = mime_content_type($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            $this->flash('error', 'Le fichier doit être un PDF.');
            Response::redirect('/admin/bons-de-commande');
        }

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        $labelSlug  = $label !== null ? '_' . preg_replace('/[^a-z0-9]/i', '_', $label) : '';
        $filename   = $year . '_prices' . $labelSlug . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $dest       = $this->storageDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->flash('error', 'Erreur lors de la sauvegarde du fichier.');
            Response::redirect('/admin/bons-de-commande');
        }

        $this->forms->create($year, $label, $filename);
        $this->flash('success', 'Bon de commande ajouté.');
        Response::redirect('/admin/bons-de-commande');
    }

    // ----------------------------------------------------------------
    // POST /admin/bons-de-commande/{id}/supprimer
    // ----------------------------------------------------------------

    public function delete(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/bons-de-commande');
        }

        $id   = (int) ($params['id'] ?? 0);
        $form = $this->forms->findByIdOrNull($id);

        if ($form === null) {
            $this->flash('error', 'Bon de commande introuvable.');
            Response::redirect('/admin/bons-de-commande');
        }

        $path = $this->storageDir . '/' . $form['filename'];
        if (is_file($path)) {
            unlink($path);
        }

        $this->forms->delete($id);
        $this->flash('success', 'Bon de commande supprimé.');
        Response::redirect('/admin/bons-de-commande');
    }

    // ----------------------------------------------------------------
    // GET /admin/bons-de-commande/{id}/telecharger
    // ----------------------------------------------------------------

    public function download(array $params): void
    {
        $this->requireAdmin();

        $id   = (int) ($params['id'] ?? 0);
        $form = $this->forms->findByIdOrNull($id);

        if ($form === null) {
            Response::abort(404);
        }

        $path = $this->storageDir . '/' . $form['filename'];
        if (!is_file($path)) {
            Response::abort(404);
        }

        $label    = $form['label'] !== null ? ' ' . $form['label'] : '';
        $download = 'Bon_commande_' . $form['year'] . $label . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $download . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-cache');
        readfile($path);
        exit;
    }
}
