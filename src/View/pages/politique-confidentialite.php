<?php
$pageTitle       = __('footer.privacy_policy');
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$metaDescription = $navLang === 'en'
    ? 'Privacy policy of Château Crabitan Bellevue — how we protect and process your personal data.'
    : 'Politique de confidentialité du Château Crabitan Bellevue — protection et traitement de vos données personnelles.';
$isBare       = $bare ?? false;

$legal        = require ROOT_PATH . '/config/legal.php'; // NOSONAR — php:S2003 : require_once retourne true au 2e appel (re-rendu tests)
$legalVersion = $legal['politique_confidentialite']['version'];
$legalDate    = $legal['politique_confidentialite']['updated_at'];
$contactEmail = $_ENV['DPO_EMAIL'];
$safeEmail    = htmlspecialchars($contactEmail, ENT_QUOTES);

require SRC_PATH . '/View/partials/legal-open.php'; // NOSONAR — php:S2003 : require_once bloquerait le re-rendu entre tests (même convention que Response::view)
?>

        <p class="legal-version"><?= htmlspecialchars(__('legal.version')) ?> <?= htmlspecialchars($legalVersion) ?> &mdash; <?= htmlspecialchars(__('legal.updated_at')) ?> <?= htmlspecialchars($legalDate) ?></p>

