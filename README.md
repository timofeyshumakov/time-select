# AzbykaMed — запись к врачу (Bitrix24 + Renovatio)

Интеграция для записи пациентов на приём из карточки сделки в **Битрикс24**. Виджет выбора времени работает внутри портала, данные о расписании и услугах берутся из **Renovatio** (МИС `app.rnova.org`), сделки и контакты синхронизируются с CRM.

## Состав проекта

| Часть | Каталог | Назначение |
|-------|---------|------------|
| Фронтенд | `time-select/` | Nuxt 4, Vue 3, Vuetify — форма выбора направления, услуги, филиала, врача и слота |
| Бэкенд | `server/` | PHP API: календарь, услуги, создание записи в Renovatio, обновление сделок в Bitrix24 |
| REST Bitrix24 | `server/crest/`, `time-select/crest/` | Библиотека CRest для OAuth и вызовов REST API |

```
azbykamed/
├── time-select/          # SPA для встраивания в Bitrix24
│   ├── app/              # app.vue, callApi.ts
│   ├── crest/            # установка локального приложения (фронт)
│   └── nuxt.config.ts
└── server/
    ├── index.php         # точка входа API
    ├── bitrix24/         # Bitrix24Handler, RenovatioHandler
    ├── crest/            # OAuth, settings.json
    └── logs/             # логи (создаётся автоматически)
```

## Как это работает

1. Менеджер открывает сделку в Bitrix24 и запускает встроенное приложение **time-select**.
2. Фронтенд через `BX24.callMethod` читает смарт-процессы CRM (врачи, клиники, услуги) и обращается к PHP API на вашем сервере.
3. Бэкенд запрашивает расписание и услуги в Renovatio (`https://app.rnova.org/api/public/`).
4. После выбора слота вызывается `action=torenova` — создаётся запись в Renovatio и обновляется сделка в Bitrix24.

### API бэкенда (`server/index.php`)

| Параметр | Описание |
|----------|----------|
| `action=get_calendar` | Расписание врача (`doctor_id`, `clinic_id`, `time_start`, `time_end`) |
| `action=get_services` | Услуги врача в филиале |
| `action=torenova` | Создание записи (`bx_id` — ID сделки, `service_id`) |
| `event=update_appointment` | Webhook: обновление сделки при смене статуса визита |

---

## Требования

- **Node.js** 20+ (для сборки фронтенда)
- **npm** 10+
- **PHP** 8.0+ с расширениями: `curl`, `json`, `mbstring`, `openssl`
- Веб-сервер (**Apache** или **Nginx**) с поддержкой PHP
- Публичный **HTTPS**-домен (обязателен для локального приложения Bitrix24)
- Аккаунт **Bitrix24** с правами на локальные приложения и REST
- **API-ключ Renovatio** для доступа к `app.rnova.org`

---

## Установка

### 1. Клонирование и подготовка каталогов

```bash
git clone <url-репозитория> azbykamed
cd azbykamed
```

На сервере должны быть доступны два URL (пример):

- `https://your-domain.example/` — корень, здесь лежит `server/`
- `https://your-domain.example/time-select/` — статика фронтенда после сборки

Создайте каталог для логов (если его ещё нет):

```bash
mkdir -p server/logs
chmod 755 server/logs
```

### 2. Настройка бэкенда (PHP)

#### 2.1. Размещение файлов

Скопируйте содержимое `server/` в корень сайта (или настройте виртуальный хост так, чтобы `index.php` открывался по `https://your-domain.example/index.php`).

#### 2.2. Секреты (`.env`)

Скопируйте пример и заполните значения:

```bash
cp server/.env.example server/.env
```

Обязательные ключи:

| Переменная | Назначение |
|------------|------------|
| `RENOVATIO_API_KEY` | API-ключ Renovatio |
| `RENOVATIO_API_BASE_URL` | `https://app.rnova.org/api/public/` |
| `C_REST_CLIENT_ID` | ID локального приложения Bitrix24 |
| `C_REST_CLIENT_SECRET` | Секрет приложения Bitrix24 |

