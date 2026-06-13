<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages  = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$cssDisabled = ' disabled';
?>

<?php if ($flash ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Newsletter <small class="u-text-2xs admin-subcount-alt">(<?= $total ?> abonné<?= $total > 1 ? 's' : '' ?>)</small></h1>
</div>

<!-- ---- Formulaire envoi ---- -->
<div class="admin-card u-mb-6">
    <div class="admin-card__body">
        <h2 class="admin-card-section-title u-mb-4">
            Envoyer une newsletter
        </h2>
        <?php if ($total === 0) : ?>
            <p class="u-text-85 u-text-soft">Aucun abonné — aucun envoi possible.</p>
        <?php else : ?>
            <form id="nl-form" method="POST" action="/admin/newsletter/envoyer"
                  class="admin-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-subject">Objet *</label>
                    <div class="u-flex u-items-center admin-subject-wrap">
                        <span class="admin-subject-prefix">
                            Château Crabitan Bellevue —
                        </span>
                        <input type="text" id="nl-subject" name="subject"
                               class="admin-field__input admin-subject-input"
                               placeholder="Nos nouveaux millésimes vous attendent…">
                    </div>
                    <span id="nl-subject-error" class="admin-field__error u-hidden">Ce champ est obligatoire.</span>
                    <p class="u-text-xxs u-text-soft u-mt-1">
                        Le préfixe château est ajouté automatiquement. Ne pas inclure "Newsletter" dans l'objet (impact délivrabilité).
                    </p>
                </div>
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-title">
                        Titre de la newsletter
                        <span class="u-font-normal u-text-xxs">(optionnel — affiché en grand titre dans l'email, avant le corps)</span>
                    </label>
                    <input type="text" id="nl-title" name="nl_title"
                           class="admin-field__input"
                           placeholder="Ex : Découvrez nos nouveaux millésimes 2024…">
                </div>
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-image">
                        Image — jpg / png / webp
                        <span class="u-font-normal u-text-xxs">(optionnel — affichée en bas du corps, avant le désabonnement)</span>
                    </label>
                    <input type="file" id="nl-image" name="nl_image"
                           accept="image/jpeg,image/png,image/webp"
                           class="admin-field__input">
                    <img id="nl-image-preview" src="" alt=""
                         class="u-hidden admin-nl-image-preview">
                </div>
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-pdf">
                        Pièce jointe PDF
                        <span class="u-font-normal u-text-xxs">(optionnel — max 10 Mo)</span>
                    </label>
                    <input type="file" id="nl-pdf" name="nl_pdf"
                           accept="application/pdf"
                           class="admin-field__input">
                    <p id="nl-pdf-name" class="u-hidden admin-nl-pdf-name"></p>
                </div>
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-body">Contenu *</label>
                    <textarea id="nl-body" name="body"
                              class="admin-field__textarea" rows="8"
                              placeholder="Rédigez votre newsletter…"></textarea>
                    <span id="nl-body-error" class="admin-field__error u-hidden">Ce champ est obligatoire.</span>
                    <p class="u-text-xxs u-text-soft u-mt-1">
                        Le texte sera envoyé en HTML avec mise en page Crabitan Bellevue.
                    </p>
                </div>
                <div class="admin-field u-mb-4">
                    <label class="admin-field__label" for="nl-segment">Segment</label>
                    <select id="nl-segment" name="segment" class="admin-field__input">
                        <option value="tous">Tous les abonnés</option>
                        <option value="particuliers">Particuliers</option>
                        <option value="professionnels">Professionnels</option>
                        <option value="commandes">Clients ayant commandé</option>
                        <option value="sans-commande">Abonnés sans commande</option>
                        <option value="inactifs">Inactifs 6 mois</option>
                        <option value="non-inscrit">Sans compte</option>
                    </select>
                    <p id="nl-segment-count"
                       class="admin-nl-segment-count">
                        <?= $total ?> abonné<?= $total > 1 ? 's' : '' ?> dans ce segment
                    </p>
                </div>
                <div class="admin-form__actions">
                    <button type="button" class="admin-btn admin-btn--primary" id="nl-open-modal-btn">
                        Envoyer
                    </button>
                </div>
            </form>

            <!-- Modal de confirmation -->
            <div id="nl-modal" role="dialog" aria-modal="true" aria-labelledby="nl-modal-title"
                 class="u-hidden admin-nl-modal">
                <div class="admin-nl-modal__box">
                    <h2 id="nl-modal-title"
                        class="admin-nl-modal__title">
                        Confirmer l'envoi
                    </h2>
                    <p class="u-text-9 u-text-gray u-mb-2">
                        Objet : <strong class="u-text-soft">Château Crabitan Bellevue —&nbsp;</strong><strong id="nl-modal-subject" class="u-text-primary">—</strong>
                    </p>
                    <p class="u-text-9 u-text-gray u-mb-2">
                        Segment : <strong id="nl-modal-segment" class="u-text-soft">Tous les abonnés</strong>
                    </p>
                    <p class="u-text-9 u-text-gray u-mb-2">
                        Cette newsletter sera envoyée à
                        <strong id="nl-modal-count" class="u-text-gold"><?= $total ?> abonné<?= $total > 1 ? 's' : '' ?></strong>.
                        Cette action est irréversible.
                    </p>
                    <p id="nl-modal-pdf" class="u-hidden u-text-85 u-text-gray u-mb-4">
                        Pièce jointe : <strong id="nl-modal-pdf-name" class="u-text-primary"></strong>
                    </p>
                    <p class="u-mb-6"></p>
                    <div class="u-flex u-gap-4 u-justify-end">
                        <button type="button" class="admin-btn admin-btn--outline" id="nl-close-modal-btn">Annuler</button>
                        <button type="button" class="admin-btn admin-btn--primary" id="nl-confirm-send-btn">
                            Confirmer l'envoi
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ---- Historique des campagnes ---- -->
<div class="admin-card u-mb-6">
    <div class="admin-card__body u-pb-0">
        <div class="u-flex u-items-center u-justify-between u-flex-wrap u-gap-2 u-mb-4">
            <h2 class="admin-card-section-title u-m-0">
                Historique des envois
                <?php if (($historyTotal ?? 0) > 0) : ?>
                    <small class="admin-subcount-alt u-text-2xs">
                        (<?= (int) $historyTotal ?> campagne<?= $historyTotal > 1 ? 's' : '' ?>)
                    </small>
                <?php endif; ?>
            </h2>
            <form method="GET" class="u-flex u-items-center u-gap-2 u-text-sm">
                <label for="hperpage" class="u-text-soft">Par page :</label>
                <select id="hperpage" name="hperpage" data-auto-submit="1"
                        class="admin-hperpage-select">
                    <?php foreach ([10, 25, 50] as $opt) : ?>
                        <option value="<?= $opt ?>" <?= ($historyPerPage ?? 10) === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Objet</th>
                    <th>Segment</th>
                    <th>Envoyés</th>
                    <th>Ouvertures</th>
                    <th>Échecs</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($history)) : ?>
                <tr><td colspan="9" class="text-center u-text-soft u-p-8">Aucune campagne envoyée</td></tr>
            <?php else : ?>
                <?php foreach ($history as $campaign) : ?>
                    <tr>
                        <td class="u-text-soft"><?= (int) $campaign['id'] ?></td>
                        <td><strong><?= htmlspecialchars($campaign['subject']) ?></strong></td>
                        <td class="u-text-78 u-text-soft">
                            <?= $campaign['segment_label'] !== null
                                ? htmlspecialchars((string) $campaign['segment_label'])
                                : '—' ?>
                        </td>
                        <td class="u-text-green"><?= (int) $campaign['sent_count'] ?></td>
                        <?php
                        $sent  = (int) $campaign['sent_count'];
                        $opens = (int) ($campaign['opens_count'] ?? 0);
                        $rate  = $sent > 0 ? round($opens / $sent * 100, 1) : 0.0;
                        ?>
                        <td class="u-text-sm-plus">
                            <?= $opens ?> / <?= $sent ?>
                            &nbsp;<span class="u-font-semibold u-text-brown-50"><?= $rate ?>%</span>
                        </td>
                        <td class="<?= (int) $campaign['failed_count'] > 0 ? 'u-text-error-dk' : 'u-text-soft' ?>">
                            <?= (int) $campaign['failed_count'] ?>
                        </td>
                        <?php
                        $queued    = (int) ($campaign['queued_count'] ?? 0);
                        $processed = (int) $campaign['sent_count'] + (int) $campaign['failed_count'];
                        if ($queued === 0) {
                            echo '<td class="u-text-78 u-text-soft">—</td>';
                        } elseif ($processed >= $queued) {
                            echo '<td class="u-text-78">'
                               . '<span class="u-text-green u-font-semibold">✓ Terminé</span>'
                               . '<span class="u-text-xxs u-text-soft">&nbsp;(' . $queued . ')</span>'
                               . '</td>';
                        } else {
                            echo '<td class="u-text-78">'
                               . '<span class="u-text-amber-dk u-font-semibold">⏳ En cours</span>'
                               . '<span class="u-text-xxs u-text-soft">&nbsp;' . $processed . '/' . $queued . '</span>'
                               . '</td>';
                        }
                        ?>
                        <td class="u-nowrap u-text-sm">
                            <?= date('d/m/Y H:i', strtotime($campaign['sent_at'])) ?>
                        </td>
                        <td>
                            <a href="/admin/newsletter/<?= (int) $campaign['id'] ?>"
                               class="admin-btn admin-btn--sm admin-btn--outline">Voir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $safeHistoryPages  = $historyPages > 0 ? $historyPages : 1;
    $historyTotalPages = ($historyTotal ?? 0) > 0
        ? (int) ceil(($historyTotal ?? 0) / $safeHistoryPages)
        : 1;
    // réutiliser $historyPages passé par le contrôleur
    $hpp = ($historyPerPage ?? 10) !== 10 ? '&hperpage=' . (int) ($historyPerPage ?? 10) : '';
    if (($historyPages ?? 1) > 1) : ?>
        <?php
        $disabledPrev = $historyPage <= 1 ? $cssDisabled : '';
        $disabledNext = $historyPage >= $historyPages ? $cssDisabled : '';
        ?>
        <div class="admin-pagination">
            <a href="?hpage=<?= max(1, $historyPage - 1) ?><?= $hpp ?>"
               class="admin-pagination__item<?= $disabledPrev ?>">‹</a>
            <?php for ($i = max(1, $historyPage - 2); $i <= min($historyPages, $historyPage + 2); $i++) : ?>
                <a href="?hpage=<?= $i ?><?= $hpp ?>"
                   class="admin-pagination__item<?= $i === $historyPage ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?hpage=<?= min($historyPages, $historyPage + 1) ?><?= $hpp ?>"
               class="admin-pagination__item<?= $disabledNext ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<!-- ---- Liste des abonnés ---- -->
<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom / Société</th>
                    <th>E-mail</th>
                    <th>Type</th>
                    <th>Langue</th>
                    <th>Inscription</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($subscribers)) : ?>
                <tr><td colspan="6" class="text-center u-text-soft u-p-8">Aucun abonné</td></tr>
            <?php else : ?>
                <?php foreach ($subscribers as $sub) : ?>
                    <?php
                    if ($sub['account_type'] === 'company') {
                        $name = htmlspecialchars($sub['company_name'] ?? '—');
                    } else {
                        $parts = array_filter([$sub['firstname'] ?? '', $sub['lastname'] ?? '']);
                        $name  = $parts ? htmlspecialchars(implode(' ', $parts)) : '—';
                    }
                    ?>
                    <tr>
                        <td class="u-text-soft">
                            <?= $sub['id'] !== null ? (int) $sub['id'] : '<span title="Abonné sans compte">—</span>' ?>
                        </td>
                        <td><strong><?= $name ?></strong></td>
                        <td><?= htmlspecialchars($sub['email']) ?></td>
                        <td class="u-text-78">
                            <?php
                            $source = $sub['source'] ?? 'account';
                            if ($source === 'subscription') {
                                echo '<span class="u-text-soft" title="Abonné sans compte">visiteur</span>';
                            } elseif ($sub['account_type'] === 'company') {
                                echo 'Société';
                            } else {
                                echo 'Particulier';
                            }
                            ?>
                        </td>
                        <td class="u-text-78 u-uppercase"><?= htmlspecialchars($sub['lang'] ?? '—') ?></td>
                        <td class="u-nowrap u-text-sm">
                            <?= $sub['created_at'] ? date('d/m/Y', strtotime($sub['created_at'])) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="?page=<?= max(1, $page - 1) ?>"
               class="admin-pagination__item<?= $page <= 1 ? $cssDisabled : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="?page=<?= $i ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages, $page + 1) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? $cssDisabled : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
