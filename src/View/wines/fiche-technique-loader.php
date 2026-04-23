<?php
/**
 * Page loader QR code : progression % réelle (fetch + ReadableStream) puis PDF inline.
 */
$lang     = $lang     ?? 'fr';
$wineName = $wineName ?? '';
$pdfUrl   = $pdfUrl   ?? '';
$title    = htmlspecialchars($wineName !== '' ? $wineName : 'Fiche technique');
$isEn     = $lang === 'en';
$loading  = $isEn ? 'Loading technical sheet…' : 'Chargement de la fiche technique…';
$hint     = $isEn ? 'Your PDF will open in a moment.' : 'Le PDF va s\'afficher dans un instant.';
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $title ?></title>
<style>
html,body { margin:0; padding:0; background:#0c0a06; color:#f5efe2; font-family:Georgia, serif; height:100%; }
body.has-pdf { background:#1a1208; }
.loader { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; padding:2rem; text-align:center; transition:opacity 300ms ease; }
.loader.hidden { display:none; }
.loader h1 { font-size:1.4rem; margin:0 0 0.5rem; color:#e8c86a; letter-spacing:0.04em; }
.loader p { font-size:0.92rem; margin:0.3rem 0; color:#d9cfbb; }
.spinner { width:56px; height:56px; margin:1.25rem auto 0.5rem; border:4px solid rgba(232,200,106,0.2); border-top-color:#e8c86a; border-radius:50%; animation:spin 0.9s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.progress { width:260px; max-width:85%; height:6px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; margin:0.75rem auto 0.5rem; }
.progress__bar { width:0; height:100%; background:linear-gradient(90deg,#c9a84c,#e8c86a); transition:width 180ms ease; }
.pct { font-size:0.95rem; font-weight:600; color:#e8c86a; margin-top:0.2rem; }
.hint { margin-top:1rem; font-size:0.82rem; color:#8a7060; font-style:italic; }
.pdf-frame { width:100vw; height:100vh; border:0; display:block; }
</style>
</head>
<body>
<main class="loader" id="loader">
    <h1><?= $title ?></h1>
    <p><?= htmlspecialchars($loading) ?></p>
    <div class="spinner" aria-hidden="true"></div>
    <div class="progress"><div class="progress__bar" id="bar"></div></div>
    <div class="pct" id="pct">0 %</div>
    <p class="hint"><?= htmlspecialchars($hint) ?></p>
</main>
<script>
(function () {
    var url    = <?= json_encode($pdfUrl) ?>;
    var bar    = document.getElementById('bar');
    var pct    = document.getElementById('pct');
    var loader = document.getElementById('loader');

    function setProgress(p) {
        var v = Math.min(100, Math.max(0, Math.round(p)));
        bar.style.width = v + '%';
        pct.textContent = v + ' %';
    }

    function showPdf(blobUrl) {
        loader.classList.add('hidden');
        document.body.classList.add('has-pdf');
        var iframe = document.createElement('iframe');
        iframe.className = 'pdf-frame';
        iframe.src = blobUrl;
        iframe.setAttribute('title', <?= json_encode($title) ?>);
        document.body.appendChild(iframe);
    }

    if (!('fetch' in window) || !('ReadableStream' in window)) {
        // Fallback browser trop ancien : redirect direct
        setTimeout(function () { window.location.href = url; }, 800);
        return;
    }

    fetch(url).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var total = parseInt(response.headers.get('Content-Length') || '0', 10);
        var loaded = 0;
        var chunks = [];
        var reader = response.body.getReader();

        function read() {
            return reader.read().then(function (res) {
                if (res.done) {
                    setProgress(100);
                    var blob = new Blob(chunks, { type: 'application/pdf' });
                    setTimeout(function () { showPdf(URL.createObjectURL(blob)); }, 200);
                    return;
                }
                chunks.push(res.value);
                loaded += res.value.length;
                if (total > 0) {
                    setProgress((loaded / total) * 100);
                } else {
                    // Pas de Content-Length : progression pseudo
                    setProgress(Math.min(90, (loaded / 65536) * 10));
                }
                return read();
            });
        }
        return read();
    }).catch(function () {
        window.location.href = url;
    });
})();
</script>
</body>
</html>
