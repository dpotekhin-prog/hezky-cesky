# Čeština pro 3. třídu

Тренажёр по чешскому языку за 3 класс: 3 уровня сложности, вопросы с вариантами и со свободным вводом,
регистрация детей, ранги 0–10 (игроки ФК Барселона) и статистика для родителя.

Стек: Symfony 8.1, PHP 8.5, SQLite, Apache. Всё в одном контейнере.

## Запуск

```bash
docker compose up -d --build
# создать родителя (пароль спросит интерактивно)
docker compose exec app su -s /bin/sh www-data -c 'php bin/console app:user:parent rodic'
```

Открыть http://localhost:8080. Порт можно поменять: `APP_PORT=9000 docker compose up -d`.

Дети регистрируются сами на странице «Registrace». Родитель видит всех детей в разделе «Pro rodiče».

База лежит в docker volume `czech-quiz_app_data` и переживает пересборку.
`docker compose down -v` удаляет её вместе со всеми результатами.

## Правила

- Каждый вопрос в обычном режиме выпадает ребёнку **один раз**. Все ответы сохраняются: что ввёл, правильно ли, когда.
- Вопросы с ошибкой возвращаются только в режиме «Opravit chyby» (кнопка на главной).
- Ранг на каждом уровне: `очки = правильные − 0.5 × ошибки`, `ранг = floor(10 × очки / число вопросов уровня)`.
  Игроки для рангов лежат в `content/players.yaml`.
- Уровень 2 (и 3) открывается, когда на предыдущем пройдено ≥ 80 % вопросов с успешностью ≥ 70 %.
  Пороги заданы константами в `src/Service/StatsProvider.php`. Начатый уровень остаётся открытым.

## Вопросы

Темы лежат в `content/topics.yaml`, вопросы в `content/questions/*.yaml`. Формат:

```yaml
- id: "l1-uu-001"          # стабильный уникальный id
  topic: "u-u"
  level: 1
  type: "input"            # или "choice" с options + correct
  prompt: "Doplň ú nebo ů a napiš celé slovo: d_m"
  answers: ["dům"]         # все допустимые ответы; регистр и пробелы не важны, диакритика важна
  explanation: { cs: "…", en: "…" }
```

Папка `content/` смонтирована в контейнер, поэтому после правки YAML достаточно `docker compose restart`
или вручную: `docker compose exec app su -s /bin/sh www-data -c 'php bin/console app:questions:import'`.
Импорт находит вопрос по `id` и обновляет его. Вопрос, удалённый из YAML, деактивируется, а история ответов по нему остаётся.

## Разработка без Docker-сборки

```bash
docker run --rm -it -u $(id -u):$(id -g) -v $PWD:/app -w /app -p 8000:8000 php:8.5-cli \
  sh -c 'php bin/console d:m:m -n && php bin/console app:questions:import && php -S 0.0.0.0:8000 -t public'
```
