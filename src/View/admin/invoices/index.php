<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Factures</h1>
    <p class="admin-page-header__sub">Registre légal — Art. L441-9 &amp; L123-22 C.com.</p>
</div>

<!-- ================================================================
     Filtres période + bouton export
================================================================ -->
<?php
$months = [
    1 => 'Janvier',  2 => 'Février',  3 => 'Mars',
    4 => 'Avril',    5 => 'Mai',       6 => 'Juin',
    7 => 'Juillet',  8 => 'Août',      9 => 'Septembre',
    10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
$quarters = [1 => 'T1 (jan-mar)', 2 => 'T2 (avr-jun)', 3 => 'T3 (jul-sep)', 4 => 'T4 (oct-dec)'];

if (!function_exists('buildInvoiceUrl')) {
    function buildInvoiceUrl(int $year, ?int $month, ?int $quarter, int $page = 1, int $perPage = 25): string
    {
        $params = array_filter([
            'year'     => $year,
            'month'    => $month,
            'quarter'  => $quarter,
            'page'     => $page > 1 ? $page : null,
            'per_page' => $perPage !== 25 ? $perPage : null,
        ], fn ($v) => $v !== null);
        $q = http_build_query($params);
        return '/admin/factures' . ($q !== '' ? '?' . $q : '');
    }
}
?>

<div class="admin-card u-mb-6">
    <div class="admin-card__body">
        <form method="GET" action="/admin/factures" class="admin-filters" data-auto-submit>
            <div>
                <div class="admin-filters-sublabel">Année</div>
                <select name="year" class="admin-filters__select" aria-label="Année">
                    <?php foreach ($availableYears as $yr) : ?>
                        <option value="<?= (int) $yr ?>" <?= (int) $yr === $year ? 'selected' : '' ?>>
                            <?= (int) $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div class="admin-filters-sublabel">Mois</div>
                <select name="month" class="admin-filters__select" aria-label="Mois">
                    <option value="">— Tous —</option>
                    <?php foreach ($months as $num => $label) : ?>
                        <option value="<?= $num ?>" <?= $month === $num ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div class="admin-filters-sublabel">Trimestre</div>
                <select name="quarter" class="admin-filters__select" aria-label="Trimestre">
                    <option value="">— Tous —</option>
                    <?php foreach ($quarters as $num => $label) : ?>
                        <option value="<?= $num ?>" <?= $quarter === $num ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div class="admin-filters-sublabel">Recherche</div>
                <input type="text" name="search" class="admin-filters__input"
                       aria-label="Rechercher une facture"
                       placeholder="N° facture, réf. commande…"
                       value="<?= htmlspecialchars($search ?? '') ?>">
            </div>
            <div class="u-items-end">
                <noscript><button type="submit" class="admin-filters__btn">Filtrer</button></noscript>
                <a href="/admin/factures" class="admin-btn admin-btn--dark admin-btn--sm">Réinitialiser</a>
            </div>
        </form>

        <?php
        $exportParams = array_filter([
            'year'    => $year,
            'month'   => $month,
            'quarter' => $quarter,
        ], fn ($v) => $v !== null);
        $exportUrl = '/admin/factures/export?' . http_build_query($exportParams);
        ?>
        <div class="admin-export-bar">
            <a href="<?= htmlspecialchars($exportUrl) ?>"
               class="admin-btn admin-btn--primary"
               title="Télécharger un ZIP contenant les PDFs + un fichier CSV pour la période sélectionnée">
                ↓ Exporter ZIP (PDFs + CSV)
            </a>
            <span class="u-text-2xs text-muted u-ml-3">
                Période : <?= (int) $year ?>
                <?php if ($month !== null) : ?>
                    — <?= htmlspecialchars($months[$month] ?? '') ?>
                <?php elseif ($quarter !== null) : ?>
                    — <?= htmlspecialchars($quarters[$quarter] ?? '') ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- ================================================================
     Registre des fichiers de facture (1 ligne = 1 PDF)
================================================================ -->
<div class="admin-card">
    <div class="admin-card__header">
        <h2>
            <?= number_format($total, 0, ',', ' ') ?> fichier<?= $total > 1 ? 's' : '' ?>
        </h2>
    </div>
    <div class="admin-card__body admin-card__body--flush">
        <?php if (empty($invoices)) : ?>
            <p class="admin-empty-message text-center text-muted">
                Aucune facture pour cette période.
            </p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Réf. commande</th>
                        <th>Date commande</th>
                        <th class="u-text-right">Montant TTC</th>
                        <th>Client</th>
                        <th>Source</th>
                        <th>Générée le</th>
                        <th class="text-center" title="Facture en vigueur pour cette commande">Valide</th>
                        <th class="text-center">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv) : ?>
                        <tr>
                            <td data-label="N° Facture">
                                <?php if ($inv['invoice_number']) : ?>
                                    <code class="u-text-2xs"><?= htmlspecialchars((string) $inv['invoice_number']) ?></code>
                                <?php else : ?>
                                    <span class="u-text-sm text-muted"><?= htmlspecialchars(basename((string) $inv['path'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Réf. commande">
                                <a href="/admin/commandes/<?= (int) $inv['order_id'] ?>"
                                   class="u-text-sm">
                                    <?= htmlspecialchars((string) $inv['order_reference']) ?>
                                </a>
                            </td>
                            <td data-label="Date" class="u-text-sm u-nowrap">
                                <?= date('d/m/Y', strtotime((string) $inv['ordered_at'])) ?>
                            </td>
                            <td data-label="Montant" class="u-text-85 u-font-semibold">
                                <?= number_format((float) $inv['price'], 2, ',', '&nbsp;') ?>&nbsp;&euro;
                            </td>
                            <td data-label="Client" class="u-text-sm">
                                <?= htmlspecialchars(trim($inv['firstname'] . ' ' . $inv['lastname'])) ?><br>
                                <span class="u-text-xxs text-muted"><?= htmlspecialchars((string) $inv['email']) ?></span>
                            </td>
                            <td data-label="Source" class="u-text-sm" title="<?= $inv['source'] === 'auto' ? 'Générée automatiquement' : 'Téléversée manuellement' ?>">
                                <?= $inv['source'] === 'auto' ? '⚙ Auto' : '⬆ Manuel' ?>
                            </td>
                            <td data-label="Générée le" class="u-text-sm u-nowrap text-muted">
                                <?= date('d/m/Y H:i', strtotime((string) $inv['uploaded_at'])) ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int) $inv['invoice_rank'] === 1) : ?>
                                    <span class="u-text-success" title="Facture en vigueur">✓</span>
                                <?php else : ?>
                                    <span class="u-text-sm text-muted" title="Version remplacée">Historique</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="/admin/commandes/<?= (int) $inv['order_id'] ?>/facture/historique/<?= (int) $inv['id'] ?>"
                                   class="admin-btn admin-btn--outline admin-btn--sm"
                                   target="_blank" rel="noopener"
                                   title="Ouvrir le PDF dans un nouvel onglet">
                                    Voir PDF
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $pages = max(1, (int) ceil($total / $perPage));
            if ($pages > 1) : // NOSONAR — php:S3973 : syntaxe alternative PHP template intentionnelle
            ?>
            <div class="admin-pagination">
                <?php if ($page > 1) : ?>
                    <a href="<?= htmlspecialchars(buildInvoiceUrl($year, $month, $quarter, $page - 1, $perPage)) ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">&laquo; Précédent</a>
                <?php endif; ?>
                <span class="admin-pagination__info">
                    Page <?= $page ?> / <?= $pages ?>
                </span>
                <?php if ($page < $pages) : ?>
                    <a href="<?= htmlspecialchars(buildInvoiceUrl($year, $month, $quarter, $page + 1, $perPage)) ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
