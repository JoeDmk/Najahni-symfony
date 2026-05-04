<?php

$uri = urldecode(

    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)

);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the

// built-in PHP web server. This provides a convenient way to test a Symfony

// application without having to set up a full web server.

if ($uri !== '/' && file_exists(__DIR__.'/'.$uri)) {

    return false;

}

$_GET['_url'] = $uri;

require_once __DIR__.'/index.php';