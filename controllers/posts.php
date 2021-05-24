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
        case "PATCH": checkPatchRequest($urlData, $formData); break;
        case "DELETE": checkDeleteRequest($urlData); break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///GET///////////////////////////////////////////////////////

function checkGetRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])){
         getPostById($urlData[0]);
    }
    else if (empty($urlData)) getPosts();
    else $response -> BadRequest();

}

function getPosts(){

    global $link;
    global $response;

    try {
        $posts = mysqli_query($link, "SELECT * FROM `post`");
        $resPosts = array();

        $curPost = mysqli_fetch_assoc($posts);
        while ($curPost){
            array_push($resPosts, $curPost);
            $curPost = mysqli_fetch_assoc($posts);
        }
        header('Content-Type: application/json');
        echo json_encode($resPosts);
        //$response->OK();
    }
    catch(Exception $e){
        $response -> NotFound();
    }
}

function getPostById($postId){

    global $link;
    global $response;

    try{
        if(postIdExisted($postId)) {
            $post = mysqli_query($link, "SELECT * FROM `post` WHERE (`id` = $postId)");
            $curPost = mysqli_fetch_assoc($post);

            if (!$curPost) $response->NotFound();
            else {
                header('Content-Type: application/json');
                echo json_encode($curPost);
                //$response->OK();
            }
        }
        else $response->NotFound();
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($urlData, $formData){

    global $response;

    if (empty($urlData) && $formData->text){
        createPost($formData->text);
    }
    else $response -> BadRequest();
}

function createPost($text){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        if ($auth->tokenExisted($token, $link)){

            $today = date("Y-m-d H:i:s");
            $today = explode(' ', $today, 2);
            $today = $today[0];

            $user = $auth->getCurUser($token, $link);
            $userId = $user["id"];

            $newPostCreated = mysqli_query($link, "INSERT INTO `post`(`text`, `date`, `id`, `user`) VALUES ('$text', '$today', null,$userId)");

            if (!$newPostCreated) $response->BadRequest();
            else {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'Text'=> $text,
                    'Date'=> $today
                ));
                //$response->OK();
            }
        }

    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

///PATCH///////////////////////////////////////////////////////

function checkPatchRequest($urlData, $formData){

    global $response;

    if (is_numeric($urlData[0])) {
        updatePost($urlData[0], $formData);
    }
    else $response -> BadRequest();
}

function updatePost($postId, $formData){

    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if (postIdExisted($postId)) {

            $postCreatorId = mysqli_query($link, "SELECT `user`.`id` FROM `user` JOIN `post` ON `post`.`user`=`user`.`id` AND `post`.`id`=$postId") or die($response->BadRequest());
            $postCreatorId = mysqli_fetch_assoc($postCreatorId);
            $postCreatorId = $postCreatorId["id"];

            if (canEdit($curUserRole, $postCreatorId)){

                $today = date("Y-m-d H:i:s");
                $today = explode(' ', $today, 2);
                $today = $today[0];

                if (!empty($formData->text)) {

                    mysqli_query($link, "UPDATE `post` SET `text`='$formData->text', `date`='$today' WHERE `id`=$postId") or die($response->BadRequest());

                    header('Content-Type: application/json');
                    echo json_encode(array(
                        'Text' => $formData->text
                    ));
                    $response->OK();
                }
                else $response->BadRequest();
            }
            else $response->Forbidden();
        }
        else $response->BadRequest();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function postIdExisted($postId): bool{

    global $response;
    global $link;

    $post = mysqli_query($link, "SELECT * FROM `post` WHERE `id`=$postId") or die($response->BadRequest());
    $post = mysqli_fetch_assoc($post);
    if ($post) return true;
    else return false;
}

function canEdit($curUserRole, $postCreatorId): bool{

    global $link;
    global $auth;

    switch ($curUserRole){
        case "admin":
        case "moderator": return true;
        default:
        {
            $token = $auth->getCurToken();
            $curUser = $auth->getCurUser($token, $link);
            if ($curUser["id"] == $postCreatorId) return true;
            else return false;
        }
    }

}

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])) {
        deletePost($urlData[0]);
    }
    else $response -> BadRequest();
}

function deletePost($postId){
    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);
        if (postIdExisted($postId)) {

            $postCreatorId = mysqli_query($link, "SELECT `user`.`id` FROM `user` JOIN `post` ON `post`.`user`=`user`.`id` AND `post`.`id`=$postId") or die($response->BadRequest());
            $postCreatorId = mysqli_fetch_assoc($postCreatorId);
            $postCreatorId = $postCreatorId["id"];

            if (canEdit($curUserRole, $postCreatorId)){

                mysqli_query($link, "DELETE FROM `post` WHERE `id`=$postId") or die($response->BadRequest());
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