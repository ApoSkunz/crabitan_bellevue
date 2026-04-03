<?php
/**
 * Fermeture commune aux pages légales (mentions-légales, politique-confidentialité).
 * Variables attendues : $isBare (bool)
 */
?>
    </article>
<?php if ($isBare) : ?>
</body>
</html>
<?php else : ?>
</main>
    <?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
<?php endif; ?>
