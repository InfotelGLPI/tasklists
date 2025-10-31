#!/usr/bin/env bash


# --- Étape 1 : Extraction des chaînes PHP ---
find . -name '*.php' > php_files.list

xgettext --files-from=php_files.list \
  --copyright-holder='Tasklists Development Team' \
  --package-name='Tasklists plugin' \
  -o locales/glpi.pot \
  -L PHP \
  --add-comments=TRANS \
  --from-code=UTF-8 \
  --force-po \
  --sort-output \
  --keyword=_n:1,2,4t \
  --keyword=__s:1,2t \
  --keyword=__:1,2t \
  --keyword=_e:1,2t \
  --keyword=_x:1c,2,3t \
  --keyword=_ex:1c,2,3t \
  --keyword=_nx:1c,2,3,5t \
  --keyword=_sx:1c,2,3t

rm php_files.list

# --- Étape 2 : Extraction des chaînes Twig ---

SCRIPT_DIR=$(dirname "$0")
WORKING_DIR=$(readlink -f "$SCRIPT_DIR/..")
OUTPUT_FILE="$WORKING_DIR/locales/glpi.pot"

F_ARGS_N="1,2"
F_ARGS__S="1"
F_ARGS__="1"
F_ARGS_X="1c,2"
F_ARGS_SX="1c,2"
F_ARGS_NX="1c,2,3"
F_ARGS_SN="1,2"

find "$WORKING_DIR/templates" -type f -name "*.twig" | while read -r file; do

    # Convertit les blocs Twig {{ ... }} en pseudo-code PHP pour xgettext
    perl -0pe 's/\{\{\s*(.*?)\s*\}\}/<?php \1; ?>/g' "$file" \
    | xgettext -o "$OUTPUT_FILE" -L PHP \
        --add-comments=TRANS \
        --from-code=UTF-8 \
        --force-po \
        --join-existing \
        --sort-output \
        --keyword=_n:$F_ARGS_N \
        --keyword=__:$F_ARGS__ \
        --keyword=_x:$F_ARGS_X \
        --keyword=_nx:$F_ARGS_NX \
        --keyword=_sn:$F_ARGS_SN \
        -

    # Corrige les références de fichier dans le POT
    sed -i -r "s|standard input:([0-9]+)|$file:\1|g" "$OUTPUT_FILE"
done
# --- Étape 3 : Extraction des chaînes JavaScript ---
KANBAN_DIR="$WORKING_DIR/public/lib/kanban/js"
OUTPUT_FILE="$WORKING_DIR/locales/glpi.pot"

# Crée le fichier de sortie s'il n'existe pas
mkdir -p "$(dirname "$OUTPUT_FILE")"
touch "$OUTPUT_FILE"

if [ -d "$KANBAN_DIR" ]; then
    echo "Extraction depuis JavaScript : $KANBAN_DIR"
    find "$KANBAN_DIR" -type f -name "*.js" > js_files.list

    # xgettext a besoin du fichier de sortie en paramètre explicite APRÈS -j
    xgettext --files-from=js_files.list \
      -L JavaScript \
      --add-comments=TRANS \
      --from-code=UTF-8 \
      --force-po \
      --sort-output \
      --keyword=_n:1,2 \
      --keyword=__:1 \
      --keyword=_x:1c,2 \
      --keyword=_nx:1c,2,3 \
      -o "$OUTPUT_FILE" -j

    rm js_files.list
else
    echo "⚠️ Dossier $KANBAN_DIR introuvable — étape JavaScript ignorée."
fi
