<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$typeLabels = \Model\RgpdRequestModel::TYPE_LABELS;
?>

<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header" style="margin-bottom:24px;">
    <h1>Nouvelle demande RGPD</h1>
    <p style="color:#8a7a60;font-size:0.9rem;margin-top:4px;">
        Saisir une demande d'exercice de droits reçue par email (Art. 12-22 RGPD).
        Le délai légal de traitement est d'1 mois (Art. 12 RGPD).
    </p>
</div>

<form method="POST" action="/admin/rgpd/demandes/ajouter" style="max-width:560px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

    <div style="margin-bottom:16px;">
        <label for="type" style="display:block;font-weight:600;margin-bottom:4px;">
            Type de droit exercé <span style="color:#c62828;" aria-hidden="true">*</span>
        </label>
        <select id="type" name="type" required
                style="width:100%;padding:8px 10px;border:1px solid #d0c8b8;border-radius:4px;font-size:0.95rem;">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($typeLabels as $val => $label) : ?>
                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-bottom:16px;">
        <label for="requester_name" style="display:block;font-weight:600;margin-bottom:4px;">
            Nom du demandeur <span style="color:#c62828;" aria-hidden="true">*</span>
        </label>
        <input type="text" id="requester_name" name="requester_name" required
               placeholder="Ex : Jean Dupont"
               style="width:100%;padding:8px 10px;border:1px solid #d0c8b8;border-radius:4px;font-size:0.95rem;box-sizing:border-box;">
    </div>

    <div style="margin-bottom:16px;">
        <label for="requester_email" style="display:block;font-weight:600;margin-bottom:4px;">
            Email du demandeur <span style="color:#c62828;" aria-hidden="true">*</span>
        </label>
        <input type="email" id="requester_email" name="requester_email" required
               placeholder="demandeur@exemple.fr"
               style="width:100%;padding:8px 10px;border:1px solid #d0c8b8;border-radius:4px;font-size:0.95rem;box-sizing:border-box;">
    </div>

    <div style="margin-bottom:24px;">
        <label for="received_at" style="display:block;font-weight:600;margin-bottom:4px;">
            Date de réception
            <span style="font-weight:400;color:#8a7a60;font-size:0.85rem;">(laisser vide = aujourd'hui)</span>
        </label>
        <input type="date" id="received_at" name="received_at"
               max="<?= date('Y-m-d') ?>"
               style="width:100%;padding:8px 10px;border:1px solid #d0c8b8;border-radius:4px;font-size:0.95rem;box-sizing:border-box;">
        <p style="font-size:0.8rem;color:#8a7a60;margin-top:4px;">
            Le chronomètre de traitement démarre à cette date. Délai légal : 1 mois (alerte J-5 = 25 jours).
        </p>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="admin-btn admin-btn--primary">Enregistrer la demande</button>
        <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--outline">Annuler</a>
    </div>
</form>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
