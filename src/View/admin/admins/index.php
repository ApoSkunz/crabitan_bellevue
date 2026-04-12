<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($success ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Gestion des admins <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= count($admins) ?>)</small></h1>
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
                    <td colspan="7" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun admin trouvé</td>
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
                        <td data-label="ID" style="color:#8a7a60;"><?= (int) $admin['id'] ?></td>
                        <td data-label="Nom"><?= htmlspecialchars($displayName) ?></td>
                        <td data-label="Email"><?= htmlspecialchars($admin['email']) ?></td>
                        <td data-label="Rôle"><?= htmlspecialchars($roleLabel) ?></td>
                        <td data-label="Dernière connexion" style="white-space:nowrap;font-size:0.8rem;color:#8a7a60;">
                            <?php if (!empty($admin['last_login_at'])) : ?>
                                <?= date('d/m/Y H:i', strtotime($admin['last_login_at'])) ?>
                            <?php else : ?>
                                <span style="color:#b91c1c;">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut">
                            <?php if ($isBlocked) : ?>
                                <span style="color:#b91c1c;font-size:0.8rem;font-weight:600;">Bloqué</span>
                            <?php else : ?>
                                <span style="color:#15803d;font-size:0.8rem;">Actif</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if (!$isSelf) : ?>
                                <?php if (!$isBlocked) : ?>
                                    <form method="POST"
                                          action="/admin/admins/<?= (int) $admin['id'] ?>/bloquer"
                                          style="display:inline;"
                                          data-admin-confirm="Bloquer le compte <?= htmlspecialchars($admin['email']) ?> ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm"
                                                style="color:#b91c1c;border-color:#b91c1c;">
                                            Bloquer
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <form method="POST"
                                          action="/admin/admins/<?= (int) $admin['id'] ?>/debloquer"
                                          style="display:inline;"
                                          data-admin-confirm="Débloquer le compte <?= htmlspecialchars($admin['email']) ?> ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm"
                                                style="color:#15803d;border-color:#15803d;">
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
