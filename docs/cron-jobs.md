# Cron jobs — Château Crabitan Bellevue

Tous les scripts se trouvent dans `bin/`. Ils utilisent `Core\CronRunner` qui gère :
- **Lock** : un seul job par script en simultané (`flock`)
- **Logs** : `storage/logs/{script}-YYYY-MM-DD.log` + stdout
- **Rotation** : fichiers > 30 jours supprimés automatiquement
- **Exit code** : 0 = succès, 1 = erreur fatale (IONOS détecte l'échec)

---

## Liste des jobs

| Script | Fréquence | Commande IONOS |
|---|---|---|
| `purge_accounts.php` | Quotidien 02h00 | `0 2 * * * php /var/www/crabitan_bellevue/bin/purge_accounts.php` |
| `purge_inactive_accounts.php` | Quotidien 02h00 | `0 2 * * * php /var/www/crabitan_bellevue/bin/purge_inactive_accounts.php` |
| `pseudonymize-orders.php` | Quotidien 02h00 | `0 2 * * * php /var/www/crabitan_bellevue/bin/pseudonymize-orders.php` |
| `purge_connections.php` | Quotidien 03h00 | `0 3 * * * php /var/www/crabitan_bellevue/bin/purge_connections.php` |
| `purge_addresses.php` | Quotidien 03h00 | `0 3 * * * php /var/www/crabitan_bellevue/bin/purge_addresses.php` |
| `send_deletion_reminders.php` | Quotidien 09h00 | `0 9 * * * php /var/www/crabitan_bellevue/bin/send_deletion_reminders.php` |
| `clean_expired_tokens.php` | Hebdo dimanche 04h00 | `0 4 * * 0 php /var/www/crabitan_bellevue/bin/clean_expired_tokens.php` |
| `purge_newsletter_tracking.php` | Mensuel 1er 04h00 | `0 4 1 * * php /var/www/crabitan_bellevue/bin/purge_newsletter_tracking.php` |
| `check_ssl_expiration.php` | Quotidien 07h00 | `0 7 * * * php /var/www/crabitan_bellevue/bin/check_ssl_expiration.php` |

> Sur IONOS : **Hébergement → Outils → Cron jobs → Ajouter**

---

## Logs

Emplacement : `storage/logs/{script}-YYYY-MM-DD.log`

```
2026-04-10 02:00:01 [purge_accounts] START
2026-04-10 02:00:01 [purge_accounts] Comptes éligibles à l'anonymisation : 2
2026-04-10 02:00:02 [purge_accounts] 2 compte(s) anonymisé(s)
2026-04-10 02:00:02 [purge_accounts] END
```

Les fichiers de log sont ignorés par git (`.gitignore` : `storage/logs/*.log`).
Rotation automatique : suppression après 30 jours.

---

## Ajouter un nouveau job

1. Créer `bin/mon_script.php`
2. Inclure `require_once __DIR__ . '/cron-bootstrap.php';`
3. Envelopper la logique dans `(new \Core\CronRunner('mon_script'))->run(...)`
4. Ajouter à ce document + à `BACKLOG/TODO_IONOS.md`
