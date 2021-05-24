<?php
include_once "DataBaseConnection.php";
require_once("./responses/Response.php");
require_once("./services/Authorization.php");

global $link, $response;
$auth = new Authorization();

function route($method, $urlData, $formData) {

    global $response;

    switch ($method){
        case "GET": checkGetRequest($urlData); break;
        case "POST": checkPostRequest($formData);  break;
        case "PATCH": checkPatchRequest($urlData, $formData); break;
        case "DELETE": checkDeleteRequest($urlData); break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///GET///////////////////////////////////////////////////////

function checkGetRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])){
        if ($urlData[1] == "peoples"){
            getCityUsers($urlData[0]);
        }
        else getCityById($urlData[0]);
    }
    else if (empty($urlData)) getCities();
    else $response -> BadRequest();

}

function getCities(){

    global $link;
    global $response;

    try {
        $cities = mysqli_query($link, "SELECT * FROM `city`");
        $resCities = array();

        $curCity = mysqli_fetch_assoc($cities);
        while ($curCity){
            array_push($resCities, $curCity);
            $curCity = mysqli_fetch_assoc($cities);
        }
        header('Content-Type: application/json');
        echo json_encode($resCities);
        //$response->OK();
    }
    catch(Exception $e){
        $response -> NotFound();
    }
}

function getCityById($cityId){

    global $link;
    global $response;

    try{
        $city = mysqli_query($link, "SELECT * FROM `city` WHERE (`id` = $cityId)");
        $curCity = mysqli_fetch_assoc($city);

        if(!$curCity) $response->NotFound();
        else {
            header('Content-Type: application/json');
            echo json_encode($curCity);
            $response->OK();
        }

    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function getCityUsers($cityId){

    global $link;
    global $response;

    try{
        $city = mysqli_query($link, "SELECT * FROM `city` WHERE (`id` = $cityId)");
        $curCity = mysqli_fetch_assoc($city);

        if(!$curCity) $response->NotFound();
        else {
            $cityUsers = mysqli_query($link, "SELECT `name`, `surname`, `username`, `id`, `avatar`, `status` FROM `user` WHERE (`city` = $cityId)");
            $resUsers = array();

            $curUser = mysqli_fetch_assoc($cityUsers);
            if($curUser == null) $response->NotFound();
            else{
                while ($curUser){
                    array_push($resUsers, $curUser);
                    $curUser = mysqli_fetch_assoc($cityUsers);
                }
                header('Content-Type: application/json');
                echo json_encode($resUsers);
            }
        }
        //$response->OK();
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($formData){

    global $response;

    if ($formData->name != null){
        createCity($formData->name);
    }
    else $response -> BadRequest();
}

function createCity($newCityName){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            $newCityCreated = mysqli_query($link, "INSERT INTO `city`(`id`, `name`) VALUES (null, '$newCityName')");

            if (!$newCityCreated) $response->BadRequest();
            else {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'Name' => $newCityName
                ));
                $response->OK();
            }
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

///PATCH///////////////////////////////////////////////////////

function checkPatchRequest($urlData, $formData){
    global $response;

    if (is_numeric($urlData[0])) {
        setNewCityName($urlData[0], $formData);
    }
    else $response -> BadRequest();

}

function setNewCityName($cityId, $formData){
    global $auth;
    global $response;
    global $link;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            $newCityName = $formData->name;
            $cityIdExisted = cityIdExisted($cityId);
            $cityNameExisted = cityNameExisted($newCityName);

            if ($cityIdExisted and !$cityNameExisted) {

                mysqli_query($link, "UPDATE `city` SET `name`='$newCityName' WHERE `id`=$cityId") or die($response->BadRequest());

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Name' => $newCityName
                ));
                $response->OK();
            } else $response->BadRequest();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function cityIdExisted($cityId): bool{

    global $response;
    global $link;

    $city = mysqli_query($link, "SELECT * FROM `city` WHERE `id`=$cityId") or die($response->BadRequest());
    $city = mysqli_fetch_assoc($city);
    if ($city) return true;
    else return false;
}

function cityNameExisted($newCityName): bool{

    global $response;
    global $link;

    $city = mysqli_query($link, "SELECT * FROM `city` WHERE `name`='$newCityName'") or die($response->BadRequest());
    $city = mysqli_fetch_assoc($city);
    if ($city) return true;
    else return false;

}

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])) {
        deleteCity($urlData[0]);
    }
    else $response -> BadRequest();
}

function deleteCity($cityId){
    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            if (cityIdExisted($cityId)) {
                mysqli_query($link, "DELETE FROM `city` WHERE `id`=$cityId") or die($response->BadRequest());
                $response->OK();
            } else $response->BadRequest();
        }
        else $response ->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}