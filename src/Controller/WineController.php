<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\WineModel;
use TCPDF;

class WineController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/vins
    // ----------------------------------------------------------------

    private const VALID_PER_PAGE = [10, 25, 50, 100];
    private const DEFAULT_PER_PAGE = 25;

    public function index(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color   = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort    = $_GET['sort'] ?? 'default';
        $perPage = (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total      = $model->countAll($color);
        $totalPages = (int) ceil($total / $perPage);
        $wines      = $model->getAll($color, $sort, $perPage, $offset);

        $this->view('wines/index', [
            'lang'          => $lang,
            'wines'         => $wines,
            'activeColor'   => $color,
            'activeSort'    => $sort,
            'activePerPage' => $perPage,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'total'         => $total,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/collection
    // ----------------------------------------------------------------

    public function collection(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color   = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort    = $_GET['sort'] ?? 'default';
        $rawAvail = $_GET['avail'] ?? '';
        $avail   = in_array($rawAvail, ['available', 'out'], true) ? $rawAvail : null;
        $perPage = (int) ($_GET['per_page'] ?? 25);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total        = $model->countAllByColor($color, $avail);
        $totalPages   = (int) ceil($total / $perPage);
        $winesByColor = $model->getAllByColor($color, $sort, $avail, $perPage, $offset);
        // Page de première apparition de chaque couleur (sans filtre couleur, pour les raccourcis nav)
        $colorPages   = $model->getColorFirstPages($avail, $perPage);

        $this->view('wines/collection', [
            'lang'          => $lang,
            'winesByColor'  => $winesByColor,
            'activeColor'   => $color,
            'activeSort'    => $sort,
            'activeAvail'   => $rawAvail,
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

        $l    = fn(array $arr): string => $arr[$lang] ?? ($arr['fr'] ?? '');
        $logo = $this->stripAlpha(ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png');
        $img  = $this->stripAlpha(ROOT_PATH . '/public/assets/images/wines/' . $wine['image_path']);

        $pdf = $this->buildPdf($wine, $logo);
        $this->renderPdfBody($pdf, $wine, $img, $lang, $l);

        $filename = 'Fiche_Technique_' . $wine['label_name'] . '_' . $wine['vintage'] . '.pdf';
        $pdf->Output($filename, 'I');
        foreach (array_filter([$logo['tmp'], $img['tmp']]) as $tmp) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
        exit;
    }

    // ----------------------------------------------------------------
    // PDF helpers (technicalSheet)
    // ----------------------------------------------------------------

    /**
     * Strip PNG alpha channel to JPEG for TCPDF compatibility.
     * Tries GD first, then Imagick; if neither is available the image is
     * skipped (path = null) rather than letting TCPDF crash.
     *
     * @return array{path: string|null, type: string, tmp: string|null}
     */
    private function stripAlpha(string $srcPath): array
    {
        if (!is_file($srcPath)) {
            return ['path' => null, 'type' => 'PNG', 'tmp' => null];
        }
        if (!str_ends_with(strtolower($srcPath), '.png')) {
            return ['path' => $srcPath, 'type' => 'PNG', 'tmp' => null];
        }

        $converted = $this->convertPngToJpg($srcPath);
        // Fallback: use original PNG directly (TCPDF handles alpha, slower but better than no image)
        return $converted ?? ['path' => $srcPath, 'type' => 'PNG', 'tmp' => null];
    }

    /**
     * Convert a PNG to JPEG using GD or Imagick.
     * Returns null when no suitable extension is available.
     *
     * @return array{path: string, type: string, tmp: string}|null
     */
    private function convertPngToJpg(string $srcPath): ?array
    {
        $tmp = sys_get_temp_dir() . '/cb_img_' . md5($srcPath) . '.jpg'; // NOSONAR — temp filename only // nosemgrep: php.lang.security.weak-crypto.weak-crypto

        if (function_exists('imagecreatefromstring')) {
            $data = @file_get_contents($srcPath);
            $src  = $data !== false ? @imagecreatefromstring($data) : false;
            if ($src !== false) {
                $dst = imagecreatetruecolor(imagesx($src), imagesy($src));
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagealphablending($dst, true);
                imagecopy($dst, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
                imagedestroy($src);
                imagejpeg($dst, $tmp, 95);
                imagedestroy($dst);
                return ['path' => $tmp, 'type' => 'JPG', 'tmp' => $tmp];
            }
        }

        if (class_exists('Imagick', false)) {
            try {
                $img = new \Imagick($srcPath);
                $img->setImageBackgroundColor(new \ImagickPixel('white'));
                $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $img->setImageFormat('JPEG');
                $img->writeImage($tmp);
                $img->clear();
                return ['path' => $tmp, 'type' => 'JPG', 'tmp' => $tmp];
            } catch (\Throwable) {
                // Imagick failed — fall through to null
            }
        }

        return null;
    }

    /**
     * Build a TCPDF instance with cream background, custom header (logo + title)
     * and footer with French tagline, repeated on every page.
     *
     * @param array<string, mixed>                                       $wine
     * @param array{path: string|null, type: string, tmp: string|null}  $logo
     */
    private function buildPdf(array $wine, array $logo): TCPDF
    {
        $appName = APP_NAME;
        $pdf = new class ($logo, $appName) extends TCPDF {
            /**
             * @param array{path: string|null, type: string, tmp: string|null} $logo
             */
            public function __construct(
                private array $logo,
                private string $appName,
            ) {
                parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
                $this->SetCreator($this->appName);
                $this->SetAuthor($this->appName);
                $this->SetMargins(15, 35, 15);
                $this->SetHeaderMargin(5);
                $this->SetFooterMargin(14);
                $this->setPrintHeader(true);
                $this->setPrintFooter(true);
                $this->SetAutoPageBreak(true, 20);
            }

            public function Header(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
            {
                $pageW  = $this->getPageWidth();
                $pageH  = $this->getPageHeight();

                // Cream background — #f5f0e8
                $this->SetFillColor(245, 240, 232);
                $this->Rect(0, 0, $pageW, $pageH, 'F');

                // Logo left
                if ($this->logo['path'] !== null) {
                    $this->Image($this->logo['path'], 13, 6, 16, 0, $this->logo['type']);
                }

                // Title centered
                $this->SetFont('dejavusans', 'B', 13);
                $this->SetTextColor(0, 0, 0);
                $this->SetXY(10, 8);
                $this->Cell($pageW - 20, 8, 'FICHE TECHNIQUE', 0, 0, 'C');

                // Gold separator line under header
                $this->SetDrawColor(201, 168, 76);
                $this->SetLineWidth(0.5);
                $this->Line(15, 28, $pageW - 15, 28);
            }

            public function Footer(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
            {
                $pageW = $this->getPageWidth();
                $y     = $this->getPageHeight() - 12;

                // Gold separator line above footer
                $this->SetDrawColor(201, 168, 76);
                $this->SetLineWidth(0.5);
                $this->Line(15, $y - 2, $pageW - 15, $y - 2);

                $this->SetFont('dejavusans', 'I', 8);
                $this->SetTextColor(160, 130, 60);
                $this->SetXY(15, $y);
                $this->Cell($pageW - 30, 6, $this->appName . ' est ravi de vous offrir ces informations.', 0, 0, 'C');
            }
        };

        $pdf->SetTitle($wine['label_name'] . ' ' . $wine['vintage']);
        $pdf->AddPage();
        return $pdf;
    }

    /**
     * Build the label → value field map for the PDF body.
     *
     * @param array<string, mixed>                        $wine
     * @param callable(array<string, string>): string     $l
     * @return array<string, string>
     */
    private function buildPdfFields(array $wine, string $lang, callable $l): array
    {
        $oeno    = json_decode($wine['oenological_comment'] ?? '{}', true) ?? [];
        $soil    = json_decode($wine['soil']                ?? '{}', true) ?? [];
        $pruning = json_decode($wine['pruning']             ?? '{}', true) ?? [];
        $harvest = json_decode($wine['harvest']             ?? '{}', true) ?? [];
        $vinif   = json_decode($wine['vinification']        ?? '{}', true) ?? [];
        $barrel  = json_decode($wine['barrel_fermentation'] ?? '{}', true) ?? [];
        $award   = json_decode($wine['award']               ?? '{}', true) ?? [];

        $areaFmt = $wine['area'] ? number_format((float) $wine['area'], 2, ',', ' ') . ' ha' : '';
        $ageStr  = $wine['age_of_vineyard'] ? $wine['age_of_vineyard'] . ' ans' : '';

        return [
            ($lang === 'en' ? 'Comment'      : 'Commentaire')    => $l($oeno),
            ($lang === 'en' ? 'Award'        : 'Récompense')     => $l($award),
            ($lang === 'en' ? 'Specificity'  : 'Spécificité')    => (string) ($wine['certification_label'] ?? ''),
            'Commune'                                             => (string) ($wine['city'] ?? ''),
            ($lang === 'en' ? 'Grape'        : 'Encépagement')   => (string) ($wine['variety_of_vine'] ?? ''),
            ($lang === 'en' ? 'Harvest'      : 'Vendanges')      => $l($harvest),
            ($lang === 'en' ? 'Ageing'       : 'Élevage')        => $l($barrel),
            ($lang === 'en' ? 'Soil'         : 'Terroir')        => $l($soil),
            ($lang === 'en' ? 'Pruning'      : 'Taille')         => $l($pruning),
            ($lang === 'en' ? 'Area'         : 'Surface')        => $areaFmt,
            ($lang === 'en' ? 'Age of vines' : 'Âge des vignes') => $ageStr,
            'Vinification'                                        => $l($vinif),
        ];
    }

    /**
     * Render the full PDF body: dark banner, bottle image (left), fields (right).
     * Designed to fit on a single A4 page.
     *
     * @param array<string, mixed>                        $wine
     * @param array{path: string|null, type: string, tmp: string|null} $img
     * @param callable(array<string, string>): string     $l
     */
    private function renderPdfBody(TCPDF $pdf, array $wine, array $img, string $lang, callable $l): void
    {
        $pageW = $pdf->getPageWidth();

        // Château name in gold
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->SetTextColor(201, 168, 76);
        $pdf->SetX(15);
        $pdf->Cell($pageW - 30, 10, APP_NAME, 0, 1, 'C');

        // Subtitle: AOC label vintage
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 6, 'AOC ' . $wine['label_name'] . ' ' . $wine['vintage'], 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);

        // Two-column layout: bottle image left (25 mm wide), fields right
        $startY = $pdf->GetY();
        $imgCol = 15;
        $imgW   = 25;
        $fldCol = 44;
        $fldW   = $pageW - $fldCol - 15;

        if ($img['path'] !== null) {
            $pdf->Image($img['path'], $imgCol, $startY, $imgW, 0, $img['type'], '', 'T', false, 300);
        }

        $fields = $this->buildPdfFields($wine, $lang, $l);

        // Render fields in the right column
        $pdf->SetXY($fldCol, $startY);
        foreach ($fields as $label => $value) {
            $pdf->SetX($fldCol);
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetTextColor(201, 168, 76);
            $labelStr = $label . ' :';
            $labelW   = $pdf->GetStringWidth($labelStr) + 1;
            $pdf->Cell($labelW, 5, $labelStr, 0, 0);
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(40, 40, 40);
            if ($value !== '') {
                $pdf->MultiCell($fldW - $labelW, 5, ' ' . $value, 0, 'L');
            } else {
                $pdf->Ln(5);
            }
            $pdf->Ln(1);
        }
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

        $this->view('wines/show', [
            'lang' => $lang,
            'wine' => $wine,
        ]);
    }
}
