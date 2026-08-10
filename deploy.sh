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
DEST=/var/www/u3524441/data/www/public_html/bento

FLAGS=(-az --delete --human-readable --itemize-changes)
[[ "${1:-}" == "--go" ]] || FLAGS+=(--dry-run)

# служебное и исходники наружу не выкладываем;
# content/ и uploads/ пойдут, их правит редактор — при заливке не удаляем чужое
rsync "${FLAGS[@]}" \
  --exclude '.git/' --exclude '.github/' --exclude '.gitignore' \
  --exclude '_articles/' --exclude '_cases/' --exclude '_pages/' \
  --exclude 'admin/' --exclude 'README.md' --exclude 'deploy.sh' \
  --exclude '.DS_Store' --exclude '.nojekyll' \
  ./ "$HOST:$DEST/"

if [[ "${1:-}" == "--go" ]]; then
  echo
  echo "Готово. Проверить: http://buhstart.prconsru.prconsr5.cp.regruhosting.ru/"
else
  echo
  echo "Это была проверка без записи. Чтобы залить: ./deploy.sh --go"
fi
