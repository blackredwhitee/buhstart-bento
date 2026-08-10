/**
 * Приём заявок с сайта «Доверительная Бухгалтерия» → Google Таблица.
 *
 * Что делать:
 *   1. Открыть таблицу → Расширения → Apps Script.
 *   2. Заменить весь код этим файлом.
 *   3. Сохранить. Один раз запустить функцию setupSheets — она приведёт
 *      существующие листы к новой структуре, старые строки не потеряются.
 *   4. Развернуть заново НЕ нужно: адрес /exec остаётся тем же.
 *
 * Что изменилось: тип обращения, форма и страница теперь отдельные столбцы,
 * а не текст внутри «Комментария». Цены с калькулятора — три числовых столбца.
 */

var SPREADSHEET_ID = '1tmmOmpvX9nTPAtrhHIrMLqQ3b0GbPp74IRdjMjq8HOA';
var NOTIFY_EMAIL   = '';   // почта для писем о новых заявках; пусто — не отправлять
var TZ             = 'Europe/Moscow';

var BRAND = {
  orange:'#F07828', dark:'#3D3D3D',
  lightOrange:'#FEF0E6', border:'#E8E4DF', white:'#FFFFFF'
};

var STATUSES = ['Новая', 'В работе', 'Ждём ответ клиента', 'Договор', 'Отказ', 'Спам'];

var SHEETS_CONFIG = {
  'Заявки': {
    headers:['Дата','Тип обращения','Форма / кнопка','Страница','Имя','Телефон','Email','Сообщение','Доп. данные','Статус','Ответственный','Заметки'],
    widths:[130,150,220,150,170,140,180,300,320,120,140,240],
    statusCol:10
  },
  // порядок первых девяти столбцов не меняем — иначе поедут уже накопленные строки
  'Калькулятор': {
    headers:['Дата','Имя','Компания','Телефон','Email','Тарифы','Сотрудники','Система','Ссылка на КП','Базовая','Стандарт','Оптима','№ КП','Статус','Ответственный','Заметки'],
    widths:[130,170,120,140,180,260,150,130,260,100,100,100,130,120,140,240],
    statusCol:14
  },
  'Работа у нас': {
    headers:['Дата','Имя','Телефон','Контакт','Направление','Опыт','Навыки','О себе','Резюме','Статус','Заметки'],
    widths:[130,170,140,180,170,110,220,280,260,120,240],
    statusCol:10
  }
};

var TYPE_MAP = { lead:'Заявки', calculator:'Калькулятор', vacancy:'Работа у нас' };

/* ---------------------------------------------------------------- приём */

function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var sheetName = TYPE_MAP[data.type] || 'Заявки';
    var cfg = SHEETS_CONFIG[sheetName];
    var ss = SpreadsheetApp.openById(SPREADSHEET_ID);

    var sheet = ss.getSheetByName(sheetName);
    if (!sheet) { sheet = ss.insertSheet(sheetName); initSheet(sheet, cfg); }

    var now = Utilities.formatDate(new Date(), TZ, 'dd.MM.yyyy HH:mm');
    var row;

    if (sheetName === 'Заявки') {
      row = [
        now,
        data.kind   || fromComment(data.comment, 'Тип')      || 'Заявка',
        data.source || fromComment(data.comment, 'Источник') || '',
        data.page   || fromComment(data.comment, 'Страница') || '',
        data.name || '',
        data.phone || '',
        data.email || '',
        data.message !== undefined ? data.message : cleanComment(data.comment),
        data.extra || '',
        'Новая', '', ''
      ];

    } else if (sheetName === 'Калькулятор') {
      row = [
        now,
        data.name || '', data.company || '', data.phone || '', data.email || '',
        data.tariff || '', data.employees || '', data.system || '',
        saveFile(data.kpBase64, data.kpName, data.kpMime, 'КП — Доверительная'),
        num(data.priceBase), num(data.priceStd), num(data.priceOpt),
        data.kpNum || '',
        'Новая', '', ''
      ];

    } else {
      row = [
        now,
        data.name || '', data.phone || '', data.contact || '', data.role || '',
        data.exp || '', data.skills || '', data.about || '',
        saveFile(data.resumeBase64, data.resumeName, data.resumeMime, 'Резюме — Доверительная') || (data.resumeLink || ''),
        'Новая', ''
      ];
    }

    sheet.appendRow(row);
    styleRow(sheet, sheet.getLastRow(), cfg);
    notify(sheetName, cfg.headers, row);

  } catch (err) {
    logError(err);
  }
  return ContentService.createTextOutput('ok');
}

