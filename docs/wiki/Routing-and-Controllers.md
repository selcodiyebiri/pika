# Routing and Controllers

[Home](Home) | **English** | [Türkçe](Routing-and-Controllers-TR)

Routing uses the `izniburak/router` package. Every file in `routes/` is loaded automatically.

## Routes
```php
// routes/web.php
$router->add('GET|HEAD', '/', 'Home@main', ['name' => 'HOME']);
$router->add('GET|HEAD|POST', 'login', 'Login@login', ['name' => 'LOGIN']);
$router->add('GET|HEAD|POST', 'reset/:any', 'PasswordReset@reset', ['name' => 'RESET']);
$router->add('GET|HEAD', 'ping', function () { json_output(['ok' => true]); });
```
* The third argument is `Controller@method`, or a closure. Controllers live in `app/controllers/`; use `'Admin.Name@method'` for `app/controllers/Admin/Name.php`.
* Parameters: `:any` matches one path segment, `:all` matches the rest. Values arrive as the first method arguments, then `Request` and `Response` (Symfony HttpFoundation).
* A GET route does not answer HEAD unless you list it: `'GET|HEAD'`.
* A controller returns the `Response` (`$response->setContent(...)`); it does not call `send()`.

## Route examples
Every file in `routes/` is loaded, so this page's examples live in their own file. Open the URLs in a browser or with `curl`; the examples are run by the project's test suite.

**File:** `routes/demo.php`
```php
<?php
// 1. A closure: the quickest way to answer a request. Named, so route('DEMO_PING') gives its URL.
$router->add('GET|HEAD', 'demo/ping', function () {
    json_output(['status' => true]);
}, ['name' => 'DEMO_PING']);

// 2. A placeholder: its value arrives as the first argument. Escape it before printing.
$router->add('GET|HEAD', 'demo/hello/:any', function ($name) {
    echo 'Hello ' . esc($name);
});

// 3. Several placeholders arrive in order.
$router->add('GET|HEAD', 'demo/post/:any/comment/:any', function ($post, $comment) {
    echo 'Post ' . esc($post) . ', comment ' . esc($comment);
});

// 4. :all takes the rest of the path, slashes included.
$router->add('GET|HEAD', 'demo/files/:all', function ($path) {
    echo 'File ' . esc($path);
});

// 5. One URL, two methods. A POST needs the CSRF token (csrf_field() in the form).
$router->add('GET|POST', 'demo/form', function () {
    echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Saved' : 'Show the form';
});

// 6. A group shares a URL prefix and a middleware (here: a per-IP request limit).
$router->group('demo/api', function ($router) {
    $router->add('GET|HEAD', 'time', function () {
        json_output(['time' => time()]);
    });
}, ['before' => RateLimit::class]);
```
| URL | Answer |
|---|---|
| `/demo/ping/` | `{"status":true}` |
| `/demo/hello/Ayse/` | `Hello Ayse` |
| `/demo/post/7/comment/3/` | `Post 7, comment 3` |
| `/demo/files/reports/2026/summary` | `File reports/2026/summary` |
| `/demo/api/time/` | the time as JSON, limited per IP |

In a real project a route points to a controller method instead of a closure: `$router->add('GET|HEAD', 'posts/:any', 'Posts@show', ['name' => 'POST'])` calls `show($id, Request $request, Response $response)` in `app/controllers/Posts.php`. The [Notes tutorial](Tutorial-Notes) builds one from start to end.

## Named routes
Give a route a name, then build its URL anywhere:
```php
$url = route('RESET', ['any' => $token]);   // in PHP
```
In Blade: `{{ route('LOGIN') }}`, `{{ route('RESET', ['any' => $token]) }}` or `@route('LOGIN')`. Values are URL-encoded and the result is HTML-escaped. An unknown name or a missing parameter returns `null` and writes a warning to the log. `route()` needs the router, so call it while handling a request.

## Groups and middleware
```php
$router->group('admin', function ($router) {
    $router->add('GET|HEAD', '/', 'Admin.Dashboard@index', ['name' => 'ADMIN_HOME']);
}, ['before' => Admin::class]);
```
Middleware classes are in `app/middlewares/`:
* `User`: site area, requires a signed-in user (optional IP allow-list per user).
* `Admin`: admin area, fails closed, audits every non-GET request.
* `RateLimit`: per-IP request limit (`RATE_LIMIT` per `RATE_WINDOW`), answers 429 with `Retry-After`.

A middleware returns `true` to continue; any other value is sent as the response.

## Controllers
`php pika make:controller Posts` (add `--admin` for the admin area). A controller extends `Pika\Controller` (theme `default`) or `Pika\AdminController` (theme `admin`). Both have `$this->authorize('permission')`. See [Views and Themes](Views-and-Themes).

## CSRF
Every POST, PUT, PATCH and DELETE needs the session token (form field `token` or header `X-CSRF-Token`). Token-authenticated endpoints that cannot send it (webhooks) must be listed in `includes/components/post_security.php`; review that list with care.
