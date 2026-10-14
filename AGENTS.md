# AGENTS.md — инструкция для AI-агентов

Hezky česky — тренажёр по чешскому языку для ученика 3 класса чешской школы. Ученик — русскоязычный ребёнок.
Здесь собраны правила продукта и команды, которые нужны, чтобы безопасно менять проект.
Как запустить приложение, описано в [README.md](README.md).

## Стек

- PHP 8.5, Symfony 8.1, Twig, Doctrine ORM 3, SQLite (файл `var/data/app.db`).
- Фронтенд — серверный рендер на Twig, AssetMapper (`assets/app.js`, `assets/styles/app.css`), без сборщика и без JS-фреймворков.
- Docker Compose: `app` (php-fpm, `php:8.5-fpm`) + `web` (nginx, `nginx:1.27-alpine`). Оба образа собираются из одного
  `Dockerfile` (targets `app` и `web`). Локально PHP не нужен: все команды выполняются в Docker.
- Тестов PHPUnit пока нет, `tests/` пустой. Как проверять изменения — в разделе «Проверка».

## Структура

| Путь | Что там |
|---|---|
| `src/Entity/` | `User`, `Topic`, `Question`, `QuizSession`, `Answer` |
| `src/Controller/` | тонкие контроллеры: `Home`, `Quiz`, `Stats` (свои результаты), `Parent` (кабинет родителя), `Security`, `Registration`, `Root` |
| `src/Service/QuizFactory.php` | собирает квиз из 10 вопросов: только те, на которые ребёнок ещё не отвечал, или режим ошибок |
| `src/Service/AnswerChecker.php` | проверка ответа и нормализация ввода |
| `src/Service/StatsProvider.php` | прогресс, ранги, открытие уровней, статистика; пороги заданы константами |
| `src/Service/PlayerRoster.php` | формула ранга 0–10 и игроки из `content/players.yaml` |
| `src/Service/QuestionBankImporter.php` | синхронизирует YAML-банк с БД и валидирует его |
| `src/Command/` | `app:questions:import`, `app:user:parent` |
| `content/topics.yaml` | темы (стабильные `code`) |
| `content/questions/*.yaml` | банк вопросов, 511 шт.: `level1*.yaml`, `level2*.yaml`, `level3*.yaml` |
| `content/players.yaml` | ранги 0–10 и игроки ФК Барселона |
| `templates/` | Twig; `base.html.twig` содержит навигацию и переключатель языка |
| `translations/messages.{cs,en}.yaml` | все тексты интерфейса; `validators.*` — сообщения валидации |
| `docs/USER_GUIDE.{cs,en}.md` | инструкция для пользователей; её же рендерит страница `/{_locale}/help` (`UserGuide`, GFM, кеш по mtime) |
| `docker/entrypoint.sh` | при старте `app`: секрет → cache:warmup → migrations → импорт вопросов → php-fpm |
| `docker/nginx.conf` | nginx: статика, проксирование в `app:9000`, заголовки безопасности (CSP и др.) |
| `docker/php-fpm.conf`, `docker/php.ini` | пул FPM (`clear_env = no`), `expose_php = Off` |
| `config/packages/security.yaml`, `rate_limiter.yaml` | вход, ограничение попыток входа, лимиты регистрации и привязки |

## Правила продукта (не ломать)

1. **Каждый вопрос в обычном режиме ребёнок получает один раз.** Всё, на что он уже отвечал, правильно или нет, `QuizFactory::create()` пропускает.
2. **Ошибки возвращаются только в режиме «Opravit chyby»** (`QuizSession::MODE_MISTAKES`). Туда попадают вопросы, на которые последний ответ был неверным.
3. **Каждый ответ сохраняется** в `Answer`: что введено, верно ли, когда, в каком квизе. Историю ответов не удалять и не переписывать.
4. **3 уровня сложности.** На каждом уровне у ребёнка свой ранг 0–10:
   `очки = правильные − 0.5 × ошибки` (не меньше 0), `ранг = floor(10 × очки / число активных вопросов уровня)`.
   Каждому рангу соответствует игрок из `content/players.yaml`: внизу текущий состав, наверху легенды, ранг 10 — Месси.
5. **Уровни открываются по очереди.** Уровень N+1 открыт, если на уровне N пройдено ≥ 80 % вопросов и правильных ≥ 70 %.
   Начатый уровень (есть хоть один ответ) остаётся открытым, даже если позже добавились вопросы.
