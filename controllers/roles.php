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

    if (is_numeric($urlData[0])) getRoleById($urlData[0]);
    else if (empty($urlData)) getRoles();
    else $response -> BadRequest();

}

function getRoles(){

    global $link;
    global $response;

    try {
        $roles = mysqli_query($link, "SELECT * FROM `role`");
        $resRoles = array();

        $curRole = mysqli_fetch_assoc($roles);
        while ($curRole){
            array_push($resRoles, $curRole);
            $curRole = mysqli_fetch_assoc($roles);
        }
        header('Content-Type: application/json');
        echo json_encode($resRoles);
        //$response->OK();
    }
    catch(Exception $e){
        $response -> NotFound();
    }
}

function getRoleById($roleId){

    global $link;
    global $response;

    try{
        $role = mysqli_query($link, "SELECT * FROM `role` WHERE (`id` = $roleId)");
        $curRole = mysqli_fetch_assoc($role);

        if(!$curRole) $response->NotFound();
        else {
            header('Content-Type: application/json');
            echo json_encode($curRole);
        }
        $response->OK();
    }
    catch(Exception $e){
        $response->NotFound();
    }
}

///POST///////////////////////////////////////////////////////

function checkPostRequest($formData){

    global $response;

    if ($formData->name != null){
        createRole($formData->name);
    }
    else $response -> BadRequest();
}

function createRole($newRoleName){
    global $link;
    global $response;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            if (!roleNameExisted($newRoleName)) {
                mysqli_query($link, "INSERT INTO `role`(`id`, `name`) VALUES (null, '$newRoleName')");

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Name' => $newRoleName
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

///PATCH///////////////////////////////////////////////////////

function checkPatchRequest($urlData, $formData){
    global $response;

    if (is_numeric($urlData[0])) {
        //TODO: check role (only admin)
        setNewRoleName($urlData[0], $formData);
    }
    else $response -> BadRequest();

}

function setNewRoleName($roleId, $formData){
    global $auth;
    global $response;
    global $link;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            $newRoleName = $formData->name;
            $roleIdExisted = roleIdExisted($roleId);
            $roleNameExisted = roleNameExisted($newRoleName);
            $isSystemRole = isSystemRole($newRoleName);

            if ($roleIdExisted and !$roleNameExisted and !$isSystemRole) {
                mysqli_query($link, "UPDATE `role` SET `name`='$newRoleName' WHERE `id`=$roleId") or die($response->BadRequest());

                header('Content-Type: application/json');
                echo json_encode(array(
                    'Name' => $newRoleName
                ));
                //$response->OK();
            } else $response->BadRequest();
        }else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}

function roleIdExisted($roleId): bool{

    global $response;
    global $link;

    $role = mysqli_query($link, "SELECT * FROM `role` WHERE `id`=$roleId") or die($response->BadRequest());
    $role = mysqli_fetch_assoc($role);
    if ($role)  return true;
    else return false;
}

function roleNameExisted($newRoleName): bool{

    global $response;
    global $link;

    $role = mysqli_query($link, "SELECT * FROM `role` WHERE `name`='$newRoleName'") or die($response->BadRequest());
    $role = mysqli_fetch_assoc($role);
    if ($role)  return true;
    else return false;
}

function isSystemRole($newRoleName): bool{

    switch($newRoleName){
        case "user":
        case "moderator":
        case "admin": return true;
        default: return false;
    }
}

///DELETE///////////////////////////////////////////////////////

function checkDeleteRequest($urlData){

    global $response;

    if (is_numeric($urlData[0])) {
        //TODO: check role (only admin)
        deleteRole($urlData[0]);
    }
    else $response -> BadRequest();
}

function deleteRole($roleId){
    global $response;
    global $link;
    global $auth;

    try{
        $token = $auth->getCurToken();
        $curUserRole = $auth->getCurUserRole($token, $link);

        if ($curUserRole == "admin") {
            if (roleIdExisted($roleId)) {

                $role = mysqli_query($link, "SELECT * FROM `role` WHERE `id`='$roleId'");
                $role = mysqli_fetch_assoc($role);

                $roleName = $role["name"];
                if (!isSystemRole($roleName)) {
                    mysqli_query($link, "DELETE FROM `role` WHERE `id`=$roleId") or die($response->BadRequest());
                    //$response->OK();
                }
                else $response->BadRequest();
            }
            else $response->BadRequest();
        }
        else $response->Forbidden();
    }
    catch(Exception $e){
        $response->BadRequest();
    }
}
?>