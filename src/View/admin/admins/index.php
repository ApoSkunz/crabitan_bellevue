<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($success ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Gestion des admins <small class="u-text-2xs admin-subcount-alt">(<?= count($admins) ?>)</small></h1>
</div>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Dernière connexion</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($admins)) : ?>
                <tr>
                    <td colspan="7" class="text-center u-text-soft u-p-8">Aucun admin trouvé</td>
                </tr>
            <?php else : ?>
                <?php foreach ($admins as $admin) : ?>
                    <?php
                    $isSelf      = (int) $admin['id'] === (int) $adminUser['id'];
                    $isBlocked   = (bool) $admin['is_blocked'];
                    $roleLabel   = $admin['role'] === 'super_admin' ? 'Super Admin' : 'Admin';
                    $displayName = trim(($admin['firstname'] ?? '') . ' ' . ($admin['lastname'] ?? '')) ?: '—';
                    ?>
                    <tr>
                        <td data-label="ID" class="u-text-soft"><?= (int) $admin['id'] ?></td>
                        <td data-label="Nom"><?= htmlspecialchars($displayName) ?></td>
                        <td data-label="Email"><?= htmlspecialchars($admin['email']) ?></td>
                        <td data-label="Rôle"><?= htmlspecialchars($roleLabel) ?></td>
                        <td data-label="Dernière connexion" class="u-nowrap u-text-sm u-text-soft">
                            <?php if (!empty($admin['last_login_at'])) : ?>
                                <?= date('d/m/Y H:i', strtotime($admin['last_login_at'])) ?>
                            <?php else : ?>
                                <span class="u-text-error-alt">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut">
                            <?php if ($isBlocked) : ?>
                                <span class="u-text-error-alt u-text-sm u-font-semibold">Bloqué</span>
                            <?php else : ?>
                                <span class="u-text-green u-text-sm">Actif</span>
                            <?php endif; ?>
                        </td>
                        <td class="u-nowrap">
                            <?php if (!$isSelf) : ?>
                                <?php if (!$isBlocked) : ?>
                                    <form method="POST"
                                          action="/admin/admins/<?= (int) $admin['id'] ?>/bloquer"
                                          class="u-inline-block"
                                          data-admin-confirm="Bloquer le compte <?= htmlspecialchars($admin['email']) ?> ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm admin-btn--block-action">
                                            Bloquer
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <form method="POST"
                                          action="/admin/admins/<?= (int) $admin['id'] ?>/debloquer"
                                          class="u-inline-block"
                                          data-admin-confirm="Débloquer le compte <?= htmlspecialchars($admin['email']) ?> ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm admin-btn--unblock-action">
                                            Débloquer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
