<?php
include_once "DataBaseConnection.php";
require_once("./responses/Response.php");
require_once("./statuses/Status.php");

global $link, $response;
$status = new Status();

function route($method, $urlData, $formData) {

    global $response;

    switch ($method){
        case "POST": checkPostRequest($urlData);  break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($urlData){

    global $response;

    if (empty($urlData)){
        doLogout();
    }
    else $response -> BadRequest();
}

function doLogout(){
    global $link;
    global $response;

    try{
        $token = getallheaders()["Authorization"];
        $token = explode(" ", $token, 2);
        $token = $token[1];

        if (tokenExisted($token)) {

            mysqli_query($link, "UPDATE `user` SET `status`='offline', `token`=null WHERE `token`= '$token'");
            $response->OK();
        }
        else $response->BadRequest();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function tokenExisted($token): bool{

    global $response;
    global $link;

    if(!empty($token)) {
        $user = mysqli_query($link, "SELECT * FROM `user` WHERE `token`='$token'") or die($response->BadRequest());
        $user = mysqli_fetch_assoc($user);
        if ($user) return true;
        else return false;
    }
    else return false;
}

