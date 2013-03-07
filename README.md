PHP Planfix client (Planfix_API) v.1.0
======================================

Базовый класс для взаимодействия с API сервиса [ПланФикс](http://planfix.ru/).

Представляет собой удобный интерфейс между клиентским приложением и самим сервисом, так как включает в себя все необходимые тонкости отправки API-запросов и получения ответов в формате XML.

Не является официальным продуктом компании ПланФикс, а всего лишь любительская разработка активного пользователя сервиса.

Пример использования
--------------------

1. Подключаем клиент:

        require 'Planfix_API.php';
        $PF = new Planfix_API(array('apiKey' => 'YOUR_API_KEY', 'apiSecret' => 'YOUR_API_SECRET'));
        $PF->setAccount('YOUR_ACCOUNT');

   Подразумевается, что у вас уже есть ApiKey и ApiSecret. Получить их можно [здесь](http://planfix.ru/dev.html).
   Создаем обьект клиента и устанавливаем аккаунт в ПланФиксе ({аккаунт}.planfix.ru).

2. Проходим процедуру авторизации пользователя:

        session_start();
        if (empty($_SESSION['planfixSid'])) {
            $PF->setUser(array('login' => 'YOUR_LOGIN', 'password' => 'YOUR_PASSWORD'));
            $PF->authenticate();
            $_SESSION['planfixSid'] = $PF->getSid();
        }
        $PF->setSid($_SESSION['planfixSid']);

   Для авторизации пользователя нужен его логин и пароль в ПланФикс.
   Так как авторизацию не рекомендуется вызывать слишком часто, то полученный идентификатор Sid мы сохраняем в обычную PHP-сессию.

3. Выполнение одного запроса:

        $method = 'client.getList';
        $params = array(
            'user' => array(
                array('id' => 1)
            ),
            'pageCurrent' => 1
        );
        $clients = $PF->api($method, $params);

   Основной метод класса — `api`. С его помощью можно вызывать методы API и передавать их параметры в виде простых массивов. Про формат ответа смотреть ниже.
   Подробное описание доступных методов и параметров доступно в официальной [документации](http://planfix.ru/docs/%D0%A1%D0%BF%D0%B8%D1%81%D0%BE%D0%BA_%D1%84%D1%83%D0%BD%D0%BA%D1%86%D0%B8%D0%B9).

4. Выполнение пакета запросов:

        $batch = array(
            array(
                'method' => 'project.getList',
                'params' => array(
                    'user' => array(
                        array('id' => 1)
                    ),
                    'pageCurrent' => 1
                )
            ),
            array(
                'method' => 'task.getList',
                'params' => array(
                    'user' => array(
                        array('id' => 1)
                    ),
                    'pageCurrent'   => 1
                )
            )
        );
        list($projects, $tasks) = $PF->api($batch);

   Если нужно выполнить несколько запросов, то чтобы не выполнять их по-одному в цикле — внедрена возможность выполнения мультизапроса. На вход `api` подаётся массив запросов, а на выходе будет массив ответов в той же последовательности, что и запросы.


Формат ответа
-------------

Структура ответа состоит из 4-ех частей:

1. Параметр `success` принимает значение 0 или 1. Если запрос выполнен успешно, то 1, в обратном случае — 0.
2. Параметр `error_str` — строка с ошибкой, если запрос не успешен. В обратном случае — пустая строка.
3. Параметр `meta` содержит два подпараметра `count` и `totalCount`. Количество результатов в данном ответе и общее количество результатов соответственно. Максимум результатов для одного запроса — 100, если элементов больше ста, то по этим параметрам сожно понять сколько еще нужно сформировать запросов.
4. Параметр `data` содержит массив с результатами в виде простого массива, по своей структуре повторяющий оригинальный XML принятый в качестве ответа.

        Array
        (
            [success] => 1
            [error_str] => 
            [meta] => Array
                (
                    [totalCount] => 1
                    [count] => 1
                )
            [data] => Array
                (
                    [projects] => Array
                        (
                            [0] => Array
                                (
                                    [project] => Array
                                        (
                                            [id] => id
                                            [title] => title
                                            [description] => description
                                            [owner] => Array
                                                (
                                                    [id] => id
                                                    [name] => name
                                                )
                                            [client] => Array
                                                (
                                                    [id] => id
                                                    [name] => name
                                                )
                                            [group] => Array
                                                (
                                                    [id] => id
                                                    [name] => name
                                                )
                                            [status] => status
                                            [hidden] => hidden
                                            [hasEndDate] => hasEndDate
                                            [taskCount] => taskCount
                                            [isOverdued] => isOverdued
                                            [isCloseToDeadline] => isCloseToDeadline
                                            [beginDate] => beginDate
                                        )
                                )
                        )
                )
        )