Файл `server/.env` не коммитится. Ключ Renovatio выдаётся в ЛК МИС / у администратора.

#### 2.3. Локальное приложение Bitrix24 (CRest)

`server/crest/settings.php` и `time-select/crest/settings.php` читают `C_REST_CLIENT_ID` / `C_REST_CLIENT_SECRET` из `.env`.

При первой установке приложения откройте в браузере:

```
https://your-domain.example/crest/install.php
```

После успешной установки появится файл `server/crest/settings.json` с токенами — **не коммитьте его в git**.

Проверка окружения PHP:

```
https://your-domain.example/crest/checkserver.php
```

#### 2.4. Разрешённые домены (CORS)

В `server/index.php` в массиве `$allowedDomains` укажите URL вашего портала Bitrix24 и домен, с которого отдаётся фронтенд:

```php
private $allowedDomains = [
    'https://your-company.bitrix24.ru',
    'https://your-domain.example'
];
```

### 3. Регистрация приложения в Bitrix24

1. В портале: **Приложения → Разработчикам → Другое → Локальное приложение**.
2. Укажите:
   - **Путь вашего обработчика**: `https://your-domain.example/crest/install.php`
   - **Путь установки**: тот же URL
   - Права: CRM (сделки, контакты), смарт-процессы, пользователи — по фактическим вызовам в `app.vue` и `Bitrix24Handler.php`.
3. Скопируйте **client_id** и **client_secret** в `server/crest/settings.php` и `time-select/crest/settings.php`.
4. В настройках виджета/вкладки сделки укажите URL фронтенда:  
   `https://your-domain.example/time-select/`

Идентификаторы смарт-процессов в коде (при необходимости измените под свой портал):

- `1040` — врачи (`ufCrm7Renovatioid`, клиники, специализации)
- `1044` — справочник, связанный с Renovatio

### 4. Сборка и установка фронтенда

```bash
cd time-select
npm install
```

#### 4.1. Конфигурация URL

В `time-select/nuxt.config.ts` задайте свой домен:

```ts
app: {
  baseURL: '/time-select/',
  cdnURL: 'https://your-domain.example/time-select',
  // ...
}
```

В `time-select/app/app.vue` замените все вхождения `https://renovoapp.webtm.ru` на `https://your-domain.example` (запросы к `index.php`).

При локальной разработке без BX24 можно задать webhook в `time-select/app/callApi.ts`:

```ts
const BITRIX_WEBHOOK_URL = 'https://your-company.bitrix24.ru/rest/1/xxxxxxxx/'
```

#### 4.2. Продакшен-сборка

```bash
cd time-select
npm run generate
```

Артефакты появятся в `time-select/.output/public/`. Скопируйте **содержимое** этой папки на сервер в каталог `/time-select/` (рядом с корнем PHP или в подкаталог виртуального хоста).

Проверка: в браузере открывается `https://your-domain.example/time-select/` без ошибок 404 на `/_nuxt/`.

#### 4.3. Локальная разработка

Для работы в iframe Bitrix24 нужен HTTPS (см. раздел про сертификат ниже):

```bash
cd time-select
npm run dev
```

Сервер поднимется с сертификатами `cert.pem` и `private.key` из каталога `time-select/`.

Запуск без HTTPS (только для отладки вне Bitrix24):

```bash
npm run dev:http
```

### 5. Финальная проверка

1. `https://your-domain.example/crest/checkserver.php` — без критичных ошибок.
2. Установка приложения через `install.php` — статус «installation has been finished».
3. Открытие виджета из сделки — загружаются направления, врачи, календарь.
4. Тестовая запись — сделка обновляется, в Renovatio появляется визит.

---

## Получение SSL-сертификата

Bitrix24 открывает локальные приложения только по **HTTPS**. Для разработки на `localhost` или локальном домене нужен доверенный сертификат. Фронтенд ожидает файлы в каталоге `time-select/`:

