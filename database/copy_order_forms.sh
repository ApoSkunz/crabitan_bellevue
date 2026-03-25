#!/usr/bin/env bash
# Copie les bons de commande historiques vers storage/order_forms/
set -euo pipefail

SRC="$(dirname "$0")/../resources_publique/resources/files/prices"
DEST="$(dirname "$0")/../storage/order_forms"

mkdir -p "$DEST"
cp "$SRC"/*.pdf "$DEST/"
echo "Bons de commande copiés dans $DEST"
