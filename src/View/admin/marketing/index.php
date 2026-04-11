<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var float                                                              $avgOrder */
/** @var float                                                              $conversionRate */
/** @var array{pro: int, particulier: int, fr: int, en: int, total: int}   $segmentation */
/** @var array{active: int, unsubscribed: int, total: int}                 $newsletterStats */
/** @var array<int, array{wine_id: int, label_name: string, avg_rating: float, count: int}> $topRated */
?>

<div class="admin-page-header">
    <h1>Dashboard Marketing</h1>
</div>

<!-- ================================================================
     Panier moyen (30 derniers jours)
================================================================ -->
<section class="admin-section" style="margin-bottom:2rem;">
    <h2 class="admin-section__title" style="margin-bottom:1rem;">Panier moyen — 30 derniers jours</h2>
    <div class="admin-card" style="display:inline-block;padding:1.5rem 2.5rem;text-align:center;">
        <p style="font-size:2rem;font-weight:700;color:var(--color-gold);margin:0;">
            <?= number_format($avgOrder, 2, ',', ' ') ?>&nbsp;€
        </p>
        <p style="color:var(--color-text-muted);font-size:0.875rem;margin:0.25rem 0 0;">
            commandes paid / processing / shipped / delivered
        </p>
    </div>
</section>

<!-- ================================================================
     Taux de conversion (30 derniers jours)
================================================================ -->
<section class="admin-section" style="margin-bottom:2rem;">
    <h2 class="admin-section__title" style="margin-bottom:1rem;">Taux de conversion — 30 derniers jours</h2>
    <div class="admin-card" style="display:inline-block;padding:1.5rem 2.5rem;text-align:center;">
        <p style="font-size:2rem;font-weight:700;color:var(--color-gold);margin:0;">
            <?= round($conversionRate * 100, 1) ?>%
        </p>
        <p style="color:var(--color-text-muted);font-size:0.875rem;margin:0.25rem 0 0;">
            commandes payées / paniers actifs
        </p>
    </div>
</section>

<!-- ================================================================
     Segmentation clients
================================================================ -->
<section class="admin-section" style="margin-bottom:2rem;">
    <h2 class="admin-section__title" style="margin-bottom:1rem;">Segmentation clients</h2>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;">

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;color:var(--color-gold);margin:0;">
                <?= $segmentation['total'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Total comptes actifs</p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $segmentation['particulier'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Particuliers</p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $segmentation['pro'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Professionnels</p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $segmentation['fr'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Interface FR</p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $segmentation['en'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Interface EN</p>
        </div>

    </div>
</section>

<!-- ================================================================
     Newsletter
================================================================ -->
<section class="admin-section" style="margin-bottom:2rem;">
    <h2 class="admin-section__title" style="margin-bottom:1rem;">Newsletter</h2>

    <?php
    $nlTotal  = $newsletterStats['total'];
    $nlActive = $newsletterStats['active'];
    $nlPct    = $nlTotal > 0 ? round($nlActive / $nlTotal * 100, 1) : 0.0;
    ?>

    <div style="display:flex;flex-wrap:wrap;gap:1rem;">

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;color:var(--color-gold);margin:0;">
                <?= $nlActive ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">
                Abonnés actifs (<?= $nlPct ?>%)
            </p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $newsletterStats['unsubscribed'] ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Désabonnés</p>
        </div>

        <div class="admin-card" style="flex:1;min-width:160px;padding:1.25rem;text-align:center;">
            <p style="font-size:1.75rem;font-weight:700;margin:0;">
                <?= $nlTotal ?>
            </p>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0.2rem 0 0;">Total comptes</p>
        </div>

    </div>
</section>

<!-- ================================================================
     Top vins notés
================================================================ -->
<section class="admin-section" style="margin-bottom:2rem;">
    <h2 class="admin-section__title" style="margin-bottom:1rem;">Top vins notés</h2>

    <?php if (empty($topRated)) : ?>
        <p style="color:var(--color-text-muted);">Aucune donnée de notation disponible.</p>
    <?php else : ?>
        <div class="admin-card" style="padding:0;overflow:hidden;">
            <table class="admin-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:0.75rem 1rem;">#</th>
                        <th style="text-align:left;padding:0.75rem 1rem;">Vin</th>
                        <th style="text-align:center;padding:0.75rem 1rem;">Note moyenne</th>
                        <th style="text-align:center;padding:0.75rem 1rem;">Avis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topRated as $i => $wine) : ?>
                        <tr>
                            <td style="padding:0.75rem 1rem;color:var(--color-text-muted);">
                                <?= $i + 1 ?>
                            </td>
                            <td style="padding:0.75rem 1rem;font-weight:500;">
                                <?= htmlspecialchars((string) ($wine['label_name'] ?? '')) ?>
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:center;">
                                <?php
                                $avg    = (float) ($wine['avg_rating'] ?? 0);
                                $full   = (int) floor($avg);
                                $half   = ($avg - $full) >= 0.5;
                                $empty  = 5 - $full - ($half ? 1 : 0);
                                ?>
                                <span style="color:var(--color-gold);letter-spacing:2px;">
                                    <?= str_repeat('★', $full) ?>
                                    <?= $half ? '½' : '' ?>
                                    <?= str_repeat('☆', $empty > 0 ? $empty : 0) ?>
                                </span>
                                <span style="font-size:0.8rem;color:var(--color-text-muted);margin-left:0.25rem;">
                                    <?= number_format($avg, 1, ',', '') ?>/5
                                </span>
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:center;color:var(--color-text-muted);">
                                <?= (int) ($wine['count'] ?? 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
