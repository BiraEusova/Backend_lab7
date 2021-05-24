<?php
include_once "DataBaseConnection.php";
require_once("./responses/Response.php");
require_once("./statuses/Status.php");
require_once("./services/Authorization.php");

global $link, $response;
$status = new Status();
$auth = new Authorization();

function route($method, $urlData, $formData) {

    global $response;

    switch ($method){
        case "GET": checkGetRequest($urlData, $formData); break;
        case "POST": checkPostRequest($urlData, $formData);  break;
        case "PATCH": checkPatchRequest($urlData, $formData); break;
        case "DELETE": checkDeleteRequest($urlData); break;
        default: $response -> MethodNotAllowed(); break;
    }
}

///GET///////////////////////////////////////////////////////

function checkGetRequest($urlData, $formData){

    global $response;

    if (is_numeric($urlData[0]) && $urlData[1] == "posts") getPostsByUserId($urlData[0]);
    if (is_numeric($urlData[0]) && $urlData[1] == "messages") getMessagesByUserId($urlData[0], $formData);
    else if (is_numeric($urlData[0]) && empty($urlData[1])) getUserById($urlData[0]);
    else if (empty($urlData)) getUsers();
    else $response -> BadRequest();

}

function getMessagesByUserId ($userId, $formData){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUser = $auth->getCurUser($token, $link);
        $curUserId = $curUser["id"];

        if(userIdExisted($userId)) {

            $offset = $formData->offset;
            $limit = $formData->limit;

            $messages = mysqli_query($link, "SELECT * FROM `message` WHERE (`sender` = $curUserId and `receiver` = $userId) or (`sender` = $userId and `receiver` = $curUserId) LIMIT $offset, $limit") or die ($response -> BadRequest());
            $resMessages = array();

            $curMessage = mysqli_fetch_assoc($messages);
            if ($curMessage == null) $response->NotFound();
            else {
                while ($curMessage) {
                    array_push($resMessages, $curMessage);
                    $curMessage = mysqli_fetch_assoc($messages);
                }

                header('Content-Type: application/json');
                echo json_encode($resMessages);
            }
        }else $response->NotFound();

    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function getUsers(){
    global $link;
    global $response;
    global $auth;

    try {
        $token = $auth->getCurToken();
        $userRole = $auth->getCurUserRole($token, $link);

        if ($userRole == "admin"){
            $querySelect = "SELECT `user`.`name`, `user`.`surname`, `user`.`username`, `user`.`id`, `user`.`avatar`, `user`.`status`, `user`.`role`, `user`.`birthday`, `user`.`city`";
        }
        else{
            $querySelect = "SELECT `user`.`name`, `user`.`surname`, `user`.`username`, `user`.`id`, `user`.`avatar`, `user`.`status`, `user`.`city`";
        }
        $queryFrom = "FROM `user`";
        //$queryJoin = "JOIN `city`";
        //$queryOn = "ON `user`.`city` = `city`.`id`";
        $query = $querySelect.$queryFrom;//.$queryJoin.$queryOn;

        $users = mysqli_query($link, $query);
        $resUsers = array();

        $curUser = mysqli_fetch_assoc($users);
        while ($curUser){
            array_push($resUsers, $curUser);
            $curUser = mysqli_fetch_assoc($users);
        }

        header('Content-Type: application/json');
        echo json_encode($resUsers);
        //$response->OK();
    }
    catch(Exception $e){
        $response -> NotFound();
    }
}

function getUserById($userId){
    global $auth;
    global $link;
    global $response;

    try{
        $token = $auth->getCurToken();
        $userRole = $auth->getCurUserRole($token, $link);

        if ($userRole == "admin"){
            $querySelect = "SELECT `user`.`name`, `user`.`surname`, `user`.`username`, `user`.`id`, `user`.`avatar`, `user`.`status`, `user`.`role`, `user`.`birthday`, `user`.`city`";
        }
        else{
            $querySelect = "SELECT `user`.`name`, `user`.`surname`, `user`.`username`, `user`.`id`, `user`.`avatar`, `user`.`status`, `user`.`city`";
        }
        $queryFrom = "FROM `user` WHERE `id` = $userId";
        //$queryJoin = "JOIN `city`";
        //$queryOn = "ON `user`.`city` = `city`.`id` AND `user`.`id`=$userId";

        $query = $querySelect.$queryFrom;//.$queryJoin.$queryOn;

        $user = mysqli_query($link, $query);

        $curUser = mysqli_fetch_assoc($user);
        if(!$curUser) $response->NotFound();
        else {
            header('Content-Type: application/json');
            echo json_encode($curUser);
            //$response->OK();
        }

    }
    catch(Exception $e){
        $response->NotFound();
    }
}

function getPostsByUserId($userId){

    global $link;
    global $response;

    try{
        //TODO: check roles
        $querySelectFrom = "SELECT `text`, `date` FROM `post`";
        $queryJoin = "JOIN `user`";
        $queryOn = "ON `user`.`id` = `post`.`user` AND `post`.`user`=$userId";
        $query = $querySelectFrom.$queryJoin.$queryOn;

        $posts = mysqli_query($link, $query);
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
        $response->NotFound();
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($urlData, $formData){

    global $response;

    if (is_numeric($urlData[0]) && $urlData[1] == "avatar"){
        setUserAvatar($urlData[0]);
    }
    else if (is_numeric($urlData[0]) && $urlData[1] == "messages") {
        createMessage($urlData[0], $formData);
    }
    else if (empty($urlData) && checkFormData($formData)) {
        createUser($formData);
    }
    else {
        $response -> BadRequest();
    }
}

function createMessage($receiverId, $formData){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        if($token) {
            if (userIdExisted($receiverId)) {

                $user = mysqli_query($link, "SELECT `id` FROM `user` WHERE `token` = '$token'");
                $user = mysqli_fetch_assoc($user);
                $user = $user["id"];

                if ($formData->text) {

                    $today = date("Y-m-d H:i:s");
                    $today = explode(' ', $today, 2);
                    $today = $today[0];

                    $query = "INSERT INTO `message`(`sender`, `receiver`, `text`, `date`, `id`) VALUES ($user, $receiverId,'$formData->text','$today',null)";
                    mysqli_query($link, $query);

                    header('Content-Type: application/json');
                    echo json_encode(array(
                        'Text' => $formData->text,
                        'Date' => $today,
                        'Sender' => $user,
                        'Receiver' => $receiverId
                    ));

                    //$response->OK();
                }
                else $response->BadRequest();
            } else $response->NotFound();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }

}

function createUser($formData){
    global $link;
    global $response;
    global $auth;

    try{
          $token = $auth->getCurToken();
          if(canCreateUser($token)) {

              if (!usernameExisted($formData->username)) {

                  $queryInsert = "INSERT INTO `user`(`name`, `surname`, `username`, `password`, `id`, `birthday`, `avatar`, `status`, `city`, `role`)";
                  $queryValues = "VALUES ('$formData->name','$formData->surname','$formData->username','$formData->password', null, '$formData->birthday', null, 'online', null, 4)";
                  $query = $queryInsert . $queryValues;
                  mysqli_query($link, $query);

                  if (!$token){
                      $token = createToken();
                      mysqli_query($link, "UPDATE `user` SET `status`='online', `token`='$token' WHERE `username`= '$formData->username'");
                  }
                  else{
                      mysqli_query($link, "UPDATE `user` SET `status`='offline' WHERE `username`= '$formData->username'");
                  }

                  header('Content-Type: application/json');
                  echo json_encode(array(
                      'Name' => $formData->name,
                      'Surname' => $formData->surname,
                      'Username' => $formData->username,
                      'Password' => $formData->password,
                      'Birthday' => $formData->birthday,
                      'Token' => $token,
                      'Status' => "online"
                  ));

                  //$response->OK();
              } else $response->BadRequest();
          }
          else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function createToken()
{
    $bytes = openssl_random_pseudo_bytes(16,$cstrong);
    return bin2hex($bytes);
}

function setUserAvatar($userId){
    global $link;
    global $response;
    global $auth;

    try{
        if (userIdExisted($userId)) {

            $token = $auth->getCurToken();
            $curUserRole = $auth->getCurUserRole($token, $link);

            if (canEdit($curUserRole, $userId)) {

                $avatar = mysqli_query($link, "SELECT * FROM `user` WHERE `id`=$userId");
                $avatar = mysqli_fetch_assoc($avatar);
                $avatar = $avatar["avatar"];

                if ($avatar){
                    mysqli_query($link, "UPDATE `user` SET `avatar`='null' WHERE `id`=$userId");
                    unlink($avatar);
                }

                if ($_FILES && $_FILES["pictures"]["error"] == UPLOAD_ERR_OK) {
                    $name = htmlspecialchars(basename($_FILES["file"]["name"]));
                    $path = "uploads/".time().$name;


                    if (move_uploaded_file($_FILES["file"]["tmp_name"], $path)) {
                        mysqli_query($link, "UPDATE `user` SET `avatar`='$path' WHERE `id`=$userId");

                        header('Content-Type: application/json');
                        echo json_encode(array(
                            'File' => $path
                        ));
                    } else {
                        $response->Conflict();
                    }
                }
            }
        }
        else $response->BadRequest();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function checkFormData($formData){

    if ($formData->name != null
        && $formData->surname != null
        && $formData->username != null
        && $formData->password != null) return true;
    else return false;
}

function canCreateUser($token): bool{
    global $auth;
    global $link;

    if ($token){
        $curUserRole = $auth->getCurUser($token, $link);

        switch ($curUserRole){
            case "admin": return true;
            default: return false;
        }
    }
    else return true;
}

///PATCH///////////////////////////////////////////////////////

function checkPatchRequest($urlData, $formData){

    global $response;

    if (is_numeric($urlData[0]) && userIdExisted($urlData[0])){
        switch($urlData[1]){
            case "city": updateCity($urlData[0], $formData->city); break;
            case "status": updateStatus($urlData[0], $formData->status); break;
            case "role": updateRole($urlData[0], $formData->role); break;
            case "":
            case null: updateUserData($urlData[0], $formData); break;
            default: $response -> BadRequest();
        }
    }
    else $response -> BadRequest();
}

function updateCity($userId, $newCityId){

    global $response;
    global $link;

    try{
        if (userIdExisted($userId) and cityIdExisted($newCityId)) {

            mysqli_query($link, "UPDATE `user` SET `city`='$newCityId' WHERE `id`=$userId") or die($response->BadRequest());

            header('Content-Type: application/json');
            echo json_encode(array(
                'CityId'=> $newCityId
            ));
            //$response->OK();
        }
        else $response->BadRequest();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function updateStatus($userId, $newStatus){
    global $auth;
    global $response;
    global $link;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if (canEdit($curUserRole, $userId)) {

            if (userIdExisted($userId) and statusIsValid($newStatus)) {

                mysqli_query($link, "UPDATE `user` SET `status`='$newStatus' WHERE `id`=$userId") or die($response->BadRequest());

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Status'=> $newStatus
                ));
                //$response->OK();
            }
            else $response->BadRequest();
        } else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function updateRole($userId, $newRoleId){
    global $auth;
    global $response;
    global $link;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {

            if (userIdExisted($userId) and roleIdExisted($newRoleId)) {

                mysqli_query($link, "UPDATE `user` SET `role`='$newRoleId' WHERE `id`=$userId") or die($response->BadRequest());

                header('Content-Type: application/json');
                echo json_encode(array(
                    'RoleId' => $newRoleId
                ));
                //$response->OK();
            } else $response->BadRequest();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function updateUserData($userId, $formData){
    global $auth;
    global $response;
    global $link;

    try{

        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if (canEdit($curUserRole, $userId)) {

            if (userIdExisted($userId)) {
                $user = mysqli_query($link, "SELECT * FROM `user` WHERE `id`=$userId") or die($response->BadRequest());
                $user = mysqli_fetch_assoc($user);

                $newName = $formData->name ? $formData->name : $user["name"];
                $newSurname = $formData->surname ? $formData->surname : $user["surname"];
                $newUsername = ($formData->username && !usernameExisted($formData->username)) ? $formData->username : $user["username"];
                $newPassword = $formData->password ? $formData->password : $user["password"];
                $newBirthday = $formData->birthday ? $formData->birthday : $user["birthday"];
                $newAvatar = $formData->avatar ? $formData->avatar : $user["avatar"];

                $queryUpdate = "UPDATE `user`";
                $querySet = "SET `name`='$newName', `surname`='$newSurname', `username`='$newUsername', `password`='$newPassword' ";
                $queryWhere = "WHERE `id`=$userId";
                $query = $queryUpdate . $querySet . $queryWhere;

                mysqli_query($link, $query) or die($response->BadRequest());

                if ($newBirthday == null) {
                    $query = "UPDATE `user` SET `birthday`=null WHERE `id`=$userId";
                } else {
                    $query = "UPDATE `user` SET `birthday`='$newBirthday' WHERE `id`=$userId";
                }
                mysqli_query($link, $query) or die($response->BadRequest());

                if ($newAvatar == null) {
                    $query = "UPDATE `user` SET `avatar`=null WHERE `id`=$userId";
                } else {
                    $query = "UPDATE `user` SET `avatar`='$newAvatar' WHERE `id`=$userId";
                }
                mysqli_query($link, $query) or die($response->BadRequest());

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Name' => $newName,
                    'Surname' => $newSurname,
                    'Username' => $newUsername,
                    'Password' => $newPassword,
                    'Birthday' => $newBirthday,
                    'Avatar' => $newAvatar
                ));
                //$response->OK();
            } else $response->BadRequest();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function statusIsValid($newStatus): bool{

    global $status;

    switch($newStatus){
        case $status->statuses[1]:
        case $status->statuses[2]:
        case $status->statuses[3]:
        case $status->statuses[4]:
        case $status->statuses[5]: return true;
        default: return false;
    }
}

function userIdExisted($userId): bool{

    global $response;
    global $link;

    $user = mysqli_query($link, "SELECT * FROM `user` WHERE `id`=$userId") or die($response->BadRequest());
    $user = mysqli_fetch_assoc($user);
    if ($user)  return true;
    else return false;
}

function roleIdExisted($roleId): bool{

    global $response;
    global $link;

    $role = mysqli_query($link, "SELECT * FROM `role` WHERE `id`=$roleId") or die($response->BadRequest());
    $role = mysqli_fetch_assoc($role);
    if ($role)  return true;
    else return false;
}

function cityIdExisted($cityId): bool{

    global $response;
    global $link;

    $city = mysqli_query($link, "SELECT * FROM `city` WHERE `id`=$cityId") or die($response->BadRequest());
    $city = mysqli_fetch_assoc($city);
    if ($city)  return true;
    else return false;
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

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])) {
        //TODO: check role (only admin)
        deleteUser($urlData[0]);
    }
    else $response -> BadRequest();
}

function deleteUser($userId){
    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {

            if (userIdExisted($userId)) {
                mysqli_query($link, "DELETE FROM `user` WHERE `id`=$userId") or die($response->BadRequest());
                //$response->OK();
            } else $response->BadRequest();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

/////CHECK/////////////////////////////////////////////////

function canEdit($curUserRole, $userId):bool{

    global $link;
    global $auth;

    switch ($curUserRole){
        case "admin": return true;
        default:
        {
            $token = $auth->getCurToken();
            $curUser = $auth->getCurUser($token, $link);
            if ($curUser["id"] == $userId) return true;
            else return false;
        }
    }
}
?>