- `cert.pem` — сертификат
- `private.key` — закрытый ключ

### Вариант A — mkcert (рекомендуется)

Удобно для Windows, macOS и Linux: браузер и Bitrix24 доверяют сертификату без предупреждений.

**Windows (Chocolatey):**

```powershell
choco install mkcert
mkcert -install
```

**Windows (Scoop):**

```powershell
scoop install mkcert
mkcert -install
```

**Создание сертификата для локальной разработки:**

```bash
cd time-select
mkcert -key-file private.key -cert-file cert.pem localhost 127.0.0.1 ::1
```

Если используете локальный домен (например, `azbykamed.local`), добавьте его в команду:

```bash
mkcert -key-file private.key -cert-file cert.pem localhost azbykamed.local 127.0.0.1
```

Пропишите домен в `hosts`:

```
127.0.0.1 azbykamed.local
```

После этого `npm run dev` поднимет Nuxt на HTTPS с этими файлами.

### Вариант B — OpenSSL (самоподписанный)

Подходит, если mkcert установить нельзя. Браузер покажет предупреждение — его нужно принять вручную; для iframe Bitrix24 иногда потребуется открыть URL приложения в отдельной вкладке и подтвердить исключение.

```bash
cd time-select
openssl req -x509 -newkey rsa:2048 -nodes \
  -keyout private.key \
  -out cert.pem \
  -days 365 \
  -subj "/CN=localhost"
```

### Продакшен

На боевом сервере используйте сертификат от доверенного CA:

- **Let's Encrypt** (certbot) — бесплатно для публичного домена;
- сертификат от хостинг-провайдера или корпоративного УЦ.

Сертификаты Let's Encrypt обычно лежат в `/etc/letsencrypt/live/<домен>/` (`fullchain.pem`, `privkey.pem`). Nginx/Apache настраивают на эти файлы; для `npm run dev` они не нужны — в продакшене отдаётся только статическая сборка `time-select` через веб-сервер с HTTPS.

---

## Переменные и секреты

| Что | Где настраивается |
|-----|-------------------|
| Renovatio API | `RENOVATIO_API_KEY` в `server/.env` |
| Bitrix24 OAuth | `C_REST_CLIENT_ID`, `C_REST_CLIENT_SECRET` в `server/.env` |

### Тесты (PHP)

```bash
cd server
composer install
composer test
```

| Bitrix24 OAuth | `server/crest/settings.php`, `time-select/crest/settings.php` |
| Токены после установки | `settings.json` (не публиковать в репозиторий) |
| URL API | `app.vue`, `nuxt.config.ts` |
| Webhook Bitrix24 (опционально) | `time-select/app/callApi.ts` → `BITRIX_WEBHOOK_URL` |

---

## Полезные команды

```bash
# Установка зависимостей фронтенда
cd time-select && npm install

# Сборка статики для продакшена
cd time-select && npm run generate

# Dev-сервер с HTTPS (нужны cert.pem и private.key)
cd time-select && npm run dev

# Линт фронтенда
cd time-select && npm run lint
```

---

## Логи и отладка

| Файл / каталог | Содержимое |
|----------------|------------|
| `server/logs/` | Ошибки PHP, ротация логов |
| `server/log.json` | Входящие запросы к API |
| `server/crest/log.json` | События webhook (если используется `handler.php`) |

При ошибках интеграции проверьте: срок действия токенов в `settings.json`, доступность `app.rnova.org` с сервера, соответствие ID смарт-процессов в CRM и прав приложения в Bitrix24.

---

## Дополнительно

- `server/patient_stats.php` — отдельный скрипт статистики пациентов (не входит в основной поток записи).
- `server/config.php` — пример конфигурации; основная логика использует CRest и `RenovatioHandler` в `Bitrix24Handler.php`.

При переносе на новый домен обновите URL в **трёх местах**: настройки локального приложения в Bitrix24, `nuxt.config.ts` и обращения к API в `app.vue`.
