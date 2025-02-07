<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<!-- Небольшая форма для примера -->
<body>
    <form action="" method="post">
        <input name="event_id" type="number" value="003">
        <input name="event_date" type="datetime-local" value="2021-08-21 13:11:11">
        <input name="ticket_adult_price" type="number" value="700">
        <input name="ticket_adult_quantity" type="number" value="2">
        <input name="ticket_kid_price" type="number" value="300">
        <input name="ticket_kid_quantity" type="number" value="3">
        <input name="user_id" type="number" value="00451">
        <button type="submit">Submit</button>
    </form>
</body>

</html>

<?php
// Функция отправки данных api, принимает ссылку и массив данных для отправки, извлекает из json и возвращает полученные данные или ошибку 
function sendToApi($url, $params)
{
    $result = file_get_contents("$url", false, stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($params)
        )
    )));

    $decoded = json_decode($result, true);

    if ($decoded != 0) {
        return array_values($decoded)[0];
    } else {
        return 'Error. try again later.';
    };
};

// Главная функция 
function addToDb()
{
    // Подключение к базе данных, ошибка в случае отсутствия подключения
    $database_host = 'testhost';
    $database_user = 'testuser';
    $database_password = 'testpass';
    $database_name = 'testdb';

    $connect = mysqli_connect($database_host, $database_user, $database_password, $database_name, 3306);

    if (!$connect) {
        echo "<script>console.log('no connection to db')</script>";
        die("Connection failed");
    }

    // Функция генерации восьмизначного штрих-кода, возвращает новый штрих-код, в первый раз вызывается для генерации штрих-кода,
    // повторно вызывается если api бронирования возвращает ошибку
    function newBarcode()
    {
        $randomNum = mt_rand(1, 99999999);
        return str_pad($randomNum, 8, '0', STR_PAD_LEFT);
    }

    $barcode = newBarcode();

    //Создание базы данных если она не существует

    $sqlOne = mysqli_query(
        $connect,
        "CREATE TABLE IF NOT EXISTS orders (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
		event_id VARCHAR(255) NOT NULL, 
		event_date DATETIME NOT NULL, 
		ticket_adult_price INT NOT NULL, 
		ticket_adult_quantity INT NOT NULL, 
		ticket_kid_price INT NOT NULL, 
		ticket_kid_quantity INT NOT NULL, 
		barcode VARCHAR(255) NOT NULL,
		user_id VARCHAR(255) NOT NULL,
		equal_price INT NOT NULL,
		created DATETIME NOT NULL);"
    );

    if (
        //Если поля формы заполнены, то данные полей присваиваются соответствующим переменным
        isset($_POST["event_id"]) && isset($_POST["event_date"]) &&
        isset($_POST["ticket_adult_price"]) && isset($_POST["ticket_adult_quantity"]) &&
        isset($_POST["ticket_kid_price"])&& isset($_POST["user_id"])) 
    {
        $event_id = $_POST["event_id"];
        $event_date = $_POST["event_date"];
        $ticket_adult_price = $_POST["ticket_adult_price"];
        $ticket_adult_quantity = $_POST["ticket_adult_quantity"];
        $ticket_kid_price = $_POST["ticket_kid_price"];
        $ticket_kid_quantity = $_POST["ticket_kid_quantity"];
        $user_id = $_POST["user_id"];

        // Цена всего заказа высчитывается на основании указанных данных 

        $equal_price = $ticket_adult_price * $ticket_adult_quantity + $ticket_kid_price * $ticket_kid_quantity;

        // Пока не будет получен положительный ответ от api бронирования, 
        // продолжать попытки отправить api данные заказа и каждый раз генерировать новый штрих-код, поскольку
        // у api могут быть только два варианта ответа - {message: 'order successfully booked'} или ошибка

        do {
            $booked = sendToApi('https://api.site.com/book', array(
                'event_id' => $event_id,
                'event_date' => $event_date,
                'ticket_adult_price' => $ticket_adult_price,
                'ticket_adult_quantity' => $ticket_adult_quantity,
                'ticket_kid_price' => $ticket_kid_price,
                'ticket_kid_quantity' => $ticket_kid_quantity,
                'barcode' => $barcode,
            ));

            if ($booked !== 'order successfully booked') {
                $barcode = newBarcode();
            };
        } while ($booked !== 'order successfully booked');

        // Если api бронирования подтверждает бронь, то отправить штрих-код api подтверждения, или сообщить об ошибке 

        if ($booked === 'order successfully booked') {
            $approved = sendToApi('https://api.site.com/approve', array(
                'barcode' => $barcode,
            ));

        // Если api подтверждения подтверждает штрих-код, то внести данные в таблицу mysql, после закрыть соединение или сообщить об ошибке 

            if ($approved === 'order successfully approved') {
                $sqlTwo = mysqli_query($connect, "INSERT INTO orders (
                    event_id,
                    event_date, 
                    ticket_adult_price, 
                    ticket_adult_quantity, 
                    ticket_kid_price, 
                    ticket_kid_quantity, 
                    barcode, 
                    user_id, 
                    equal_price, 
                    created) VALUES (
                    '$event_id', 
                    '$event_date', 
                    '$ticket_adult_price', 
                    '$ticket_adult_quantity', 
                    '$ticket_kid_price', 
                    '$ticket_kid_quantity', 
                    '$barcode', 
                    '$user_id', 
                    '$equal_price', 
                    NOW());");
                if ($sqlTwo) {
                    //очистить поля формы
                    mysqli_close($connect);
                } else {
                    //выдать ошибку соединения
                    echo '<script>console.log("Произошла ошибка");</script>';
                };
            } else {
                echo "<script>console.log($approved);</script>";
            };
        };
    };
};

addToDb();
 
?>