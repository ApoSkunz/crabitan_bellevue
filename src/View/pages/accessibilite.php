<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('accessibility.title');
$metaDescription = $navLang === 'en'
    ? 'Accessibility statement for the Château Crabitan Bellevue website — our commitment to digital inclusion.'
    : 'Déclaration d\'accessibilité du site Château Crabitan Bellevue — notre engagement pour l\'inclusion numérique.';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var string $lang */
$lang = $lang ?? 'fr';
$isEn = $lang === 'en';
?>

<main class="page-accessibility" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('accessibility.title')) ?></h1>
            <p class="page-hero__intro"><?= htmlspecialchars(__('accessibility.intro')) ?></p>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <div class="legal-content container">

        <?php if ($isEn) : ?>

        <h2>Accessibility Statement</h2>
        <p>
            Château Crabitan Bellevue is committed to making its website accessible in accordance with
            Article 47 of French Law No. 2005-102 of 11 February 2005.
        </p>

        <h3>Compliance Status</h3>
        <p>
            <strong>crabitanbellevue.fr</strong> is <strong>partially compliant</strong> with the
            General Accessibility Guidelines (RGAA v4). The non-conformities and exemptions are listed below.
        </p>

        <h3>Automated Audit Results (Lighthouse)</h3>
        <p>
            An automated audit was performed using
            <a href="https://developer.chrome.com/docs/lighthouse/accessibility/" rel="noopener noreferrer" target="_blank">Google Lighthouse</a>
            (<em>Accessibility</em> category) on the main pages of the site.
        </p>
        <ul>
            <li><strong>Average score: 94.7 / 100</strong> (min 93 — max 96)</li>
            <li>24 unique pages audited — 37 runs total</li>
            <li>Audit date: 13 April 2026</li>
        </ul>
        <p>
            Lighthouse automatically evaluates a subset of RGAA/WCAG 2.1 criteria. It does not replace
            a full manual audit, but provides a reproducible objective score.
        </p>

        <h3>Non-accessible Content</h3>
        <ul>
            <li>Some decorative images may not have an empty <code>alt</code> attribute in all contexts.</li>
            <li>Some interactive components may not be fully operable by keyboard in older browsers.</li>
            <li>Some third-party content (maps, external widgets) may not meet all accessibility criteria.</li>
        </ul>

        <h3>Disproportionate Burden</h3>
        <p>
            Certain content is exempt from accessibility requirements due to disproportionate burden
            (RGAA exemption — Art. 47 III): legacy archival content and third-party embedded tools
            outside our direct control.
        </p>

        <h3>Preparation of this Accessibility Statement</h3>
        <p>
            This statement was prepared on <strong>1 April 2026</strong>. It was last reviewed on
            <strong>13 April 2026</strong>.
        </p>
        <p>
            The assessment was carried out via an internal audit of the main pages of the site
            against RGAA v4 criteria, supplemented by automated Google Lighthouse runs.
        </p>

        <h3>Feedback and Contact</h3>
        <p>
            If you are unable to access content or a service, you can contact us to be directed towards
            an accessible alternative or to obtain the content in another form.
        </p>
        <ul>
            <li>
                By email: <a href="/<?= htmlspecialchars($lang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
            </li>
        </ul>

        <h3>Enforcement Procedure</h3>
        <p>
            This procedure is to be used in the following case: you have notified the website manager
            of a defect in accessibility compliance which prevents you from accessing a content or one
            of the portal's services and you have not obtained a satisfactory response.
        </p>
        <ul>
            <li>
                Write a message to the
                <a href="https://formulaire.defenseurdesdroits.fr" rel="noopener noreferrer" target="_blank">
                    Défenseur des droits</a>
            </li>
            <li>
                Contact the
                <a href="https://www.defenseurdesdroits.fr/saisir/delegues" rel="noopener noreferrer" target="_blank">
                    Défenseur des droits delegate</a> in your region
            </li>
            <li>
                Send a letter by post (free of charge, no stamp required) to:<br>
                Défenseur des droits<br>
                Libre réponse 71120<br>
                75342 Paris CEDEX 07
            </li>
        </ul>

        <?php else : ?>

        <h2>Déclaration d'accessibilité</h2>
        <p>
            Le Château Crabitan Bellevue s'engage à rendre son site internet accessible conformément
            à l'article 47 de la loi n° 2005-102 du 11 février 2005.
        </p>

        <h3>État de conformité</h3>
        <p>
            <strong>crabitanbellevue.fr</strong> est <strong>partiellement conforme</strong> avec le
            Référentiel Général d'Amélioration de l'Accessibilité (RGAA v4). Les non-conformités et
            les dérogations sont énumérées ci-dessous.
        </p>

        <h3>Résultats de l'audit automatisé (Lighthouse)</h3>
        <p>
            Un audit automatisé a été réalisé à l'aide de
            <a href="https://developer.chrome.com/docs/lighthouse/accessibility/" rel="noopener noreferrer" target="_blank">Google Lighthouse</a>
            (catégorie <em>Accessibility</em>) sur les principales pages du site.
        </p>
        <ul>
            <li><strong>Score moyen : 94,7 / 100</strong> (min 93 — max 96)</li>
            <li>24 pages uniques auditées — 37 exécutions au total</li>
            <li>Date de l'audit : 13 avril 2026</li>
        </ul>
        <p>
            Lighthouse évalue automatiquement un sous-ensemble des critères RGAA/WCAG 2.1. Il ne
            remplace pas un audit manuel exhaustif, mais fournit un score objectif et reproductible.
        </p>

        <h3>Contenus non accessibles</h3>
        <ul>
            <li>Certaines images décoratives peuvent ne pas disposer d'un attribut <code>alt</code> vide dans tous les contextes.</li>
            <li>Certains composants interactifs peuvent ne pas être entièrement utilisables au clavier sur les navigateurs anciens.</li>
            <li>Certains contenus tiers (cartes, widgets externes) peuvent ne pas respecter l'ensemble des critères d'accessibilité.</li>
        </ul>

        <h3>Dérogations pour charge disproportionnée</h3>
        <p>
            Certains contenus sont exemptés des exigences d'accessibilité pour charge disproportionnée
            (dérogation RGAA — Art. 47 III) : contenus archivés anciens et outils tiers intégrés
            hors de notre contrôle direct.
        </p>

        <h3>Établissement de cette déclaration d'accessibilité</h3>
        <p>
            Cette déclaration a été établie le <strong>1er avril 2026</strong>. Elle a été mise à jour
            le <strong>13 avril 2026</strong>.
        </p>
        <p>
            L'évaluation a été réalisée par un audit interne des principales pages du site selon les
            critères du RGAA v4, complété par des passages automatisés avec Google Lighthouse.
        </p>

        <h3>Retour d'information et contact</h3>
        <p>
            Si vous n'arrivez pas à accéder à un contenu ou à un service, vous pouvez contacter le
            responsable du site internet pour être orienté vers une alternative accessible ou obtenir
            le contenu sous une autre forme.
        </p>
        <ul>
            <li>
                Via le formulaire de contact :
                <a href="/<?= htmlspecialchars($lang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
            </li>
        </ul>

        <h3>Voies de recours</h3>
        <p>
            Cette procédure est à utiliser dans le cas suivant : vous avez signalé au responsable du
            site internet un défaut d'accessibilité qui vous empêche d'accéder à un contenu ou à un
            des services du portail et vous n'avez pas obtenu de réponse satisfaisante.
        </p>
        <ul>
            <li>
                Écrire un message au
                <a href="https://formulaire.defenseurdesdroits.fr" rel="noopener noreferrer" target="_blank">
                    Défenseur des droits</a>
            </li>
            <li>
                Contacter le
                <a href="https://www.defenseurdesdroits.fr/saisir/delegues" rel="noopener noreferrer" target="_blank">
                    délégué du Défenseur des droits</a> dans votre région
            </li>
            <li>
                Envoyer un courrier par la poste (gratuit, ne pas mettre de timbre) :<br>
                Défenseur des droits<br>
                Libre réponse 71120<br>
                75342 Paris CEDEX 07
            </li>
        </ul>

        <?php endif; ?>

    </div>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
