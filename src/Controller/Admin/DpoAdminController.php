<?php

declare(strict_types=1);

namespace Controller\Admin;

/**
 * Contrôleur DPO — génération des documents RGPD (Art. 28, 30, 33) au format PDF.
 * Accès réservé aux rôles admin et super_admin.
 */
class DpoAdminController extends AdminController
{
    private const CREATOR   = 'Château Crabitan Bellevue';
    private const AUTHOR    = 'GFA Bernard Solane et Fils';
    private const SIRET     = '398 341 701 00017';
    private const ADDRESS   = 'Château Crabitan Bellevue — 33410 Sainte-Croix-du-Mont — France';
    private const DPO_EMAIL = 'crabitan.bellevue@orange.fr';

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
    // Helpers privés — génération PDF
    // ----------------------------------------------------------------

    /**
     * Construit le PDF via TCPDF et retourne les octets en mémoire.
     *
     * @throws \RuntimeException Si TCPDF n'est pas disponible
     */
    private function buildPdf(string $title, string $html): string
    {
        $tcpdfPath = ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!file_exists($tcpdfPath)) {
            throw new \RuntimeException('TCPDF non disponible : ' . $tcpdfPath);
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
            .tag-ok   { color: #2e7d32; font-weight: bold; }
            .tag-warn { color: #e65100; font-weight: bold; }
        </style>';
    }

    /** En-tête commune à tous les PDFs. */
    private function pdfHeader(string $title, string $subtitle, string $date): string
    {
        return '<h1>' . htmlspecialchars(self::CREATOR) . '</h1>'
            . '<p class="meta">'
            . htmlspecialchars(self::AUTHOR) . ' — SIRET&nbsp;: ' . htmlspecialchars(self::SIRET) . '<br>'
            . htmlspecialchars(self::ADDRESS) . '<br>'
            . 'DPO&nbsp;: ' . htmlspecialchars(self::DPO_EMAIL)
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
        $date = date('d/m/Y');

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
                'securite'   => 'Chiffrement AES-256-GCM colonnes sensibles (RGPD Art. 32) — sauvegardes chiffrees au repos — preparation chiffrement post-quantique (QPC/NIST PQC) — acces restreint DevSecOps',
            ],
            [
                'title'      => '8. Traductions DeepL (API)',
                'finalite'   => 'Traduction de l\'interface bilingue (FR ↔ EN)',
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

        $html = $this->pdfStyles()
            . $this->pdfHeader(
                'Registre des activités de traitement',
                'Conformément à l\'Art. 30 RGPD — Règlement UE 2016/679',
                $date
            );

        foreach ($treatments as $t) {
            $html .= '<h3>' . htmlspecialchars($t['title']) . '</h3>'
                . '<table>'
                . $this->row('Finalité', htmlspecialchars($t['finalite']))
                . $this->row('Base légale', htmlspecialchars($t['base']))
                . $this->row('Catégories de données', htmlspecialchars($t['donnees']))
                . $this->row('Catégories de personnes', htmlspecialchars($t['personnes']))
                . $this->row('Destinataires', htmlspecialchars($t['destinataires']))
                . $this->row('Durée de conservation', htmlspecialchars($t['duree']))
                . $this->row('Transfert hors UE', htmlspecialchars($t['transfert']))
                . $this->row('Mesures de sécurité', htmlspecialchars($t['securite']))
                . '</table>';
        }

        $html .= '<p class="footer">Document confidentiel — à la disposition de la CNIL sur demande (Art. 30-4 RGPD) — Généré le ' . $date . '</p>';

        return $html;
    }

    /**
     * Retourne le contenu HTML du document DPA sous-traitants (Art. 28 RGPD).
     */
    private function htmlSousTraitants(): string
    {
        $date = date('d/m/Y');

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
                'role'     => 'API de traduction (FR ↔ EN) — contenu éditorial uniquement, aucune donnée personnelle',
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

        $html = $this->pdfStyles()
            . $this->pdfHeader(
                'Sous-traitants — DPA',
                'Conformément à l\'Art. 28 RGPD — Règlement UE 2016/679',
                $date
            );

        $html .= '<p class="meta">Prochaine révision annuelle : ' . date('d/m/Y', strtotime('+1 year')) . '</p>';

        foreach ($processors as $p) {
            $html .= '<h3>' . htmlspecialchars($p['name']) . ' — <span class="tag-ok">✓ Conforme</span></h3>'
                . '<table>'
                . $this->row('Rôle', htmlspecialchars($p['role']))
                . $this->row('Localisation', htmlspecialchars($p['location']))
                . $this->row('Transfert hors UE', htmlspecialchars($p['transfer']))
                . $this->row('Base contractuelle RGPD', htmlspecialchars($p['dpa']))
                . $this->row('Note', htmlspecialchars($p['note']))
                . '</table>';
        }

        $html .= '<p class="footer">Document confidentiel — Art. 28 RGPD — Généré le ' . $date . '</p>';

        return $html;
    }

    /**
     * Retourne le contenu HTML de la procédure de notification de violation (Art. 33 RGPD).
     */
    private function htmlProcedure(): string
    {
        $date     = date('d/m/Y');
        $revision = date('d/m/Y', strtotime('+1 year'));

        $html = $this->pdfStyles()
            . $this->pdfHeader(
                'Procédure de notification de violation de données',
                'Conformément aux Art. 33 & 34 RGPD — délai légal 72h',
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
            . '<tr><td>Alerter le référent RGPD (email + téléphone)</td><td>DevSecOps → DPO</td><td>&lt; 2h</td></tr>'
            . '<tr><td>Évaluer le risque (arbre de décision ci-dessus)</td><td>DPO + DevSecOps</td><td>&lt; 4h</td></tr>'
            . '<tr><td>Alerter la direction si risque probable ou élevé</td><td>DPO → Direction</td><td>&lt; 4h</td></tr>'
            . '<tr><td>Notifier la CNIL si obligatoire (notifications.cnil.fr)</td><td>DPO</td><td><strong>≤ 72h</strong></td></tr>'
            . '<tr><td>Communiquer aux personnes concernées si risque élevé</td><td>DPO + Direction</td><td>Sans délai injustifié</td></tr>'
            . '<tr><td>Documenter dans le registre interne des violations</td><td>DPO</td><td>Dans les 72h</td></tr>'
            . '<tr><td>Rapport post-incident</td><td>DevSecOps + DPO</td><td>Dans les 7 jours</td></tr>'
            . '</table>';

        $html .= '<h2>4. Contacts d\'urgence</h2>'
            . '<table>'
            . $this->row('Référent RGPD / DPO', htmlspecialchars(self::DPO_EMAIL))
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
            . '<p>Exercice annuel avant le 30 juin. Scénario type : "détection d\'une requête SQL suspecte — données clients potentiellement extraites." Déroulé : qualification (30 min) → test chaîne escalade → rédaction template CNIL (sans envoi) → debriefing. Responsable : DevSecOps + DPO.</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        $html .= '<p class="footer">Document confidentiel — Art. 33 &amp; 34 RGPD — Prochaine révision : ' . $revision . ' — Généré le ' . $date . '</p>'; // phpcs:ignore Generic.Files.LineLength.TooLong

        return $html;
    }
}
