<?php
require_once("./responses/Response.php");
//TODO: убрать все лишние выводы

$method = $_SERVER['REQUEST_METHOD'];
$formData = getFormData($method);
$url = (isset($_GET['q'])) ? $_GET['q'] : '';

$url = rtrim($url, '/'); //Удаляет "/" из конца строки
$urls = explode('/', $url);

$controller = $urls[0];
$urlData = array_slice($urls, 1); //удаляем роутер из запроса
$response = new Response(); // Подключаем файл-роутер и запускаем главную функцию

include_once 'controllers/' . $controller . '.php'; //include_once подключает внешний файл с кодом

route($method, $urlData, $formData);

function getFormData($method) {
    if ($method === 'GET') return $_GET;
    if ($method === 'POST' && !empty($_POST)) return $_POST;

    $incomingData = file_get_contents('php://input');
    $decodedJSON = json_decode($incomingData); //пытаемся преобразовать то, что нам пришло из JSON в объект PHP
    if ($decodedJSON)
    {
        $data = $decodedJSON;
    }
    else
    {
        $data = array();
        $exploded = explode('&', file_get_contents('php://input'));
        foreach($exploded as $pair)
        {
            $item = explode('=', $pair);
            if (count($item) == 2)
            {
                $data[urldecode($item[0])] = urldecode($item[1]);
            }
        }
    }
    return $data;
}
?>



