<?php

/**
 * Procédure de rotation de clé AES-256-GCM (accounts.encryption_version).
 *
 * Ce script documente et exécute la rotation des clés de chiffrement
 * pour les colonnes sensibles chiffrées :
 *   - accounts.email_change_new_email
 *   - accounts.majority_declared_ip
 *
 * ## Prérequis
 *   1. Définir la nouvelle clé dans .env :
 *      ENCRYPTION_KEY_V2=<64 hex chars>
 *   2. Garder ENCRYPTION_KEY (v1) disponible pendant la migration
 *
 * ## Procédure
 *   1. Ce script lit chaque compte avec encryption_version = 1
 *   2. Déchiffre les colonnes avec la clé v1
 *   3. Rechiffre avec la clé v2
 *   4. Met à jour la ligne + encryption_version = 2
 *   5. Une fois tous les comptes migrés, retirer ENCRYPTION_KEY v1 du .env
 *
 * ## Exécution (fenêtre de maintenance recommandée)
 *   php /var/www/crabitan_bellevue/bin/rotate_encryption_keys.php >> /var/log/crabitan/key-rotation.log 2>&1
 *
 * ## Rollback
 *   Relancer avec v2 → v1 si nécessaire (conserver les deux clés jusqu'à validation)
 *
 * ⚠️  NE PAS planifier via cron — exécution manuelle unique par rotation.
 * ⚠️  Tester impérativement en staging avant production.
 */

declare(strict_types=1);

// Ce script est un guide documentaire — l'implémentation effective requiert
// que EncryptionService supporte la lecture multi-version et l'écriture v2.
// Voir Service\EncryptionService et accounts.encryption_version.

echo "[INFO] Rotation de clé AES-256-GCM — voir commentaires de ce fichier pour la procédure.\n";
echo "[INFO] Implémentation à réaliser avec l'Expert PHP lors de l'activation de la v2.\n";
exit(0);
