#!/bin/bash
# Script per creare lo zip del modulo PrestaShop con la struttura corretta

MODULE_NAME="productadvancedmanager"
CURRENT_DIR=$(pwd)
PARENT_DIR=$(dirname "$CURRENT_DIR")

# Crea una cartella temporanea
TMP_DIR=$(mktemp -d)

# Copia i file nella cartella con il nome corretto del modulo
mkdir -p "$TMP_DIR/$MODULE_NAME"
cp -r config.xml "$TMP_DIR/$MODULE_NAME/"
cp -r productadvancedmanager.php "$TMP_DIR/$MODULE_NAME/"
cp -r logo.png "$TMP_DIR/$MODULE_NAME/"
cp -r cron.php "$TMP_DIR/$MODULE_NAME/"
cp -r controllers "$TMP_DIR/$MODULE_NAME/"
cp -r views "$TMP_DIR/$MODULE_NAME/"
cp -r mails "$TMP_DIR/$MODULE_NAME/"

# Rimuovi file non necessari
rm -rf "$TMP_DIR/$MODULE_NAME/.git"
rm -rf "$TMP_DIR/$MODULE_NAME/.gitignore"
rm -rf "$TMP_DIR/$MODULE_NAME/README.md"
rm -rf "$TMP_DIR/$MODULE_NAME/build_zip.sh"

# Crea lo zip
cd "$TMP_DIR"
zip -r "$PARENT_DIR/$MODULE_NAME.zip" "$MODULE_NAME"

# Pulisci
rm -rf "$TMP_DIR"

echo ""
echo "✓ File zip creato: $PARENT_DIR/$MODULE_NAME.zip"
echo ""
echo "Ora puoi caricare il file '$MODULE_NAME.zip' su PrestaShop."
