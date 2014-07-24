<?php

/*
 * Example script of how to authenticate and authorize user
 */

$baseUrlToCAS = 'https://login.example.com';
$serviceName = 'http://service.example.com';
$requiredRoles = ['admin'];

parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $queryArray);

if (!empty($queryArray['username']) && !empty($queryArray['password'])) {
    require_once 'RdeyCASRestLogin.php';

    $cas = new RdeyCASRestLogin($baseUrlToCAS, $queryArray['username'], $queryArray['password'], $serviceName);
    if ($cas->login($requiredRoles)) {
        http_response_code(200);
        die();
    }
}
http_response_code(400);
die();
