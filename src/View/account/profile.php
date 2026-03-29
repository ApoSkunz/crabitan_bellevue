<?php
$pageTitle     = __('account.profile');
$activeSection = 'profile';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<string, mixed> $account */
/** @var array<string, string> $errors */
/** @var string|null $success */
/** @var string $csrf */
$isCompany = ($account['account_type'] ?? '') === 'company';
$selected  = static fn(bool $c): string => $c ? ' selected' : '';
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.profile') ?></h1>
            </header>

            <?php if ($success) : ?>
                <div class="alert alert--success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <section class="account-section">
                <h2 class="account-section__title"><?= __('account.profile_title') ?></h2>

                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/profil"
                      class="account-form"
                      novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <!-- Email (lecture seule + lien mailto support) -->
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input type="email" id="profile-email" value="<?= htmlspecialchars($account['email'] ?? '') ?>"
                               disabled class="form-input--readonly">
                        <?php
                            $mailtoSubject = rawurlencode(__('account.email_change_subject'));
                            $mailtoBody    = rawurlencode(
                                ($lang === 'fr'
                                    ? "Bonjour,\n\nJe souhaite modifier mon adresse e-mail.\nAdresse actuelle : "
                                    : "Hello,\n\nI would like to change my email address.\nCurrent address: ")
                                . ($account['email'] ?? '')
                                . ($lang === 'fr'
                                    ? "\nNouvelle adresse souhaitée : \n\nCordialement."
                                    : "\nRequested new address: \n\nKind regards.")
                            );
                            $mailtoHref = 'mailto:' . htmlspecialchars($ownerEmail ?? '')
                                        . '?subject=' . $mailtoSubject
                                        . '&body=' . $mailtoBody;
                        ?>
                        <p class="form-hint">
                            <?= __('account.email_readonly') ?>
                            <a href="<?= $mailtoHref ?>"><?= __('account.email_change_request_btn') ?></a>
                        </p>
                    </div>

                    <?php if (!$isCompany) : ?>
                        <!-- Particulier -->
                        <div class="form-group">
                            <label for="civility"><?= __('account.civility') ?></label>
                            <select id="civility" name="civility" autocomplete="honorific-prefix">
                                <option value="M"<?= $selected(($account['civility'] ?? '') === 'M') ?>><?= __('account.civility_m') ?></option>
                                <option value="F"<?= $selected(($account['civility'] ?? '') === 'F') ?>><?= __('account.civility_f') ?></option>
                                <option value="other"<?= $selected(($account['civility'] ?? '') === 'other') ?>><?= __('account.civility_other') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="firstname"><?= __('account.firstname') ?> *</label>
                            <input type="text" id="firstname" name="firstname"
                                   value="<?= htmlspecialchars($account['firstname'] ?? '') ?>"
                                   required autocomplete="given-name">
                            <?php if (isset($errors['firstname'])) : ?>
                                <p class="form-error"><?= htmlspecialchars($errors['firstname']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="lastname"><?= __('account.lastname') ?> *</label>
                            <input type="text" id="lastname" name="lastname"
                                   value="<?= htmlspecialchars($account['lastname'] ?? '') ?>"
                                   required autocomplete="family-name">
                            <?php if (isset($errors['lastname'])) : ?>
                                <p class="form-error"><?= htmlspecialchars($errors['lastname']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <!-- Société -->
                        <div class="form-group">
                            <label for="company_name"><?= __('account.company_name') ?> *</label>
                            <input type="text" id="company_name" name="company_name"
                                   value="<?= htmlspecialchars($account['company_name'] ?? '') ?>"
                                   required>
                            <?php if (isset($errors['company_name'])) : ?>
                                <p class="form-error"><?= htmlspecialchars($errors['company_name']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="siret"><?= __('account.siret') ?></label>
                            <input type="text" id="siret" name="siret"
                                   value="<?= htmlspecialchars($account['siret'] ?? '') ?>"
                                   maxlength="14" pattern="\d{14}">
                        </div>
                    <?php endif; ?>

                    <!-- Newsletter -->
                    <div class="form-group form-group--checkbox">
                        <label class="form-checkbox">
                            <input type="checkbox" name="newsletter" value="1"
                                <?= ($account['newsletter'] ?? 0) ? ' checked' : '' ?>>
                            <?= __('account.newsletter_label') ?>
                        </label>
                    </div>

                    <button type="submit" class="btn btn--primary">
                        <?= __('account.save_profile') ?>
                    </button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
