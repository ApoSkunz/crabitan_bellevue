<?php
/**
 * Partial — checkbox filtre "Cuvée spéciale".
 * Requiert : $activeCuvee (bool), $cls (string ' is-active').
 */
?>
<label class="wines-filters__check wines-filters__check--cuvee<?= $activeCuvee ? $cls : '' ?>">
    <input type="checkbox" name="cuvee_speciale" value="1" <?= $activeCuvee ? 'checked' : '' ?>>
    <?= htmlspecialchars(__('wine.filter_cuvee')) ?>
</label>
