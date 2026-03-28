<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
?>

<?php if ($flash ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Newsletter <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?> abonné<?= $total > 1 ? 's' : '' ?>)</small></h1>
</div>

<!-- ---- Formulaire envoi ---- -->
<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <h2 style="font-size:0.85rem;letter-spacing:0.12em;text-transform:uppercase;color:#6b5f50;margin-bottom:1rem;">
            Envoyer une newsletter
        </h2>
        <?php if ($total === 0) : ?>
            <p style="font-size:0.85rem;color:#8a7a60;">Aucun abonné — aucun envoi possible.</p>
        <?php else : ?>
            <form id="nl-form" method="POST" action="/admin/newsletter/envoyer"
                  class="admin-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="admin-field" style="margin-bottom:1rem;">
                    <label class="admin-field__label" for="nl-subject">Objet *</label>
                    <input type="text" id="nl-subject" name="subject"
                           class="admin-field__input" placeholder="Ex : Nouveaux millésimes disponibles…">
                    <span id="nl-subject-error" class="admin-field__error" style="display:none;">Ce champ est obligatoire.</span>
                </div>
                <div class="admin-field" style="margin-bottom:1rem;">
                    <label class="admin-field__label" for="nl-image">
                        Image — jpg / png / webp
                        <span style="font-weight:400;font-size:0.72rem;">(optionnel — affichée en bas du corps, avant le désabonnement)</span>
                    </label>
                    <input type="file" id="nl-image" name="nl_image"
                           accept="image/jpeg,image/png,image/webp"
                           class="admin-field__input"
                           onchange="previewNlImage(this)">
                    <img id="nl-image-preview" src="" alt=""
                         style="display:none;max-height:120px;max-width:320px;object-fit:cover;
                                margin-top:0.5rem;border:1px solid rgba(0,0,0,0.1);border-radius:4px;">
                </div>
                <div class="admin-field" style="margin-bottom:1rem;">
                    <label class="admin-field__label" for="nl-pdf">
                        Pièce jointe PDF
                        <span style="font-weight:400;font-size:0.72rem;">(optionnel — max 10 Mo)</span>
                    </label>
                    <input type="file" id="nl-pdf" name="nl_pdf"
                           accept="application/pdf"
                           class="admin-field__input"
                           onchange="updateNlPdfName(this)">
                    <p id="nl-pdf-name" style="display:none;font-size:0.78rem;color:#3d3425;margin-top:0.25rem;"></p>
                </div>
                <div class="admin-field" style="margin-bottom:1rem;">
                    <label class="admin-field__label" for="nl-body">Contenu *</label>
                    <textarea id="nl-body" name="body"
                              class="admin-field__textarea" rows="8"
                              placeholder="Rédigez votre newsletter…"></textarea>
                    <span id="nl-body-error" class="admin-field__error" style="display:none;">Ce champ est obligatoire.</span>
                    <p style="font-size:0.72rem;color:#8a7a60;margin-top:0.25rem;">
                        Le texte sera envoyé en HTML avec mise en page Crabitan Bellevue.
                    </p>
                </div>
                <div class="admin-form__actions">
                    <button type="button" class="admin-btn admin-btn--primary"
                            onclick="openNlModal()">
                        Envoyer à <?= $total ?> abonné<?= $total > 1 ? 's' : '' ?>
                    </button>
                </div>
            </form>

            <!-- Modal de confirmation -->
            <div id="nl-modal" role="dialog" aria-modal="true" aria-labelledby="nl-modal-title"
                 style="display:none;position:fixed;inset:0;z-index:9999;
                        background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
                <div style="background:#fff;border-radius:6px;padding:2rem 2.5rem;max-width:440px;width:90%;
                             box-shadow:0 8px 32px rgba(0,0,0,0.18);">
                    <h2 id="nl-modal-title"
                        style="font-family:var(--font-serif);font-size:1.05rem;color:#1a1208;margin:0 0 0.75rem;">
                        Confirmer l'envoi
                    </h2>
                    <p style="font-size:0.9rem;color:#3d3425;margin:0 0 0.5rem;">
                        Objet : <strong id="nl-modal-subject" style="color:#1a1208;">—</strong>
                    </p>
                    <p style="font-size:0.9rem;color:#3d3425;margin:0 0 0.5rem;">
                        Cette newsletter sera envoyée à
                        <strong style="color:#c9a84c;"><?= $total ?> abonné<?= $total > 1 ? 's' : '' ?></strong>.
                        Cette action est irréversible.
                    </p>
                    <p id="nl-modal-pdf" style="display:none;font-size:0.85rem;color:#3d3425;margin:0 0 1rem;">
                        Pièce jointe : <strong id="nl-modal-pdf-name" style="color:#1a1208;"></strong>
                    </p>
                    <p style="margin:0 0 1.5rem;"></p>
                    <div style="display:flex;gap:1rem;justify-content:flex-end;">
                        <button type="button" class="admin-btn admin-btn--outline"
                                onclick="closeNlModal()">Annuler</button>
                        <button type="button" class="admin-btn admin-btn--primary"
                                onclick="document.getElementById('nl-form').submit()">
                            Confirmer l'envoi
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

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
                <tr><td colspan="6" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun abonné</td></tr>
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
                        <td style="color:#8a7a60;"><?= (int) $sub['id'] ?></td>
                        <td><strong><?= $name ?></strong></td>
                        <td><?= htmlspecialchars($sub['email']) ?></td>
                        <td style="font-size:0.78rem;">
                            <?= $sub['account_type'] === 'company' ? 'Société' : 'Particulier' ?>
                        </td>
                        <td style="font-size:0.78rem;text-transform:uppercase;"><?= htmlspecialchars($sub['lang'] ?? '—') ?></td>
                        <td style="white-space:nowrap;font-size:0.8rem;">
                            <?= date('d/m/Y', strtotime($sub['created_at'])) ?>
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
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="?page=<?= $i ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages, $page + 1) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<script>
function previewNlImage(input) {
    const preview = document.getElementById('nl-image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateNlPdfName(input) {
    const el = document.getElementById('nl-pdf-name');
    if (input.files && input.files[0]) {
        el.textContent = input.files[0].name;
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
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

    if (!subject) {
        subjectInput.classList.add('is-error');
        subjectErr.style.display = 'block';
        hasError = true;
    } else {
        subjectInput.classList.remove('is-error');
        subjectErr.style.display = 'none';
    }
    if (!body) {
        bodyInput.classList.add('is-error');
        bodyErr.style.display = 'block';
        hasError = true;
    } else {
        bodyInput.classList.remove('is-error');
        bodyErr.style.display = 'none';
    }
    if (hasError) {
        subjectInput.closest('.admin-form').querySelector('.admin-field__error[style*="block"]')
            ?.closest('.admin-field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    document.getElementById('nl-modal-subject').textContent = subject || '—';
    const pdfInput  = document.getElementById('nl-pdf');
    const modalPdf  = document.getElementById('nl-modal-pdf');
    const modalPdfName = document.getElementById('nl-modal-pdf-name');
    if (pdfInput.files && pdfInput.files[0]) {
        modalPdfName.textContent = pdfInput.files[0].name;
        modalPdf.style.display = 'block';
    } else {
        modalPdf.style.display = 'none';
    }
    const modal = document.getElementById('nl-modal');
    modal.style.display = 'flex';
    document.addEventListener('keydown', nlModalEsc);
}

function closeNlModal() {
    document.getElementById('nl-modal').style.display = 'none';
    document.removeEventListener('keydown', nlModalEsc);
}

function nlModalEsc(e) {
    if (e.key === 'Escape') closeNlModal();
}
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
