<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$typeLabels = \Model\RgpdRequestModel::TYPE_LABELS;
?>

<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header admin-rgpd-create-header">
    <h1>Nouvelle demande RGPD</h1>
    <p class="admin-rgpd-create-subtitle">
        Saisir une demande d'exercice de droits reçue par email (Art. 12-22 RGPD).
        Le délai légal de traitement est d'1 mois (Art. 12 RGPD).
    </p>
</div>

<form method="POST" action="/admin/rgpd/demandes/ajouter" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

    <div class="admin-field u-mb-4">
        <label class="admin-field__label" for="type">
            Type de droit exercé <span class="u-text-error" aria-hidden="true">*</span>
        </label>
        <select id="type" name="type" required class="admin-field__select u-w-full">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($typeLabels as $val => $label) : ?>
                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="admin-field u-mb-4">
        <label class="admin-field__label" for="requester_name">
            Nom du demandeur <span class="u-text-error" aria-hidden="true">*</span>
        </label>
        <input type="text" id="requester_name" name="requester_name" required
               placeholder="Ex : Jean Dupont"
               class="admin-field__input u-w-full">
    </div>

    <div class="admin-field u-mb-4">
        <label class="admin-field__label" for="requester_email">
            Email du demandeur <span class="u-text-error" aria-hidden="true">*</span>
        </label>
        <input type="email" id="requester_email" name="requester_email" required
               placeholder="demandeur@exemple.fr"
               class="admin-field__input u-w-full">
    </div>

    <div class="admin-field u-mb-8">
        <label class="admin-field__label" for="received_at">
            Date de réception
            <span class="u-font-normal text-muted u-text-85">(laisser vide = aujourd'hui)</span>
        </label>
        <input type="date" id="received_at" name="received_at"
               max="<?= date('Y-m-d') ?>"
               class="admin-field__input u-w-full">
        <p class="admin-field__hint">
            Le chronomètre de traitement démarre à cette date. Délai légal : 1 mois (alerte J-5 = 25 jours).
        </p>
    </div>

    <div class="u-flex u-gap-3">
        <button type="submit" class="admin-btn admin-btn--primary">Enregistrer la demande</button>
        <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--outline">Annuler</a>
    </div>
</form>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
