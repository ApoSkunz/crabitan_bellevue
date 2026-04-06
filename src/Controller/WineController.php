<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Jwt;
use Core\Response;
use Model\FavoriteModel;
use Model\WineModel;
use TCPDF;

class WineController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/vins
    // ----------------------------------------------------------------

    private const VALID_PER_PAGE = [10, 25, 50, 100];
    private const DEFAULT_PER_PAGE = 25;

    /**
     * Retourne les IDs de vins likés par l'utilisateur courant (tableau indexé pour lookup O(1)).
     * Retourne [] si non connecté ou token invalide.
     *
     * @return array<int, true>
     */
    private function getCurrentUserLikedIds(): array
    {
        $token = $_COOKIE['auth_token'] ?? null;
        if ($token === null) {
            return [];
        }
        try {
            $payload = Jwt::decode($token);
            $userId  = (int) $payload['sub'];
            return (new FavoriteModel())->getLikedIds($userId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function index(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color        = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort         = $_GET['sort'] ?? 'default';
        $cuveSpeciale = isset($_GET['cuvee_speciale']) && $_GET['cuvee_speciale'] === '1' ? true : null;
        $perPage      = (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total      = $model->countAll($color, $cuveSpeciale);
        $totalPages = (int) ceil($total / $perPage);
        $wines      = $model->getAll($color, $sort, $perPage, $offset, $cuveSpeciale);

        $this->view('wines/index', [
            'lang'           => $lang,
            'wines'          => $wines,
            'likedIds'       => $this->getCurrentUserLikedIds(),
            'activeColor'    => $color,
            'activeSort'     => $sort,
            'activePerPage'  => $perPage,
            'activeCuvee'    => $cuveSpeciale === true,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'total'          => $total,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/collection
    // ----------------------------------------------------------------

    public function collection(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color        = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort         = $_GET['sort'] ?? 'default';
        $rawAvail     = $_GET['avail'] ?? '';
        $avail        = in_array($rawAvail, ['available', 'out'], true) ? $rawAvail : null;
        $cuveSpeciale = isset($_GET['cuvee_speciale']) && $_GET['cuvee_speciale'] === '1' ? true : null;
        $perPage      = (int) ($_GET['per_page'] ?? 25);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total        = $model->countAllByColor($color, $avail, $cuveSpeciale);
        $totalPages   = (int) ceil($total / $perPage);
        $winesByColor = $model->getAllByColor($color, $sort, $avail, $perPage, $offset, $cuveSpeciale);
        // Page de première apparition de chaque couleur (sans filtre couleur, pour les raccourcis nav)
        $colorPages   = $model->getColorFirstPages($avail, $perPage);

        $this->view('wines/collection', [
            'lang'          => $lang,
            'winesByColor'  => $winesByColor,
            'likedIds'      => $this->getCurrentUserLikedIds(),
            'activeColor'   => $color,
            'activeSort'    => $sort,
            'activeAvail'   => $rawAvail,
            'activeCuvee'   => $cuveSpeciale === true,
            'activePerPage' => $perPage,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'colorPages'    => $colorPages,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/{slug}/fiche-technique
    // ----------------------------------------------------------------

    public function technicalSheet(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $slug  = $params['slug'] ?? '';
        $model = new WineModel();
        $wine  = $model->getBySlug($slug);

        if ($wine === null) {
            Response::abort(404);
        }

        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $l      = fn(array $arr): string => $arr[$lang] ?? ($arr['fr'] ?? '');
        $logoPath = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        $imgPath  = ROOT_PATH . '/public/assets/images/wines/' . ($wine['image_path'] ?? '');

        $pdf = $this->buildPdf($logoPath);
        $this->renderPdfBody($pdf, $wine, $imgPath, $lang, $l);

        $label    = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($wine['label_name'] ?? 'vin'));
        $vintage  = (string) ($wine['vintage'] ?? '');
        $filename = 'Fiche_Technique_' . $label . '_' . $vintage . '_' . $lang . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }

    // ----------------------------------------------------------------
    // PDF helpers (technicalSheet)
    // ----------------------------------------------------------------

    /**
     * Build a TCPDF instance: cream background, elegant centered header (logo + name),
     * gold footer. PNG images passed directly — alpha rendered on cream rect.
     *
     * @param string $logoPath Absolute path to the logo PNG
     */
    private function buildPdf(string $logoPath): TCPDF
    {
        $appName = APP_NAME;

        $pdf = new class ($logoPath, $appName) extends TCPDF {
            public function __construct(
                private string $logoPath,
                private string $appName,
            ) {
                parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
                $this->SetCreator($this->appName);
                $this->SetAuthor($this->appName);
                $this->SetMargins(15, 44, 15);
                $this->SetHeaderMargin(5);
                $this->SetFooterMargin(12);
                $this->setPrintHeader(true);
                $this->setPrintFooter(true);
                $this->SetAutoPageBreak(true, 22);
            }

            public function Header(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
            {
                $pageW = $this->getPageWidth();
                $pageH = $this->getPageHeight();

                // Fond blanc — optimal pour impression (grossistes)
                $this->SetFillColor(255, 255, 255);
                $this->Rect(0, 0, $pageW, $pageH, 'F');

                // "FICHE TECHNIQUE" en petites caps espacées
                $this->SetFont('dejavusans', '', 8);
                $this->SetTextColor(155, 135, 95);
                $this->SetXY(0, 5);
                $this->Cell($pageW, 5, 'F I C H E   T E C H N I Q U E', 0, 1, 'C');

                // Logo centré — légèrement agrandi
                if (is_file($this->logoPath)) {
                    $logoW = 22;
                    $logoX = ($pageW - $logoW) / 2;
                    $this->Image($this->logoPath, $logoX, 11, $logoW, 0, 'PNG');
                }

                // Nom du château — police serif élégante, doré, centré
                $this->SetFont('dejavuserif', 'B', 16);
                $this->SetTextColor(140, 105, 35);
                $this->SetXY(0, 30);
                $this->Cell($pageW, 8, $this->appName, 0, 1, 'C');

                // Double filet doré (fin + épais) sous l'en-tête
                $this->SetDrawColor(201, 168, 76);
                $this->SetLineWidth(0.2);
                $this->Line(20, 39.5, $pageW - 20, 39.5);
                $this->SetLineWidth(0.8);
                $this->Line(20, 41, $pageW - 20, 41);
            }

            public function Footer(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
            {
                $pageW = $this->getPageWidth();
                $y     = $this->getPageHeight() - 11;

                // Double filet doré au-dessus du footer (symétrique avec le header)
                $this->SetDrawColor(201, 168, 76);
                $this->SetLineWidth(0.8);
                $this->Line(20, $y - 3, $pageW - 20, $y - 3);
                $this->SetLineWidth(0.2);
                $this->Line(20, $y - 1.5, $pageW - 20, $y - 1.5);

                $this->SetFont('dejavusans', 'I', 7.5);
                $this->SetTextColor(160, 130, 60);
                $this->SetXY(15, $y);
                $this->Cell($pageW - 30, 5, $this->appName . ' est ravi de vous offrir ces informations.', 0, 0, 'C');
            }
        };

        return $pdf;
    }

    /**
     * Render the full PDF body:
     *  - Bandeau sombre (nom château + AOC/millésime)
     *  - Colonne gauche : image bouteille (35 mm)
     *  - Colonne droite : TOUTES les sections (INFORMATIONS → DÉGUSTATION → VITICULTURE → VINIFICATION)
     * Conçu pour tenir sur une seule page A4.
     *
     * @param array<string, mixed>                    $wine
     * @param callable(array<string, string>): string $l
     */
    private function renderPdfBody(TCPDF $pdf, array $wine, string $imgPath, string $lang, callable $l): void
    {
        $pdf->AddPage();

        $pageW   = $pdf->getPageWidth();
        $margins = 15;
        $isEn    = $lang === 'en';

        // ── AOC + millésime + cuvée spéciale — sous le double filet, élégant ──
        $pdf->SetFont('dejavusans', '', 9.5);
        $pdf->SetTextColor(100, 80, 30);
        $pdf->SetX(0);
        $pdf->Cell($pageW, 6, 'AOC ' . ($wine['label_name'] ?? '') . '  —  ' . ($wine['vintage'] ?? ''), 0, 1, 'C');

        if (!empty($wine['is_cuvee_speciale'])) {
            $pdf->SetFont('dejavusans', 'I', 8.5);
            $pdf->SetTextColor(180, 140, 50);
            $pdf->SetX(0);
            $pdf->Cell($pageW, 5, '✦   Cuvée Spéciale   ✦', 0, 1, 'C');
        }

        $pdf->Ln(3);
        $layoutStartY = $pdf->GetY();

        // ── Image bouteille gauche — boîte fixe 35×95 mm (toutes images homogènes) ──
        $boxW = 35.0;
        $boxH = 95.0;

        if (is_file($imgPath)) {
            $size = @getimagesize($imgPath);
            if ($size !== false && $size[0] > 0) {
                $ratio = $size[1] / $size[0];
                // Ajuster pour tenir dans la boîte en conservant le ratio
                $iW = $boxW;
                $iH = $iW * $ratio;
                if ($iH > $boxH) {
                    $iH = $boxH;
                    $iW = $iH / $ratio;
                }
                // Centrer dans la boîte
                $iX = (float) $margins + ($boxW - $iW) / 2;
                $iY = $layoutStartY + ($boxH - $iH) / 2;
                $pdf->Image($imgPath, $iX, $iY, $iW, $iH, 'PNG');
            }
        }
        $imgH = $boxH; // la colonne gauche occupe toujours 95 mm

        // ── Colonne droite : toutes les sections ────────────────────────
        $rightX    = $margins + $boxW + 5;
        $rightW    = $pageW - $rightX - $margins;
        $labelColW = 36;

        // Décoder les champs JSON
        $oeno    = json_decode((string) ($wine['oenological_comment'] ?? '{}'), true) ?? [];
        $soil    = json_decode((string) ($wine['soil']                ?? '{}'), true) ?? [];
        $pruning = json_decode((string) ($wine['pruning']             ?? '{}'), true) ?? [];
        $harvest = json_decode((string) ($wine['harvest']             ?? '{}'), true) ?? [];
        $vinif   = json_decode((string) ($wine['vinification']        ?? '{}'), true) ?? [];
        $barrel  = json_decode((string) ($wine['barrel_fermentation'] ?? '{}'), true) ?? [];
        $award   = json_decode((string) ($wine['award']               ?? '{}'), true) ?? [];

        $areaFmt = $wine['area']            ? number_format((float) $wine['area'], 2, ',', ' ') . ' ha' : '';
        $ageStr  = $wine['age_of_vineyard'] ? $wine['age_of_vineyard'] . ' ans'                         : '';

        $colorMap = [
            'red'   => ['fr' => 'Rouge',           'en' => 'Red'],
            'white' => ['fr' => 'Blanc',           'en' => 'White'],
            'rosé'  => ['fr' => 'Rosé',            'en' => 'Rosé'],
            'sweet' => ['fr' => 'Blanc liquoreux', 'en' => 'Sweet white'],
        ];
        $colorStr = $colorMap[$wine['wine_color'] ?? ''][$lang] ?? (string) ($wine['wine_color'] ?? '');

        // Ordre marketing : DÉGUSTATION en premier (ce qui vend), puis technique
        $sections = [
            ($isEn ? 'TASTING NOTES' : 'DÉGUSTATION') => array_filter([
                ($isEn ? 'Tasting notes' : 'Commentaire') => $l($oeno),
                ($isEn ? 'Award'         : 'Récompense')  => $l($award),
            ]),
            ($isEn ? 'INFORMATION' : 'INFORMATIONS') => array_filter([
                ($isEn ? 'Colour'  : 'Couleur')   => $colorStr,
                ($isEn ? 'Vintage' : 'Millésime') => (string) ($wine['vintage'] ?? ''),
            ]),
            ($isEn ? 'VITICULTURE' : 'VITICULTURE') => array_filter([
                ($isEn ? 'Soil'         : 'Terroir')         => $l($soil),
                ($isEn ? 'Pruning'      : 'Taille')          => $l($pruning),
                ($isEn ? 'Grape'        : 'Encépagement')    => (string) ($wine['variety_of_vine'] ?? ''),
                ($isEn ? 'Village'      : 'Commune')         => (string) ($wine['city'] ?? ''),
                ($isEn ? 'Area'         : 'Surface')         => $areaFmt,
                ($isEn ? 'Age of vines' : 'Âge des vignes')  => $ageStr,
            ]),
            ($isEn ? 'WINEMAKING' : 'VINIFICATION') => array_filter([
                ($isEn ? 'Harvest'  : 'Vendanges')  => $l($harvest),
                'Vinification'                      => $l($vinif),
                ($isEn ? 'Ageing'   : 'Élevage')    => $l($barrel),
            ]),
        ];

        $pdf->SetXY($rightX, $layoutStartY);
        $first = true;
        foreach ($sections as $sectionTitle => $fields) {
            if (empty($fields)) {
                continue;
            }
            $this->renderPdfSection($pdf, $sectionTitle, $fields, $rightX, $rightW, $labelColW, $first);
            $first = false;
        }

        // Curseur sous les deux colonnes
        $rightBottomY = $pdf->GetY();
        $pdf->SetY(max($layoutStartY + $imgH, $rightBottomY) + 4);
    }

    /**
     * Render one section inside the right column.
     * First section : no leading separator. Subsequent sections : thin gold line + spacing.
     *
     * @param array<string, string> $fields
     */
    private function renderPdfSection(
        TCPDF $pdf,
        string $title,
        array $fields,
        float $startX,
        float $contentW,
        float $labelColW,
        bool $first = false,
    ): void {
        $valueColW = $contentW - $labelColW;

        // Séparateur entre sections (sauf la première)
        if (!$first) {
            $pdf->Ln(2.5);
            $pdf->SetDrawColor(201, 168, 76);
            $pdf->SetLineWidth(0.25);
            $y = $pdf->GetY();
            $pdf->Line($startX, $y, $startX + $contentW, $y);
            $pdf->Ln(2);
        }

        // Titre de section en doré, petite police
        $pdf->SetX($startX);
        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->SetTextColor(180, 145, 55);
        $pdf->Cell($contentW, 5, $title, 0, 1, 'L');
        $pdf->Ln(0.5);

        // Lignes label (doré chaud) / valeur (gris foncé) — colonne label fixe
        // SetLeftMargin temporaire : MultiCell wrap sur la colonne valeur, pas sur la marge page
        $oldLeftMargin = $pdf->getMargins()['left'];

        foreach ($fields as $label => $value) {
            $rowY = $pdf->GetY();

            // SetLeftMargin temporaire : le wrap de MultiCell reste aligné sur la colonne valeur
            $pdf->SetLeftMargin($startX + $labelColW);

            // MultiCell(ln=0) pour le label : même rendu interne que la valeur → baseline identique
            $pdf->SetXY($startX, $rowY);
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetTextColor(120, 90, 25);
            $pdf->MultiCell($labelColW, 5.5, $label . ' :', 0, 'L', false, 0);

            $pdf->SetXY($startX + $labelColW, $rowY);
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(35, 35, 35);
            $pdf->MultiCell($valueColW, 5.5, $value, 0, 'L');

            $pdf->Ln(0.5);
        }

        $pdf->SetLeftMargin($oldLeftMargin);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/{slug}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $slug  = $params['slug'] ?? '';
        $model = new WineModel();

        $wine = $model->getBySlug($slug);

        if ($wine === null) {
            Response::abort(404);
        }

        $isLiked = false;
        $token   = $_COOKIE['auth_token'] ?? null;
        if ($token !== null) {
            try {
                $payload = \Core\Jwt::decode($token);
                $userId  = (int) $payload['sub'];
                $isLiked = (new \Model\FavoriteModel())->isLiked($userId, (int) $wine['id']);
            } catch (\Throwable) {
                // Token invalide : isLiked reste false
            }
        }

        $this->view('wines/show', [
            'lang'    => $lang,
            'wine'    => $wine,
            'isLiked' => $isLiked,
        ]);
    }
}
