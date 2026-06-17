# MaxNotify 3 для miniShop3

**MaxNotify 3** — отдельный компонент для MODX Revolution 3 и miniShop3,
который отправляет уведомления о заказах в мессенджер MAX через
<a href="https://dev.max.ru/docs/maxbusiness/connection">официальный MAX Business API</a>
или через сервис (<a href="https://rumaxbot.ru">https://rumaxbot.ru</a>).

Компонент помогает владельцу и менеджерам интернет-магазина быстро узнавать
о новых заказах и изменениях их статуса без постоянной проверки панели MODX.

## Автор

Разработчик: Mishiko23  
Email: bigo2008@gmail.com

## Возможности

- уведомление сразу после создания заказа miniShop3;
- уведомление при изменении статуса заказа;
- фильтрация уведомлений по ID статусов;
- номер, сумма и состав заказа;
- имя, телефон и email покупателя;
- адрес доставки и комментарий клиента;
- название способа доставки и оплаты;
- ссылка на конкретный заказ в панели MODX;
- официальный MAX Business API и сервис rumaxbot.ru на выбор;
- отправка одному или нескольким чатам, каналам или пользователям;
- сообщения в формате Markdown или HTML;
- редактируемые чанки сообщений;
- запись ошибок API и соединения в журнал MODX.

## Требования

- MODX Revolution 3.x;
- miniShop3 1.x;
- PHP 8.1+;
- PHP cURL или включённый `allow_url_fopen`;
- токен официального MAX-бота или канал и API-ключ rumaxbot.ru.

Компонент проверен с MODX Revolution 3.2.1-pl и miniShop3 1.11.1-beta1.

## Установка

1. Откройте в MODX раздел **Пакеты → Установщик**.
2. Найдите компонент MaxNotify 3 в репозитории.
3. Нажмите **Скачать**, затем **Установить**.
4. После установки очистите кэш MODX.

## Настройка

Откройте **Системные настройки** и выберите пространство имён `maxnotify3`.

Основные параметры:

- <strong>maxnotify3.enabled</strong> — включает или отключает компонент;
- <strong>maxnotify3.provider</strong> — `rumaxbot` или `maxbusiness`;
- <strong>maxnotify3.format</strong> — `markdown` или `html`;
- <strong>maxnotify3.timeout</strong> — таймаут API-запроса в секундах;
- <strong>maxnotify3.notify_new_order</strong> — уведомления о новых заказах;
- <strong>maxnotify3.notify_status_change</strong> — уведомления о смене статуса;
- <strong>maxnotify3.statuses</strong> — ID статусов через запятую, пустое поле разрешает все.

## Подключение официального MAX Business API

Официальное подключение доступно верифицированным организациям и ИП, которые
являются резидентами РФ.

Создайте и верифицируйте профиль на
<a href="https://business.max.ru/">платформе MAX для партнёров</a>.
Создайте чат-бота и дождитесь прохождения модерации.
Получите токен в разделе <strong>Чат-боты → Перейти → Расширенные настройки → Настроить</strong>.
Добавьте бота в нужный чат или канал либо запустите личный диалог с ботом.
Получите <strong>chat_id</strong> или <strong>user_id</strong> через
<strong>Webhook/Long Polling API MAX</strong>.
Установите `maxnotify3.provider` в значение `maxbusiness`.

Заполните настройки:

- <strong>maxnotify3.max_token</strong> — токен бота;
- <strong>maxnotify3.max_recipient_type</strong> — <strong>chat_id</strong> или <strong>user_id</strong>;
- <strong>maxnotify3.max_recipient_ids</strong> — один или несколько ID через запятую;
- <strong>maxnotify3.max_notify</strong> — уведомлять участников чата;
- <strong>maxnotify3.max_disable_link_preview</strong> — отключить превью ссылок.

Официальный API принимает сообщения длиной до 4000 символов. Более длинные
уведомления MaxNotify 3 автоматически сокращает.

## Подключение rumaxbot.ru

1. Зарегистрируйтесь на rumaxbot.ru и подтвердите email.
2. Создайте канал.
3. Подключите MAX-бота к каналу по инструкции сервиса.
4. Создайте API-ключ канала.
5. Укажите ключ в настройке `maxnotify3.api_key`.

API-ключ нельзя публиковать или добавлять в репозиторий.

## Шаблоны сообщений

После установки в категории элементов `MaxNotify 3` будут созданы чанки:

- `maxNotify3OrderCreated` — новый заказ в Markdown;
- `maxNotify3OrderStatus` — новый статус в Markdown;
- `maxNotify3OrderCreatedHtml` — новый заказ в HTML;
- `maxNotify3OrderStatusHtml` — новый статус в HTML.

Доступные плейсхолдеры: `num`, `uuid`, `cost`, `receiver`, `first_name`,
`last_name`, `phone`, `email`, `address`, `comment`, `order_comment`,
`products`, `delivery_name`, `payment_name`, `status_name`, `manager_url` и
другие поля заказа miniShop3.
