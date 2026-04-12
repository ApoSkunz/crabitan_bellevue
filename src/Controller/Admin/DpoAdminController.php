<?php

declare(strict_types=1);

namespace Controller\Admin;

/**
 * Contrôleur DPO — génération des documents RGPD (Art. 28, 30, 33) au format PDF.
 * Accès réservé aux rôles admin et super_admin.
 */
class DpoAdminController extends AdminController
{
    private const CREATOR     = 'Château Crabitan Bellevue';
    private const AUTHOR      = 'GFA Bernard Solane et Fils';
    private const SIRET       = '398 341 701 00017';
    private const ADDRESS     = 'Château Crabitan Bellevue — 33410 Sainte-Croix-du-Mont — France';
    /** @return string Email DPO lu depuis la variable d'environnement DPO_EMAIL */
    private function dpoEmail(): string
    {
        return $_ENV['DPO_EMAIL'];
    }
    private const DATE_FORMAT = 'd/m/Y';
    // Offset révision annuelle (S1192)
    private const NEXT_YEAR = '+1 year';
    // Labels répétés dans les tableaux RGPD (S1192)
    private const ROW_FINALITE      = 'Finalité';
    private const ROW_BASE_LEGALE   = 'Base légale';
    private const ROW_DUREE         = 'Durée de conservation';
    private const ROW_DONNEES       = 'Données traitées';
    private const ROW_PERSONNES     = 'Personnes concernées';
    private const ROW_CRITERES_DPIA = 'Critères DPIA remplis';
    private const ROW_NECESSITE     = 'Nécessité';
    private const ROW_PROPORTION    = 'Proportionnalité';
    private const ROW_OPPOSITION    = "Droit d'opposition";

    /** @var array<string, array<string, mixed>>|null Cache de la configuration légale */
    private ?array $legalConfigCache = null;

    /**
     * Charge et met en cache la configuration légale depuis la BDD.
     *
     * @return array<string, array<string, mixed>>
     */
    private function legalConfig(): array
    {
        if ($this->legalConfigCache === null) {
            $this->legalConfigCache = (new \Model\LegalDocumentModel())->findAll();
        }
        return $this->legalConfigCache;
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo
    // ----------------------------------------------------------------

    /**
     * Page index — liste des documents RGPD téléchargeables.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/dpo/index', [
            'adminUser'   => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'   => 'DPO — Documents RGPD',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'DPO — Documents RGPD'],
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/registre-traitements
    // ----------------------------------------------------------------

    /**
     * Génère et télécharge le registre des activités de traitement (Art. 30 RGPD).
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function downloadRegistre(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();
        $pdfBytes = $this->buildPdf('Registre des traitements — Art. 30 RGPD', $this->htmlRegistre());
        $this->sendPdf($pdfBytes, 'registre-traitements-art30.pdf');
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/sous-traitants
    // ----------------------------------------------------------------

    /**
     * Génère et télécharge le document DPA sous-traitants (Art. 28 RGPD).
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function downloadSousTraitants(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();
        $pdfBytes = $this->buildPdf('Sous-traitants — Art. 28 RGPD', $this->htmlSousTraitants());
        $this->sendPdf($pdfBytes, 'sous-traitants-art28.pdf');
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/matrice-habilitations
    // ----------------------------------------------------------------

    /**
     * Génère et télécharge la matrice des habilitations RGPD au format PDF.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function downloadMatrice(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();
        $pdfBytes = $this->buildPdf('Matrice des habilitations RGPD', $this->htmlMatrice());
        $this->sendPdf($pdfBytes, 'matrice-habilitations-rgpd.pdf');
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/procedure-violation
    // ----------------------------------------------------------------

    /**
     * Génère et télécharge la procédure de notification de violation (Art. 33 RGPD).
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function downloadProcedure(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();
        $pdfBytes = $this->buildPdf('Procédure violation de données — Art. 33 RGPD', $this->htmlProcedure());
        $this->sendPdf($pdfBytes, 'procedure-violation-art33.pdf');
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/dpia
    // ----------------------------------------------------------------

    /**
     * Génère et télécharge la DPIA — Analyse d'impact (Art. 35 RGPD).
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function downloadDpia(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();
        $pdfBytes = $this->buildPdf('DPIA — Analyse d\'impact relative à la protection des données — Art. 35 RGPD', $this->htmlDpia());
        $this->sendPdf($pdfBytes, 'dpia-art35-rgpd.pdf');
    }

    // ----------------------------------------------------------------
    // Helpers privés — génération PDF
    // ----------------------------------------------------------------

    /**
     * Construit le PDF via TCPDF et retourne les octets en mémoire.
     *
     * @throws \Exception\TcpdfNotAvailableException Si TCPDF n'est pas disponible
     */
    private function buildPdf(string $title, string $html): string
    {
        $tcpdfPath = ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!file_exists($tcpdfPath)) {
            throw new \Exception\TcpdfNotAvailableException('TCPDF non disponible : ' . $tcpdfPath);
        }
        require_once $tcpdfPath;

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(self::CREATOR);
        $pdf->SetAuthor(self::AUTHOR);
        $pdf->SetTitle($title);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return (string) $pdf->Output('', 'S');
    }

    /**
     * Envoie le PDF en prévisualisation navigateur (Content-Disposition: inline).
     */
    private function sendPdf(string $pdfBytes, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, no-cache');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
    }

    // ----------------------------------------------------------------
    // Helpers privés — contenu HTML des documents RGPD
    // ----------------------------------------------------------------

    /**
     * Retourne le bloc d'ouverture commun (styles + en-tête) d'un document PDF.
     */
    private function pdfOpen(string $title, string $subtitle, string $date): string
    {
        return $this->pdfStyles() . $this->pdfHeader($title, $subtitle, $date);
    }

    /**
     * Retourne le paragraphe de pied de page commun à tous les PDFs.
     *
     * @param string $notice Texte de la notice (sans "Généré le")
     * @param string $date   Date de génération au format DATE_FORMAT
     */
    private function pdfFooter(string $notice, string $date): string
    {
        return '<p class="footer">' . $notice . ' — Généré le ' . $date . '</p>';
    }

