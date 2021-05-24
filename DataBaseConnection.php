<?php
$host = '127.0.0.1'; // адрес сервера
$database = 'back7'; // имя базы данных
$user = 'root'; // имя пользователя
$password = 'root'; // пароль

$link = mysqli_connect($host, $user, $password, $database) or die("error".mysqli_error($link));
