<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array{ca: float, count: int, avg: float} $stats */
/** @var array<int, array{label: string, ca: float, count: int}> $chartData */
/** @var array<int, int> $availableYears */

$chartLabels = array_column($chartData, 'label');
$chartCa     = array_column($chartData, 'ca');
$chartCount  = array_column($chartData, 'count');

// Formatage labels selon granularité
$chartLabelsFormatted = array_map(function (string $label) use ($granularity): string {
    return match ($granularity) {
        'daily'  => (new DateTime($label))->format('d/m'),
        'monthly' => (function () use ($label): string {
            [$y, $m] = explode('-', $label);
            $months = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            return ($months[(int) $m] ?? $m) . ' ' . $y;
        })(),
        default  => $label,
    };
}, $chartLabels);
?>

<div class="admin-page-header">
    <h1>Statistiques CA</h1>
    <span class="u-text-95 text-muted"><?= htmlspecialchars($periodLabel) ?></span>
</div>

<!-- ================================================================
     Filtres période
================================================================ -->
<div class="u-flex u-flex-wrap u-gap-2 u-mb-8">
    <?php
    $filters = [
        '30d' => '30 jours',
        '3m'  => '3 mois',
        '1y'  => '12 mois',
    ];
    foreach ($filters as $key => $label) :
        $active = $period === $key;
    ?>
        <a href="/admin/statistiques?period=<?= $key ?>"
           class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>

    <?php foreach ($availableYears as $yr) :
        $active = $period === (string) $yr;
    ?>
        <a href="/admin/statistiques?period=<?= $yr ?>"
           class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
            <?= $yr ?>
        </a>
    <?php endforeach; ?>

    <?php $active = $period === 'all'; ?>
    <a href="/admin/statistiques?period=all"
       class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
        Depuis toujours
    </a>
</div>

<!-- ================================================================
     KPI cards
================================================================ -->
<div class="admin-stats-kpi-grid">

    <div class="admin-card">
        <div class="admin-card__body text-center">
            <div class="admin-kpi-label">
                Chiffre d'affaires
            </div>
            <div class="admin-kpi-value">
                <?= number_format($stats['ca'], 2, ',', '&nbsp;') ?>&nbsp;€ <?php // NOSONAR php:S1192 — '&nbsp;' est une entité HTML, pas une constante métier ?>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card__body text-center">
            <div class="admin-kpi-label">
                Commandes
            </div>
            <div class="admin-kpi-value">
                <?= number_format($stats['count'], 0, ',', '&nbsp;') ?>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card__body text-center">
            <div class="admin-kpi-label">
                Panier moyen
            </div>
            <div class="admin-kpi-value">
                <?= $stats['count'] > 0 ? number_format($stats['avg'], 2, ',', '&nbsp;') . '&nbsp;€' : '—' ?>
            </div>
        </div>
    </div>

</div>

<!-- ================================================================
     Graphique
================================================================ -->
<div class="admin-card">
    <div class="admin-card__body">
        <div class="u-flex u-items-center u-justify-between u-mb-5 u-flex-wrap u-gap-3">
            <h2 class="admin-chart-title">
                Évolution du CA — <?= htmlspecialchars($periodLabel) ?>
            </h2>
            <div class="u-flex u-gap-2">
                <button type="button" id="js-chart-bar"
                        class="admin-btn admin-btn--sm admin-btn--outline admin-chart-toggle-btn">
                    Barres
                </button>
                <button type="button" id="js-chart-line"
                        class="admin-btn admin-btn--sm admin-btn--primary admin-chart-toggle-btn">
                    Courbe
                </button>
            </div>
        </div>

        <?php if ($chartData === []) : ?>
            <p class="text-center text-muted admin-chart-empty">Aucune donnée pour cette période</p>
        <?php else : ?>
            <div class="admin-chart-wrap">
                <canvas id="ca-chart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($chartData !== []) : ?>
<script type="application/json" id="chart-data"><?= json_encode([
    'labels'  => $chartLabelsFormatted,
    'caData'  => array_map(fn(float $v) => round($v, 2), $chartCa),
    'cntData' => $chartCount,
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="/assets/js/admin-charts.js" type="module"></script>
<?php endif; ?>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
