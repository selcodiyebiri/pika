<?php
// Admin routes: controllers live in app/controllers/Admin/ ("Admin.Name@method").
// The URL prefix comes from ADMIN_URL (.env).
$adminPrefix = pika_env('ADMIN_URL', 'admin');

// Open: login / logout
$router->add('GET|HEAD|POST', "$adminPrefix/login", 'Admin.Login@login', ['name' => 'ADMIN_LOGIN']);
$router->add('POST', "$adminPrefix/logout", 'Admin.Login@logout', ['name' => 'ADMIN_LOGOUT']);

// Protected by the Admin middleware
$router->group($adminPrefix, function($router) {
    $router->add('GET|HEAD', '/', 'Admin.Dashboard@index', ['name' => 'ADMIN_HOME']);
    $router->add('GET|HEAD', 'jobs', 'Admin.Jobs@index', ['name' => 'ADMIN_JOBS']);
}, ['before' => Admin::class]);
