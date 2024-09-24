<?php
echo 'Hello123';
echo "<br>";
$conn_string = "pgsql:host=localhost;port=5432;dbname=bitrixAppDb;user=kirill;password=bitrix24lib";
try {
    $db = new PDO($conn_string);

    // Установка режима обработки ошибок
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Подключение к базе данных успешно установлено.";
}catch (PDOException $error)
{
    echo "Ошибка :". $error->getMessage();
}

?>
