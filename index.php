<?php
echo 'Hello123';
echo "<br>";
$dbName = getenv('POSTGRES_DB');
$dbPassword = getenv('POSTGRES_PASSWORD');
$dbUser = getenv('POSTGRES_USER');
$conn_string = "pgsql:host=localhost;port=5432;dbname=$dbUser;user=$dbUser;password=$dbPassword";
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
