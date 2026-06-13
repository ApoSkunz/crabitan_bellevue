<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var float                                                              $avgOrder */
/** @var float                                                              $conversionRate */
/** @var int                                                                $conversionOrders */
/** @var int                                                                $conversionCarts */
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
<section class="admin-section u-mb-8">
    <h2 class="admin-section__title u-mb-4">Panier moyen — 30 derniers jours</h2>
    <div class="admin-card admin-marketing-kpi-card text-center">
        <p class="u-text-2xl u-font-bold u-text-gold u-m-0">
            <?= number_format($avgOrder, 2, ',', ' ') ?>&nbsp;€
        </p>
        <p class="text-muted u-text-85 u-mt-1 u-m-0">
            commandes paid / processing / shipped / delivered
        </p>
    </div>
</section>

<!-- ================================================================
     Taux de conversion (30 derniers jours)
================================================================ -->
<section class="admin-section u-mb-8">
    <h2 class="admin-section__title u-mb-4">Taux de conversion — 30 derniers jours</h2>
    <div class="u-flex u-flex-wrap u-gap-4 u-items-stretch">

        <div class="admin-card admin-marketing-kpi-card text-center">
            <p class="u-text-2xl u-font-bold u-text-gold u-m-0">
                <?= round($conversionRate * 100, 1) ?>%
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">
                commandes / (commandes + paniers abandonnés)
            </p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-text-gold u-m-0">
                <?= $conversionOrders ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Commandes payées</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $conversionCarts ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Paniers abandonnés</p>
        </div>

    </div>
</section>

<!-- ================================================================
     Segmentation clients
================================================================ -->
<section class="admin-section u-mb-8">
    <h2 class="admin-section__title u-mb-4">Segmentation clients</h2>
    <div class="u-flex u-flex-wrap u-gap-4">

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-text-gold u-m-0">
                <?= $segmentation['total'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Total comptes actifs</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $segmentation['particulier'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Particuliers</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $segmentation['pro'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Professionnels</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $segmentation['fr'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Interface FR</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $segmentation['en'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Interface EN</p>
        </div>

    </div>
</section>

<!-- ================================================================
     Newsletter
================================================================ -->
<section class="admin-section u-mb-8">
    <h2 class="admin-section__title u-mb-4">Newsletter</h2>

    <?php
    $nlTotal  = $newsletterStats['total'];
    $nlActive = $newsletterStats['active'];
    $nlPct    = $nlTotal > 0 ? round($nlActive / $nlTotal * 100, 1) : 0.0;
    ?>

    <div class="u-flex u-flex-wrap u-gap-4">

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-text-gold u-m-0">
                <?= $nlActive ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">
                Abonnés actifs (<?= $nlPct ?>%)
            </p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $newsletterStats['unsubscribed'] ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Désabonnés</p>
        </div>

        <div class="admin-card admin-marketing-stat-card text-center">
            <p class="u-text-xl u-font-bold u-m-0">
                <?= $nlTotal ?>
            </p>
            <p class="text-muted u-text-sm u-mt-1 u-m-0">Total comptes</p>
        </div>

    </div>
</section>

<!-- ================================================================
     Top vins notés
================================================================ -->
<section class="admin-section u-mb-8">
    <h2 class="admin-section__title u-mb-4">Top vins notés</h2>

    <?php if (empty($topRated)) : ?>
        <p class="text-muted">Aucune donnée de notation disponible.</p>
    <?php else : ?>
        <div class="admin-card u-p-0 u-overflow-hidden">
            <table class="admin-table u-w-full u-border-collapse">
                <thead>
                    <tr>
                        <th class="u-text-left admin-table-pad">#</th>
                        <th class="u-text-left admin-table-pad">Vin</th>
                        <th class="text-center admin-table-pad">Note moyenne</th>
                        <th class="text-center admin-table-pad">Avis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topRated as $i => $wine) : ?>
                        <tr>
                            <td class="admin-table-pad text-muted">
                                <?= $i + 1 ?>
                            </td>
                            <td class="admin-table-pad u-font-medium">
                                <?= htmlspecialchars((string) ($wine['label_name'] ?? '')) ?>
                            </td>
                            <td class="admin-table-pad text-center">
                                <?php
                                $avg    = (float) ($wine['avg_rating'] ?? 0);
                                $full   = (int) floor($avg);
                                $half   = ($avg - $full) >= 0.5;
                                $empty  = 5 - $full - ($half ? 1 : 0);
                                ?>
                                <span class="u-text-gold u-tracking-wide">
                                    <?= str_repeat('★', $full) ?>
                                    <?= $half ? '½' : '' ?>
                                    <?= str_repeat('☆', $empty > 0 ? $empty : 0) ?>
                                </span>
                                <span class="u-text-sm text-muted u-ml-1">
                                    <?= number_format($avg, 1, ',', '') ?>/5
                                </span>
                            </td>
                            <td class="admin-table-pad text-center text-muted">
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
