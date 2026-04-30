<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\NewsModel;
use Model\OrderFormModel;
use Model\WineModel;
use Service\MailService;
use Service\RateLimiterService;

class PageController extends Controller // NOSONAR php:S1448 — contrôleur de pages statiques, découpage artificiel sans valeur métier
{
    private const GALLERY_IMG_BASE       = '/assets/images/gallery/';
    private const DEFAULT_APP_URL        = 'https://crabitanbellevue.fr';
    private const MAX_CONTACT_IP         = 5;
    private const WINDOW_CONTACT         = 3600;
    private const HEADER_XML_CONTENT     = 'Content-Type: application/xml; charset=utf-8';
    private const HEADER_NOINDEX         = 'X-Robots-Tag: noindex';
    private const XML_DECLARATION        = '<?xml version="1.0" encoding="UTF-8"?>';
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

        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rlKey   = 'contact:' . $ip;
        $limiter = $this->newRateLimiterService();
        if (!$limiter->checkLimit($rlKey, self::MAX_CONTACT_IP, self::WINDOW_CONTACT)) {
            $this->json(['success' => false, 'message' => __('contact.error_rate_limit')], 429);
        }

        // Honeypot anti-bot : champ caché dans le formulaire — doit rester vide
        $honeypot = $this->request->post('website', '');
        if ($honeypot !== '') {
            // Rejet silencieux : réponse succès pour ne pas informer le bot
            $this->json(['success' => true, 'message' => __('contact.success')]);
        }

        $limiter->recordAttempt($rlKey, self::WINDOW_CONTACT);

