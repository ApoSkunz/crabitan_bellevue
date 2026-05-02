<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<div class="admin-page-header">
    <h1>DPO — Documents RGPD</h1>
</div>

<p class="admin-muted u-mb-8">
    Téléchargez les documents réglementaires RGPD au format PDF. Ces documents sont à la disposition
    de la CNIL sur simple demande.
</p>

<div class="admin-dpo-grid">

    <!-- Registre des traitements — Art. 30 -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">📋</div>
        <h2 class="admin-dpo-card__title">Registre des traitements</h2>
        <p class="admin-dpo-card__desc">
            Art. 30 RGPD — Liste de toutes les activités de traitement avec bases légales,
            catégories de données, destinataires et durées de conservation.
        </p>
        <a href="/admin/dpo/registre-traitements"
           class="admin-btn admin-btn--primary admin-dpo-card__btn"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- Sous-traitants — Art. 28 -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">🤝</div>
        <h2 class="admin-dpo-card__title">Sous-traitants (DPA)</h2>
        <p class="admin-dpo-card__desc">
            Art. 28 RGPD — Inventaire de tous les sous-traitants (IONOS, Google, GitHub, Crédit Agricole…)
            avec statut de conformité et liens DPA.
        </p>
        <a href="/admin/dpo/sous-traitants"
           class="admin-btn admin-btn--primary admin-dpo-card__btn"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- Procédure violation — Art. 33 -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">🚨</div>
        <h2 class="admin-dpo-card__title">Procédure violation de données</h2>
        <p class="admin-dpo-card__desc">
            Art. 33 & 34 RGPD — Procédure d'escalade, arbre de décision notification CNIL (72h),
            template de notification et registre interne des violations.
        </p>
        <a href="/admin/dpo/procedure-violation"
           class="admin-btn admin-btn--primary admin-dpo-card__btn"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- Matrice des habilitations RGPD -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">🔐</div>
        <h2 class="admin-dpo-card__title">Matrice des habilitations</h2>
        <p class="admin-dpo-card__desc">
            Tableau rôle × ressource × données personnelles visibles — base du contrôle d'accès RGPD.
            À réviser annuellement ou à chaque nouveau traitement.
        </p>
        <a href="/admin/dpo/matrice-habilitations"
           class="admin-btn admin-btn--primary admin-dpo-card__btn"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- DPIA — Art. 35 -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">🔍</div>
        <h2 class="admin-dpo-card__title">DPIA — Analyse d'impact</h2>
        <p class="admin-dpo-card__desc">
            Art. 35 RGPD — Analyse d'impact sur les traitements à risque élevé : relance panier abandonné,
            profilage inactivité client, tracking newsletter.
        </p>
        <a href="/admin/dpo/dpia"
           class="admin-btn admin-btn--primary admin-dpo-card__btn"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <?php if (($adminUser['role'] ?? '') === 'super_admin') : ?>
    <!-- Pages légales versionnées — super_admin uniquement -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">📝</div>
        <h2 class="admin-dpo-card__title">Pages légales</h2>
        <p class="admin-dpo-card__desc">
            CGV, mentions légales, politique de confidentialité — gestion des versions et dates
            de mise à jour publiées sur le site.
        </p>
        <a href="/admin/dpo/pages-legales"
           class="admin-btn admin-btn--primary admin-dpo-card__btn">
            Gérer les versions →
        </a>
    </div>

    <!-- Demandes RGPD — super_admin uniquement -->
    <div class="admin-card admin-dpo-card">
        <div class="admin-dpo-card__icon" aria-hidden="true">📬</div>
        <h2 class="admin-dpo-card__title">Demandes RGPD</h2>
        <p class="admin-dpo-card__desc">
            Art. 15-21 RGPD — Suivi des demandes d'accès, rectification, effacement, portabilité
            et opposition. Délai légal de traitement : 1 mois (CNIL).
        </p>
        <a href="/admin/rgpd/demandes"
           class="admin-btn admin-btn--primary admin-dpo-card__btn">
            Gérer les demandes →
        </a>
    </div>
    <?php endif; ?>

</div>

<div class="admin-dpo-legal-note">
    <strong>Rappel légal :</strong> Ces documents doivent être mis à jour à chaque nouveau traitement,
    changement de sous-traitant ou modification des durées de conservation. Révision annuelle obligatoire (Art. 30-4 RGPD).
    En cas de violation de données, notifier la CNIL via
    <a href="https://notifications.cnil.fr/" target="_blank" rel="noopener noreferrer" class="admin-dpo-legal-note__link">
        notifications.cnil.fr
    </a>
    dans les 72h suivant la prise de connaissance.
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
