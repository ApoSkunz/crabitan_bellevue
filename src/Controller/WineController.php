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

    private const PER_PAGE = 12;

    public function index(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color   = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort    = $_GET['sort'] ?? 'default';
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * self::PER_PAGE;

        $total      = $model->countAll($color);
        $totalPages = (int) ceil($total / self::PER_PAGE);
        $wines      = $model->getAll($color, $sort, self::PER_PAGE, $offset);

        $this->view('wines/index', [
            'lang'        => $lang,
            'wines'       => $wines,
            'activeColor' => $color,
            'activeSort'  => $sort,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/collection
    // ----------------------------------------------------------------

    public function collection(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $winesByColor = $model->getAllByColor();

        $this->view('wines/collection', [
            'lang'         => $lang,
            'winesByColor' => $winesByColor,
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

        // Logo
        $logoPath = ROOT_PATH . '/public/assets/images/crabitan-bellevue-logo.png';
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 15, 12, 18, 18, 'PNG');
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

        // Photo bouteille
        $imgPath = ROOT_PATH . '/public/assets/images/wines/' . $wine['image_path'];
        if (is_file($imgPath)) {
            $pdf->Ln(3);
            $x = ($pdf->getPageWidth() - 35) / 2;
            $pdf->Image(
                $imgPath,
                $x,
                $pdf->GetY(),
                35,
                0,
                '',
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