        $firstname = trim($this->request->post('firstname', ''));
        $lastname  = trim($this->request->post('lastname', ''));
        $email     = strtolower(trim($this->request->post('email', '')));
        $subject   = trim($this->request->post('subject', ''));
        $message   = trim($this->request->post('message', ''));
        $rgpd      = $this->request->post('rgpd', '');
        $phone     = trim($_POST['phone'] ?? '');
        if ($phone !== '' && !preg_match('/^[+\d\s\-()]{1,20}$/', $phone)) {
            $phone = '';
        }

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
            $mail->sendContactToOwner($firstname, $lastname, $email, $subject, $message, $lang, $phone);
            $mail->sendContactConfirmation($email, $firstname, $subject, $lang, $message);
        } catch (\Core\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && $e->getCode() === 429) {
                $this->json(['success' => false, 'message' => __('contact.error_rate_limit')], 429);
            }
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
        $this->view('pages/mentions-legales', ['lang' => $lang, 'bare' => $bare]);
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
        $this->view('pages/politique-confidentialite', ['lang' => $lang, 'bare' => $bare]);
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
     * Affiche la page webmaster (CV technique — indexée).
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
            // Exclure les variantes générées (-600w, -800w, -1200w, -1600w)
            if (preg_match('/-\d+w\.(jpg|jpeg|png|webp)$/i', $filename)) {
                continue;
            }
            $alt      = ucfirst(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
            $size     = @getimagesize($file);
            $base600  = pathinfo($filename, PATHINFO_FILENAME) . '-600w';
            $photos[] = [
                'src'           => self::GALLERY_IMG_BASE . $filename,
                'src_600w'      => file_exists($dir . $base600 . '.jpg')  ? self::GALLERY_IMG_BASE . $base600 . '.jpg'  : null,
                'src_600w_webp' => file_exists($dir . $base600 . '.webp') ? self::GALLERY_IMG_BASE . $base600 . '.webp' : null,
                'alt'       => $alt,
                'width'     => $size ? (int) $size[0] : 0,
                'height'    => $size ? (int) $size[1] : 0,
            ];
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
     * Affiche la page webmaster (CV technique — indexée).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function webmaster(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/webmaster', ['lang' => $lang]);
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
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_APP_URL, '/');
        $today     = date('Y-m-d');
        $langs     = ['fr', 'en'];

        // Pages statiques — [chemin => [fr_priority, en_priority]]
        // Hiérarchie : Accueil > Vins > Collection > Château > Savoir-faire > Vin dédié > autres
        // Noindex exclues : mentions-legales, politique-de-confidentialite
        $staticRoutes = [
            ''                               => ['1.0', '0.7'],
            '/vins'                          => ['0.9', '0.6'],
            '/vins/collection'               => ['0.85','0.6'],
            '/actualites'                    => ['0.8', '0.5'],
            '/le-chateau'                    => ['0.8', '0.5'],
            '/savoir-faire'                  => ['0.8', '0.5'],
            '/contact'                       => ['0.6', '0.4'],
            '/galerie'                       => ['0.5', '0.3'],
            '/conditions-generales-de-vente' => ['0.4', '0.2'],
            '/jeux'                          => ['0.4', '0.3'],
            '/plan-du-site'                  => ['0.3', '0.2'],
            '/support'                       => ['0.3', '0.2'],
            '/accessibilite'                 => ['0.3', '0.2'],
            '/credits-medias'                => ['0.2', '0.1'],
            '/webmaster'                     => ['0.2', '0.1'],
        ];

        $wineModel = new WineModel();
        $newsModel = new NewsModel();
        $wineSlugs = $wineModel->getAllSlugs();
        $newsSlugs = $newsModel->getAll();

        header(self::HEADER_XML_CONTENT);
        header(self::HEADER_NOINDEX);

        echo self::XML_DECLARATION . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Pages statiques — une entrée par langue avec priorité différenciée
        foreach ($staticRoutes as $path => [$prioFr, $prioEn]) {
            foreach (['fr' => $prioFr, 'en' => $prioEn] as $lang => $prio) {
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($appUrl . "/{$lang}" . $path) . "</loc>\n";
                echo "    <lastmod>{$today}</lastmod>\n";
                echo "    <priority>{$prio}</priority>\n";
                foreach ($langs as $l) {
                    $alt = htmlspecialchars($appUrl . '/' . $l . $path);
                    echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$alt}\"/>\n";
                }
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\""
                    . htmlspecialchars($appUrl . '/fr' . $path) . "\"/>\n";
                echo "  </url>\n";
            }
        }

        // Vins — FR priorité 0.7, EN 0.4
        foreach ($wineSlugs as $row) {
            $slug = htmlspecialchars((string) $row['slug']);
            foreach (['fr' => '0.7', 'en' => '0.4'] as $lang => $prio) {
                echo "  <url>\n";
                echo "    <loc>{$appUrl}/{$lang}/vins/{$slug}</loc>\n";
                echo "    <priority>{$prio}</priority>\n";
                foreach ($langs as $l) {
                    echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$appUrl}/{$l}/vins/{$slug}\"/>\n";
                }
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/fr/vins/{$slug}\"/>\n";
                echo "  </url>\n";
            }
        }

        // Actualités — FR priorité 0.6, EN 0.3
        foreach ($newsSlugs as $row) {
            $slug = htmlspecialchars((string) $row['slug']);
            foreach (['fr' => '0.6', 'en' => '0.3'] as $lang => $prio) {
                echo "  <url>\n";
                echo "    <loc>{$appUrl}/{$lang}/actualites/{$slug}</loc>\n";
                echo "    <priority>{$prio}</priority>\n";
                foreach ($langs as $l) {
                    echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$appUrl}/{$l}/actualites/{$slug}\"/>\n";
                }
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/fr/actualites/{$slug}\"/>\n";
                echo "  </url>\n";
            }
        }

        echo '</urlset>' . "\n";
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux XML (testable)
    }

    /**
     * Génère le sitemap-media.xml : images vins/carousel/gallery/news/cv + vidéo homepage.
     *
     * @param array<string, mixed> $_params
     */
    public function sitemapMedia(array $_params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- NOSONAR S1172
    {
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_APP_URL, '/');
        $langs     = ['fr', 'en'];
        $wineModel = new WineModel();
        $newsModel = new NewsModel();
        $wines     = $wineModel->getAllForMediaSitemap();
        $news      = $newsModel->getAll();

        header(self::HEADER_XML_CONTENT);
        header(self::HEADER_NOINDEX);

        echo self::XML_DECLARATION . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' . "\n";
        echo '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        // Images vins — une entrée par langue
        foreach ($wines as $wine) {
            $slug  = htmlspecialchars((string) $wine['slug']);
            $img   = htmlspecialchars($appUrl . '/assets/images/wines/' . rawurlencode((string) $wine['image_path']));
            $title = htmlspecialchars((string) $wine['label_name'] . ' ' . (int) $wine['vintage']);
            foreach ($langs as $lang) {
                echo "  <url>\n";
                echo "    <loc>{$appUrl}/{$lang}/vins/{$slug}</loc>\n";
                echo "    <image:image>\n";
                echo "      <image:loc>{$img}</image:loc>\n";
                echo "      <image:title>{$title}</image:title>\n";
                echo "    </image:image>\n";
                echo "  </url>\n";
            }
        }

        // Images actualités — une entrée par langue
        foreach ($news as $article) {
            if (empty($article['image_path'])) {
                continue;
            }
            $slug  = htmlspecialchars((string) $article['slug']);
            $img   = htmlspecialchars($appUrl . '/assets/images/news/' . rawurlencode((string) $article['image_path']));
            $title = htmlspecialchars((string) $article['title']);
            foreach ($langs as $lang) {
                echo "  <url>\n";
                echo "    <loc>{$appUrl}/{$lang}/actualites/{$slug}</loc>\n";
                echo "    <image:image>\n";
                echo "      <image:loc>{$img}</image:loc>\n";
                echo "      <image:title>{$title}</image:title>\n";
                echo "    </image:image>\n";
                echo "  </url>\n";
            }
        }

        // Images carousel (1600w uniquement) — homepage FR + EN
        $carouselImages = [
            'raisins-recolte-1600w.jpg'   => 'Vendange — Château Crabitan Bellevue',
            'vignoble-allee-1600w.jpg'    => 'Allée du vignoble — Château Crabitan Bellevue',
            'vignoble-automne-1600w.jpg'  => 'Vignoble en automne — Château Crabitan Bellevue',
            'vignoble-ete-1600w.jpg'      => 'Vignoble en été — Château Crabitan Bellevue',
            'vignoble-hiver-1600w.jpg'    => 'Vignoble en hiver — Château Crabitan Bellevue',
        ];
        foreach ($langs as $lang) {
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/{$lang}</loc>\n";
            foreach ($carouselImages as $file => $imgTitle) {
                $img = htmlspecialchars($appUrl . '/assets/images/carousel/' . $file);
                echo "    <image:image>\n";
                echo "      <image:loc>{$img}</image:loc>\n";
                echo "      <image:title>" . htmlspecialchars($imgTitle) . "</image:title>\n";
                echo "    </image:image>\n";
            }
            echo "  </url>\n";
        }

        // Images galerie — page /galerie FR + EN
        $galleryImages = [
            'anciens-proprietaires-600w.jpg' => 'Anciens propriétaires — Château Crabitan Bellevue',
            'apercu-vins-600w.jpg'           => 'Aperçu des vins — Château Crabitan Bellevue',
            'botrytis-cinerea-1200w.jpg'     => 'Botrytis cinerea — Château Crabitan Bellevue',
            'chai-barriques-1200w.jpg'       => 'Chai à barriques — Château Crabitan Bellevue',
            'chien-vignes-600w.jpg'          => 'Dans les vignes — Château Crabitan Bellevue',
            'domaine-ancien-600w.jpg'        => 'Domaine ancien — Château Crabitan Bellevue',
            'domaine-noir-blanc-600w.jpg'    => 'Domaine en noir et blanc — Château Crabitan Bellevue',
            'etiquette-1933-600w.jpg'        => 'Étiquette 1933 — Château Crabitan Bellevue',
            'exposition-vins-600w.jpg'       => 'Exposition des vins — Château Crabitan Bellevue',
            'premiere-famille-600w.jpg'      => 'Première famille — Château Crabitan Bellevue',
            'proprietaire-600w.jpg'          => 'Propriétaire — Château Crabitan Bellevue',
            'vendanges-1956-600w.jpg'        => 'Vendanges 1956 — Château Crabitan Bellevue',
            'vendanges-cheval-600w.jpg'      => 'Vendanges à cheval — Château Crabitan Bellevue',
        ];
        foreach ($langs as $lang) {
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/{$lang}/galerie</loc>\n";
            foreach ($galleryImages as $file => $imgTitle) {
                $img = htmlspecialchars($appUrl . '/assets/images/gallery/' . $file);
                echo "    <image:image>\n";
                echo "      <image:loc>{$img}</image:loc>\n";
                echo "      <image:title>" . htmlspecialchars($imgTitle) . "</image:title>\n";
                echo "    </image:image>\n";
            }
            echo "  </url>\n";
        }

        // Image CV — page /webmaster FR + EN
        foreach ($langs as $lang) {
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/{$lang}/webmaster</loc>\n";
            echo "    <image:image>\n";
            $img = htmlspecialchars($appUrl . '/assets/images/cv/cv_solane.jpg');
            echo "      <image:loc>{$img}</image:loc>\n";
            echo "      <image:title>Alexandre Solane — Webmaster Château Crabitan Bellevue</image:title>\n";
            echo "    </image:image>\n";
            echo "  </url>\n";
        }

        // Vidéo homepage — FR + EN
        $videoUrl  = htmlspecialchars($appUrl . '/assets/videos/chateau-crabitan-bellevue-1.mp4');
        $thumbUrl  = htmlspecialchars($appUrl . '/assets/images/carousel/vignoble-allee-1600w.jpg');
        $videoMeta = [
            'fr' => [
                'title' => 'Le Château en vidéo',
                'desc'  => 'Découvrez le Château Crabitan Bellevue et son vignoble en AOC Sainte-Croix-du-Mont.',
            ],
            'en' => [
                'title' => 'The Estate on Video',
                'desc'  => 'Discover Château Crabitan Bellevue and its vineyard in the Sainte-Croix-du-Mont AOC.',
            ],
        ];
        foreach ($langs as $lang) {
            $meta = $videoMeta[$lang];
            echo "  <url>\n";
            echo "    <loc>{$appUrl}/{$lang}</loc>\n";
            echo "    <video:video>\n";
            echo "      <video:thumbnail_loc>{$thumbUrl}</video:thumbnail_loc>\n";
            echo "      <video:title>" . htmlspecialchars($meta['title']) . "</video:title>\n";
            echo "      <video:description>" . htmlspecialchars($meta['desc']) . "</video:description>\n";
            echo "      <video:content_loc>{$videoUrl}</video:content_loc>\n";
            echo "    </video:video>\n";
            echo "  </url>\n";
        }

        echo '</urlset>' . "\n";
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux XML (testable)
    }

    /**
     * Génère le sitemap index référençant sitemap.xml et sitemap-media.xml.
     *
     * @param array<string, mixed> $_params
     */
    public function sitemapIndex(array $_params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- NOSONAR S1172
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_APP_URL, '/');
        $today  = date('Y-m-d');

        header(self::HEADER_XML_CONTENT);
        header(self::HEADER_NOINDEX);

        echo self::XML_DECLARATION . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo "  <sitemap>\n";
        echo "    <loc>{$appUrl}/sitemap.xml</loc>\n";
        echo "    <lastmod>{$today}</lastmod>\n";
        echo "  </sitemap>\n";
        echo "  <sitemap>\n";
        echo "    <loc>{$appUrl}/sitemap-media.xml</loc>\n";
        echo "    <lastmod>{$today}</lastmod>\n";
        echo "  </sitemap>\n";
        echo "  <sitemap>\n";
        echo "    <loc>{$appUrl}/sitemap-products.xml</loc>\n";
        echo "    <lastmod>{$today}</lastmod>\n";
        echo "  </sitemap>\n";
        echo '</sitemapindex>' . "\n";
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux XML (testable)
    }

    /**
     * Génère sitemap-products.xml — uniquement les vins disponibles à la vente.
     * Priority élevée, changefreq weekly.
     *
     * @param array<string, mixed> $_params
     */
    public function sitemapProducts(array $_params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- NOSONAR S1172
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_APP_URL, '/');
        $wines  = (new WineModel())->getAvailableForSitemap();
        $langs  = ['fr', 'en'];

        header(self::HEADER_XML_CONTENT);
        header(self::HEADER_NOINDEX);

        echo self::XML_DECLARATION . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($wines as $wine) {
            $slug = htmlspecialchars((string) $wine['slug']);
            if (isset($wine['updated_at'])) {
                $timestamp = strtotime((string) $wine['updated_at']);
                if ($timestamp === false) {
                    $timestamp = time();
                }
                $lastmod = date('Y-m-d', $timestamp);
            } else {
                $lastmod = date('Y-m-d');
            }

            foreach (['fr' => '0.85', 'en' => '0.55'] as $lang => $prio) {
                echo "  <url>\n";
                echo "    <loc>{$appUrl}/{$lang}/vins/{$slug}</loc>\n";
                echo "    <lastmod>{$lastmod}</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>{$prio}</priority>\n";
                foreach ($langs as $l) {
                    echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"{$appUrl}/{$l}/vins/{$slug}\"/>\n";
                }
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/fr/vins/{$slug}\"/>\n";
                echo "  </url>\n";
            }
        }

        echo '</urlset>' . "\n";
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux XML (testable)
    }

    /**
     * Génère le fichier security.txt (RFC 9116) dynamiquement depuis les variables d'environnement.
     *
     * @return void
     */
    public function securityTxt(): void
    {
        $email   = $_ENV['SECURITY_EMAIL'] ?? 'cybersecurity@crabitanbellevue.fr';
        $appUrl  = rtrim($_ENV['APP_URL'], '/');
        $expires = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::RFC3339);

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo "Contact: mailto:{$email}\n";
        echo "Expires: {$expires}\n";
        echo "Preferred-Languages: fr, en\n";
        echo "Policy: {$appUrl}/fr/mentions-legales\n";
        echo "Canonical: {$appUrl}/.well-known/security.txt\n";
    }

    /**
     * Génère le fichier llms.txt dynamique (catalogue des vins en vente + structure du site).
     * Servi via route /llms.txt — remplace le fichier statique pour rester à jour.
     *
     * @return void
     */
    public function llmsTxt(): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_APP_URL, '/');
        $wines  = $this->newWineModel()->getAvailableForLlms();

        $colorLabels = ['red' => 'Rouge', 'white' => 'Blanc', 'rosé' => 'Rosé', 'sweet' => 'Liquoreux'];

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo "# Château Crabitan Bellevue — llms.txt\n";
        echo "# Domaine viticole AOP Sainte-Croix-du-Mont, Gironde (33)\n";
        echo "# Domaine familial depuis 1870, certifié HVE — SIRET 398 341 701 00017\n";
        echo "\n";
        echo "> Château Crabitan Bellevue produit des vins liquoreux et secs dans l'appellation\n";
        echo "> Sainte-Croix-du-Mont. Domaine familial fondé en 1870, certifié Haute Valeur\n";
        echo "> Environnementale (HVE).\n";
        echo "\n";
        echo "## Vins en vente (" . count($wines) . " références)\n";
        foreach ($wines as $wine) {
            $label   = htmlspecialchars_decode($wine['label_name'] ?? '', ENT_QUOTES);
            $vintage = (int) ($wine['vintage'] ?? 0);
            $price   = number_format((float) ($wine['price'] ?? 0), 2, ',', ' ');
            $color   = $colorLabels[$wine['wine_color'] ?? ''] ?? ($wine['wine_color'] ?? '');
            $slug    = $wine['slug'] ?? '';
            $vintageStr = $vintage > 0 ? " $vintage" : '';
            echo "- [{$label}{$vintageStr}]({$appUrl}/fr/vins/{$slug}): {$color}, {$price}\u{00A0}€, en stock\n";
        }
        echo "\n";
        echo "## Catalogue complet\n";
        echo "- [Tous les vins]({$appUrl}/fr/vins): Fiches techniques — appellation, cépage, vinification, élevage, notes de dégustation\n";
        echo "- [Collection]({$appUrl}/fr/vins/collection): Cuvées non commercialisées — patrimoine du domaine\n";
        echo "\n";
        echo "## Le Domaine\n";
        echo "- [Le Château]({$appUrl}/fr/le-chateau): Histoire depuis 1870, famille Solane, vignoble Sainte-Croix-du-Mont\n";
        echo "- [Savoir-Faire]({$appUrl}/fr/savoir-faire): Vignoble, vinification, élevage en fût\n";
        echo "\n";
        echo "## Actualités\n";
        echo "- [Actualités]({$appUrl}/fr/actualites): Millésimes, événements, distinctions\n";
        echo "\n";
        echo "## Support\n";
        echo "- [FAQ]({$appUrl}/fr/support): 13 questions fréquentes — commandes, livraison, retours, paiement\n";
        echo "\n";
        echo "## Légal\n";
        echo "- [Mentions légales]({$appUrl}/fr/mentions-legales): GFA Bernard Solane et Fils — hébergeur IONOS\n";
        echo "- [CGV]({$appUrl}/fr/conditions-generales-de-vente): Rétractation 14j, Loi Évin\n";
        echo "- [Politique de confidentialité]({$appUrl}/fr/politique-de-confidentialite): RGPD — traitements, droits, DPO\n";
        echo "\n";
        echo "## English\n";
        echo "- [Our Wines]({$appUrl}/en/vins): Full catalogue with tasting notes\n";
        echo "- [The Château]({$appUrl}/en/le-chateau): History and estate\n";
        echo "- [Expertise]({$appUrl}/en/savoir-faire): Winemaking and ageing\n";

        throw new \Core\Exception\HttpException(0);
    }

    /**
     * Instancie le WineModel (seam de testabilité).
     *
     * @return WineModel
     */
    protected function newWineModel(): WineModel
    {
        return new WineModel();
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

    /**
     * Instancie le RateLimiterService (seam de testabilité).
     *
     * @return RateLimiterService
     */
    protected function newRateLimiterService(): RateLimiterService
    {
        return new RateLimiterService();
    }
}