/* ------------------------------------------------------------ помощники */

function num(v) {
  var n = parseFloat(String(v === undefined || v === null ? '' : v).replace(/[^\d.,-]/g, '').replace(',', '.'));
  return isNaN(n) ? '' : n;
}

// достаём «Тип: …» из старого склеенного комментария — на случай кэша со старой версией сайта
function fromComment(comment, label) {
  if (!comment) return '';
  var m = String(comment).match(new RegExp(label + ':\\s*([^|]+)'));
  return m ? m[1].trim() : '';
}

function cleanComment(comment) {
  if (!comment) return '';
  return String(comment).split('|').filter(function (part) {
    return !/^\s*(Тип|Источник|Страница)\s*:/.test(part);
  }).join('|').replace(/\s*\|\s*$/, '').trim();
}

function saveFile(b64, name, mime, folderName) {
  if (!b64 || !name) return '';
  try {
    var blob = Utilities.newBlob(Utilities.base64Decode(b64), mime || 'application/octet-stream', name);
    var it = DriveApp.getFoldersByName(folderName);
    var folder = it.hasNext() ? it.next() : DriveApp.createFolder(folderName);
    var file = folder.createFile(blob);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    return file.getUrl();
  } catch (err) {
    return 'Ошибка загрузки: ' + err.message;
  }
}

function notify(sheetName, headers, row) {
  if (!NOTIFY_EMAIL) return;
  try {
    var lines = [];
    for (var i = 0; i < headers.length; i++) {
      if (row[i] !== '' && row[i] !== undefined) lines.push(headers[i] + ': ' + row[i]);
    }
    MailApp.sendEmail({
      to: NOTIFY_EMAIL,
      subject: 'Сайт: ' + sheetName + ' — ' + (row[1] || row[4] || ''),
      body: lines.join('\n') + '\n\nТаблица: https://docs.google.com/spreadsheets/d/' + SPREADSHEET_ID
    });
  } catch (err) {}
}

function logError(err) {
  try {
    var ss = SpreadsheetApp.openById(SPREADSHEET_ID);
    var log = ss.getSheetByName('Ошибки') || ss.insertSheet('Ошибки');
    log.appendRow([Utilities.formatDate(new Date(), TZ, 'dd.MM.yyyy HH:mm'), String(err && err.message || err)]);
  } catch (e) {}
}

/* ------------------------------------------------------------ оформление */

function styleRow(sheet, r, cfg) {
  var range = sheet.getRange(r, 1, 1, cfg.headers.length);
  range.setBackground(r % 2 === 0 ? BRAND.lightOrange : BRAND.white)
       .setFontColor(BRAND.dark).setFontSize(10)
       .setVerticalAlignment('top').setWrap(true);
  sheet.setRowHeight(r, 30);
  if (cfg.statusCol) {
    sheet.getRange(r, cfg.statusCol).setDataValidation(
      SpreadsheetApp.newDataValidation().requireValueInList(STATUSES, true).build()
    );
  }
}

function initSheet(sheet, cfg) {
  sheet.appendRow(cfg.headers);
  headerStyle(sheet, cfg);
}

function headerStyle(sheet, cfg) {
  sheet.getRange(1, 1, 1, cfg.headers.length)
       .setBackground(BRAND.orange).setFontColor(BRAND.white)
       .setFontWeight('bold').setFontSize(11)
       .setVerticalAlignment('middle').setHorizontalAlignment('center').setWrap(true);
  sheet.setRowHeight(1, 40);
  sheet.setFrozenRows(1);
  sheet.setFrozenColumns(1);
  cfg.widths.forEach(function (w, i) { sheet.setColumnWidth(i + 1, w); });
}

/* --------------------------------------- разовая настройка / перестройка */

