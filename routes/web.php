<?php
// Public routes (GET routes also answer HEAD)
$router->add('GET|HEAD', '/', 'Home@main', ['name' => 'HOME']);
$router->add('GET|HEAD|POST', 'login', 'Login@login', ['name' => 'LOGIN']);
$router->add('POST', 'logout', 'Login@logout', ['name' => 'LOGOUT']);

// Password reset
$router->add('GET|HEAD|POST', 'forgot', 'PasswordReset@forgot', ['name' => 'FORGOT']);
$router->add('GET|HEAD|POST', 'reset/:any', 'PasswordReset@reset', ['name' => 'RESET']);
