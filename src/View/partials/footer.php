<?php

$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr'); ?>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></p>
</footer>
</body>
</html>
