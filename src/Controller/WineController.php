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

        $oeno   = json_decode($wine['oenological_comment'] ?? '{}', true) ?? [];
        $soil   = json_decode($wine['soil']                ?? '{}', true) ?? [];
        $pruning = json_decode($wine['pruning']            ?? '{}', true) ?? [];
        $harvest = json_decode($wine['harvest']            ?? '{}', true) ?? [];
        $vinif   = json_decode($wine['vinification']       ?? '{}', true) ?? [];
        $barrel  = json_decode($wine['barrel_fermentation'] ?? '{}', true) ?? [];
        $award   = json_decode($wine['award']              ?? '{}', true) ?? [];

        $l = fn(array $arr): string => $arr[$lang] ?? ($arr['fr'] ?? '');

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(APP_NAME);
        $pdf->SetTitle($wine['label_name'] . ' ' . $wine['vintage']);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Logo (strip alpha if needed — imagecreatefromstring is more robust than imagecreatefrompng)
        $logoPath = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        $tmpLogo  = null;
        if (is_file($logoPath)) {
            $logoImg  = $logoPath;
            $logoType = 'PNG';
            if (function_exists('imagecreatefromstring')) {
                $logoData = @file_get_contents($logoPath);
                if ($logoData !== false) {
                    $lSrc = @imagecreatefromstring($logoData);
                    if ($lSrc !== false) {
                        $lDst = imagecreatetruecolor(imagesx($lSrc), imagesy($lSrc));
                        imagefill($lDst, 0, 0, imagecolorallocate($lDst, 255, 255, 255));
                        imagealphablending($lDst, true);
                        imagecopy($lDst, $lSrc, 0, 0, 0, 0, imagesx($lSrc), imagesy($lSrc));
                        imagedestroy($lSrc);
                        $tmpLogo  = sys_get_temp_dir() . '/logo_' . md5($logoPath) . '.jpg';
                        imagejpeg($lDst, $tmpLogo, 95);
                        imagedestroy($lDst);
                        $logoImg  = $tmpLogo;
                        $logoType = 'JPG';
                    }
                }
            }
            $pdf->Image($logoImg, 15, 12, 18, 18, $logoType);
        }

        // Château name + wine name
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(36, 13);
        $pdf->Cell(0, 8, APP_NAME, 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetXY(36, 21);
        $pdf->SetTextColor(160, 120, 50);
        $pdf->Cell(0, 6, strtoupper($lang === 'en' ? 'Technical Sheet' : 'Fiche Technique'), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Ln(10);

        // Wine title block
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, $wine['label_name'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 7, (string) $wine['vintage'], 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        // Photo bouteille (strip alpha channel if GD available to avoid TCPDF error)
        $imgPath = ROOT_PATH . '/public/assets/images/wines/' . $wine['image_path'];
        $tmpImg  = null;
        if (is_file($imgPath)) {
            $useImg  = $imgPath;
            $imgType = '';
            if (function_exists('imagecreatefromstring') && str_ends_with(strtolower($imgPath), '.png')) {
                $imgData = @file_get_contents($imgPath);
                if ($imgData !== false) {
                    $src = @imagecreatefromstring($imgData);
                    if ($src !== false) {
                        $dst = imagecreatetruecolor(imagesx($src), imagesy($src));
                        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                        imagealphablending($dst, true);
                        imagecopy($dst, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
                        imagedestroy($src);
                        $tmpImg = sys_get_temp_dir() . '/wine_' . md5($imgPath) . '.jpg';
                        imagejpeg($dst, $tmpImg, 95);
                        imagedestroy($dst);
                        $useImg  = $tmpImg;
                        $imgType = 'JPG';
                    }
                }
            }
            $pdf->Ln(3);
            $x = ($pdf->getPageWidth() - 35) / 2;
            $pdf->Image(
                $useImg,
                $x,
                $pdf->GetY(),
                35,
                0,
                $imgType,
                '',
                'T',
                false,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
            $pdf->Ln(80);
        } else {
            $pdf->Ln(5);
        }

        // Séparateur
        $pdf->SetDrawColor(160, 120, 50);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        // Appellation + certification
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(160, 120, 50);
        $pdf->Cell(0, 5, strtoupper($lang === 'en' ? 'Appellation' : 'Appellation'), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 5, $wine['city'] . ' — ' . $wine['variety_of_vine'], 0, 1);
        if ($wine['certification_label']) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(80, 120, 60);
            $pdf->Cell(0, 5, $wine['certification_label'], 0, 1);
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(3);

        // Commentaire oenologique
        $oenoText = $l($oeno);
        if ($oenoText !== '') {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(160, 120, 50);
            $pdf->Cell(0, 5, strtoupper($lang === 'en' ? 'Tasting notes' : 'Dégustation'), 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 5, $oenoText, 0, 'L');
            $pdf->Ln(3);
        }

        // Fiche technique
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(160, 120, 50);
        $pdf->Cell(0, 5, strtoupper($lang === 'en' ? 'Technical data' : 'Données techniques'), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);

        $areaFmt = number_format((float) $wine['area'], 2, ',', ' ') . ' ha';
        $specs = [
            ($lang === 'en' ? 'Area'         : 'Superficie')      => $areaFmt,
            ($lang === 'en' ? 'Age of vines' : 'Âge des vignes')  => $wine['age_of_vineyard'] . ' ans',
            ($lang === 'en' ? 'Soil'          : 'Sol')          => $l($soil),
            ($lang === 'en' ? 'Pruning'       : 'Taille')       => $l($pruning),
            ($lang === 'en' ? 'Harvest'       : 'Vendanges')    => $l($harvest),
            ($lang === 'en' ? 'Vinification'  : 'Vinification') => $l($vinif),
            ($lang === 'en' ? 'Ageing'        : 'Élevage')      => $l($barrel),
        ];

        foreach ($specs as $label => $value) {
            if ($value === '' || $value === ' ha' || $value === ' ans') {
                continue;
            }
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(45, 5, $label . ' :', 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $value, 0, 'L');
        }

        // Médaille
        $awardText = $l($award);
        if ($awardText !== '') {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(160, 120, 50);
            $pdf->Cell(0, 5, strtoupper($lang === 'en' ? 'Award' : 'Récompense'), 0, 1);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 5, $awardText, 0, 1);
        }

        // Footer discret
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetXY(15, $pdf->getPageHeight() - 15);
        $pdf->Cell(0, 5, APP_NAME . ' — ' . $wine['city'] . ' — crabitanbellevue.fr', 0, 0, 'C');

        $filename = 'Fiche_Technique_' . $wine['label_name'] . '_' . $wine['vintage'] . '.pdf';
        $pdf->Output($filename, 'D');
        if ($tmpImg !== null && is_file($tmpImg)) {
            unlink($tmpImg);
        }
        if ($tmpLogo !== null && is_file($tmpLogo)) {
            unlink($tmpLogo);
        }
        exit;
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
