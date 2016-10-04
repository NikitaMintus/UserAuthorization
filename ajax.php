<?php
/**
 * Created by PhpStorm.
 * User: Nikita
 * Date: 04.10.2016
 * Time: 18:47
 */

include './classes/Auth.php';
include './classes/AjaxRequest.php';

if (!empty($_COOKIE['sid'])) {
    // check session id in cookies
    session_id($_COOKIE['sid']);
}
session_start();

class AuthorizationAjaxRequest extends AjaxRequest
{
    public $actions = array(
        "login" => "login",
        "logout" => "logout",
        "register" => "register",
    );

    public function login()
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            http_response_code(405);
            header("Allow: POST");
            $this->setFieldError("main", "Method Not Allowed");
            return;
        }
        setcookie("sid", "");

        $username = $this->getRequestParam("username");
        $password = $this->getRequestParam("password");
        $remember = !!$this->getRequestParam("remember-me");
        $captcha = $this->getRequestParam("g-recaptcha-response");


        if (empty($username)) {
            $this->setFieldError("username", "Enter the username");
            return;
        }

        if (empty($password)) {
            $this->setFieldError("password", "Enter the password");
            return;
        }

        if (empty($captcha)) {
            $this->setFieldError("captcha", "Perform captcha");
            return;
        }

        $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfbTAgUAAAAAKuD0qqrzAQjbCbkT5x6sYyusa3L&response=".$captcha."&remoteip=".$_SERVER['REMOTE_ADDR']), true);

        if(!($response['success']))
        {
            $this->setFieldError("captcha", "You are spamer");
            return;
        }

        $user = new Auth\User();
        $auth_result = $user->authorize($username, $password, $remember);


        if (!$auth_result) {
            $this->setFieldError("password", "Invalid username or password");
            return;
        }

        $this->status = "ok";
        $this->setResponse("redirect", ".");
        $this->message = sprintf("Hello, %s! Access granted.", $username);
    }

    public function logout()
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            http_response_code(405);
            header("Allow: POST");
            $this->setFieldError("main", "Method Not Allowed");
            return;
        }

        setcookie("sid", "");

        $user = new Auth\User();
        $user->logout();

        $this->setResponse("redirect", ".");
        $this->status = "ok";
    }

    public function register()
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            // Method Not Allowed
            http_response_code(405);
            header("Allow: POST");
            $this->setFieldError("main", "Method Not Allowed");
            return;
        }

        setcookie("sid", "");

        $username = $this->getRequestParam("username");
        $password1 = $this->getRequestParam("password1");
        $password2 = $this->getRequestParam("password2");
        $email = $this->getRequestParam("email");

        if (empty($username)) {
            $this->setFieldError("username", "Enter the username");
            return;
        }

        if (empty($password1)) {
            $this->setFieldError("password1", "Enter the password");
            return;
        }

        if (empty($password2)) {
            $this->setFieldError("password2", "Confirm the password");
            return;
        }

        if ($password1 !== $password2) {
            $this->setFieldError("password2", "Confirm password is not match");
            return;
        }

        if (empty($email)) {
            $this->setFieldError("email", "Enter the email");
            return;
        }

        $user = new Auth\User();

        try {
            $new_user_id = $user->create($username, $password1, $email);
        } catch (\Exception $e) {
            $this->setFieldError("username", $e->getMessage());
            return;
        }
        $user->authorize($username, $password1);

        $this->message = sprintf("Hello, %s! Thank you for registration.", $username);
        $this->setResponse("redirect", "/");
        $this->status = "ok";
    }
}

$ajaxRequest = new AuthorizationAjaxRequest($_REQUEST);
$ajaxRequest->showResponse();