    /** Styles CSS communs injectés dans chaque PDF. */
    private function pdfStyles(): string
    {
        return '<style>
            body  { font-family: dejavusans; font-size: 9pt; color: #1a1208; }
            h1    { font-size: 14pt; color: #c1a14b; margin: 0 0 2px; }
            h2    { font-size: 11pt; color: #c1a14b; margin: 14px 0 4px; border-bottom: 1px solid #c1a14b; padding-bottom: 2px; }
            h3    { font-size: 9pt; color: #5a4030; margin: 10px 0 3px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
            th    { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; font-size: 8pt; }
            td    { padding: 3px 6px; border-bottom: 1px solid #e8e0d0; font-size: 8pt; vertical-align: top; }
            td.label { font-weight: bold; width: 35%; color: #5a4030; }
            .meta { font-size: 8pt; color: #8a7060; margin: 0 0 12px; }
            .footer { font-size: 7.5pt; color: #888; margin-top: 14px; border-top: 1px solid #e0d8cc; padding-top: 6px; }
            .tag-ok   { color: #2e7d32; }
            .tag-warn { color: #e65100; }
        </style>';
    }

    /** En-tête commune à tous les PDFs. */
    private function pdfHeader(string $title, string $subtitle, string $date): string
    {
        return '<h1>' . htmlspecialchars(self::CREATOR) . '</h1>'
            . '<p class="meta">'
            . htmlspecialchars(self::AUTHOR) . ' — SIRET&nbsp;: ' . htmlspecialchars(self::SIRET) . '<br>'
            . htmlspecialchars(self::ADDRESS) . '<br>'
            . 'DPO&nbsp;: ' . htmlspecialchars($this->dpoEmail())
            . '</p>'
            . '<h2>' . htmlspecialchars($title) . '</h2>'
            . '<p class="meta">' . htmlspecialchars($subtitle) . ' — Généré le ' . htmlspecialchars($date) . '</p>';
    }

    /** Ligne de tableau à deux colonnes label / valeur. */
    private function row(string $label, string $value): string
    {
        return '<tr><td class="label">' . htmlspecialchars($label) . '</td>'
            . '<td>' . $value . '</td></tr>';
    }

    /**
     * Retourne le contenu HTML du registre des activités de traitement (Art. 30 RGPD).
     */
    private function htmlRegistre(): string
    {
        $legal   = $this->legalConfig();
        $version = $legal['registre_traitements']['version'] ?? '1.0';
        $updAt   = \DateTime::createFromFormat('Y-m-d', $legal['registre_traitements']['updated_at'] ?? date('Y-m-d'));
        $date    = $updAt !== false ? $updAt->format(self::DATE_FORMAT) : date(self::DATE_FORMAT);

        $treatments = [
            [
                'title'      => '1. Gestion des comptes clients',
                'finalite'   => 'Gestion de l\'espace personnel, authentification, historique commandes',
                'base'       => 'Art. 6-1-b — exécution du contrat',
                'donnees'    => 'Nom, prénom, e-mail, MDP haché (bcrypt), adresse, téléphone, tokens JWT/MFA',
                'personnes'  => 'Clients ayant créé un compte',
                'destinataires' => 'GFA Bernard Solane et Fils (interne) — IONOS SE (hébergement)',
                'duree'      => 'Durée de la relation + 3 ans après inactivité',
                'transfert'  => 'Non',
                'securite'   => 'HTTPS/TLS, bcrypt, JWT HMAC-SHA256, MFA TOTP, rate limiting',
            ],
            [
                'title'      => '2. Traitement des commandes et facturation',
                'finalite'   => 'Exécution des commandes, émission de factures, archivage comptable',
                'base'       => 'Art. 6-1-b (contrat) + Art. 6-1-c (obligation légale — L123-22 C.com.)',
                'donnees'    => 'Nom, prénom, adresses livraison/facturation, e-mail, téléphone, produits, montant, UUID commande',
                'personnes'  => 'Clients ayant passé une commande',
                'destinataires' => 'GFA (interne) — IONOS SE — Crédit Agricole (paiement CB) — Transporteur (livraison)',
                'duree'      => '10 ans (obligation comptable Art. L123-22 C.com.)',
                'transfert'  => 'Non',
                'securite'   => 'HTTPS/TLS, UUID v4, validation IPN, prestataire paiement PCI-DSS certifié',
            ],
            [
                'title'      => '3. Newsletter',
                'finalite'   => 'Envoi de newsletters commerciales sur les actualités et offres du domaine',
                'base'       => 'Art. 6-1-a — consentement explicite (double opt-in)',
                'donnees'    => 'Adresse e-mail, token confirmation (SHA-256), date de consentement, statut abonnement',
                'personnes'  => 'Visiteurs et clients ayant souscrit à la newsletter',
                'destinataires' => 'GFA Bernard Solane et Fils (interne) — IONOS SE (SMTP sortant)',
                'duree'      => 'Jusqu\'à désinscription + 3 ans si compte inactif',
                'transfert'  => 'Non',
                'securite'   => 'Double opt-in, lien désinscription dans chaque email, token HMAC à usage unique',
            ],
            [
                'title'      => '4. Cookies analytics (Google Analytics 4)',
                'finalite'   => 'Mesure d\'audience — pages consultées, durée de session',
                'base'       => 'Art. 6-1-a — consentement (Art. 82 LIL)',
                'donnees'    => 'Identifiant aléatoire GA4, pages visitées, durée de session, IP anonymisée',
                'personnes'  => 'Visiteurs ayant accepté les cookies analytics',
                'destinataires' => 'GFA (interne) — Google LLC (États-Unis — SCCs)',
                'duree'      => 'Cookie : 13 mois max — Données GA4 : 26 mois',
                'transfert'  => 'Oui — Google LLC (États-Unis) — SCCs + EU-US Data Privacy Framework',
                'securite'   => 'Consentement préalable obligatoire, anonymisation IP, aucun chargement sans consentement',
            ],
            [
                'title'      => '5. Logs de connexion',
                'finalite'   => 'Sécurité applicative, détection tentatives d\'intrusion et force brute',
                'base'       => 'Art. 6-1-f — intérêt légitime (sécurité du SI)',
                'donnees'    => 'IP, user-agent, timestamp, action (connexion/échec/déconnexion), ID compte',
                'personnes'  => 'Tous les visiteurs s\'authentifiant',
                'destinataires' => 'GFA (accès admin restreint) — IONOS SE (hébergement)',
                'duree'      => '1 an (recommandation CNIL — LCEN Art. L34-1)',
                'transfert'  => 'Non',
                'securite'   => 'Accès restreint administrateurs, rotation automatique',
            ],
            [
                'title'      => '6. Logs serveur Apache',
                'finalite'   => 'Monitoring technique, débogage, statistiques de charge serveur',
                'base'       => 'Art. 6-1-f — intérêt légitime (maintenance)',
                'donnees'    => 'IP, URL, user-agent, code HTTP, horodatage',
                'personnes'  => 'Tous les visiteurs du site',
                'destinataires' => 'GFA (accès admin) — IONOS SE',
                'duree'      => '1 an',
                'transfert'  => 'Non',
                'securite'   => 'Accès restreint administrateurs, hébergement en Allemagne (UE)',
            ],
            [
                'title'      => '7. Sauvegardes et chiffrement de la base de données',
                'finalite'   => 'Continuité de service, plan de reprise d\'activité, protection des données au repos',
                'base'       => 'Art. 6-1-f — intérêt légitime (résilience) + Art. 32 RGPD (sécurité technique)',
                'donnees'    => 'Ensemble des données BDD (comptes, commandes, newsletter — colonnes sensibles chiffrées)',
                'personnes'  => 'Tous les utilisateurs enregistrés',
                'destinataires' => 'GFA (DevSecOps restreint) — IONOS SE (stockage)',
                'duree'      => '90 jours (rotation)',
                'transfert'  => 'Non',
                'securite'   => 'Chiffrement AES-256-GCM colonnes sensibles (RGPD Art. 32) — sauvegardes chiffrees au repos — preparation chiffrement post-quantique (QPC/NIST PQC) — acces restreint DevSecOps', // phpcs:ignore Generic.Files.LineLength.TooLong
            ],
            [
                'title'      => '8. Traductions DeepL (API)',
                'finalite'   => 'Traduction de l\'interface bilingue (FR &#8596; EN)',
                'base'       => 'Art. 6-1-b — nécessaire à la fourniture du service',
                'donnees'    => 'Chaînes de texte statiques (vins, actualités, newsletter) — aucune donnée personnelle',
                'personnes'  => 'N/A — contenu éditorial uniquement',
                'destinataires' => 'DeepL SE (Allemagne, UE)',
                'duree'      => 'Aucune — traitement temps réel',
                'transfert'  => 'Non applicable — aucune donnée personnelle transmise (confirmé PO)',
                'securite'   => 'API HTTPS/TLS',
            ],
            [
                'title'      => '9. Demandes de contact',
                'finalite'   => 'Traitement des demandes via le formulaire de contact',
                'base'       => 'Art. 6-1-f — intérêt légitime',
                'donnees'    => 'Nom, adresse e-mail, contenu du message',
                'personnes'  => 'Toute personne utilisant le formulaire de contact',
                'destinataires' => 'GFA (interne) — IONOS SE (SMTP)',
                'duree'      => '3 ans à compter du dernier contact',
                'transfert'  => 'Non',
                'securite'   => 'HTTPS/TLS, CSRF token, validation serveur',
            ],
            [
                'title'      => '10. Authentification sociale (Google OAuth)',
                'finalite'   => 'Authentification via compte Google sans création de mot de passe',
                'base'       => 'Art. 6-1-b — exécution du contrat',
                'donnees'    => 'Identifiant OAuth (sub), e-mail, prénom fournis par Google',
                'personnes'  => 'Clients choisissant l\'authentification Google',
                'destinataires' => 'GFA (interne) — Google LLC (OAuth)',
                'duree'      => 'Identique au compte client',
                'transfert'  => 'Oui — Google LLC (États-Unis, SCCs)',
                'securite'   => 'Paramètre state anti-CSRF, token PKCE, aucun token OAuth stocké',
            ],
        ];

        $html = $this->pdfOpen(
            'Registre des activités de traitement — v' . htmlspecialchars($version),
            'Conformément à l\'Art. 30 RGPD — Règlement UE 2016/679',
            $date
        );

        foreach ($treatments as $t) {
            $html .= '<h3>' . htmlspecialchars($t['title']) . '</h3>'
                . '<table>'
                . $this->row(self::ROW_FINALITE, htmlspecialchars($t['finalite']))
                . $this->row(self::ROW_BASE_LEGALE, htmlspecialchars($t['base']))
                . $this->row('Catégories de données', htmlspecialchars($t['donnees']))
                . $this->row('Catégories de personnes', htmlspecialchars($t['personnes']))
                . $this->row('Destinataires', htmlspecialchars($t['destinataires']))
                . $this->row(self::ROW_DUREE, htmlspecialchars($t['duree']))
                . $this->row('Transfert hors UE', htmlspecialchars($t['transfert']))
                . $this->row('Mesures de sécurité', htmlspecialchars($t['securite']))
                . '</table>';
        }

        $html .= $this->pdfFooter('Document confidentiel — à la disposition de la CNIL sur demande (Art. 30-4 RGPD)', $date);

        return $html;
    }

    /**
     * Retourne le contenu HTML du document DPA sous-traitants (Art. 28 RGPD).
     */
    private function htmlSousTraitants(): string
    {
        $legal   = $this->legalConfig();
        $version = $legal['sous_traitants']['version'] ?? '1.0';
        $updAt   = \DateTime::createFromFormat('Y-m-d', $legal['sous_traitants']['updated_at'] ?? date('Y-m-d'));
        $date    = $updAt !== false ? $updAt->format(self::DATE_FORMAT) : date(self::DATE_FORMAT);

        $processors = [
            [
                'name'     => 'IONOS SE',
                'role'     => 'Hébergement serveur web (Apache/PHP/MySQL) + envoi d\'e-mails transactionnels (SMTP)',
                'location' => 'Allemagne (UE)',
                'transfer' => 'Non',
                'dpa'      => 'DPA inclus dans les CGU IONOS — ionos.fr/assistance/cgu',

                'note'     => 'Vérification annuelle des CGU recommandée.',
            ],
            [
                'name'     => 'DeepL SE',
                'role'     => 'API de traduction (FR &#8596; EN) — contenu éditorial uniquement, aucune donnée personnelle',
                'location' => 'Allemagne (UE)',
                'transfer' => 'Non applicable — aucune donnée personnelle transmise (confirmé PO)',
                'dpa'      => 'Art. 28 non applicable — pas de données personnelles transmises',

                'note'     => 'Si usage évolue vers contenus personnalisés, migrer vers DeepL Pro + DPA.',
            ],
            [
                'name'     => 'GitHub Inc. (Microsoft)',
                'role'     => 'Hébergement du code source, CI/CD GitHub Actions',
                'location' => 'États-Unis (Azure)',
                'transfer' => 'Oui — SCCs Microsoft',
                'dpa'      => 'GitHub Customer Agreement + DPA Microsoft — github.com/customer-agreement',

                'note'     => 'Aucune donnée personnelle client dans le code source versionné.',
            ],
            [
                'name'     => 'SonarSource SA (SonarCloud)',
                'role'     => 'Analyse statique du code (SAST), détection de vulnérabilités',
                'location' => 'Suisse + AWS EU (Frankfurt)',
                'transfer' => 'Non',
                'dpa'      => 'SonarCloud Privacy Policy — sonarcloud.io — DPA sur demande (offre commerciale)',

                'note'     => 'Aucune donnée personnelle traitée — uniquement code source.',
            ],
            [
                'name'     => 'Google LLC',
                'role'     => 'Authentification OAuth 2.0 + Google Analytics 4 (si consentement)',
                'location' => 'États-Unis',
                'transfer' => 'Oui — SCCs + EU-US Data Privacy Framework',
                'dpa'      => 'Google Cloud DPA — cloud.google.com/terms/data-processing-addendum',

                'note'     => 'GA4 conditionné au consentement CNIL. IP anonymisée avant stockage.',
            ],
            [
                'name'     => 'Crédit Agricole',
                'role'     => 'Traitement des paiements par carte bancaire (certifié PCI-DSS)',
                'location' => 'France (UE)',
                'transfer' => 'Non',
                'dpa'      => 'Contrat commercial + DPA PSP inclus',

                'note'     => 'Aucune CB stockée sur nos serveurs. Prestataire PCI-DSS certifié.',
            ],
        ];

        $html = $this->pdfOpen(
            'Sous-traitants — DPA — v' . htmlspecialchars($version),
            'Conformément à l\'Art. 28 RGPD — Règlement UE 2016/679',
            $date
        );

        $html .= '<p class="meta">Prochaine révision annuelle : ' . date(self::DATE_FORMAT, strtotime(self::NEXT_YEAR)) . '</p>';

        foreach ($processors as $p) {
            $html .= '<h3>' . htmlspecialchars($p['name']) . ' — <span class="tag-ok">Conforme</span></h3>'
                . '<table>'
                . $this->row('Rôle', htmlspecialchars($p['role']))
                . $this->row('Localisation', htmlspecialchars($p['location']))
                . $this->row('Transfert hors UE', htmlspecialchars($p['transfer']))
                . $this->row('Base contractuelle RGPD', htmlspecialchars($p['dpa']))
                . $this->row('Note', htmlspecialchars($p['note']))
                . '</table>';
        }

        $html .= $this->pdfFooter('Document confidentiel — Art. 28 RGPD', $date);

        return $html;
    }

    /**
     * Retourne le contenu HTML de la procédure de notification de violation (Art. 33 RGPD).
     */
    private function htmlProcedure(): string
    {
        $legal    = $this->legalConfig();
        $version  = $legal['procedure_violation']['version'] ?? '1.0';
        $updAt    = \DateTime::createFromFormat('Y-m-d', $legal['procedure_violation']['updated_at'] ?? date('Y-m-d'));
        $date     = $updAt !== false ? $updAt->format(self::DATE_FORMAT) : date(self::DATE_FORMAT);
        $revision = date(self::DATE_FORMAT, strtotime(self::NEXT_YEAR));

        $html = $this->pdfOpen(
            'Procédure violation de données — v' . htmlspecialchars($version),
            'Conformément aux Art. 33 &amp; 34 RGPD — délai légal 72h',
            $date
        );

        $html .= '<h2>1. Définition d\'une violation (Art. 4-12 RGPD)</h2>'
            . '<p>Violation de la sécurité entraînant, de manière accidentelle ou illicite, la destruction, la perte, l\'altération, la divulgation non autorisée ou l\'accès non autorisé à des données à caractère personnel.</p>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<p><strong>Exemples :</strong> accès non autorisé à la BDD clients · fuite d\'e-mails ou MDP hachés · panne/suppression accidentelle · ransomware · envoi d\'e-mail au mauvais destinataire avec données tierces.</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= '<h2>2. Arbre de décision — Notification CNIL obligatoire ?</h2>'
            . '<table>'
            . '<tr><th>Niveau de risque</th><th>Notification CNIL (Art. 33)</th><th>Communication aux personnes (Art. 34)</th></tr>'
            . '<tr><td>Risque faible (données déjà publiques, chiffrement fort)</td><td>Non obligatoire</td><td>Non</td></tr>'
            . '<tr><td>Risque probable (e-mails exposés, MDP hachés fuitent)</td><td><strong>Obligatoire ≤ 72h</strong></td><td>Non</td></tr>'
            . '<tr><td>Risque élevé (MDP en clair, CB, données sensibles)</td><td><strong>Obligatoire ≤ 72h</strong></td><td><strong>Sans délai injustifié</strong></td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>'
            . '<p><strong>Principe de précaution :</strong> en cas de doute, notifier. Le délai de 72h court dès la prise de connaissance de la violation, pas depuis sa survenance.</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= '<h2>3. Procédure d\'escalade</h2>'
            . '<table>'
            . '<tr><th>Étape</th><th>Qui</th><th>Délai</th></tr>'
            . '<tr><td>Détecter et qualifier l\'incident (nature, périmètre, données)</td><td>DevSecOps / Toute personne</td><td>Immédiatement</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<tr><td>Contenir : isoler système, révoquer tokens, bloquer IP</td><td>DevSecOps</td><td>&lt; 1h</td></tr>'
            . '<tr><td>Alerter le référent RGPD (email + téléphone)</td><td>DevSecOps &#8594; DPO</td><td>&lt; 2h</td></tr>'
            . '<tr><td>Évaluer le risque (arbre de décision ci-dessus)</td><td>DPO + DevSecOps</td><td>&lt; 4h</td></tr>'
            . '<tr><td>Alerter la direction si risque probable ou élevé</td><td>DPO &#8594; Direction</td><td>&lt; 4h</td></tr>'
            . '<tr><td>Notifier la CNIL si obligatoire (notifications.cnil.fr)</td><td>DPO</td><td><strong>≤ 72h</strong></td></tr>'
            . '<tr><td>Communiquer aux personnes concernées si risque élevé</td><td>DPO + Direction</td><td>Sans délai injustifié</td></tr>'
            . '<tr><td>Documenter dans le registre interne des violations</td><td>DPO</td><td>Dans les 72h</td></tr>'
            . '<tr><td>Rapport post-incident</td><td>DevSecOps + DPO</td><td>Dans les 7 jours</td></tr>'
            . '</table>';

        $html .= '<h2>4. Contacts d\'urgence</h2>'
            . '<table>'
            . $this->row('Référent RGPD / DPO', htmlspecialchars($this->dpoEmail()))
            . $this->row('CNIL — notification en ligne', 'https://notifications.cnil.fr/')
            . $this->row('CNIL — standard', '01 53 73 22 22')
            . '</table>';

        $html .= '<h2>5. Éléments de la notification CNIL (Art. 33)</h2>'
            . '<table>'
            . '<tr><th>Champ</th><th>Contenu attendu</th></tr>'
            . '<tr><td>Nature de la violation</td><td>Décrire : accès non autorisé / divulgation / perte / altération</td></tr>'
            . '<tr><td>Catégories et volume de personnes concernées</td><td>Ex. : environ X comptes clients — e-mails, adresses</td></tr>'
            . '<tr><td>Catégories et volume d\'enregistrements</td><td>Ex. : table users — X enregistrements — e-mails, MDP hachés</td></tr>'
            . '<tr><td>Conséquences probables</td><td>Ex. : risque phishing, credential stuffing</td></tr>'
            . '<tr><td>Mesures prises ou envisagées</td><td>Révocation tokens, réinitialisation MDP, correction faille</td></tr>'
            . '</table>';

        $html .= '<h2>6. Registre interne des violations</h2>'
            . '<p>Toutes les violations doivent être documentées, y compris celles ne nécessitant pas de notification CNIL (Art. 33-5 RGPD). Tenir un fichier de suivi séparé (tableur ou outil interne) avec les colonnes : date de détection, nature, périmètre, risque évalué, notification CNIL, communication personnes, mesures prises, clôture.</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= '<h2>7. Simulation annuelle</h2>'
            . '<p>Exercice annuel avant le 30 juin. Scénario type : "détection d\'une requête SQL suspecte — données clients potentiellement extraites." Déroulé : qualification (30 min) &#8594; test chaîne escalade &#8594; rédaction template CNIL (sans envoi) &#8594; debriefing. Responsable : DevSecOps + DPO.</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= $this->pdfFooter('Document confidentiel — Art. 33 &amp; 34 RGPD — Prochaine révision : ' . $revision, $date);

        return $html;
    }

    /**
     * Retourne le contenu HTML de la matrice des habilitations RGPD.
     * Basé sur le code réel du projet (controllers, models, vues, schéma BDD v3).
     */
    private function htmlMatrice(): string
    {
        $legal    = $this->legalConfig();
        $version  = $legal['matrice_habilitations']['version'] ?? '1.0';
        $updatedAt = \DateTime::createFromFormat('Y-m-d', $legal['matrice_habilitations']['updated_at'] ?? date('Y-m-d'));
        $date     = $updatedAt !== false ? $updatedAt->format(self::DATE_FORMAT) : date(self::DATE_FORMAT);
        $revision = date(self::DATE_FORMAT, strtotime(self::NEXT_YEAR, $updatedAt !== false ? $updatedAt->getTimestamp() : time())); // phpcs:ignore Generic.Files.LineLength.TooLong

        $html = $this->pdfOpen(
            'Matrice des habilitations RGPD — v' . htmlspecialchars($version),
            'Rôle × Ressource × Données personnelles visibles — Art. 5-1-c &amp; Art. 32 RGPD',
            $date
        );

        $html .= '<h2>1. Rôles applicatifs</h2>'
            . '<table>'
            . '<tr><th>Rôle</th><th>Définition</th><th>Middleware</th></tr>'
            . '<tr><td>visiteur</td><td>Utilisateur non authentifié</td><td>Aucun</td></tr>'
            . '<tr><td>customer</td><td>Client authentifié (JWT valide)</td><td>AuthMiddleware</td></tr>'
            . '<tr><td>admin</td><td>Administrateur</td><td>AdminMiddleware (admin | super_admin)</td></tr>'
            . '<tr><td>super_admin</td><td>Super administrateur</td><td>AdminMiddleware</td></tr>'
            . '</table>';

        $html .= '<h2>2. Comptes clients (table accounts)</h2>'
            . '<table>'
            . '<tr><th>Donnée</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>id, email, role, lang, account_type</td><td>—</td><td>Oui</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>firstname, lastname, civility (profil)</td><td>—</td><td>Oui</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>company_name, siret (profil société)</td><td>—</td><td>Oui</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>email_verified_at, has_connected, created_at</td><td>—</td><td>—</td><td>Oui</td><td>Oui</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<tr><td>password (hash bcrypt)</td><td>—</td><td>—</td><td>—</td><td>Oui</td></tr>'
            . '<tr><td>Tokens internes (email, newsletter, sécurité)</td><td>—</td><td>—</td><td>—</td><td>Oui</td></tr>'
            . '<tr><td>google_id, apple_id, majority_declared_ip</td><td>—</td><td>—</td><td>—</td><td>Oui</td></tr>'
            . '</table>'
            . '<p><small>(*) Colonnes sensibles exclues de AccountModel::getForAdmin() — minimisation Art. 5-1-c RGPD. Accessibles au super_admin via accès direct BDD (rôle mainteneur).</small></p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= '<h2>3. Adresses (table addresses)</h2>'
            . '<table>'
            . '<tr><th>Donnée</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>Nom, prénom, adresse, téléphone</td><td>—</td><td>Propres adresses</td><td>Via snapshots commandes</td><td>Via snapshots commandes</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>';

        $html .= '<h2>4. Commandes (orders + snapshots)</h2>'
            . '<table>'
            . '<tr><th>Donnée</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>Référence, contenu, prix, statut, méthode paiement</td><td>—</td><td>Propres</td><td>Toutes (lecture + MAJ statut)</td><td>Toutes</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<tr><td>Snapshot adresse livraison/facturation</td><td>—</td><td>Propres</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>Email client (jointure)</td><td>—</td><td>Propre</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>Données bancaires CB</td><td>—</td><td>—</td><td>— (PSP PCI-DSS)</td><td>— (PSP PCI-DSS)</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>';

        $html .= '<h2>5. Sessions / Logs connexion (table connections)</h2>'
            . '<table>'
            . '<tr><th>Donnée</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>Token JWT, IP, user-agent, device</td><td>—</td><td>Propres sessions</td><td>—</td><td>Propres sessions + gestion</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>';

        $html .= '<h2>6. Journaux d\'audit</h2>'
            . '<table>'
            . '<tr><th>Journal</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>audit_log (RGPD Art. 30)</td><td>—</td><td>—</td><td>—</td><td>Accès BDD direct (DevSecOps)</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<tr><td>admin_logs (accountability Art. 5.2)</td><td>—</td><td>—</td><td>—</td><td>Lecture via /admin/logs (AuditLogAdminController)</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>';

        $html .= '<h2>7. Documents RGPD DPO</h2>'
            . '<table>'
            . '<tr><th>Document</th><th>Visiteur</th><th>Customer</th><th>Admin</th><th>Super Admin</th></tr>'
            . '<tr><td>Registre des traitements (Art. 30)</td><td>—</td><td>—</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>Sous-traitants DPA (Art. 28)</td><td>—</td><td>—</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>Procédure violation (Art. 33)</td><td>—</td><td>—</td><td>Oui</td><td>Oui</td></tr>'
            . '<tr><td>Matrice des habilitations</td><td>—</td><td>—</td><td>Oui</td><td>Oui</td></tr>'
            . '</table>';

        $html .= '<h2>8. Procédure de révision</h2>'
            . '<p>Révision annuelle obligatoire (au plus tard le 31 mars), ou à chaque nouveau controller/route admin exposant des données personnelles, ou après tout audit Red Team. Responsable : Expert PHP + Expert RGPD. Validation : DPO.</p>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<p>Document source : <strong>docs/rgpd/matrice-habilitations.md</strong> (versionné dans le dépôt).</p>';

        $html .= $this->pdfFooter(
            'Document confidentiel — v' . htmlspecialchars($version) . ' — Art. 5-1-c &amp; Art. 32 RGPD — Prochaine révision : ' . $revision, // phpcs:ignore Generic.Files.LineLength.TooLong
            $date
        );

        return $html;
    }

    /**
     * Retourne le contenu HTML de la DPIA (Art. 35 RGPD).
     *
     * Couvre les trois traitements identifiés comme susceptibles d'engendrer un risque élevé :
     * relance panier abandonné, profilage inactivité client, tracking newsletter.
     */
    private function htmlDpia(): string
    {
        $legal    = $this->legalConfig();
        $version  = $legal['dpia']['version'] ?? '1.0';
        $updatedAt = \DateTime::createFromFormat('Y-m-d', $legal['dpia']['updated_at'] ?? date('Y-m-d'));
        $date      = $updatedAt !== false ? $updatedAt->format(self::DATE_FORMAT) : date(self::DATE_FORMAT);
        $revision  = date(self::DATE_FORMAT, strtotime(self::NEXT_YEAR, $updatedAt !== false ? $updatedAt->getTimestamp() : time())); // phpcs:ignore Generic.Files.LineLength.TooLong

        $html = $this->pdfOpen(
            'DPIA — Analyse d\'impact relative à la protection des données — v' . htmlspecialchars($version),
            'Art. 35 RGPD — Traitements à risque élevé — ' . htmlspecialchars(self::CREATOR),
            $date
        );

        $html .= '<h2>1. Contexte et périmètre</h2>'
            . '<p>L\'Art. 35 RGPD impose une analyse d\'impact (DPIA) avant la mise en œuvre de tout traitement '
            . 'susceptible d\'engendrer un risque élevé pour les droits et libertés des personnes. '
            . 'Les lignes directrices CNIL/EDPB identifient 9 critères déclencheurs — 2 critères remplis suffisent.</p>'
            . '<p>Les traitements suivants ont été identifiés sur ' . htmlspecialchars(self::CREATOR) . ' comme nécessitant une DPIA. '
            . 'Ils sont planifiés (non encore déployés) et cette analyse est réalisée <strong>avant</strong> leur mise en œuvre, '
            . 'conformément à l\'obligation préalable de l\'Art. 35-1.</p>';

        // ---- Traitement 1 : Relance panier abandonné ----
        $html .= '<h2>2. DPIA n°1 — Relance panier abandonné (profilage comportemental)</h2>';
        $html .= '<h3>2.1 Description du traitement</h3>'
            . '<table>'
            . $this->row(self::ROW_FINALITE, 'Relancer automatiquement les clients ayant abandonné leur panier sans finaliser la commande, par envoi d\'un email personnalisé.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_BASE_LEGALE, 'Art. 6-1-a — consentement (opt-in lors de l\'inscription) + Art. 6-1-f — intérêt légitime')
            . $this->row(self::ROW_DONNEES, 'Email, prénom, contenu du panier (produits, quantités, montant), horodatage d\'abandon, historique de navigation (pages produits visitées)') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_PERSONNES, 'Clients authentifiés ayant ajouté des produits au panier sans finaliser la commande')
            . $this->row('Destinataires', 'GFA Bernard Solane et Fils (interne) — IONOS SE (SMTP sortant)')
            . $this->row(self::ROW_DUREE, '30 jours après l\'abandon (donnée de relance) — email envoyé 24h après l\'abandon')
            . $this->row(self::ROW_CRITERES_DPIA, 'Profilage comportemental (critère 2) + décision automatisée (critère 3)')
            . '</table>';

        $html .= '<h3>2.2 Nécessité et proportionnalité</h3>'
            . '<table>'
            . $this->row(self::ROW_NECESSITE, 'Le traitement est limité aux données strictement nécessaires (email + contenu panier). Aucune donnée de navigation hors panier n\'est collectée.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_PROPORTION, 'Un seul email de relance — pas de séquence automatisée multi-messages. Délai de 24h pour éviter le caractère intrusif.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_OPPOSITION, 'Lien de désinscription obligatoire dans chaque email de relance. Opposition possible depuis l\'espace client.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row('Minimisation', 'Seul le contenu du panier actif est utilisé — aucun historique de commandes précédentes')
            . '</table>';

        $html .= '<h3>2.3 Risques identifiés</h3>'
            . '<table>'
            . '<tr><th>Risque</th><th>Gravité</th><th>Probabilité</th><th>Niveau</th></tr>'
            . '<tr><td>Envoi non souhaité (spam perçu)</td><td>Faible</td><td>Modérée</td><td class="tag-warn">Modéré</td></tr>'
            . '<tr><td>Détournement d\'usage (profilage extensif)</td><td>Modérée</td><td>Faible</td><td class="tag-ok">Faible</td></tr>'
            . '<tr><td>Fuite email + contenu panier (violation)</td><td>Modérée</td><td>Faible</td><td class="tag-ok">Faible</td></tr>'
            . '</table>';

        $html .= '<h3>2.4 Mesures d\'atténuation</h3>'
            . '<table>'
            . $this->row('Consentement', 'Opt-in explicite à l\'inscription ou case dédiée "Recevoir un rappel si panier abandonné"')
            . $this->row('Limitation', '1 seul email — délai minimum 24h — suppression des données à J+30')
            . $this->row(self::ROW_OPPOSITION, 'Lien one-click dans chaque email + gestion depuis l\'espace client')
            . $this->row('Sécurité', 'Accès restreint aux logs de relance (admin uniquement) — chiffrement SMTP TLS')
            . $this->row('Audit trail', 'Journalisation de chaque envoi dans admin_logs')
            . '</table>';

        $html .= '<h3>2.5 Conclusion</h3>'
            . '<p><strong>Risque résiduel : Faible.</strong> Consultation CNIL préalable non requise (Art. 35-4). '
            . 'La mise en œuvre est conditionnée à l\'implémentation préalable du consentement opt-in et du lien d\'opposition.</p>';

        // ---- Traitement 2 : Profilage inactivité client ----
        $html .= '<h2>3. DPIA n°2 — Profilage inactivité client</h2>';
        $html .= '<h3>3.1 Description du traitement</h3>'
            . '<table>'
            . $this->row(self::ROW_FINALITE, 'Détecter les comptes inactifs depuis plus de 3 ans afin de procéder à la pseudonymisation ou suppression conforme au RGPD Art. 5-1-e (limitation de la durée de conservation).') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_BASE_LEGALE, 'Art. 6-1-c — obligation légale (RGPD Art. 5-1-e) + Art. 6-1-f — intérêt légitime (hygiène des données)') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_DONNEES, 'Email, date de dernière connexion, date de dernière commande, statut du compte')
            . $this->row(self::ROW_PERSONNES, 'Clients n\'ayant pas effectué de connexion ni de commande depuis plus de 3 ans')
            . $this->row('Destinataires', 'GFA Bernard Solane et Fils (interne) — traitement automatisé par cron')
            . $this->row(self::ROW_DUREE, 'Données pseudonymisées conservées pour archivage comptable légal — données personnelles supprimées') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_CRITERES_DPIA, 'Profilage (critère 2) + traitement à grande échelle potentiel (critère 5) + décision automatisée (critère 3)') // phpcs:ignore Generic.Files.LineLength.TooLong
            . '</table>';

        $html .= '<h3>3.2 Nécessité et proportionnalité</h3>'
            . '<table>'
            . $this->row(self::ROW_NECESSITE, 'Obligation RGPD de ne pas conserver les données au-delà de la durée nécessaire (Art. 5-1-e). Le traitement est obligatoire.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_PROPORTION, 'Envoi d\'un email d\'avertissement 30 jours avant toute suppression. Possibilité de réactivation du compte.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row('Minimisation', 'Seule la date de dernière activité est analysée — aucun profilage sur le comportement d\'achat')
            . $this->row('Transparence', 'Information dans la politique de confidentialité et dans l\'email d\'avertissement')
            . '</table>';

        $html .= '<h3>3.3 Risques identifiés</h3>'
            . '<table>'
            . '<tr><th>Risque</th><th>Gravité</th><th>Probabilité</th><th>Niveau</th></tr>'
            . '<tr><td>Suppression involontaire d\'un compte actif (bug cron)</td><td>Élevée</td><td>Faible</td><td class="tag-warn">Modéré</td></tr>'
            . '<tr><td>Non-réception de l\'email d\'avertissement</td><td>Modérée</td><td>Modérée</td><td class="tag-warn">Modéré</td></tr>'
            . '<tr><td>Perte de données nécessaires à un litige</td><td>Élevée</td><td>Faible</td><td class="tag-warn">Modéré</td></tr>'
            . '</table>';

        $html .= '<h3>3.4 Mesures d\'atténuation</h3>'
            . '<table>'
            . $this->row('Email d\'avertissement', 'Email envoyé 30 jours avant toute action, avec lien de réactivation valable 30 jours')
            . $this->row('Pseudonymisation', 'Pseudonymisation des données personnelles avant suppression complète (conservation des données comptables)') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row('Journal', 'Chaque action cron journalisée dans admin_logs avec horodatage et nombre de comptes traités')
            . $this->row('Sauvegarde', 'Sauvegarde BDD réalisée systématiquement avant exécution du cron de suppression')
            . $this->row('Test préproduction', 'Simulation obligatoire en préprod avant déploiement en production')
            . '</table>';

        $html .= '<h3>3.5 Conclusion</h3>'
            . '<p><strong>Risque résiduel : Modéré.</strong> Consultation CNIL préalable non requise si les mesures d\'atténuation sont intégralement déployées. ' // phpcs:ignore Generic.Files.LineLength.TooLong
            . 'La mise en œuvre nécessite l\'implémentation préalable du cron RGPD (us-rgpd-cron) et du script de pseudonymisation.</p>';

        // ---- Traitement 3 : Tracking newsletter ----
        $html .= '<h2>4. DPIA n°3 — Tracking newsletter (pixel ouverture + clics)</h2>';
        $html .= '<h3>4.1 Description du traitement</h3>'
            . '<table>'
            . $this->row(self::ROW_FINALITE, 'Mesurer le taux d\'ouverture et de clic des newsletters afin d\'optimiser les campagnes marketing.')
            . $this->row(self::ROW_BASE_LEGALE, 'Art. 6-1-a — consentement (opt-in newsletter avec mention explicite du tracking)')
            . $this->row(self::ROW_DONNEES, 'Email, horodatage d\'ouverture, URLs cliquées, identifiant de campagne, user-agent, IP (anonymisée)')
            . $this->row(self::ROW_PERSONNES, 'Abonnés à la newsletter ayant consenti au tracking')
            . $this->row('Destinataires', 'GFA Bernard Solane et Fils (interne uniquement) — aucun prestataire tiers de tracking')
            . $this->row(self::ROW_DUREE, '13 mois (aligné CNIL recommandation analytics) — suppression automatique')
            . $this->row(self::ROW_CRITERES_DPIA, 'Surveillance systématique (critère 6) + profilage comportemental (critère 2)')
            . '</table>';

        $html .= '<h3>4.2 Nécessité et proportionnalité</h3>'
            . '<table>'
            . $this->row(self::ROW_NECESSITE, 'Amélioration des campagnes pour limiter les envois non pertinents — réduction indirecte du volume de données traitées.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_PROPORTION, 'IP anonymisée (derniers octets masqués) — pixel unique par campagne — pas de tracking cross-site.')
            . $this->row('Information', 'Mention explicite du tracking dans le formulaire d\'inscription à la newsletter et dans la politique de confidentialité.') // phpcs:ignore Generic.Files.LineLength.TooLong
            . $this->row(self::ROW_OPPOSITION, 'Désinscription newsletter = fin du tracking. Pas de tracking sans inscription active.')
            . '</table>';

        $html .= '<h3>4.3 Risques identifiés</h3>'
            . '<table>'
            . '<tr><th>Risque</th><th>Gravité</th><th>Probabilité</th><th>Niveau</th></tr>'
            . '<tr><td>Profilage comportemental non consenti</td><td>Modérée</td><td>Faible</td><td class="tag-ok">Faible</td></tr>'
            . '<tr><td>Fuite des données de tracking (violation)</td><td>Faible</td><td>Faible</td><td class="tag-ok">Faible</td></tr>'
            . '<tr><td>Usage secondaire des données de clic</td><td>Modérée</td><td>Faible</td><td class="tag-ok">Faible</td></tr>'
            . '</table>';

        $html .= '<h3>4.4 Mesures d\'atténuation</h3>'
            . '<table>'
            . $this->row('Consentement granulaire', 'Case dédiée "Accepter le suivi d\'ouverture et de clic" séparée du consentement newsletter')
            . $this->row('Anonymisation IP', 'Masquage du dernier octet IPv4 et des 80 derniers bits IPv6 avant stockage')
            . $this->row('Hébergement interne', 'Pixel hébergé sur le domaine crabitan.fr — aucun appel vers un tiers tracking')
            . $this->row('Purge automatique', 'Suppression automatique des données de tracking à J+395 (13 mois + marge)')
            . $this->row('Accès restreint', 'Statistiques visibles uniquement par les admins — non exportables vers des tiers')
            . '</table>';

        $html .= '<h3>4.5 Conclusion</h3>'
            . '<p><strong>Risque résiduel : Faible.</strong> Consultation CNIL préalable non requise. '
            . 'La mise en œuvre nécessite la mise à jour de la politique de confidentialité et du formulaire d\'inscription newsletter.</p>';

        // ---- Bilan ----
        $html .= '<h2>5. Bilan et décision</h2>'
            . '<table>'
            . '<tr><th>Traitement</th><th>Risque résiduel</th><th>Consultation CNIL</th><th>Statut</th></tr>'
            . '<tr><td>Relance panier abandonné</td><td>Faible</td><td>Non requise</td><td>Planifié — DPIA préalable réalisée</td></tr>'
            . '<tr><td>Profilage inactivité client</td><td>Modéré</td><td>Non requise (mesures déployées)</td><td>Planifié — DPIA préalable réalisée</td></tr>' // phpcs:ignore Generic.Files.LineLength.TooLong
            . '<tr><td>Tracking newsletter</td><td>Faible</td><td>Non requise</td><td>Planifié — DPIA préalable réalisée</td></tr>'
            . '</table>'
            . '<p>Aucun des traitements analysés ne présente un risque résiduel élevé après application des mesures d\'atténuation. '
            . 'La consultation préalable de la CNIL (Art. 35-4) n\'est pas requise.</p>';

        $html .= '<h2>6. Révision</h2>'
            . '<p>Cette DPIA doit être réexaminée à chaque modification substantielle des traitements concernés, '
            . 'ou au minimum tous les 3 ans (recommandation EDPB). '
            . 'Prochaine révision prévue avant le&nbsp;: ' . htmlspecialchars($revision) . '.</p>'
            . '<p>Responsable : Expert RGPD + DPO (' . htmlspecialchars($this->dpoEmail()) . ').</p>';

        $html .= $this->pdfFooter(
            'Document confidentiel — v' . htmlspecialchars($version) . ' — Art. 35 RGPD — Prochaine révision : ' . $revision,
            $date
        );

        return $html;
    }
}
