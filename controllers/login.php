<?php
include_once "DataBaseConnection.php";
require_once("./responses/Response.php");
require_once("./statuses/Status.php");

global $link, $response;
$status = new Status();

function route($method, $urlData, $formData) {

    global $response;

    switch ($method){
        case "POST": checkPostRequest($urlData, $formData);  break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($urlData, $formData){

    global $response;

    if (empty($urlData)){
        doLogin($formData->username, $formData->password);
    }
    else $response -> BadRequest();
}

function doLogin($username, $password){
    global $link;
    global $response;

    try{
        if (usernameExisted($username)) {

            $user = mysqli_query($link, "SELECT * FROM `user` WHERE `username`='$username'") or die($response->BadRequest());
            $user = mysqli_fetch_assoc($user);

            if($user["password"] == $password){
                $token = createToken();
                //var_dump($token);
                mysqli_query($link, "UPDATE `user` SET `status`='online', `token`='$token' WHERE `username`= '$username'");

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Token' => $token
                ));
                //$response->OK();
            }
            else $response->BadRequest();

        }
        else $response->BadRequest();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function usernameExisted($newUsername): bool{

    global $response;
    global $link;

    if(!empty($newUsername)) {
        $user = mysqli_query($link, "SELECT * FROM `user` WHERE `username`='$newUsername'") or die($response->BadRequest());
        $user = mysqli_fetch_assoc($user);
        if ($user) return true;
        else return false;
    }
    else return false;
}

function createToken()
{
    $bytes = openssl_random_pseudo_bytes(16,$cstrong);
    return bin2hex($bytes);
}
