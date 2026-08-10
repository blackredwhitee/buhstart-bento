#!/bin/bash
# Публикация сайта на хостинг reg.ru.
# Заливает только изменившиеся файлы. Ничего за пределами папки сайта не трогает.
#
#   ./deploy.sh            — показать, что изменится, без записи
#   ./deploy.sh --go       — залить
#
# Доступ идёт по ключу ~/.ssh/buhstart_regru (алиас buhstart-hosting в ~/.ssh/config),
# пароль не нужен. Отозвать доступ: убрать строку buhstart-deploy
# из ~/.ssh/authorized_keys на хостинге.

set -euo pipefail
cd "$(dirname "$0")"

HOST=buhstart-hosting
DEST=/var/www/u3524441/data/www/public_html/db

FLAGS=(-az --delete --human-readable --itemize-changes)
[[ "${1:-}" == "--go" ]] || FLAGS+=(--dry-run)

# служебное и исходники наружу не выкладываем;
# content/ и uploads/ пойдут, их правит редактор — при заливке не удаляем чужое
# 1. код и страницы: старое на сервере удаляем, чужое трогать нельзя
rsync "${FLAGS[@]}" \
  --exclude '.git/' --exclude '.github/' --exclude '.gitignore' \
  --exclude '_articles/' --exclude '_cases/' --exclude '_pages/' \
  --exclude 'admin/' --exclude 'README.md' --exclude 'deploy.sh' \
  --exclude '.DS_Store' --exclude '.nojekyll' \
  --exclude 'uploads/' --exclude 'content/' \
  --filter 'protect uploads/***' --filter 'protect content/***' \
  --filter 'protect _old_wp/***' --filter 'protect .well-known/***' --filter 'protect .ftpquota' \
  ./ "$HOST:$DEST/"

# 2. картинки и данные: НЕ перезаписываем то, что новее на сервере —
#    иначе моя выгрузка затёрла бы фото и тексты, заменённые через редактор
rsync "${FLAGS[@]/--delete/}" --update \
  --exclude '.DS_Store' \
  ./uploads/ "$HOST:$DEST/uploads/"
rsync "${FLAGS[@]/--delete/}" --update \
  ./content/ "$HOST:$DEST/content/"

if [[ "${1:-}" == "--go" ]]; then
  echo
  echo "Готово. Проверить: https://buhstart.prconsru.prconsr5.cp.regruhosting.ru/"
else
  echo
  echo "Это была проверка без записи. Чтобы залить: ./deploy.sh --go"
fi