<?php if ($navLang === 'en') : ?>

        <h2>I. Data Controller</h2>
        <p>The data controller for your personal data is GFA Bernard Solane et Fils, registered at Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont &ndash; FRANCE (SIRET&nbsp;: 398&nbsp;341&nbsp;701&nbsp;00017).</p>
        <p>Contact&nbsp;: <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a> &mdash; Phone&nbsp;: <a href="tel:+33556620153">+33&nbsp;(0)5&nbsp;56&nbsp;62&nbsp;01&nbsp;53</a></p>

        <h2>II. Data Collected and Purposes</h2>

        <h3>1. Orders and payments</h3>
        <p>When placing an order, we collect your first and last name, delivery and billing address, email address and phone number. This data is necessary for the performance of the contract (GDPR Article 6(1)(b)). Card payments are processed by a PCI-DSS certified provider; we never store your full payment card details.</p>

        <h3>2. Account creation</h3>
        <p>If you create a customer account, we store your email address and password (hashed). This data allows us to manage your personal space and order history (legal basis: performance of contract).</p>

        <h3>3. Contact form</h3>
        <p>When you use our contact form, we collect your name, email address and message content solely to handle your enquiry (legal basis: legitimate interest).</p>

        <h3>4. Newsletter</h3>
        <p>If you subscribe to our newsletter, we retain your email address based on your consent (GDPR Article 6(1)(a)). You may unsubscribe at any time from your "My Account" space or via the unsubscribe link in every email. The newsletter uses a double opt-in mechanism: your subscription is only confirmed after email validation.</p>

        <h3>5. Email tracking</h3>
        <p>Our emails (newsletters and cart-abandonment reminders) include a tracking pixel (1&times;1 invisible image) and tracking links that allow us to measure open and click rates. This data is processed on the basis of our <strong>legitimate interest</strong> (GDPR Article&nbsp;6(1)(f)) in improving the relevance of our communications. No tracking data is shared with third parties. You may object to this processing by unsubscribing from the newsletter or by contacting our DPO at <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a>.</p>

        <h3>6. Abandoned cart reminders</h3>
        <p>If you added products to your cart without completing your order, we may send you a reminder email within 72 hours of abandonment. This processing is based on our <strong>legitimate interest</strong> (GDPR Article&nbsp;6(1)(f)) in maintaining a commercial relationship with identified customers. Only customers with a verified account are concerned. You may object to this processing at any time by contacting our DPO at <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a> or by deleting your account.</p>

        <h3>7. Analytics cookies</h3>
        <p>With your consent, we use Google Analytics 4 to measure website traffic (pages visited, visit duration, device type). Your IP address is anonymised before any storage. No analytics script is loaded without your prior consent (GDPR Article 6(1)(a)). You may withdraw your consent at any time via the "Manage cookies" link at the bottom of every page.</p>

        <h3>8. Technical cookies</h3>
        <p>We use cookies strictly necessary for the operation of the website: session cookie (authentication), language preference cookie and age verification cookie. These cookies do not require consent as they are essential to the requested service.</p>

        <h3>9. Full list of cookies set</h3>
        <p>In accordance with GDPR Article 13 and CNIL deliberation n°2020-091 of 17&nbsp;September 2020, here is the complete list of cookies we set:</p>
        <table>
            <thead>
                <tr>
                    <th scope="col">Cookie name</th>
                    <th scope="col">Purpose</th>
                    <th scope="col">Retention period</th>
                    <th scope="col">Consent required</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>auth_token</code></td>
                    <td>Authentication — contains the session JWT of the logged-in user (HttpOnly, Secure)</td>
                    <td>2&nbsp;hours (JWT duration)</td>
                    <td>No — strictly necessary</td>
                </tr>
                <tr>
                    <td><code>device_token</code></td>
                    <td>Remembers trusted device for MFA validation (HttpOnly, Secure)</td>
                    <td>90&nbsp;days</td>
                    <td>No — strictly necessary for account security</td>
                </tr>
                <tr>
                    <td><code>lang</code></td>
                    <td>Stores language preference (FR/EN)</td>
                    <td>Session (cleared when browser is closed)</td>
                    <td>No — functional preference</td>
                </tr>
                <tr>
                    <td><code>age_verified</code></td>
                    <td>Records the age declaration (legal alcohol age gate)</td>
                    <td>24h (13 months if "Remember me" is checked)</td>
                    <td>No — legal obligation (Art. L3342-1 French Public Health Code)</td>
                </tr>
                <tr>
                    <td><code>cookie_consent</code></td>
                    <td>Stores analytics cookie consent choice</td>
                    <td>13&nbsp;months (CNIL 2020 recommendation)</td>
                    <td>No — necessary to respect your choice</td>
                </tr>
                <tr>
                    <td><code>_ga</code></td>
                    <td>Google Analytics 4 — anonymised session identifier for audience measurement</td>
                    <td>2&nbsp;years</td>
                    <td><strong>Yes</strong> — only set after explicit consent</td>
                </tr>
                <tr>
                    <td><code>_ga_*</code></td>
                    <td>Google Analytics 4 — session persistence for page-view tracking</td>
                    <td>2&nbsp;years</td>
                    <td><strong>Yes</strong> — only set after explicit consent</td>
                </tr>
            </tbody>
        </table>

        <h2>III. Data Minimisation</h2>
        <p>Protecting your personal data is our priority. We apply the data minimisation principle set out in GDPR Article 5(1)(c): we only collect data strictly necessary for the purpose for which it is requested. No superfluous data is retained. Your data is never sold, transferred or shared with third parties for commercial purposes.</p>

        <h2>IV. Retention Periods</h2>
        <ul>
            <li><strong>Order and billing data</strong>: 10&nbsp;years from the order date (legal accounting obligation — French Commercial Code Art.&nbsp;L123-22).</li>
            <li><strong>Customer account data</strong>: duration of the commercial relationship, then 3&nbsp;years in intermediate archiving after inactivity.</li>
            <li><strong>Contact data</strong>: 3&nbsp;years from the last contact.</li>
            <li><strong>Newsletter</strong>: until unsubscription.</li>
            <li><strong>Newsletter tracking data</strong>: 13&nbsp;months from the date of opening or click (legitimate interest, CNIL recommendation).</li>
            <li><strong>Connection logs and server logs</strong>: 1&nbsp;year (CNIL recommendation — Art. L34-1 CPCE).</li>
            <li><strong>Consent cookie</strong>: 13&nbsp;months maximum (CNIL 2020 recommendation).</li>
            <li><strong>Google Analytics data</strong>: 26&nbsp;months (default GA4 setting).</li>
        </ul>

        <h2>V. Processors and Data Recipients</h2>
        <p>Your data is processed solely by authorised members of GFA Bernard Solane et Fils. It may also be processed by the following providers acting as processors in accordance with GDPR Article 28:</p>
        <table>
            <thead>
                <tr>
                    <th scope="col">Processor</th>
                    <th scope="col">Role</th>
                    <th scope="col">Location</th>
                    <th scope="col">GDPR safeguards</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>IONOS SE</strong></td>
                    <td>Web server hosting, database, transactional email delivery</td>
                    <td>Germany (EU)</td>
                    <td>DPA included in IONOS Terms of Service</td>
                </tr>
                <tr>
                    <td><strong>Crédit Agricole</strong></td>
                    <td>Card payment processing (PCI-DSS certified)</td>
                    <td>France (EU)</td>
                    <td>Commercial contract + PSP DPA</td>
                </tr>
                <tr>
                    <td><strong>Google LLC</strong></td>
                    <td>OAuth authentication (Google sign-in) — Audience measurement (GA4, with consent)</td>
                    <td>United States</td>
                    <td>Standard Contractual Clauses (SCCs) + Google Cloud DPA — EU-US Data Privacy Framework</td>
                </tr>
                <tr>
                    <td><strong>DeepL SE</strong></td>
                    <td>Interface translation (editorial content only, no personal data)</td>
                    <td>Germany (EU)</td>
                    <td>DeepL Terms of Service — no personal data transmitted</td>
                </tr>
                <tr>
                    <td><strong>GitHub Inc. (Microsoft)</strong></td>
                    <td>Source code hosting and continuous integration (CI/CD)</td>
                    <td>United States</td>
                    <td>Microsoft SCCs + GitHub Customer Agreement DPA</td>
                </tr>
            </tbody>
        </table>
        <p>These providers are contractually bound to respect the confidentiality of your data and to use it only for the agreed purposes.</p>

        <h2>VI. International Data Transfers</h2>
        <p>Some of our processors (Google LLC, GitHub Inc.) are established in the United States. These transfers are governed by <strong>Standard Contractual Clauses (SCCs)</strong> adopted by the European Commission, in compliance with the EU-US Data Privacy Framework. You may request information on the safeguards in place by contacting: <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>

        <h2>VII. Your Rights</h2>
        <p>Under the General Data Protection Regulation (GDPR — EU Regulation 2016/679), you have the following rights:</p>
        <ul>
            <li><strong>Right of access</strong> (Art. 15) — obtain a copy of your data.</li>
            <li><strong>Right to rectification</strong> (Art. 16) — correct inaccurate data.</li>
            <li><strong>Right to erasure</strong> (Art. 17) — request deletion of your data.</li>
            <li><strong>Right to data portability</strong> (Art. 20) — receive your data in a structured format.</li>
            <li><strong>Right to object</strong> (Art. 21) — object to processing based on legitimate interest.</li>
            <li><strong>Right to withdraw consent</strong> (Art. 7) — at any time for consent-based processing (e.g. analytics cookies, newsletter).</li>
        </ul>
        <p>To exercise these rights, contact: GFA Bernard Solane et Fils, Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont, France or by email at <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>. We respond within one month (GDPR Art. 12).</p>
        <p>If you believe your rights are not being respected, you may lodge a complaint with the <strong>CNIL</strong> (French Data Protection Authority) — <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a> — or with the supervisory authority of your country of residence.</p>

        <h2>VIII. Data Security</h2>
        <p>We implement appropriate technical and organisational measures to protect your data against unauthorised access, loss or accidental destruction: HTTPS/TLS connection, bcrypt password hashing, multi-factor authentication (MFA), PCI-DSS certified payment processing, hosting on a dedicated IONOS server in Germany, AES-256-GCM encryption of sensitive database columns (GDPR Art. 32). Our infrastructure is designed with a view to migrating to quantum-resistant algorithms (NIST PQC) as standards become available. In the event of a personal data breach, we undertake to notify the CNIL within 72 hours of becoming aware of the incident, in accordance with GDPR Art. 33.</p>

        <h2>IX. Changes to This Policy</h2>
        <p>We reserve the right to update this privacy policy to reflect legal changes or updates to our practices. In the event of a material change, we will inform users with an account. The current version is always accessible from this page and from the website footer.</p>
        <p>For any question relating to the protection of your data, please also consult our <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales"><?= htmlspecialchars(__('footer.legal_notice')) ?></a>.</p>

