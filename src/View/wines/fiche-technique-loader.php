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
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-pdf-loader">
<main class="loader" id="loader">
    <h1><?= $title ?></h1>
    <p><?= htmlspecialchars($loading) ?></p>
    <div class="pdf-spinner" aria-hidden="true"></div>
    <div class="progress"><div class="progress__bar" id="bar"></div></div>
    <div class="pct" id="pct">0 %</div>
    <p class="hint"><?= htmlspecialchars($hint) ?></p>
</main>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
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
        // Navigation directe vers blob URL (CSP frame-src bloque iframe blob:)
        window.location.href = blobUrl;
    }

    if (!('fetch' in window) || !('ReadableStream' in window)) {
        // Fallback browser trop ancien : redirect direct
        setTimeout(function () { window.location.href = url; }, 800);
        return;
    }

    // Safety : si le fetch n'aboutit pas en 15s, redirect direct
    var timeoutId = setTimeout(function () { window.location.href = url; }, 15000);

    // Progression pseudo toujours visible (min 1.2s) même si PDF très rapide
    var fakeStart = Date.now();
    var fakeMax   = 90;
    var fakeTimer = setInterval(function () {
        var elapsed = Date.now() - fakeStart;
        var target  = Math.min(fakeMax, (elapsed / 1200) * fakeMax);
        var current = parseInt(bar.style.width || '0', 10);
        if (target > current) setProgress(target);
    }, 60);

    fetch(url).then(function (response) {
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var total = parseInt(response.headers.get('Content-Length') || '0', 10);
        var loaded = 0;
        var chunks = [];
        var reader = response.body.getReader();

        function read() {
            return reader.read().then(function (res) {
                if (res.done) {
                    clearInterval(fakeTimer);
                    setProgress(100);
                    var blob = new Blob(chunks, { type: 'application/pdf' });
                    // Min 1.2s d'affichage pour que l'animation soit visible
                    var minElapsed = Math.max(0, 1200 - (Date.now() - fakeStart));
                    setTimeout(function () { showPdf(URL.createObjectURL(blob)); }, minElapsed + 200);
                    return;
                }
                chunks.push(res.value);
                loaded += res.value.length;
                if (total > 0) {
                    setProgress(Math.max(parseInt(bar.style.width || '0', 10), (loaded / total) * 90));
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