const NL_SEGMENT_LABELS = {
    'tous':          'Tous les abonnés',
    'particuliers':  'Particuliers',
    'professionnels':'Professionnels',
    'commandes':     'Clients ayant commandé',
    'sans-commande': 'Abonnés sans commande',
    'inactifs':      'Inactifs 6 mois',
    'non-inscrit':   'Sans compte',
};

function onSegmentChange(select) {
    const segment   = select.value;
    const countEl   = document.getElementById('nl-segment-count');
    const url       = '/admin/newsletter/segment-count?segment=' + encodeURIComponent(segment);
    countEl.textContent = '…';
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            const n = data.count ?? 0;
            countEl.textContent = n + ' abonné' + (n > 1 ? 's' : '') + ' dans ce segment';
        })
        .catch(() => { countEl.textContent = '—'; });
}

function previewNlImage(input) {
    const preview = document.getElementById('nl-image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.classList.remove('u-hidden'); };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateNlPdfName(input) {
    const el = document.getElementById('nl-pdf-name');
    if (input.files && input.files[0]) {
        el.textContent = input.files[0].name;
        el.classList.remove('u-hidden');
    } else {
        el.classList.add('u-hidden');
    }
}

function openNlModal() {
    const subjectInput = document.getElementById('nl-subject');
    const bodyInput    = document.getElementById('nl-body');
    const subject      = subjectInput.value.trim();
    const body         = bodyInput.value.trim();
    let hasError = false;

    const subjectErr = document.getElementById('nl-subject-error');
    const bodyErr    = document.getElementById('nl-body-error');

    const subjectWrap = subjectInput.parentElement;
    if (!subject) {
        if (subjectWrap) { subjectWrap.style.borderColor = '#dc2626'; }
        subjectErr.classList.remove('u-hidden');
        hasError = true;
    } else {
        if (subjectWrap) { subjectWrap.style.borderColor = ''; }
        subjectErr.classList.add('u-hidden');
    }
    if (!body) {
        bodyInput.classList.add('is-error');
        bodyErr.classList.remove('u-hidden');
        hasError = true;
    } else {
        bodyInput.classList.remove('is-error');
        bodyErr.classList.add('u-hidden');
    }
    if (hasError) { return; }

    document.getElementById('nl-modal-subject').textContent = subject || '—';
    const segmentSelect = document.getElementById('nl-segment');
    const segmentVal    = segmentSelect ? segmentSelect.value : 'tous';
    const segmentCountEl = document.getElementById('nl-segment-count');
    document.getElementById('nl-modal-segment').textContent = NL_SEGMENT_LABELS[segmentVal] ?? segmentVal;
    document.getElementById('nl-modal-count').textContent   =
        (segmentCountEl ? segmentCountEl.textContent : '—');
    const pdfInput  = document.getElementById('nl-pdf');
    const modalPdf  = document.getElementById('nl-modal-pdf');
    const modalPdfName = document.getElementById('nl-modal-pdf-name');
    if (pdfInput.files && pdfInput.files[0]) {
        modalPdfName.textContent = pdfInput.files[0].name;
        modalPdf.classList.remove('u-hidden');
    } else {
        modalPdf.classList.add('u-hidden');
    }
    const modal = document.getElementById('nl-modal');
    modal.classList.remove('u-hidden');
    modal.style.display = 'flex';
    document.addEventListener('keydown', nlModalEsc);
}

function closeNlModal() {
    const modal = document.getElementById('nl-modal');
    modal.classList.add('u-hidden');
    modal.style.display = '';
    document.removeEventListener('keydown', nlModalEsc);
}

function nlModalEsc(e) {
    if (e.key === 'Escape') closeNlModal();
}

document.addEventListener('DOMContentLoaded', function () {
    var nlImage      = document.getElementById('nl-image');
    var nlPdf        = document.getElementById('nl-pdf');
    var nlSegment    = document.getElementById('nl-segment');
    var openBtn      = document.getElementById('nl-open-modal-btn');
    var closeBtn     = document.getElementById('nl-close-modal-btn');
    var confirmBtn   = document.getElementById('nl-confirm-send-btn');

    if (nlImage)   { nlImage.addEventListener('change',   function () { previewNlImage(this); }); }
    if (nlPdf)     { nlPdf.addEventListener('change',     function () { updateNlPdfName(this); }); }
    if (nlSegment) { nlSegment.addEventListener('change', function () { onSegmentChange(this); }); }
    if (openBtn)   { openBtn.addEventListener('click', openNlModal); }
    if (closeBtn)  { closeBtn.addEventListener('click', closeNlModal); }
    if (confirmBtn){ confirmBtn.addEventListener('click', function () { document.getElementById('nl-form').submit(); }); }
});
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