<?php else : ?>

        <h2>I. Responsable du traitement</h2>
        <p>Le responsable du traitement de vos données personnelles est le GFA Bernard Solane et Fils, dont le siège social est sis Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont &ndash; FRANCE (SIRET&nbsp;: 398&nbsp;341&nbsp;701&nbsp;00017).</p>
        <p>Contact&nbsp;: <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a> &mdash; Téléphone&nbsp;: <a href="tel:+33556620153">+33&nbsp;(0)5&nbsp;56&nbsp;62&nbsp;01&nbsp;53</a></p>

        <h2>II. Données collectées et finalités</h2>

        <h3>1. Commandes et paiements</h3>
        <p>Dans le cadre d&#8217;une commande, nous collectons votre nom, prénom, adresse de livraison et de facturation, adresse e-mail et numéro de téléphone. Ces données sont nécessaires à l&#8217;exécution du contrat (article 6-1-b du RGPD). Les paiements par carte bancaire sont traités par un prestataire certifié PCI-DSS&nbsp;; nous ne stockons à aucun moment vos coordonnées bancaires complètes.</p>

        <h3>2. Création de compte</h3>
        <p>Si vous créez un compte client, nous conservons votre adresse e-mail et votre mot de passe (haché). Ces données nous permettent de gérer votre espace personnel et l&#8217;historique de vos commandes (base légale&nbsp;: exécution du contrat).</p>

        <h3>3. Formulaire de contact</h3>
        <p>Lorsque vous utilisez notre formulaire de contact, nous collectons votre nom, votre adresse e-mail et le contenu de votre message, dans le seul but de traiter votre demande (base légale&nbsp;: intérêt légitime).</p>

        <h3>4. Newsletter</h3>
        <p>Si vous vous inscrivez à notre newsletter, nous conservons votre adresse e-mail sur la base de votre consentement (article 6-1-a du RGPD). Vous pouvez vous désinscrire à tout moment depuis votre espace «&nbsp;Mon Compte&nbsp;» ou via le lien de désinscription présent dans chaque e-mail. La newsletter utilise un mécanisme de double opt-in&nbsp;: votre inscription n&#8217;est confirmée qu&#8217;après validation de votre e-mail.</p>

        <h3>5. Suivi des e-mails (tracking)</h3>
        <p>Nos e-mails (newsletters et e-mails de relance panier) intègrent un pixel de mesure (image 1&times;1 invisible) et des liens de suivi qui nous permettent de mesurer le taux d&#8217;ouverture et les clics. Ces données sont traitées sur la base de notre <strong>intérêt légitime</strong> (article&nbsp;6-1-f du RGPD) à améliorer la pertinence de nos communications. Aucune donnée de tracking n&#8217;est partagée avec des tiers. Vous pouvez vous opposer à ce traitement en vous désabonnant de la newsletter ou en contactant notre DPO à <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a>.</p>

        <h3>6. Relance panier abandonné</h3>
        <p>Si vous avez ajouté des produits à votre panier sans finaliser votre commande, nous sommes susceptibles de vous envoyer un e-mail de rappel dans les 72 heures suivant l&#8217;abandon, dans le but de faciliter votre achat. Ce traitement est fondé sur notre <strong>intérêt légitime</strong> (article&nbsp;6-1-f du RGPD) à maintenir la relation commerciale avec nos clients identifiés. Seuls les clients disposant d&#8217;un compte vérifié sont concernés. Vous pouvez vous opposer à ce traitement à tout moment en contactant notre DPO à <a href="mailto:<?= $safeEmail ?>"><?= $safeEmail ?></a> ou en supprimant votre compte.</p>

        <h3>7. Cookies de mesure d&#8217;audience</h3>
        <p>Avec votre consentement, nous utilisons Google Analytics 4 pour mesurer l&#8217;audience de notre site (pages consultées, durée de visite, type d&#8217;appareil). Votre adresse IP est anonymisée avant tout stockage. Aucun script de mesure n&#8217;est chargé sans votre consentement préalable (Art. 82 Loi Informatique et Libertés). Vous pouvez retirer votre consentement à tout moment depuis le lien «&nbsp;Gérer les cookies&nbsp;» en bas de chaque page.</p>

        <h3>8. Cookies techniques</h3>
        <p>Nous utilisons des cookies strictement nécessaires au fonctionnement du site&nbsp;: cookie de session (authentification), cookie de préférence de langue et cookie de vérification de l&#8217;âge. Ces cookies ne nécessitent pas de consentement car ils sont indispensables au service demandé.</p>

        <h3>9. Liste détaillée des cookies déposés</h3>
        <p>Conformément à la délibération CNIL n°2020-091 du 17&nbsp;septembre 2020, voici la liste exhaustive des cookies que nous déposons&nbsp;:</p>
        <table>
            <thead>
                <tr>
                    <th scope="col">Nom du cookie</th>
                    <th scope="col">Finalité</th>
                    <th scope="col">Durée de conservation</th>
                    <th scope="col">Consentement requis</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>auth_token</code></td>
                    <td>Authentification — contient le JWT de session de l&#8217;utilisateur connecté (HttpOnly, Secure)</td>
                    <td>2&nbsp;heures (durée du JWT)</td>
                    <td>Non — strictement nécessaire</td>
                </tr>
                <tr>
                    <td><code>device_token</code></td>
                    <td>Mémorisation de l&#8217;appareil de confiance pour la validation MFA (HttpOnly, Secure)</td>
                    <td>90&nbsp;jours</td>
                    <td>Non — strictement nécessaire à la sécurité du compte</td>
                </tr>
                <tr>
                    <td><code>lang</code></td>
                    <td>Mémorisation de la préférence de langue (FR/EN)</td>
                    <td>Session (fermé à la fermeture du navigateur)</td>
                    <td>Non — préférence fonctionnelle</td>
                </tr>
                <tr>
                    <td><code>age_verified</code></td>
                    <td>Mémorisation de la déclaration de majorité (barrière d&#8217;âge légale alcool)</td>
                    <td>24h (13 mois si &#171;&#8239;Se souvenir&#8239;&#187; coché)</td>
                    <td>Non — obligation légale (Art. L3342-1 CSP)</td>
                </tr>
                <tr>
                    <td><code>cookie_consent</code></td>
                    <td>Mémorisation du choix de consentement aux cookies analytiques</td>
                    <td>13&nbsp;mois (recommandation CNIL 2020)</td>
                    <td>Non — nécessaire au respect de votre choix</td>
                </tr>
                <tr>
                    <td><code>_ga</code></td>
                    <td>Google Analytics 4 — identification de session anonymisée pour mesure d&#8217;audience</td>
                    <td>2&nbsp;ans</td>
                    <td><strong>Oui</strong> — déposé uniquement après consentement explicite</td>
                </tr>
                <tr>
                    <td><code>_ga_*</code></td>
                    <td>Google Analytics 4 — persistance de session pour le suivi des pages vues</td>
                    <td>2&nbsp;ans</td>
                    <td><strong>Oui</strong> — déposé uniquement après consentement explicite</td>
                </tr>
            </tbody>
        </table>

        <h2>III. Principe de minimisation</h2>
        <p>La protection de vos données personnelles est notre priorité. Nous appliquons le principe de minimisation posé par l&#8217;article 5-1-c du RGPD&nbsp;: nous ne collectons que les données strictement nécessaires à la finalité pour laquelle elles sont demandées. Aucune donnée superflue n&#8217;est conservée. Vos données ne sont ni vendues, ni cédées, ni partagées avec des tiers à des fins commerciales.</p>

        <h2>IV. Durées de conservation</h2>
        <ul>
            <li><strong>Données de commande et de facturation</strong>&nbsp;: 10&nbsp;ans à compter de la date de la commande (obligation légale comptable &mdash; Art.&nbsp;L123-22 Code de commerce).</li>
            <li><strong>Données de compte client</strong>&nbsp;: durée de la relation commerciale, puis 3&nbsp;ans en archivage intermédiaire après inactivité.</li>
            <li><strong>Données de contact</strong>&nbsp;: 3&nbsp;ans à compter du dernier contact.</li>
            <li><strong>Newsletter</strong>&nbsp;: jusqu&#8217;à la désinscription.</li>
            <li><strong>Données de tracking newsletter</strong>&nbsp;: 13&nbsp;mois à compter de la date d&#8217;ouverture ou de clic (intérêt légitime, recommandation CNIL).</li>
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
        <p>Certains de nos sous-traitants (Google LLC, GitHub Inc.) sont établis aux États-Unis. Ces transferts sont encadrés par des <strong>Clauses Contractuelles Types (SCCs)</strong> adoptées par la Commission européenne, dans le respect du cadre EU-US Data Privacy Framework. Vous pouvez demander communication des garanties mises en place en contactant&nbsp;: <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>

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
        <p>Pour exercer ces droits, adressez-vous à&nbsp;: GFA Bernard Solane et Fils, Château Crabitan Bellevue, 33410 Sainte-Croix-du-Mont ou par e-mail à <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>. Nous répondons dans un délai d&#8217;un mois (Art. 12 RGPD).</p>
        <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de la <strong>CNIL</strong> (Commission Nationale de l&#8217;Informatique et des Libertés) &mdash; <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a>.</p>

        <h2>VIII. Sécurité des données</h2>
        <p>Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte ou destruction accidentelle&nbsp;: connexion HTTPS/TLS, hachage des mots de passe (bcrypt), authentification multi-facteurs (MFA), traitement des paiements par un prestataire certifié PCI-DSS, hébergement sur serveur dédié IONOS en Allemagne, chiffrement AES-256-GCM des colonnes sensibles de la base de données (Art. 32 RGPD). Notre infrastructure est dimensionnée en vue de la migration vers des algorithmes résistants à l&#8217;informatique quantique (NIST PQC) à mesure que les standards seront disponibles. En cas de violation de données à caractère personnel, nous nous engageons à notifier la CNIL dans les 72 heures suivant la prise de connaissance de l&#8217;incident, conformément à l&#8217;Art. 33 du RGPD.</p>

        <h2>IX. Modifications de la présente politique</h2>
        <p>Nous nous réservons le droit de modifier cette politique de confidentialité afin de l&#8217;adapter aux évolutions légales ou aux changements de nos pratiques. En cas de modification substantielle, nous en informerons les utilisateurs disposant d&#8217;un compte. La version en vigueur est toujours accessible depuis cette page et depuis le pied de page du site.</p>
        <p>Pour toute question relative à la protection de vos données ou aux présentes mentions légales, consultez également nos <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales"><?= htmlspecialchars(__('footer.legal_notice')) ?></a>.</p>

<?php endif; ?>

<?php require SRC_PATH . '/View/partials/legal-close.php'; // NOSONAR — php:S2003 : idem ?>
