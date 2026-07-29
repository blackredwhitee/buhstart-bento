#!/bin/bash
# Пост-деплой патчи — запускать после замены файлов из Claude Design
set -e
cd "$(dirname "$0")"

# FAQ секция: 760px → 1100px (в Claude Design намеренно узкая для редактора)
sed -i '' 's/max-width:760px;margin:0 auto;padding:72px 24px/max-width:1100px;margin:0 auto;padding:72px 24px/g' index.html

echo "Патчи применены"
