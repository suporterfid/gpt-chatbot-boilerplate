#!/usr/bin/env php
<?php
$repoRoot = realpath(__DIR__ . '/../..');
if ($repoRoot === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(1);
}

chdir($repoRoot);

$method = getenv('REQUEST_METHOD') ?: 'GET';
$queryString = getenv('QUERY_STRING') ?: '';

$_GET = [];
if ($queryString !== '') {
    parse_str($queryString, $_GET);
}

$_SERVER['REQUEST_METHOD'] = strtoupper($method);
$_SERVER['HTTP_HOST'] = getenv('HTTP_HOST') ?: 'localhost';
$_SERVER['REMOTE_ADDR'] = getenv('REMOTE_ADDR') ?: '127.0.0.1';

$httpsFlag = getenv('HTTPS') ?: getenv('HTTPS_FLAG');
if ($httpsFlag !== false && $httpsFlag !== '') {
    $_SERVER['HTTPS'] = $httpsFlag;
}

$authHeader = getenv('HTTP_AUTHORIZATION');
if ($authHeader) {
    $_SERVER['HTTP_AUTHORIZATION'] = $authHeader;
}

require $repoRoot . '/admin-api.php';
