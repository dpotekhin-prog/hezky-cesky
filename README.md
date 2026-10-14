# Hezky česky — čeština pro 3. třídu

Тренажёр по чешскому языку за 3 класс.

- 511 вопросов в 3 уровнях сложности: с вариантами ответа и со свободным вводом.
- Регистрация детей.
- На каждом уровне свой ранг 0–10, ранги привязаны к игрокам ФК Барселона.
- Статистика для родителя.

Стек: Symfony 8.1, PHP 8.5, SQLite, Apache. Всё в одном Docker-контейнере.
Для AI-агентов есть отдельный документ: [AGENTS.md](AGENTS.md).

## Как поднять приложение

### 1. Что нужно

- Docker 20+ с плагином Compose v2 (проверка: `docker compose version`).
- Свободный порт 8080 (можно взять другой, см. ниже).
- PHP и Composer на машине **не нужны**: всё собирается внутри образа.

### 2. Скачать и запустить

```bash
git clone git@github.com:dpotekhin-prog/hezky-cesky.git
cd hezky-cesky
docker compose up -d --build
```

Первая сборка занимает 1–3 минуты. При каждом старте контейнер сам:

1. прогревает кеш;
2. применяет миграции БД;
3. загружает вопросы из `content/`.

Проверить, что всё поднялось:

```bash
docker compose logs app | grep 'Topics:'
# [OK] Topics: 11, questions: 511, deactivated: 0
```

### 3. Открыть

```bash
xdg-open http://localhost:8080     # macOS: open http://localhost:8080
```

### 4. Создать аккаунт родителя

Пароль команда спросит интерактивно, минимум 6 символов:

```bash
docker compose exec app su -s /bin/sh www-data -c 'php bin/console app:user:parent rodic'
```

Этой же командой можно сбросить пароль родителю или сделать родителем существующего пользователя.

### 5. Аккаунт ребёнка

Ребёнок регистрируется сам: ссылка **Registrace** на странице входа.
Родитель после входа видит всех детей в разделе **Pro rodiče**.

## Повседневные команды

| Что | Команда |
|---|---|
| Остановить | `docker compose stop` |
| Запустить снова | `docker compose start` |
| Логи | `docker compose logs -f app` |
| Обновить код после `git pull` | `docker compose up -d --build` |
| Перечитать вопросы после правки YAML | `docker compose restart` |
| Другой порт | `APP_PORT=9000 docker compose up -d` |
| Бэкап базы | `docker compose cp app:/var/www/app/var/data/app.db ./backup-$(date +%F).db` |
| Удалить всё вместе с результатами | `docker compose down -v` |

База лежит в Docker volume `<папка>_app_data` и переживает пересборку и перезапуск. Удаляет её только `down -v`.

Чтобы открыть приложение с другого устройства в домашней сети (например, с планшета ребёнка), зайдите на
`http://<IP-компьютера>:8080`. Если порт закрыт, откройте его в файрволе.
Для доступа из интернета нужны HTTPS и свой `APP_SECRET` (`APP_SECRET=... docker compose up -d`).

## Правила

- **Вопросы не повторяются.** В обычном режиме каждый вопрос выпадает ребёнку **один раз**. Сохраняется каждый ответ: что введено, правильно ли, когда.
- **Ошибки.** Вопросы с ошибкой возвращаются только в режиме **Opravit chyby** (кнопка на главной).
- **Ранг.** Считается отдельно для каждого уровня:
  `очки = правильные − 0.5 × ошибки`, `ранг = floor(10 × очки / число вопросов уровня)`.
  От ранга 0 к 10: Balde → Cubarsí → Fermín → Gavi → Raphinha → Pedri → Lamine Yamal → Puyol → Xavi → Iniesta → **Messi**.
  Список игроков — в `content/players.yaml`.
- **Открытие уровней.** Уровни 2 и 3 открываются, когда на предыдущем уровне пройдено ≥ 80 % вопросов и правильных ≥ 70 %.
  Пороги — константы в `src/Service/StatsProvider.php`. Начатый уровень остаётся открытым.

## Вопросы

Темы описаны в `content/topics.yaml`, вопросы лежат в `content/questions/*.yaml`. Формат вопроса:

```yaml
- id: "l1-uu-001"          # стабильный уникальный id, не менять
  topic: "u-u"
  level: 1
  type: "input"            # или "choice" с options + correct
  prompt: "Doplň ú nebo ů a napiš celé slovo: d_m"
  answers: ["dům"]         # все допустимые ответы; регистр и пробелы не важны, диакритика важна
  explanation: { cs: "…", en: "…" }
```

Папка `content/` смонтирована в контейнер. После правки YAML достаточно `docker compose restart`.
Импорт находит вопрос по `id` и обновляет его. Вопрос, удалённый из YAML, деактивируется, а история ответов по нему остаётся.
Если в YAML ошибка, контейнер не стартует, а причина видна в `docker compose logs app`.

## Разработка

Нужны зависимости, включая dev-пакеты (ставятся через Docker):

```bash
docker run --rm -u $(id -u):$(id -g) -v $PWD:/app -w /app -e COMPOSER_HOME=/tmp/c composer:2 composer install
```

Dev-сервер с автоподхватом изменений в коде, без пересборки образа, на http://localhost:8000:

```bash
docker run --rm -it -u $(id -u):$(id -g) -v $PWD:/app -w /app -e APP_SECRET=dev -p 8000:8000 php:8.5-cli \
  sh -c 'mkdir -p var/data && php bin/console d:m:m -n && php bin/console app:questions:import && php -S 0.0.0.0:8000 -t public'
```

Линтеры, миграции и правила для изменений описаны в [AGENTS.md](AGENTS.md).

## Если что-то не так

| Симптом | Что делать |
|---|---|
| `port is already allocated` | Порт 8080 занят: запустите на другом, `APP_PORT=9000 docker compose up -d` |
| Контейнер перезапускается по кругу | `docker compose logs app`: чаще всего это ошибка в YAML вопросов, в логе будут файл и номер вопроса |
| Ошибка 500 | `docker compose logs app \| grep -i critical` |
| Забыли пароль родителя | Повторите команду из шага 4 с тем же именем |
| Ребёнок забыл пароль | Пока только через пересоздание аккаунта, сброса пароля для детей нет |
