<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Redirection…</title>
</head>
<body>
    <p>
        <a href="<?= htmlspecialchars($exitUrl, ENT_QUOTES) ?>">
            <?= htmlspecialchars($exitUrl, ENT_QUOTES) ?>
        </a>
    </p>
    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
        window.location.replace(<?= json_encode($exitUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    </script>
</body>
</html>
