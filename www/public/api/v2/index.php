<?php

define("ROOT", dirname(__FILE__, 4));

/**
 *  Header and allowed methods
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

/**
 *  Exit if method is not allowed
 */
if ($_SERVER['REQUEST_METHOD'] == 'GET' and $_SERVER['REQUEST_METHOD'] == 'POST' and $_SERVER['REQUEST_METHOD'] == 'PUT' and $_SERVER['REQUEST_METHOD'] == 'DELETE') {
    http_response_code(405);
    echo json_encode(["return" => "405", "message_error" => array('Method not allowed.')]);
    exit;
}

require_once(ROOT . '/controllers/Autoloader.php');
require_once(ROOT . '/controllers/Profile.php');
require_once(ROOT . '/controllers/Host.php');
\Controllers\Autoloader::api();

/**
 *  Quit if autoload has encountered any error
 */
if (__LOAD_GENERAL_ERROR != 0) {
    http_response_code(503);
    echo json_encode(["return" => "503", "message_error" => array('Reposerver configuration error. Please contact the administrator.')]);
    exit;
}

/**
 *  Return 403 if an update is running
 */
if (UPDATE_RUNNING == 'true') {
    http_response_code(403);
    echo json_encode(["return" => "403", "message_error" => array('Reposerver is actually being updated. Please try again later.')]);
    exit;
}

/**
 *  Parse URI
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);
$route = $uri[3];

/**
 *  If this server API status was requested
 */
if ($route == 'status') {
    http_response_code(201);
    echo json_encode(["return" => "201", "status" => 'OK']);
    exit;
}

try {
    /**
     *  Call profile API controller
     */
    if ($route == 'profile') {
        $myprofile = new \Controllers\Api\Profile();
        $resultArray = $myprofile->execute();
        \Controllers\Api\Api::returnSuccess($resultArray);
    }

    /**
     *  Call host API controller
     */
    if ($route == 'host') {
        $myhost = new \Controllers\Api\Host();
        $resultArray = $myhost->execute();
        \Controllers\Api\Api::returnSuccess($resultArray);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["return" => "400", "message_error" => array($e->getMessage())]);
    exit;
}

/**
 *  If no matching route
 */
http_response_code(400);
echo json_encode(["return" => "404", "message_error" => array('No matching route.')]);

exit;