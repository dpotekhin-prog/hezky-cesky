# AGENTS.md — инструкция для AI-агентов

Hezky česky — тренажёр по чешскому языку для ученика 3 класса чешской школы. Ученик — русскоязычный ребёнок.
Здесь собраны правила продукта и команды, которые нужны, чтобы безопасно менять проект.
Как запустить приложение, описано в [README.md](README.md).

## Стек

- PHP 8.5, Symfony 8.1, Twig, Doctrine ORM 3, SQLite (файл `var/data/app.db`).
- Фронтенд — серверный рендер на Twig, AssetMapper (`assets/app.js`, `assets/styles/app.css`), без сборщика и без JS-фреймворков.
- Один Docker-контейнер (`php:8.5-apache`). Локально PHP не нужен: все команды выполняются в Docker.
- Тестов PHPUnit пока нет, `tests/` пустой. Как проверять изменения — в разделе «Проверка».

## Структура

| Путь | Что там |
|---|---|
| `src/Entity/` | `User`, `Topic`, `Question`, `QuizSession`, `Answer` |
| `src/Controller/` | тонкие контроллеры: `Home`, `Quiz`, `Stats` (в т.ч. кабинет родителя), `Security`, `Registration`, `Root` |
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
| `docker/entrypoint.sh` | при старте: cache:warmup → migrations → импорт вопросов |

## Правила продукта (не ломать)

1. **Каждый вопрос в обычном режиме ребёнок получает один раз.** Всё, на что он уже отвечал, правильно или нет, `QuizFactory::create()` пропускает.
2. **Ошибки возвращаются только в режиме «Opravit chyby»** (`QuizSession::MODE_MISTAKES`). Туда попадают вопросы, на которые последний ответ был неверным.
3. **Каждый ответ сохраняется** в `Answer`: что введено, верно ли, когда, в каком квизе. Историю ответов не удалять и не переписывать.
4. **3 уровня сложности.** На каждом уровне у ребёнка свой ранг 0–10:
   `очки = правильные − 0.5 × ошибки` (не меньше 0), `ранг = floor(10 × очки / число активных вопросов уровня)`.
   Каждому рангу соответствует игрок из `content/players.yaml`: внизу текущий состав, наверху легенды, ранг 10 — Месси.
5. **Уровни открываются по очереди.** Уровень N+1 открыт, если на уровне N пройдено ≥ 80 % вопросов и правильных ≥ 70 %.
   Начатый уровень (есть хоть один ответ) остаётся открытым, даже если позже добавились вопросы.
6. **Роли.** Дети регистрируются сами и получают `ROLE_CHILD`. Родитель (`ROLE_PARENT`) создаётся только командой `app:user:parent`
   и видит статистику всех детей (`/{_locale}/parent`).
7. **Языки.** Интерфейс на `cs` и `en`, URL с префиксом `/{_locale}`. Тексты вопросов только на чешском, пояснения на cs и en.
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
# поднять / пересобрать / логи
docker compose up -d --build
docker compose logs -f app

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
3. Пройти затронутый сценарий через HTTP (curl с cookie-jar) или в браузере: регистрация → квиз → ответ → итоги → статистика.
   POST-формы требуют CSRF-токен со страницы. Для регистрации и логина ещё нужен заголовок `Origin: http://localhost:8080`:
   это stateless CSRF Symfony.
4. Если менялись вопросы: каждый канонический ответ (`correct` / `answers[0]`) должен засчитываться через `AnswerChecker`.
5. Если тестовые аккаунты больше не нужны, базу сбрасывает `docker compose down -v`. Это удаляет **все** данные, в том числе аккаунт родителя.

## Подводные камни

- **Apache + mod_php не передают env контейнера в `$_SERVER`.** Поэтому в образе выполняется `composer dump-env prod`
  (`.env.local.php`), а `APP_SECRET`/`DEFAULT_URI` пробрасываются через `PassEnv` в `docker/apache.conf`.
  Без этого Symfony стартует в dev и падает на отсутствии DebugBundle.
- **`content/` смонтирован в контейнер read-only.** Код (`src/`, `templates/`, `translations/`) запечён в образ, поэтому после его изменения нужен `--build`.
- **Stateless CSRF** (`config/packages/csrf.yaml`) для логина и форм проверяет Origin/Referer. Для квиза используются обычные session-токены
  (`quiz_start`, `quiz{id}`).
- **Twig-доступ к методам с аргументом:** `topic.title(locale)`, `question.explanation(locale)`.

## Соглашения

- PHP-атрибуты для маршрутов, валидации, команд и прав (`#[Route]`, `#[Assert\…]`, `#[AsCommand]`, `#[IsGranted]`). Без YAML-роутинга.
- Autowiring; параметры передаются через `#[Autowire]`. Контроллеры тонкие, логика — в `src/Service`.
- Стиль кода как в окружающих файлах: `final` для сервисов и контроллеров, комментарии редкие и по делу (зачем, а не что).
- Коммиты — только когда об этом просят. Remote: `git@github.com:dpotekhin-prog/hezky-cesky.git`, ветка `main`.
