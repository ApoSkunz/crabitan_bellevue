<?php
$pageTitle = __('footer.legal_notice');
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$isBare    = $bare ?? false;

if (!$isBare) {
    require_once SRC_PATH . '/View/partials/head.php';
    require_once SRC_PATH . '/View/partials/header.php';
}
?>
<?php if ($isBare) : ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($navLang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bare-legal">
    <div class="bare-legal__bar">
        <span><?= htmlspecialchars($pageTitle) ?></span>
        <button type="button" class="bare-legal__close" onclick="window.close()" aria-label="Fermer">&#10005;</button>
    </div>
    <article class="legal-content container" aria-label="<?= htmlspecialchars(__('footer.legal_notice')) ?>">
<?php else : ?>
<main class="page-legal" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.legal_notice')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <article class="legal-content container" aria-label="<?= htmlspecialchars(__('footer.legal_notice')) ?>">
<?php endif; ?>

        <h2>I. Informations d&#8217;exploitation</h2>

        <h3>1. Identification</h3>
        <p>Le site internet crabitanbellevue.fr est publié par la Société Civile GFA Bernard Solane et Fils, de droit français et dont le siège social est sis Château Crabitan 33410 Sainte-Croix-du-Mont &ndash; FRANCE.</p>
        <p>La Société Civile GFA Bernard Solane et Fils est immatriculée au Registre du commerce et des sociétés de Bordeaux sous le numéro 398 341 701. Son capital social est de 472&nbsp;591,95 euros (mention obligatoire en vertu de la loi n°2004-575 du 21 juin 2004).</p>
        <p>Le numéro de TVA intracommunautaire de la Société Civile GFA Bernard Solane et Fils est le FR53398341701.</p>
        <p>Téléphone&nbsp;: <a href="tel:+33556620153">+33 (0)5 56 62 01 53</a></p>
        <p>e-mail&nbsp;: <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a></p>

        <h3>2. Objet du site</h3>
        <p>Le site internet crabitanbellevue.fr est un site institutionnel destiné à présenter le Château Crabitan Bellevue, ainsi que d&#8217;effectuer la vente de marchandises en ligne. Les paiements sont acceptés par carte bancaire (Visa, Mastercard) via une plateforme sécurisée, par virement bancaire ou par réception de chèque.</p>

        <h2>II. Propriété intellectuelle</h2>

        <h3>1. Propriété</h3>
        <p>Ce site, tous les programmes informatiques front-end ou back-end et tous les logiciels utilisés en liaison avec le site internet peuvent contenir des informations confidentielles et des informations protégées par la législation sur la propriété intellectuelle en vigueur dans tous les pays.</p>
        <p>Par conséquent, sauf indication contraire, les droits de propriété intellectuelle sur les documents textuels ou photographiques contenus sur le site et chacun des éléments créés pour le site crabitanbellevue.fr restent la propriété de la Société Civile GFA Bernard Solane et Fils. Aucune licence ni aucun autre droit de propriété intellectuelle ne vous est accordé de quelque manière que ce soit, sinon de consulter notre site et son contenu en ligne. La reproduction complète ou partielle de ce document est autorisée à des fins exclusivement privées&nbsp;; toute reproduction à des fins commerciales ou publicitaires et la diffusion à des tiers est expressément interdite sans accord préalable écrit.</p>

        <h3>2. Cookies</h3>
        <p>Le GFA Bernard Solane et Fils utilise des cookies strictement nécessaires au fonctionnement du site (session, préférence de langue, vérification de l&#8217;âge) ainsi que, avec votre consentement, des cookies de mesure d&#8217;audience (Google Analytics 4). Un bandeau de consentement est affiché à chaque première visite, conformément aux recommandations CNIL 2020 et à l&#8217;Art.&nbsp;82 de la Loi Informatique et Libertés. Vous pouvez gérer votre consentement à tout moment via le lien «&nbsp;Gérer les cookies&nbsp;» en bas de page.</p>
        <p>Vous pouvez également paramétrer votre navigateur pour refuser les cookies&nbsp;:</p>
        <ul>
            <li><strong>Google Chrome</strong>&nbsp;: Paramètres &gt; Confidentialité et sécurité &gt; Paramètres des cookies.</li>
            <li><strong>Safari</strong>&nbsp;: Préférences &gt; Confidentialité &gt; Désactiver les cookies.</li>
            <li><strong>Mozilla Firefox</strong>&nbsp;: Options &gt; Vie privée et sécurité &gt; Cookies et données de sites.</li>
        </ul>

        <h2>III. Données à caractère personnel</h2>
        <p>La protection de vos données personnelles est notre priorité. Nous n&#8217;utilisons que les données strictement nécessaires (principe de minimisation, art. 5-1-c du RGPD). Les paiements par carte bancaire sont traités par un prestataire certifié PCI-DSS&nbsp;; vos coordonnées bancaires ne sont jamais stockées sur nos serveurs. Un registre des activités de traitement est tenu à jour conformément à l&#8217;Art.&nbsp;30 du RGPD et peut être mis à la disposition de la CNIL sur demande.</p>
        <p>Pour connaître en détail les données collectées, leurs finalités, les sous-traitants impliqués, les durées de conservation et exercer vos droits (accès, rectification, effacement, portabilité, opposition), consultez notre <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite"><?= htmlspecialchars(__('footer.privacy_policy')) ?></a>.</p>

        <h2>IV. Responsabilités</h2>

        <h3>1. Limitation de la responsabilité</h3>
        <p>La Société Civile GFA Bernard Solane et Fils s&#8217;engage à mettre tout en œuvre pour que les informations publiées sur ce site soient correctes et à jour. Elle décline toutefois toute responsabilité pour toute interruption du service, inexactitude ou omission portant sur les informations disponibles, ou pour tout dommage résultant d&#8217;une intrusion frauduleuse d&#8217;un tiers.</p>

        <h3>2. Liens externes</h3>
        <p>Ce site peut comporter des liens vers d&#8217;autres sites internet que nous ne maîtrisons pas. La Société Civile GFA Bernard Solane et Fils ne peut pas être tenue responsable de leur contenu ou des modifications qui leur sont apportées.</p>

        <h2>V. Généralités</h2>

        <h3>1. Loi applicable</h3>
        <p>Les présentes mentions légales sont établies en conformité avec le droit français, notamment la loi n°2004-575 du 21 juin 2004 pour la confiance dans l&#8217;économie numérique et le RGPD (Règlement UE 2016/679).</p>
        <p><?= htmlspecialchars(__('legal.hosting_info')) ?></p>
        <p><?= htmlspecialchars(__('legal.ai_mention')) ?></p>

    </article>
<?php if ($isBare) : ?>
</body>
</html>
<?php else : ?>
</main>
    <?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
<?php endif; ?>
