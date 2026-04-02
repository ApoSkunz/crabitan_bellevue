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

                    <!-- Email (lecture seule — modifiable via la section ci-dessous) -->
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input type="email" id="profile-email" value="<?= htmlspecialchars($account['email'] ?? '') ?>"
                               disabled class="form-input--readonly">
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

            <!-- Changement d'email (double opt-in) -->
            <section class="account-section" id="email-change">
                <h2 class="account-section__title"><?= __('account.email_change_section_title') ?></h2>

                <?php if (isset($errors['email'])) : ?>
                    <div class="alert alert--error" role="alert"><?= htmlspecialchars($errors['email']) ?></div>
                <?php endif; ?>

                <?php
                $hasPending = !empty($account['email_change_new_email'])
                    && !empty($account['email_change_expires_at'])
                    && strtotime((string) $account['email_change_expires_at']) > time();
                ?>

                <?php if ($hasPending) : ?>
                    <div class="alert alert--info" role="alert">
                        <strong><?= __('account.email_change_pending_title') ?></strong><br>
                        <?= htmlspecialchars(__('account.email_change_pending_body')) ?>
                        <strong><?= htmlspecialchars((string) $account['email_change_new_email']) ?></strong>
                    </div>

                    <form method="POST"
                          action="/<?= htmlspecialchars($lang) ?>/mon-compte/email/annuler"
                          class="account-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn--danger">
                            <?= __('account.email_change_cancel_btn') ?>
                        </button>
                    </form>
                <?php else : ?>
                    <p class="account-text"><?= __('account.email_change_intro') ?></p>

                    <form method="POST"
                          action="/<?= htmlspecialchars($lang) ?>/mon-compte/profil/changer-email"
                          class="account-form"
                          novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                        <div class="form-group">
                            <label for="email_change_current"><?= __('account.email_current') ?></label>
                            <input type="email" id="email_change_current"
                                   value="<?= htmlspecialchars($account['email'] ?? '') ?>"
                                   disabled class="form-input--readonly">
                        </div>

                        <div class="form-group">
                            <label for="new_email"><?= __('account.email_new') ?> *</label>
                            <input type="email" id="new_email" name="new_email"
                                   autocomplete="email" required>
                        </div>

                        <div class="form-group form-group--pwd">
                            <label for="email_change_password"><?= __('account.current_password') ?> *</label>
                            <div class="form-pwd-wrap">
                                <input type="password" id="email_change_password" name="current_password"
                                       autocomplete="current-password" required>
                                <button type="button" class="form-pwd-toggle"
                                        data-target="email_change_password"
                                        aria-label="<?= __('account.show_password') ?>">
                                    <span class="pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                    <span class="pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--gold">
                            <?= __('account.email_change_submit_btn') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
