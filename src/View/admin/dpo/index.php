<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<div class="admin-page-header">
    <h1>DPO — Documents RGPD</h1>
</div>

<p style="color:#8a7a60;margin-bottom:2rem;">
    Téléchargez les documents réglementaires RGPD au format PDF. Ces documents sont à la disposition
    de la CNIL sur simple demande.
</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">

    <!-- Registre des traitements — Art. 30 -->
    <div class="admin-card" style="padding:1.5rem;">
        <div style="font-size:1.5rem;margin-bottom:0.75rem;" aria-hidden="true">📋</div>
        <h2 style="font-size:1rem;margin:0 0 0.5rem;color:var(--color-gold,#c1a14b);">Registre des traitements</h2>
        <p style="font-size:0.8rem;color:#8a7a60;margin:0 0 1rem;">
            Art. 30 RGPD — Liste de toutes les activités de traitement avec bases légales,
            catégories de données, destinataires et durées de conservation.
        </p>
        <a href="/admin/dpo/registre-traitements"
           class="admin-btn admin-btn--primary"
           style="display:inline-block;text-decoration:none;"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- Sous-traitants — Art. 28 -->
    <div class="admin-card" style="padding:1.5rem;">
        <div style="font-size:1.5rem;margin-bottom:0.75rem;" aria-hidden="true">🤝</div>
        <h2 style="font-size:1rem;margin:0 0 0.5rem;color:var(--color-gold,#c1a14b);">Sous-traitants (DPA)</h2>
        <p style="font-size:0.8rem;color:#8a7a60;margin:0 0 1rem;">
            Art. 28 RGPD — Inventaire de tous les sous-traitants (IONOS, Google, GitHub, Crédit Agricole…)
            avec statut de conformité et liens DPA.
        </p>
        <a href="/admin/dpo/sous-traitants"
           class="admin-btn admin-btn--primary"
           style="display:inline-block;text-decoration:none;"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

    <!-- Procédure violation — Art. 33 -->
    <div class="admin-card" style="padding:1.5rem;">
        <div style="font-size:1.5rem;margin-bottom:0.75rem;" aria-hidden="true">🚨</div>
        <h2 style="font-size:1rem;margin:0 0 0.5rem;color:var(--color-gold,#c1a14b);">Procédure violation de données</h2>
        <p style="font-size:0.8rem;color:#8a7a60;margin:0 0 1rem;">
            Art. 33 & 34 RGPD — Procédure d'escalade, arbre de décision notification CNIL (72h),
            template de notification et registre interne des violations.
        </p>
        <a href="/admin/dpo/procedure-violation"
           class="admin-btn admin-btn--primary"
           style="display:inline-block;text-decoration:none;"
           target="_blank" rel="noopener noreferrer">
            ⬇ Télécharger le PDF
        </a>
    </div>

</div>

<div style="margin-top:2rem;padding:1rem;background:#f5f0e8;border-left:3px solid #c1a14b;font-size:0.8rem;color:#5a4030;border-radius:0 4px 4px 0;">
    <strong>Rappel légal :</strong> Ces documents doivent être mis à jour à chaque nouveau traitement,
    changement de sous-traitant ou modification des durées de conservation. Révision annuelle obligatoire (Art. 30-4 RGPD).
    En cas de violation de données, notifier la CNIL via
    <a href="https://notifications.cnil.fr/" target="_blank" rel="noopener noreferrer" style="color:#c1a14b;">
        notifications.cnil.fr
    </a>
    dans les 72h suivant la prise de connaissance.
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
