<?php

class Authorization
{
    public function getCurToken() {
        $token = getallheaders()["Authorization"];
        $token = explode(" ", $token, 2);
        return($token[1]);
    }

    public function getCurUserRole($token, $link) {

        $user = mysqli_query($link, "SELECT `role` FROM `user` WHERE `token` = '$token'");
        $user = mysqli_fetch_assoc($user);

        return($user["role"]);
    }

    public function getCurUser($token, $link) {

        $user = mysqli_query($link, "SELECT * FROM `user` WHERE `token` = '$token'");
        $user = mysqli_fetch_assoc($user);

        return($user);
    }

    public function tokenExisted($token, $link): bool {
        $user = mysqli_query($link, "SELECT * FROM `user` WHERE `token` = '$token'");
        $user = mysqli_fetch_assoc($user);

        if($user) return true;
        else return false;;
    }
}