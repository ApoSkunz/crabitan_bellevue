<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Exception\HttpException;
use Model\OrderModel;

/**
 * Archivage légal des factures (Art. L441-9 + L123-22 C.com.).
 * Fournit la liste des factures par période et l'export ZIP (PDFs) + CSV.
 */
class InvoiceAdminController extends AdminController
{
    private const ADMIN_URL         = '/admin/factures';
    private const DEFAULT_PER_PAGE  = 25;
    private const ALLOWED_PER_PAGES = [25, 50, 100];

    private OrderModel $orders;

    /**
     * @param \Core\Request $request
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/factures
    // ----------------------------------------------------------------

    /**
     * Liste des factures avec filtre par période (année / mois / trimestre).
     *
     * @param  array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $currentYear = (int) date('Y');
        [$year, $month, $quarter] = $this->parsePeriodParams($currentYear);

        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $total          = $this->orders->countInvoicesForAdmin($year, $month, $quarter);
        $list           = $this->orders->getInvoicesForAdmin($year, $month, $quarter, $page, $perPage);
        $availableYears = $this->orders->getAvailableYearsWithInvoices();
        if (empty($availableYears)) {
            $availableYears = [$currentYear];
        }

        $this->view('admin/invoices/index', [
            'adminUser'      => $adminUser,
            'adminSection'   => 'invoices',
            'pageTitle'      => 'Factures',
            'breadcrumbs'    => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Factures'],
            ],
            'invoices'       => $list,
            'total'          => $total,
            'page'           => $page,
            'perPage'        => $perPage,
            'year'           => $year,
            'month'          => $month,
            'quarter'        => $quarter,
            'availableYears' => $availableYears,
            'flash'          => $this->getFlash('success'),
            'flashError'     => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/factures/export
    // ----------------------------------------------------------------

    /**
     * Exporte les factures de la période : ZIP contenant les PDFs + un fichier CSV récapitulatif.
     *
     * @param  array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function export(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $this->requireAdmin();

        $currentYear = (int) date('Y');
        [$year, $month, $quarter] = $this->parsePeriodParams($currentYear);

        $rows = $this->orders->getInvoiceFilesForExport($year, $month, $quarter);

        if (empty($rows)) {
            $this->flash('error', 'Aucune facture pour cette période.');
            $q = http_build_query(array_filter([
                'year'    => $year !== $currentYear ? $year : null,
                'month'   => $month,
                'quarter' => $quarter,
            ]));
            $this->redirect(self::ADMIN_URL . ($q !== '' ? '?' . $q : ''));
        }

        $periodLabel = $this->buildPeriodLabel($year, $month, $quarter);
        $zipFileName = 'factures-' . $periodLabel . '.zip';
        $tmpPath     = $this->createTempFile();

        $zip = new \ZipArchive();
        $zip->open($tmpPath, \ZipArchive::OVERWRITE);

        // CSV (BOM UTF-8 pour compatibilité Excel)
        $csvLines   = [];
        $csvLines[] = implode(';', [
            'N° facture', 'Réf. commande', 'Date commande', 'Montant TTC (€)',
            'Mode paiement', 'Client email', 'Client prénom', 'Client nom',
            'Source', 'Date génération',
        ]);

        foreach ($rows as $row) {
            $absPath = ROOT_PATH . '/' . ltrim((string) ($row['path'] ?? ''), '/');
            if (is_file($absPath)) {
                $entry = ($row['invoice_number'] ?? null) !== null
                    ? $row['invoice_number'] . '.pdf'
                    : basename((string) $row['path']);
                $zip->addFile($absPath, (string) $entry);
            }

            $csvLines[] = implode(';', [
                $row['invoice_number'] ?? '',
                $row['order_reference'] ?? '',
                isset($row['ordered_at'])
                    ? date('d/m/Y', strtotime((string) $row['ordered_at'])) : '',
                number_format((float) ($row['price'] ?? 0), 2, ',', ' '),
                $row['payment_method'] ?? '',
                $row['email'] ?? '',
                $row['firstname'] ?? '',
                $row['lastname'] ?? '',
                $row['source'] ?? '',
                isset($row['uploaded_at'])
                    ? date('d/m/Y H:i', strtotime((string) $row['uploaded_at'])) : '',
            ]);
        }

        $zip->addFromString(
            'factures-' . $periodLabel . '.csv',
            "\xEF\xBB\xBF" . implode("\r\n", $csvLines)
        );
        $zip->close();

        $this->sendZipResponse($tmpPath, $zipFileName);
    }

    // ----------------------------------------------------------------
    // Seams de testabilité
    // ----------------------------------------------------------------

    /**
     * Envoie le fichier ZIP au navigateur.
     * Méthode surchargeable en test pour éviter readfile() + exit.
     *
     * @param  string $tmpPath  Chemin absolu du ZIP temporaire
     * @param  string $fileName Nom suggéré pour le téléchargement
     * @return never
     */
    protected function sendZipResponse(string $tmpPath, string $fileName): never
    {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($tmpPath));
        header('Cache-Control: no-cache, no-store');
        readfile($tmpPath);
        @unlink($tmpPath); // NOSONAR — nettoyage fichier temporaire
        throw new HttpException(200); // NOSONAR — terminaison identique à exit dans le contexte du routeur
    }

    /**
     * Crée un fichier temporaire et retourne son chemin.
     * Surchargeable en test.
     *
     * @return string
     */
    protected function createTempFile(): string
    {
        return (string) tempnam(sys_get_temp_dir(), 'crab_inv_');
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Extrait et valide les paramètres de période depuis la requête.
     *
     * @param  int $currentYear Année courante (défaut si absent)
     * @return array{int, int|null, int|null}
     */
    private function parsePeriodParams(int $currentYear): array
    {
        $year       = max(2000, min(2100, (int) $this->request->get('year', $currentYear)));
        $monthRaw   = $this->request->get('month', '');
        $quarterRaw = $this->request->get('quarter', '');

        $month = ($monthRaw !== '' && (int) $monthRaw >= 1 && (int) $monthRaw <= 12)
            ? (int) $monthRaw : null;
        $quarter = ($month === null && $quarterRaw !== '' && (int) $quarterRaw >= 1 && (int) $quarterRaw <= 4)
            ? (int) $quarterRaw : null;

        return [$year, $month, $quarter];
    }

    /**
     * Construit le label de période pour le nom du fichier ZIP.
     *
     * @param  int      $year
     * @param  int|null $month
     * @param  int|null $quarter
     * @return string
     */
    private function buildPeriodLabel(int $year, ?int $month, ?int $quarter): string
    {
        if ($month !== null) {
            return $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        }
        if ($quarter !== null) {
            return $year . '-Q' . $quarter;
        }
        return (string) $year;
    }
}
