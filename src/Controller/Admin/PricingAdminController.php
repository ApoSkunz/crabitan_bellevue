<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\PricingModel;

class PricingAdminController extends AdminController
{
    private PricingModel $pricing;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->pricing = new PricingModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/tarifs
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/pricing/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'pricing',
            'pageTitle'    => 'Tarifs de livraison',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Tarifs']],
            'rules'        => $this->pricing->getAll(),
            'csrfToken'    => $_SESSION['csrf'] ?? '',
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/tarifs
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/tarifs');
        }

        $ids = $this->request->post('id', []);

        if (is_array($ids)) {
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $delivery   = (float) str_replace(',', '.', $this->request->post("delivery_{$id}", '0'));
                $withdrawal = (float) str_replace(',', '.', $this->request->post("withdrawal_{$id}", '0'));
                $labelFr    = trim($this->request->post("label_fr_{$id}", ''));
                $labelEn    = trim($this->request->post("label_en_{$id}", ''));
                $active     = $this->request->post("active_{$id}", '0') === '1';

                $this->pricing->update($id, $delivery, $withdrawal, $labelFr, $labelEn, $active);
            }
        }

        $this->flash('success', 'Tarifs mis à jour avec succès.');
        Response::redirect('/admin/tarifs');
    }
}
