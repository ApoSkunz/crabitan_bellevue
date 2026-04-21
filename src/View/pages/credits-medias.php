<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('footer.credits_medias');
$metaDescription = $navLang === 'en'
    ? 'Media credits for the Château Crabitan Bellevue website — photos, icons and third-party resources.'
    : 'Crédits médias du site Château Crabitan Bellevue — photos, icônes et ressources tierces.';
$seoSlug = 'credits-medias';
require_once SRC_PATH . '/View/partials/seo-meta.php';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$isEn = isset($lang) && $lang === 'en';
?>

<main class="page-legal" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.credits_medias')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <div class="legal-content container">

        <?php if ($isEn): ?>

        <h2>Photo credits</h2>
        <p>
            All photographs published on this website are the exclusive property of
            <strong>Château Crabitan Bellevue</strong> or are taken from the family archives.
            They may not be reproduced, distributed, modified or used in any way without the
            prior written consent of the rights holder.
        </p>

        <h3>Gallery and editorial photographs</h3>
        <p>
            © Château Crabitan Bellevue — Family archives.<br>
            All rights reserved.
        </p>

        <h3>Wine labels and product photographs</h3>
        <p>
            © Château Crabitan Bellevue.<br>
            All rights reserved.
        </p>

        <h2>Video credits</h2>
        <p>
            All videos published on this website are the exclusive property of
            <strong>Château Crabitan Bellevue</strong>.
            They may not be reproduced, distributed, modified or used in any way without the
            prior written consent of the rights holder.
        </p>
        <p>
            © Château Crabitan Bellevue.<br>
            All rights reserved.
        </p>

        <h2>Removal request (DMCA / Copyright)</h2>
        <p>
            If you believe that content published on this site infringes your intellectual property rights,
            please contact us with the following information:
        </p>
        <ul>
            <li>Your full name and contact details</li>
            <li>A description of the protected work and its location on the site</li>
            <li>Proof of your rights over the work</li>
            <li>A statement that you believe the use is unauthorised</li>
        </ul>
        <p>
            <strong>Contact :</strong>
            <a href="/<?= htmlspecialchars($lang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
        </p>
        <p>We undertake to process any legitimate request within 72 hours.</p>

        <?php else: ?>

        <h2>Crédits photographiques</h2>
        <p>
            Toutes les photographies publiées sur ce site sont la propriété exclusive de
            <strong>Château Crabitan Bellevue</strong> ou sont issues des archives familiales.
            Elles ne peuvent être reproduites, distribuées, modifiées ou utilisées de quelque manière
            que ce soit sans l'accord écrit préalable du titulaire des droits.
        </p>

        <h3>Photographies de la galerie et éditoriales</h3>
        <p>
            © Château Crabitan Bellevue — Archives familiales.<br>
            Tous droits réservés.
        </p>

        <h3>Photographies des étiquettes et des vins</h3>
        <p>
            © Château Crabitan Bellevue.<br>
            Tous droits réservés.
        </p>

        <h2>Crédits vidéo</h2>
        <p>
            Toutes les vidéos publiées sur ce site sont la propriété exclusive de
            <strong>Château Crabitan Bellevue</strong>.
            Elles ne peuvent être reproduites, distribuées, modifiées ou utilisées de quelque manière
            que ce soit sans l'accord écrit préalable du titulaire des droits.
        </p>
        <p>
            © Château Crabitan Bellevue.<br>
            Tous droits réservés.
        </p>

        <h2>Demande de retrait (droit d'auteur)</h2>
        <p>
            Si vous estimez qu'un contenu publié sur ce site porte atteinte à vos droits de propriété
            intellectuelle, contactez-nous en nous communiquant les éléments suivants :
        </p>
        <ul>
            <li>Vos coordonnées complètes</li>
            <li>La description de l'œuvre protégée et sa localisation sur le site</li>
            <li>La preuve de vos droits sur l'œuvre</li>
            <li>Une déclaration attestant que vous considérez l'utilisation comme non autorisée</li>
        </ul>
        <p>
            <strong>Contact :</strong>
            <a href="/<?= htmlspecialchars($lang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
        </p>
        <p>Nous nous engageons à traiter toute demande légitime dans un délai de 72 heures.</p>

        <?php endif; ?>

    </div>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
