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
$quarters = [1 => 'T1 (jan–mar)', 2 => 'T2 (avr–jun)', 3 => 'T3 (jul–sep)', 4 => 'T4 (oct–déc)'];

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

<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <form method="GET" action="/admin/factures" class="admin-filters">
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Année</div>
                <select name="year" class="admin-filters__select" aria-label="Année">
                    <?php foreach ($availableYears as $yr) : ?>
                        <option value="<?= (int) $yr ?>" <?= (int) $yr === $year ? 'selected' : '' ?>>
                            <?= (int) $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Mois</div>
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
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Trimestre</div>
                <select name="quarter" class="admin-filters__select" aria-label="Trimestre">
                    <option value="">— Tous —</option>
                    <?php foreach ($quarters as $num => $label) : ?>
                        <option value="<?= $num ?>" <?= $quarter === $num ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="admin-filters__btn">Filtrer</button>
            <a href="/admin/factures" style="padding:0.42rem 1rem;font-size:0.82rem;letter-spacing:0.06em;border:1px solid rgba(8,8,8,0.2);color:#1a1208;text-decoration:none;">Réinitialiser</a>
        </form>

        <?php
        $exportParams = array_filter([
            'year'    => $year,
            'month'   => $month,
            'quarter' => $quarter,
        ], fn ($v) => $v !== null);
        $exportUrl = '/admin/factures/export?' . http_build_query($exportParams);
        ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(8,8,8,0.06);">
            <a href="<?= htmlspecialchars($exportUrl) ?>"
               class="admin-btn admin-btn--primary"
               title="Télécharger un ZIP contenant les PDFs + un fichier CSV pour la période sélectionnée">
                ↓ Exporter ZIP (PDFs + CSV)
            </a>
            <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.75rem;">
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
    <div class="admin-card__body" style="padding:0;">
        <?php if (empty($invoices)) : ?>
            <p style="padding:1.5rem;color:var(--color-text-muted);text-align:center;">
                Aucune facture pour cette période.
            </p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Réf. commande</th>
                        <th>Date commande</th>
                        <th style="text-align:right;">Montant TTC</th>
                        <th>Client</th>
                        <th>Source</th>
                        <th>Générée le</th>
                        <th style="text-align:center;" title="Facture en vigueur pour cette commande">Valide</th>
                        <th style="text-align:center;">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv) : ?>
                        <tr>
                            <td data-label="N° Facture">
                                <?php if ($inv['invoice_number']) : ?>
                                    <code style="font-size:0.75rem;"><?= htmlspecialchars((string) $inv['invoice_number']) ?></code>
                                <?php else : ?>
                                    <span style="color:var(--color-text-muted);font-size:0.8rem;"><?= htmlspecialchars(basename((string) $inv['path'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Réf. commande">
                                <a href="/admin/commandes/<?= (int) $inv['order_id'] ?>"
                                   style="font-size:0.8rem;">
                                    <?= htmlspecialchars((string) $inv['order_reference']) ?>
                                </a>
                            </td>
                            <td data-label="Date" style="font-size:0.8rem;white-space:nowrap;">
                                <?= date('d/m/Y', strtotime((string) $inv['ordered_at'])) ?>
                            </td>
                            <td data-label="Montant" style="font-size:0.85rem;font-weight:600;">
                                <?= number_format((float) $inv['price'], 2, ',', '&nbsp;') ?>&nbsp;&euro;
                            </td>
                            <td data-label="Client" style="font-size:0.8rem;">
                                <?= htmlspecialchars(trim($inv['firstname'] . ' ' . $inv['lastname'])) ?><br>
                                <span style="color:var(--color-text-muted);font-size:0.72rem;"><?= htmlspecialchars((string) $inv['email']) ?></span>
                            </td>
                            <td data-label="Source" style="font-size:0.8rem;" title="<?= $inv['source'] === 'auto' ? 'Générée automatiquement' : 'Téléversée manuellement' ?>">
                                <?= $inv['source'] === 'auto' ? '⚙ Auto' : '⬆ Manuel' ?>
                            </td>
                            <td data-label="Générée le" style="font-size:0.78rem;white-space:nowrap;color:var(--color-text-muted);">
                                <?= date('d/m/Y H:i', strtotime((string) $inv['uploaded_at'])) ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ((int) $inv['invoice_rank'] === 1) : ?>
                                    <span style="color:#27ae60;font-size:1rem;" title="Facture en vigueur">✓</span>
                                <?php else : ?>
                                    <span style="color:var(--color-text-muted);font-size:0.8rem;" title="Version remplacée">Historique</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
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
