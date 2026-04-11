<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\NewsModel;
use Model\OrderFormModel;
use Model\WineModel;
use Service\MailService;

class PageController extends Controller
{
    /**
     * Affiche la page "Le Château".
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function chateau(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/chateau', ['lang' => $lang]);
    }

    /**
     * Affiche la page "Savoir-faire".
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function savoirFaire(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/savoir-faire', ['lang' => $lang]);
    }

    /**
     * Affiche le formulaire de contact.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function contact(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/contact', [
            'lang'          => $lang,
            'csrf'          => $this->csrfToken(),
            'latestOrderForm' => (new OrderFormModel())->getLatest(),
        ]);
    }

    /**
     * Traite l'envoi du formulaire de contact (POST).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function contactPost(array $params): void
    {
        $lang = $this->resolveLang($params);

        if (!$this->csrfValid()) {
            $this->json(['success' => false, 'message' => __('error.csrf')], 400);
        }

        $firstname = trim($this->request->post('firstname', ''));
        $lastname  = trim($this->request->post('lastname', ''));
        $email     = strtolower(trim($this->request->post('email', '')));
        $subject   = trim($this->request->post('subject', ''));
        $message   = trim($this->request->post('message', ''));
        $rgpd      = $this->request->post('rgpd', '');

        if (
            $firstname === '' || $lastname === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $subject === '' || $message === ''
            || $rgpd !== '1'
        ) {
            $this->json(['success' => false, 'message' => __('contact.error_fields')], 422);
        }

        try {
            $mail = $this->newMailService();
            $mail->sendContactToOwner($firstname, $lastname, $email, $subject, $message, $lang);
            $mail->sendContactConfirmation($email, $firstname, $subject, $lang, $message);
        } catch (\Core\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => __('contact.error_smtp')], 500);
        }
        $this->json(['success' => true, 'message' => __('contact.success')]);
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    private function csrfValid(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Page des Conditions Générales de Vente.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function conditionsGeneralesVente(array $params): void
    {
        $lang = $this->resolveLang($params);
        $bare = isset($_GET['bare']) || ($_COOKIE['age_verified'] ?? '') !== '1';
        $this->view('pages/conditions-generales-vente', ['lang' => $lang, 'bare' => $bare]);
    }

    /**
     * Affiche la page des mentions légales (LCEN).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function mentionsLegales(array $params): void
    {
        $lang = $this->resolveLang($params);
        $bare = isset($_GET['bare']) || ($_COOKIE['age_verified'] ?? '') !== '1';
        $this->view('pages/mentions-legales', ['lang' => $lang, 'noindex' => true, 'bare' => $bare]);
    }

    /**
     * Affiche la page de politique de confidentialité (RGPD).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function politiqueConfidentialite(array $params): void
    {
        $lang = $this->resolveLang($params);
        $bare = ($_COOKIE['age_verified'] ?? '') !== '1';
        $this->view('pages/politique-confidentialite', ['lang' => $lang, 'noindex' => true, 'bare' => $bare]);
    }

    /**
     * Affiche le plan du site.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function planDuSite(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();
        $wineImages = [
            'sweet'      => $model->getRandomByColor('sweet'),
            'red'        => $model->getRandomByColor('red'),
            'white'      => $model->getRandomByColor('white'),
            'rosé'       => $model->getRandomByColor('rosé'),
            'collection' => $model->getRandom(),
        ];
        $this->view('pages/plan-du-site', [
            'lang'       => $lang,
            'noindex'    => true,
            'wineImages' => $wineImages,
        ]);
    }

    /**
     * Affiche la page support.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function support(array $params): void
    {
        $lang = $this->resolveLang($params);
        $bare = ($_COOKIE['age_verified'] ?? '') !== '1';
        $this->view('pages/support', ['lang' => $lang, 'bare' => $bare]);
    }

    /**
     * Affiche la page des jeux (espace détente).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function jeux(array $params): void
    {
        $lang        = $this->resolveLang($params);
        $wineModel        = new WineModel();
        $scoreModel       = new \Model\GameScoreModel();
        $wines            = $wineModel->getRandomForMemo(9);
        $wrVendangeuse    = $scoreModel->getBestScore('vendangeuse');
        $wrMemo           = $scoreModel->getBestScore('memo');
        $wrLabour            = $scoreModel->getBestScore('labour');
        $wrCatapulte         = $scoreModel->getBestScore('catapulte');
        $wrVendangeExpress   = $scoreModel->getBestScore('vendangeexpress');
        $this->view('pages/jeux', [
            'lang'              => $lang,
            'wines'             => $wines,
            'wrVendangeuse'     => $wrVendangeuse,
            'wrMemo'            => $wrMemo,
            'wrLabour'          => $wrLabour,
            'wrCatapulte'       => $wrCatapulte,
            'wrVendangeExpress' => $wrVendangeExpress,
        ]);
    }

    /**
     * Affiche la page webmaster (documentation technique, noindex).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    /**
     * Affiche la page crédits médias (photos, vidéos, droits d'auteur, procédure de retrait).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function creditsMedias(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/credits-medias', ['lang' => $lang]);
    }

    /**
     * Affiche la galerie photos du domaine.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function galerie(array $params): void
    {
        $lang    = $this->resolveLang($params);
        $dir     = ROOT_PATH . '/public/assets/images/gallery/';
        $files   = glob($dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        $photos  = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $alt      = ucfirst(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
            $photos[] = ['src' => '/assets/images/gallery/' . $filename, 'alt' => $alt];
        }
        $this->view('pages/galerie', ['lang' => $lang, 'photos' => $photos]);
    }

    /**
     * Affiche la déclaration d'accessibilité (RGAA v4).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function accessibilite(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/accessibilite', ['lang' => $lang]);
    }

    /**
     * Affiche la page webmaster (documentation technique, noindex).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function webmaster(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/webmaster', ['lang' => $lang, 'noindex' => true]);
    }

    /**
     * Génère et retourne le sitemap.xml dynamique.
     * Inclut les pages statiques publiques + slugs vins + slugs actualités en FR/EN.
     * Les pages marquées noindex sont exclues.
     *
     * @param array<string, mixed> $_params
     */
    public function sitemapXml(array $_params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- NOSONAR S1172
    {
        $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');
        $today     = date('Y-m-d');
        $langs     = ['fr', 'en'];

        // Pages statiques publiques (noindex exclues : mentions-legales, politique-de-confidentialite, plan-du-site)
        $staticRoutes = [
            ''                              => '1.0',
            '/vins'                         => '0.9',
            '/vins/collection'              => '0.8',
            '/actualites'                   => '0.9',
            '/le-chateau'                   => '0.7',
            '/savoir-faire'                 => '0.7',
            '/contact'                      => '0.6',
            '/conditions-generales-de-vente' => '0.4',
            '/galerie'                      => '0.5',
        ];

        $wineModel = new WineModel();
        $newsModel = new NewsModel();
        $wineSlugs = $wineModel->getAllSlugs();
        $newsSlugs = $newsModel->getAll();

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Pages statiques
        foreach ($staticRoutes as $path => $priority) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($appUrl . '/fr' . $path) . "</loc>\n";
            echo "    <lastmod>{$today}</lastmod>\n";
            echo "    <priority>{$priority}</priority>\n";
            foreach ($langs as $l) {
                $alt = htmlspecialchars($appUrl . '/' . $l . $path);
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$alt}\"/>\n";
            }
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\""
                . htmlspecialchars($appUrl . '/fr' . $path) . "\"/>\n";
            echo "  </url>\n";
        }

        // Vins
        foreach ($wineSlugs as $row) {
            $slug = htmlspecialchars((string) $row['slug']);
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/fr/vins/{$slug}</loc>\n";
            echo "    <priority>0.8</priority>\n";
            foreach ($langs as $l) {
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$appUrl}/{$l}/vins/{$slug}\"/>\n";
            }
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/fr/vins/{$slug}\"/>\n";
            echo "  </url>\n";
        }

        // Actualités
        foreach ($newsSlugs as $row) {
            $slug = htmlspecialchars((string) $row['slug']);
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/fr/actualites/{$slug}</loc>\n";
            echo "    <priority>0.7</priority>\n";
            foreach ($langs as $l) {
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$appUrl}/{$l}/actualites/{$slug}\"/>\n";
            }
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/fr/actualites/{$slug}\"/>\n";
            echo "  </url>\n";
        }

        echo '</urlset>' . "\n";
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux XML (testable)
    }

    /**
     * Instancie le MailService (seam de testabilité).
     *
     * @return MailService
     */
    protected function newMailService(): MailService
    {
        return new MailService();
    }
}
