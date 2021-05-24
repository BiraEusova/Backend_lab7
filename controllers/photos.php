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
        getPhotosByUserId($urlData[0]);
    }
    else if (empty($urlData))getPhotos();
    else $response -> BadRequest();

}

function getPhotosByUserId($userId){
    global $link;
    global $response;

    try{
        $photos = mysqli_query($link, "SELECT * FROM `photo` WHERE `user`=$userId") or die($response->BadRequest());
        $resPhotos = array();

        $curPhoto = mysqli_fetch_assoc($photos);
        if($curPhoto == null) $response->NotFound();
        else{
            while ($curPhoto){
                array_push($resPhotos, $curPhoto);
                $curPhoto = mysqli_fetch_assoc($photos);
            }

            header('Content-Type: application/json');
            echo json_encode($resPhotos);
        }
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function getPhotos(){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUser = $auth->getCurUser($token, $link);
        $curUserId = $curUser["id"];

        if($token) {

            $photos = mysqli_query($link, "SELECT * FROM `photo` WHERE `user`=$curUserId") or die($response->BadRequest());
            $resPhotos = array();

            $curPhoto = mysqli_fetch_assoc($photos);
            if ($curPhoto == null) $response->NotFound();
            else {
                while ($curPhoto) {
                    array_push($resPhotos, $curPhoto);
                    $curPhoto = mysqli_fetch_assoc($photos);
                }

                header('Content-Type: application/json');
                echo json_encode($resPhotos);
            }
        }else $response->Forbidden();
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($urlData, $formData){
    global $response;

    if (empty($urlData)){
        var_dump($urlData);
        createPhoto();
    }
    else $response -> BadRequest();
}

function createPhoto(){
    global $link;
    global $response;
    global $auth;

    try {
        $token = $auth->getCurToken();
        if ($token != null) {

            $user = mysqli_query($link, "SELECT `id` FROM `user` WHERE `user`.`token` = '$token'") or die($response->BadRequest());
            $user = mysqli_fetch_array($user);
            $user = $user["id"];

            if ($_FILES && $_FILES["pictures"]["error"] == UPLOAD_ERR_OK) {
                $name = htmlspecialchars(basename($_FILES["file"]["name"]));
                $path = "uploads/".time().$name;

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $path)) {
                    mysqli_query($link, "INSERT INTO `photo` (`link`, `user`, `id`) VALUES ('$path', $user, null)");
                    $response->OK();
                } else {
                    $response->Conflict();
                }
            }
        } else $response->Forbidden();
    }
    catch(Exception $e) {
        $response->BadRequest();
    }
}

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])) {
        deleteMessage($urlData[0]);
    }
    else $response -> BadRequest();
}

function deleteMessage($photoId){
    global $response;
    global $link;
    global $auth;

    try{

        if (photoIdExisted($photoId)) {

            $token = $auth->getCurToken();
            $curUserRole = $auth->getCurUserRole($token, $link);

            $userOwner = mysqli_query($link, "SELECT `user`.`id` FROM `user` JOIN `photo` ON `photo`.`user`=`user`.`id` AND `photo`.`id`=$photoId") or die($response->BadRequest());
            $userOwner = mysqli_fetch_assoc($userOwner);
            $userOwner = $userOwner["id"];

            if (canDelete($curUserRole, $userOwner)){

                $photo = mysqli_query($link, "SELECT `photo`.`link` FROM `photo` WHERE `photo`.`id`=$photoId");
                $photo = mysqli_fetch_assoc($photo);
                $photo = $photo["link"];

                mysqli_query($link, "DELETE FROM `photo` WHERE `id`=$photoId") or die($response->BadRequest());
                unlink($photo);
                //$response->OK();
            }
            else $response->Forbidden();
        }
        else $response->NotFound();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function canDelete($curUserRole, $photoOwner): bool{

    global $link;
    global $auth;

    switch ($curUserRole){
        case "admin": return true;
        default:
        {
            $token = $auth->getCurToken();
            $curUser = $auth->getCurUser($token, $link);
            if ($curUser["id"] == $photoOwner) return true;
            else return false;
        }
    }
}

function photoIdExisted($photoId){
    global $response;
    global $link;

    $photo = mysqli_query($link, "SELECT * FROM `photo` WHERE `id`=$photoId") or die($response->BadRequest());
    $photo = mysqli_fetch_assoc($photo);
    if ($photo) return true;
    else return false;
}