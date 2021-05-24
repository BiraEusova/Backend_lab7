<?php


class Response
{
    public function MethodNotAllowed() {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(array(
            'error' => 'Method Not Allowed'
        ));
    }

    public function OK() {
        header('HTTP/1.1 200 OK');
        echo json_encode(array(
            'response' => 'OK'
        ));
    }

    public function BadRequest() {
        header('HTTP/1.1 400 Method Not Allowed');
        echo json_encode(array(
            'error' => 'Bad Request'
        ));
    }

    public function NotFound() {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(array(
            'error' => 'Not Found'
        ));
    }

    public function Forbidden() {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(array(
            'error' => 'Forbidden'
        ));
    }

    public function Conflict() {
        header('HTTP/1.1 409 Conflict');
        echo json_encode(array(
            'error' => 'Conflict'
        ));
    }
}