/**
 * Приводит листы к новой структуре. Запускать один раз вручную.
 * Старые строки не удаляются: в «Заявках» между «Датой» и «Именем» вставляются
 * три столбца, значения сдвигаются сами, а «Тип / Форма / Страница»
 * вытаскиваются из старого текста комментария.
 */
function setupSheets() {
  var ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  var report = [];

  // 1. Заявки
  var s = ss.getSheetByName('Заявки');
  if (s) {
    var head = s.getRange(1, 1, 1, Math.max(s.getLastColumn(), 1)).getValues()[0];
    if (String(head[1]).indexOf('Тип обращения') !== 0) {
      s.insertColumnsAfter(1, 3);                       // Тип, Форма, Страница
      var last = s.getLastRow();
      if (last > 1) {
        // комментарий уехал на 5 столбцов вправо: Дата,Тип,Форма,Стр,Имя,Тел,Email,Комментарий
        var comments = s.getRange(2, 8, last - 1, 1).getValues();
        var filled = comments.map(function (c) {
          var v = c[0];
          return [fromComment(v, 'Тип') || 'Заявка', fromComment(v, 'Источник'), fromComment(v, 'Страница')];
        });
        s.getRange(2, 2, filled.length, 3).setValues(filled);
        s.getRange(2, 8, last - 1, 1).setValues(comments.map(function (c) { return [cleanComment(c[0])]; }));
      }
      report.push('«Заявки»: добавлены Тип / Форма / Страница, старые строки разобраны');
    }
    ensureTail(s, SHEETS_CONFIG['Заявки']);
  }

  // 2. Калькулятор — три числовых столбца с ценами дописываются справа,
  //    порядок старых столбцов не трогаем, цены старых строк вытаскиваем из «Тарифов»
  var c = ss.getSheetByName('Калькулятор');
  if (c) {
    var lastC = c.getLastRow();
    ensureTail(c, SHEETS_CONFIG['Калькулятор']);
    if (lastC > 1) {
      var old = c.getRange(2, 6, lastC - 1, 1).getValues();
      var prices = old.map(function (row) {
        var t = String(row[0] || '');
        function grab(label) {
          var m = t.match(new RegExp(label + ':\\s*([\\d\\s.,]+)'));
          return m ? num(m[1]) : '';
        }
        return [grab('Базовая'), grab('Стандарт'), grab('Оптима')];
      });
      c.getRange(2, 10, prices.length, 3).setValues(prices);
      report.push('«Калькулятор»: цены разложены на Базовая / Стандарт / Оптима');
    }
  }

  // 3. Работа у нас
  var v = ss.getSheetByName('Работа у нас');
  if (v) ensureTail(v, SHEETS_CONFIG['Работа у нас']);

  // 4. новые листы, если каких-то нет
  Object.keys(SHEETS_CONFIG).forEach(function (name) {
    if (!ss.getSheetByName(name)) {
      initSheet(ss.insertSheet(name), SHEETS_CONFIG[name]);
      report.push('создан лист «' + name + '»');
    }
  });

  SpreadsheetApp.getUi().alert(report.length ? 'Готово:\n\n• ' + report.join('\n• ') : 'Всё уже в новой структуре.');
}

// дописывает недостающие столбцы справа (Статус / Ответственный / Заметки) и наводит оформление
function ensureTail(sheet, cfg) {
  var need = cfg.headers.length;
  if (sheet.getMaxColumns() < need) sheet.insertColumnsAfter(sheet.getMaxColumns(), need - sheet.getMaxColumns());
  sheet.getRange(1, 1, 1, need).setValues([cfg.headers]);
  headerStyle(sheet, cfg);
  var last = sheet.getLastRow();
  for (var r = 2; r <= last; r++) styleRow(sheet, r, cfg);
  if (last >= 1) {
    sheet.getRange(1, 1, last, need)
         .setBorder(true, true, true, true, true, true, BRAND.border, SpreadsheetApp.BorderStyle.SOLID);
  }
}

function onOpen() {
  SpreadsheetApp.getUi().createMenu('Заявки с сайта')
    .addItem('Привести листы в порядок', 'setupSheets')
    .addToUi();
}