6. **Роли и семьи.** Регистрация открытая: при регистрации выбирается «ребёнок» (`ROLE_CHILD`) или «родитель» (`ROLE_PARENT`).
   Родитель видит и меняет **только своих** детей (`User::$parents` / `$children`, таблица `parent_child`, у ребёнка до двух и более родителей).
   Привязка: родитель создаёт ребёнка в кабинете (привязан сразу) или вводит **код привязки** ребёнка (`pairingCode`, виден ребёнку
   в «Moje výsledky», после использования меняется). Чужой ребёнок → **404** (`ParentController::assertOwnChild`), не 403.
   `app:user:parent` создаёт родителя или сбрасывает ему пароль из консоли.
7. **Инструкция для пользователей.** Если меняется поведение или подпись в интерфейсе, обнови **оба** файла
   `docs/USER_GUIDE.cs.md` и `docs/USER_GUIDE.en.md`. Названия кнопок в них (жирным) должны совпадать с `translations/messages.*.yaml`.
8. **Языки.** Интерфейс на `cs` и `en`, URL с префиксом `/{_locale}`. Тексты вопросов только на чешском, пояснения на cs и en.
   Любой новый текст интерфейса добавлять в **оба** файла `translations/messages.*.yaml`.

## Банк вопросов

Формат (полная валидация — в `QuestionBankImporter::validate()`):

```yaml
questions:
  - id: "l2b-vs-001"          # стабильный, уникальный во ВСЕХ файлах; префикс = файл
    topic: "vyjmenovana-slova" # code из content/topics.yaml
    level: 2                   # 1 | 2 | 3
    type: "choice"             # choice: options (2–4) + correct (∈ options)
    prompt: "Doplň í/ý: s_kora"
    options: ["í", "ý"]
    correct: "ý"
    explanation: { cs: "…", en: "…" }
  - id: "l2b-par-001"
    type: "input"              # input: answers — все допустимые варианты
    answers: ["hrad"]
    # …
```

Правила для вопросов:

- **Как сравнивается ввод** (`AnswerChecker::normalize`): регистр не важен, пробелы схлопываются, `", "` нормализуется,
  точки, `!` и `?` в конце отбрасываются (если ответ не состоит из одного знака), **диакритика важна**.
  Поэтому вопросы про заглавные буквы делаются только как `choice`. Если нужна долгая гласная, это пишется в задании («Doplň í nebo ý»).
- **Не менять `id` существующих вопросов**: на них ссылается история ответов. Вопрос, удалённый из YAML, при импорте деактивируется (`active = false`), но не удаляется.
- **Вопрос однозначный.** Омофоны (být/bít, mýt/mít, výr/vír…) — только с контекстом. В пропусках mě/mně не должно подходить «mi».
- **Без дублей**: та же пара «слово + задание» не повторяется ни в одном файле.
- **Пояснения** — в терминах 3 класса, без падежей; лучше «komu? – mně».
- **После правки:** `docker compose restart`. Импорт запустится сам, а при ошибке в YAML контейнер не стартует, причина будет в `docker compose logs app`.

## Команды

Всё выполняется из корня репозитория.

```bash
# поднять / пересобрать / логи (app = php-fpm + Symfony, web = nginx)
docker compose up -d --build
docker compose logs -f app web

# консоль Symfony внутри контейнера (от www-data, чтобы SQLite оставалась доступной для записи)
docker compose exec app su -s /bin/sh www-data -c 'php bin/console <command>'

# одноразовый dev-контейнер с исходниками (vendor должен быть установлен, см. ниже)
DEV='docker run --rm -u '"$(id -u):$(id -g)"' -v '"$PWD"':/app -w /app -e APP_SECRET=x php:8.5-cli'
$DEV php bin/console lint:container
$DEV php bin/console lint:twig templates
$DEV php bin/console lint:yaml config translations content
$DEV php bin/console doctrine:schema:validate

# установить зависимости / добавить пакет (composer тоже в Docker)
docker run --rm -u $(id -u):$(id -g) -v $PWD:/app -w /app -e COMPOSER_HOME=/tmp/c composer:2 composer install
```

Изменения схемы делаются только через миграции:

```bash
$DEV php bin/console doctrine:migrations:diff -n   # генерирует migrations/VersionXXXX.php в dev-БД var/data/app.db
```

Миграции применяются при старте контейнера. `doctrine:schema:update` не использовать.

## Проверка изменений

Перед тем как сказать, что работа готова:

1. Прогнать линтеры из раздела «Команды», все должны пройти.
2. `docker compose up -d --build`, затем `docker compose logs app`: нужна строка `[OK] Topics: …, questions: …` и отсутствие `critical`.
   Сервис `app` должен стать `healthy` (`docker compose ps`).
3. Пройти затронутый сценарий через HTTP (curl с cookie-jar) или в браузере: регистрация → квиз → ответ → итоги → статистика.
   POST-формы требуют CSRF-токен со страницы. Для регистрации и логина ещё нужен заголовок `Origin: http://localhost:8080`:
   это stateless CSRF Symfony.
