# plumbill_api

## Кратко, что это за проект
Это серверное API‑приложение для обработки лидов и сделок. Оно:
- принимает сделки/лиды от партнёров через HTTP;
- сохраняет их в базе данных;
- синхронизирует их с CRM Bitrix24;
- отправляет сделки в службы доставки (Aramex, BigBoss, FirstDelivery);
- рассылает уведомления по email и SMS;
- отдаёт данные по API партнёрам.

Проект написан на Symfony 6 (PHP 8.2), работает как API‑сервис. Для запуска в комплекте есть Docker и docker‑compose.

## Простыми словами, как это работает
1. Клиент/партнёр отправляет запрос в API (например, создать сделку).
2. API проверяет ключ `X-Api-Key`.
3. Данные валидируются и сохраняются в БД.
4. Дальше сервисы могут:
   - создать или обновить сделку в Bitrix24;
   - отправить заявку в службу доставки;
   - отправить SMS/Email о статусе.
5. Для обновлений/синхронизаций используются вебхуки Bitrix и фоновые команды.

## Основные компоненты
- **Контроллеры**: принимают HTTP‑запросы и передают в сервисы.
- **Сервисы**: вся бизнес‑логика (Bitrix, доставка, SMS, email).
- **Entities/Doctrine**: модели данных (User, Deal, Offer и т.д.).
- **Команды**: запускаются по расписанию (cron) для синхронизаций.

## Аутентификация
Все `/api/*` запросы требуют заголовок:
```
X-Api-Key: <ваш_ключ>
```
Ключ можно создать через `POST /create-api-key` (в проде этот эндпоинт лучше защитить отдельным секретом).

## Важные эндпоинты (примеры)
- `POST /api/add-deals` — массовое создание сделок
- `GET /api/get-deals` — получить сделки
- `POST /api/lead` — создать лид
- `POST /api/leads` — создать несколько лидов
- `POST /deal_postback*` — вебхуки Bitrix24

## Интеграции
- **Bitrix24** — вебхуки и API
- **Aramex / BigBoss / FirstDelivery** — доставка
- **SMS‑шлюз** — отправка уведомлений
- **Mailer** — email‑уведомления

Важно: в коде часть URL для Bitrix/внешних сервисов сейчас пустые. Перед продом их нужно заполнить.

---

# Как развернуть (самый простой способ)

## 1) Docker (рекомендуется)

### Шаги
1. Проверьте `.env` и `.env.local` (или используйте `.env.example` как шаблон).
2. Соберите и запустите сервисы:
   ```sh
   docker compose up -d --build
   ```
3. Установите зависимости PHP (если `vendor/` не актуален):
   ```sh
   docker compose exec php composer install
   ```
4. Примените миграции БД:
   ```sh
   docker compose exec php bin/console doctrine:migrations:migrate
   ```

### Адреса
- API/Nginx: http://localhost (порты 80/443)
- MySQL: порт 3307 (хост) -> 3306 (контейнер)
- phpMyAdmin: http://localhost:8081

---

## 2) Локально без Docker
1. Установите PHP 8.2, Composer и MySQL 8.
2. Выполните:
   ```sh
   composer install
   ```
3. Настройте `.env.local` (DATABASE_URL и др.).
4. Запустите миграции:
   ```sh
   php bin/console doctrine:migrations:migrate
   ```
5. Запустите сервер:
   ```sh
   symfony server:start
   ```

---

# Переменные окружения (основные)

### Базовые
- `APP_ENV` (dev/prod)
- `APP_DEBUG` (0/1)
- `APP_SECRET`
- `APP_URL`

### База данных
- `DATABASE_URL` (пример: `mysql://root:pass@db:3306/plumbill`)

### CORS
- `CORS_ALLOW_ORIGIN`

### Почта
- `MAILER_DSN`

### Интеграции доставки
- `ARAMEX_ENDPOINT`, `ARAMEX_TRACKING_ENDPOINT`, `ARAMEX_USERNAME`, `ARAMEX_PASSWORD`,
  `ARAMEX_ACCOUNT_NUMBER`, `ARAMEX_ACCOUNT_PIN`, `ARAMEX_ACCOUNT_ENTITY`,
  `ARAMEX_ACCOUNT_COUNTRY_CODE`, `ARAMEX_VERSION`
- `BIGBOSS_ENDPOINT`, `BIGBOSS_USERNAME`, `BIGBOSS_PASSWORD`

---

# Полезные команды
- `bin/console app:delivery` — отправка сделок службам доставки
- `bin/console app:first-delivery:send-deals` — отправка сделок в FirstDelivery
- `bin/console app:tracking-delivery` — трекинг доставки
- `bin/console app:sync-bitrix-deals` — синхронизация с Bitrix


# Важные замечания
- Часть интеграционных URL‑ов в коде сейчас пустые — без них интеграции не будут работать.
- Эндпоинт `/create-api-key` лучше защитить перед прод‑запуском.
- Для production используйте `APP_ENV=prod` и `APP_DEBUG=0`.
