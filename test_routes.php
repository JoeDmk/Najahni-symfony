<?php
$routes = [
    '/',
    '/login',
    '/register',
    '/projets',
    '/community/posts',
    '/investissement/opportunities',
    '/mentorat/requests',
    '/apprentissage/cours',
    '/profile',
];
foreach ($routes as $r) {
    $h = @get_headers('http://127.0.0.1:8000' . $r);
    echo $r . ' -> ' . ($h ? $h[0] : 'FAIL') . PHP_EOL;
}

// Check login page
$loginHtml = @file_get_contents('http://127.0.0.1:8000/login');
if ($loginHtml) {
    echo PHP_EOL . '=== Login Page Check ===' . PHP_EOL;
    echo 'Size: ' . strlen($loginHtml) . ' bytes' . PHP_EOL;
    echo 'Form: ' . (strpos($loginHtml, '_username') !== false ? 'OK' : 'MISSING') . PHP_EOL;
    echo 'Card: ' . (strpos($loginHtml, 'card-body') !== false ? 'OK' : 'MISSING') . PHP_EOL;
}

// Check register page  
$regHtml = @file_get_contents('http://127.0.0.1:8000/register');
if ($regHtml) {
    echo PHP_EOL . '=== Register Page Check ===' . PHP_EOL;
    echo 'Size: ' . strlen($regHtml) . ' bytes' . PHP_EOL;
    echo 'Form: ' . (strpos($regHtml, 'firstname') !== false ? 'OK' : 'MISSING') . PHP_EOL;
}
