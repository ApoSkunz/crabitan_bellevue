<?php
/**
 * Page loader QR code : animation de chargement puis auto-download PDF fiche technique.
 */
$lang     = $lang     ?? 'fr';
$wineName = $wineName ?? '';
$pdfUrl   = $pdfUrl   ?? '';
$title    = htmlspecialchars($wineName !== '' ? $wineName : 'Fiche technique');
$isEn     = $lang === 'en';
$loading  = $isEn ? 'Loading technical sheet…' : 'Chargement de la fiche technique…';
$hint     = $isEn ? 'Your download will start in a moment.' : 'Votre téléchargement va démarrer dans un instant.';
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $title ?></title>
<style>
html,body { margin:0; padding:0; background:#0c0a06; color:#f5efe2; font-family:Georgia, serif; height:100%; }
.loader { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; padding:2rem; text-align:center; }
.loader h1 { font-size:1.5rem; margin:0 0 0.75rem; color:#e8c86a; letter-spacing:0.05em; }
.loader p { font-size:0.95rem; margin:0.4rem 0; color:#d9cfbb; }
.spinner { width:64px; height:64px; margin:1.5rem auto; border:4px solid rgba(232,200,106,0.2); border-top-color:#e8c86a; border-radius:50%; animation:spin 0.9s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.progress { width:240px; max-width:80%; height:4px; background:rgba(255,255,255,0.08); border-radius:2px; overflow:hidden; margin:0.75rem auto; }
.progress__bar { width:0; height:100%; background:linear-gradient(90deg,#c9a84c,#e8c86a); animation:fill 2.5s ease-out forwards; }
@keyframes fill { to { width:100%; } }
.hint { margin-top:1.25rem; font-size:0.85rem; color:#8a7060; }
a.fallback { color:#e8c86a; margin-top:1.5rem; display:inline-block; font-size:0.9rem; }
</style>
</head>
<body>
<main class="loader">
    <h1><?= $title ?></h1>
    <p><?= htmlspecialchars($loading) ?></p>
    <div class="spinner" aria-hidden="true"></div>
    <div class="progress"><div class="progress__bar"></div></div>
    <p class="hint"><?= htmlspecialchars($hint) ?></p>
    <a class="fallback" id="fallback-link" href="<?= htmlspecialchars($pdfUrl) ?>"><?= $isEn ? 'Open PDF now' : 'Ouvrir le PDF maintenant' ?></a>
</main>
<script>
(function () {
    var url = <?= json_encode($pdfUrl) ?>;
    setTimeout(function () { window.location.href = url; }, 1800);
})();
</script>
</body>
</html>
