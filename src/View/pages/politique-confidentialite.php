<?php
$pageTitle = __('footer.privacy_policy');
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$isBare    = $bare ?? false;

require SRC_PATH . '/View/partials/legal-open.php'; // NOSONAR — php:S2003 : require_once bloquerait le re-rendu entre tests (même convention que Response::view)
?>

        <h2>I. Responsable du traitement</h2>
        <p>Le responsable du traitement de vos données personnelles est le GFA Bernard Solane et Fils, dont le siège social est sis Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont &ndash; FRANCE (SIRET&nbsp;: 398&nbsp;341&nbsp;701&nbsp;00017).</p>
        <p>Contact&nbsp;: <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a> &mdash; Téléphone&nbsp;: <a href="tel:+33556620153">+33&nbsp;(0)5&nbsp;56&nbsp;62&nbsp;01&nbsp;53</a></p>

        <h2>II. Données collectées et finalités</h2>

        <h3>1. Commandes et paiements</h3>
        <p>Dans le cadre d&#8217;une commande, nous collectons votre nom, prénom, adresse de livraison et de facturation, adresse e-mail et numéro de téléphone. Ces données sont nécessaires à l&#8217;exécution du contrat (article 6-1-b du RGPD). Les paiements par carte bancaire sont traités par un prestataire certifié PCI-DSS&nbsp;; nous ne stockons à aucun moment vos coordonnées bancaires complètes.</p>

        <h3>2. Création de compte</h3>
        <p>Si vous créez un compte client, nous conservons votre adresse e-mail et votre mot de passe (haché). Ces données nous permettent de gérer votre espace personnel et l&#8217;historique de vos commandes (base légale&nbsp;: exécution du contrat).</p>

        <h3>3. Formulaire de contact</h3>
        <p>Lorsque vous utilisez notre formulaire de contact, nous collectons votre nom, votre adresse e-mail et le contenu de votre message, dans le seul but de traiter votre demande (base légale&nbsp;: intérêt légitime).</p>

        <h3>4. Newsletter</h3>
        <p>Si vous vous inscrivez à notre newsletter, nous conservons votre adresse e-mail sur la base de votre consentement (article 6-1-a du RGPD). Vous pouvez vous désinscrire à tout moment depuis votre espace «&nbsp;Mon Compte&nbsp;» ou via le lien de désinscription présent dans chaque e-mail. La newsletter utilise un mécanisme de double opt-in&nbsp;: votre inscription n&#8217;est confirmée qu&#8217;après validation de votre e-mail.</p>

        <h3>5. Cookies de mesure d&#8217;audience</h3>
        <p>Avec votre consentement, nous utilisons Google Analytics 4 pour mesurer l&#8217;audience de notre site (pages consultées, durée de visite, type d&#8217;appareil). Votre adresse IP est anonymisée avant tout stockage. Aucun script de mesure n&#8217;est chargé sans votre consentement préalable (Art. 82 Loi Informatique et Libertés). Vous pouvez retirer votre consentement à tout moment depuis le lien «&nbsp;Gérer les cookies&nbsp;» en bas de chaque page.</p>

        <h3>6. Cookies techniques</h3>
        <p>Nous utilisons des cookies strictement nécessaires au fonctionnement du site&nbsp;: cookie de session (authentification), cookie de préférence de langue et cookie de vérification de l&#8217;âge. Ces cookies ne nécessitent pas de consentement car ils sont indispensables au service demandé.</p>

        <h2>III. Principe de minimisation</h2>
        <p>La protection de vos données personnelles est notre priorité. Nous appliquons le principe de minimisation posé par l&#8217;article 5-1-c du RGPD&nbsp;: nous ne collectons que les données strictement nécessaires à la finalité pour laquelle elles sont demandées. Aucune donnée superflue n&#8217;est conservée. Vos données ne sont ni vendues, ni cédées, ni partagées avec des tiers à des fins commerciales.</p>

        <h2>IV. Durées de conservation</h2>
        <ul>
            <li><strong>Données de commande et de facturation</strong>&nbsp;: 10&nbsp;ans à compter de la date de la commande (obligation légale comptable &mdash; Art.&nbsp;L123-22 Code de commerce).</li>
            <li><strong>Données de compte client</strong>&nbsp;: durée de la relation commerciale, puis 3&nbsp;ans en archivage intermédiaire après inactivité.</li>
            <li><strong>Données de contact</strong>&nbsp;: 3&nbsp;ans à compter du dernier contact.</li>
            <li><strong>Newsletter</strong>&nbsp;: jusqu&#8217;à la désinscription.</li>
            <li><strong>Logs de connexion et logs serveur</strong>&nbsp;: 1&nbsp;an (recommandation CNIL &mdash; Art. L34-1 CPCE).</li>
            <li><strong>Cookie de consentement</strong>&nbsp;: 13&nbsp;mois maximum (recommandation CNIL 2020).</li>
            <li><strong>Données Google Analytics</strong>&nbsp;: 26&nbsp;mois (paramétrage GA4 par défaut).</li>
        </ul>

        <h2>V. Sous-traitants et destinataires des données</h2>
        <p>Vos données sont traitées par les seuls membres du GFA Bernard Solane et Fils habilités à cet effet. Elles peuvent également être traitées par les prestataires suivants, agissant en qualité de sous-traitants conformément à l&#8217;Art. 28 du RGPD&nbsp;:</p>
        <table>
            <thead>
                <tr>
                    <th scope="col">Sous-traitant</th>
                    <th scope="col">Rôle</th>
                    <th scope="col">Localisation</th>
                    <th scope="col">Garanties RGPD</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>IONOS SE</strong></td>
                    <td>Hébergement serveur web, base de données, envoi d&#8217;e-mails transactionnels</td>
                    <td>Allemagne (UE)</td>
                    <td>DPA inclus dans les CGU IONOS</td>
                </tr>
                <tr>
                    <td><strong>Crédit Agricole</strong></td>
                    <td>Traitement des paiements par carte bancaire (certifié PCI-DSS)</td>
                    <td>France (UE)</td>
                    <td>Contrat commercial + DPA PSP</td>
                </tr>
                <tr>
                    <td><strong>Google LLC</strong></td>
                    <td>Authentification OAuth (connexion Google) &mdash; Mesure d&#8217;audience (GA4, avec consentement)</td>
                    <td>États-Unis</td>
                    <td>Clauses Contractuelles Types (SCCs) + DPA Google Cloud &mdash; EU-US Data Privacy Framework</td>
                </tr>
                <tr>
                    <td><strong>DeepL SE</strong></td>
                    <td>Traduction de l&#8217;interface (contenu éditorial, sans données personnelles)</td>
                    <td>Allemagne (UE)</td>
                    <td>CGU DeepL &mdash; aucune donnée personnelle transmise</td>
                </tr>
                <tr>
                    <td><strong>GitHub Inc. (Microsoft)</strong></td>
                    <td>Hébergement du code source et intégration continue (CI/CD)</td>
                    <td>États-Unis</td>
                    <td>SCCs Microsoft + DPA GitHub Customer Agreement</td>
                </tr>
            </tbody>
        </table>
        <p>Ces prestataires sont contractuellement tenus de respecter la confidentialité de vos données et de ne les utiliser qu&#8217;aux fins convenues.</p>

        <h2>VI. Transferts de données hors de l&#8217;Union européenne</h2>
        <p>Certains de nos sous-traitants (Google LLC, Apple Inc., GitHub Inc.) sont établis aux États-Unis. Ces transferts sont encadrés par des <strong>Clauses Contractuelles Types (SCCs)</strong> adoptées par la Commission européenne, dans le respect du cadre EU-US Data Privacy Framework. Vous pouvez demander communication des garanties mises en place en contactant&nbsp;: <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>.</p>

        <h2>VII. Vos droits</h2>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD &ndash; Règlement UE 2016/679) et à la loi Informatique et Libertés n°78-17 du 6&nbsp;janvier 1978 modifiée, vous disposez des droits suivants&nbsp;:</p>
        <ul>
            <li><strong>Droit d&#8217;accès</strong> (art. 15) &mdash; obtenir une copie de vos données.</li>
            <li><strong>Droit de rectification</strong> (art. 16) &mdash; corriger des données inexactes.</li>
            <li><strong>Droit à l&#8217;effacement</strong> (art. 17) &mdash; demander la suppression de vos données.</li>
            <li><strong>Droit à la portabilité</strong> (art. 20) &mdash; recevoir vos données dans un format structuré.</li>
            <li><strong>Droit d&#8217;opposition</strong> (art. 21) &mdash; vous opposer à un traitement fondé sur l&#8217;intérêt légitime.</li>
            <li><strong>Droit de retrait du consentement</strong> (art. 7) &mdash; à tout moment pour les traitements fondés sur le consentement (ex.&nbsp;: cookies analytics, newsletter).</li>
        </ul>
        <p>Pour exercer ces droits, adressez-vous à&nbsp;: GFA Bernard Solane et Fils, Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont ou par e-mail à <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>. Nous répondons dans un délai d&#8217;un mois (Art. 12 RGPD).</p>
        <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de la <strong>CNIL</strong> (Commission Nationale de l&#8217;Informatique et des Libertés) &mdash; <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a>.</p>

        <h2>VIII. Sécurité des données</h2>
        <p>Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte ou destruction accidentelle&nbsp;: connexion HTTPS/TLS, hachage des mots de passe (bcrypt), authentification multi-facteurs (MFA), traitement des paiements par un prestataire certifié PCI-DSS, hébergement sur serveur dédié IONOS en Allemagne, chiffrement AES-256-GCM des colonnes sensibles de la base de données (Art. 32 RGPD). Notre infrastructure est dimensionnée en vue de la migration vers des algorithmes résistants à l&#8217;informatique quantique (NIST PQC) à mesure que les standards seront disponibles. En cas de violation de données à caractère personnel, nous nous engageons à notifier la CNIL dans les 72 heures suivant la prise de connaissance de l&#8217;incident, conformément à l&#8217;Art. 33 du RGPD.</p>

        <h2>IX. Modifications de la présente politique</h2>
        <p>Nous nous réservons le droit de modifier cette politique de confidentialité afin de l&#8217;adapter aux évolutions légales ou aux changements de nos pratiques. En cas de modification substantielle, nous en informerons les utilisateurs disposant d&#8217;un compte. La version en vigueur est toujours accessible depuis cette page et depuis le pied de page du site.</p>
        <p>Pour toute question relative à la protection de vos données ou aux présentes mentions légales, consultez également nos <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales"><?= htmlspecialchars(__('footer.legal_notice')) ?></a>.</p>

<?php require SRC_PATH . '/View/partials/legal-close.php'; // NOSONAR — php:S2003 : idem ?>
