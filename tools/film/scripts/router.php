<?php

// php -S router for the capture server: /staff is also a folder in public, which the framework's own router trips over.
$public = getcwd();
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

require $public.'/index.php';
