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
        case "POST": checkPostRequest($urlData, $formData);  break;
        case "DELETE": checkDeleteRequest($urlData); break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///GET///////////////////////////////////////////////////////

function checkGetRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])){
        getMessageById($urlData[0]);
    }
    else if (empty($urlData)) getMessages();
    else $response -> BadRequest();

}

function getMessageById($messageId){
    global $link;
    global $response;

    try{
        if(messageIdExisted($messageId)) {

            $message = mysqli_query($link, "SELECT * FROM `message` WHERE `id`=$messageId") or die($response->BadRequest());
            $message = mysqli_fetch_assoc($message);

            if(canSeeMessage($messageId)){

                if (!$message) $response->NotFound();
                else {
                    header('Content-Type: application/json');
                    echo json_encode($message);
                    //$response->OK();
                }
            }
            else $response->Forbidden();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function getMessages(){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUser = $auth->getCurUser($token, $link);
        $curUserId = $curUser["id"];


        $messages = mysqli_query($link, "SELECT * FROM `message` WHERE (`sender` = $curUserId OR `receiver` = $curUserId)");
        $resMessages = array();

        $curMessage = mysqli_fetch_assoc($messages);
        if($curMessage == null) $response->NotFound();
        else{
            while ($curMessage){
                array_push($resMessages, $curMessage);
                $curMessage = mysqli_fetch_assoc($messages);
            }

            header('Content-Type: application/json');
            echo json_encode($resMessages);
        }

    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function messageIdExisted($messageId): bool{
    global $response;
    global $link;

    $message = mysqli_query($link, "SELECT * FROM `message` WHERE `id`=$messageId") or die($response->BadRequest());
    $message = mysqli_fetch_assoc($message);
    if ($message) return true;
    else return false;
}

function canSeeMessage($messageId): bool{
    global $response;
    global $link;
    global $auth;

    $token = $auth->getCurToken();
    $curUser = $auth->getCurUser($token, $link);
    $curUserId = $curUser["id"];

    $message = mysqli_query($link, "SELECT * FROM `message` WHERE `sender`=$curUserId AND `id`=$messageId") or die($response->BadRequest());
    $message = mysqli_fetch_assoc($message);
    if ($message == null) return true;


    $message = mysqli_query($link, "SELECT * FROM `message` WHERE `receiver`=$curUserId AND `id`=$messageId") or die($response->BadRequest());
    $message = mysqli_fetch_assoc($message);
    if ($message == null) return true;

    return false;
}

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){
    global $response;

    if (is_numeric($urlData[0])) {
        deleteMessage($urlData[0]);
    }
    else $response -> BadRequest();
}

function deleteMessage($messageId){
    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);
        if (messageIdExisted($messageId)) {

            $messageSenderId = mysqli_query($link, "SELECT `user`.`id` FROM `user` JOIN `message` ON `message`.`sender`=`user`.`id` AND `message`.`id`=$messageId") or die($response->BadRequest());
            $messageSenderId = mysqli_fetch_assoc($messageSenderId);
            $messageSenderId = $messageSenderId["id"];

            $messageReceiverId = mysqli_query($link, "SELECT `user`.`id` FROM `user` JOIN `message` ON `message`.`receiver`=`user`.`id` AND `message`.`id`=$messageId") or die($response->BadRequest());
            $messageReceiverId = mysqli_fetch_assoc($messageReceiverId);
            $messageReceiverId = $messageReceiverId["id"];

            if (canEdit($curUserRole, $messageSenderId, $messageReceiverId)){

                mysqli_query($link, "DELETE FROM `message` WHERE `id`=$messageId") or die($response->BadRequest());
                $response->OK();

            }
            else $response->Forbidden();
        }
        else $response->BadRequest();

    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function canEdit($curUserRole, $messageSenderId, $messageReceiverId): bool{

    global $link;
    global $auth;

    switch ($curUserRole){
        case "admin": return true;
        default:
        {
            $token = $auth->getCurToken();
            $curUser = $auth->getCurUser($token, $link);
            if ($curUser["id"] == $messageSenderId) return true;
            else if ($curUser["id"] == $messageReceiverId) return true;
            else return false;
        }
    }

}