4. Если менялись вопросы: каждый канонический ответ (`correct` / `answers[0]`) должен засчитываться через `AnswerChecker`.
5. Если менялись доступ, формы или JS: проверить в настоящем браузере (headless Chrome + puppeteer-core), что в консоли нет ошибок CSP,
   и сценарий «родитель A не видит ребёнка родителя B» (404).
5. Если тестовые аккаунты больше не нужны, базу сбрасывает `docker compose down -v`. Это удаляет **все** данные, в том числе аккаунт родителя.

## Безопасность (не ослаблять)

- **APP_SECRET** в prod читается из `var/data/.app_secret` (`APP_SECRET_FILE`). Entrypoint пишет туда `$APP_SECRET`, если он задан,
  иначе один раз генерирует случайный. Не возвращать секрет по умолчанию в `compose.yaml`.
- **Вход:** `login_throttling` — 5 неудачных попыток / 15 минут. **Регистрация:** 5 аккаунтов / час с IP; **привязка по коду:** 10 / час
  на родителя (`config/packages/rate_limiter.yaml`). При тестах лимит регистраций быстро кончается — `docker compose up -d --force-recreate`
  сбрасывает его (хранилище — кеш в контейнере).
- **CSRF** на всех POST. Логин/формы/выход — stateless-токены (`csrf.yaml`): Symfony проверяет `Origin`/`Referer`, поэтому
  curl-тестам нужен `-H "Origin: http://localhost:8080"` (для GET-выхода — `Referer`). Выход — только ссылка `logout_path()`.
- **CSP** в `docker/nginx.conf`: `script-src 'self' 'unsafe-inline'` (inline-importmap), без `data:`. Поэтому CSS подключается
  `<link>` в `base.html.twig`, а **не** `import` из JS (AssetMapper превратил бы его в `data:`-модуль, CSP заблокирует весь `app.js`).
  Инлайн-стили разрешены (`style="width: …"` у полос прогресса).
- **Доступ:** квиз — только владельцу (`QuizController::assertOwner`), кабинет родителя — `#[IsGranted(ROLE_PARENT)]` + проверка родства.
- **Сессия:** в сессию сериализуются только id/username/roles/crc пароля (`User::__serialize`) — смена пароля разлогинивает другие сессии.
- **Прокси:** `TRUSTED_PROXIES` задавать только при внешнем HTTPS-прокси перед nginx; иначе `X-Forwarded-*` игнорируются.
- **Зависимости:** проверять `composer audit` (через Docker-образ `composer:2`).

## Подводные камни

- **php-fpm по умолчанию не передаёт env контейнера в PHP** — в `docker/php-fpm.conf` стоит `clear_env = no`. Кроме того, в образе
  выполняется `composer dump-env prod` (`.env.local.php`), иначе Symfony стартует в dev и падает на отсутствии DebugBundle.
- **Не возвращаться к Apache prefork:** с ним навигации периодически зависали на ~30 с (keep-alive + `RequestReadTimeout`),
  с nginx + php-fpm проблема ушла. Для замера времени ответа в логе nginx есть `$request_time`.
- **Страницы квиза** отдаются с `Cache-Control: no-store`, а `app.js` перезагружает страницу, восстановленную из back/forward-кеша:
  иначе ребёнок отправляет устаревшую форму. Повторный POST уже отвеченного вопроса ведёт на его результат (`QuizController::answer`).
- **SQLite-миграции пересоздают таблицы** (`DROP TABLE` + копирование). Внешние ключи в DBAL/SQLite выключены, поэтому каскады
  не срабатывают, но любую миграцию, трогающую `app_user`, проверять на базе **с ответами** (количество строк до/после).
- **`content/` смонтирован в контейнер read-only.** Код (`src/`, `templates/`, `translations/`) запечён в образ, поэтому после его изменения нужен `--build`.
- **Twig-доступ к методам с аргументом:** `topic.title(locale)`, `question.explanation(locale)`.

## Соглашения

- PHP-атрибуты для маршрутов, валидации, команд и прав (`#[Route]`, `#[Assert\…]`, `#[AsCommand]`, `#[IsGranted]`). Без YAML-роутинга.
- Autowiring; параметры передаются через `#[Autowire]`. Контроллеры тонкие, логика — в `src/Service`.
- Стиль кода как в окружающих файлах: `final` для сервисов и контроллеров, комментарии редкие и по делу (зачем, а не что).
- Коммиты — только когда об этом просят. Remote: `git@github.com:dpotekhin-prog/hezky-cesky.git`, ветка `main`.
