# Getting Started

[Home](Home) | **English** | [Türkçe](Getting-Started-TR)

## Install
With a terminal:
```bash
composer install
cp .env.example .env          # APP_ENV=local for development
php pika key:generate         # writes a random PIKA_LICENSE to .env
# edit MYSQL_* in .env, create the (empty) database, then:
php pika migrate
php pika make:admin           # first admin account
php pika serve                # http://127.0.0.1:8000  (admin: /admin/login)
```
Or let the wizard do it all: `php pika install`. Without a terminal, use the web installer, see [Deployment](Deployment).

Run `php pika doctor` at any time: it checks the configuration, the database connection, the PHP used by the command line and your jobs.

## Folders
```
public/        web root: index.php, install.php, assets/, uploads/
src/           Pika\ classes (framework core)
app/           your code: controllers/, middlewares/, models/, jobs/
routes/        web.php, admin.php, api.php (all files are loaded)
includes/      loader, config, helpers.php, components/, error_pages/
content/       themes/, cache/, logs/
database/      migrations/NNN_name.sql (+ NNN_name.down.sql)
tests/         unit and integration tests
```
Point the web server at `public/`. Everything else must not be reachable from the web.

## Your first page
1. A route in `routes/web.php` (GET routes should also list HEAD):
```php
$router->add('GET|HEAD', 'about', 'About@index', ['name' => 'ABOUT']);
```
2. A controller: `php pika make:controller About` creates `app/controllers/About.php`. Make it return a view:
```php
public function index(Request $request, Response $response) {
    return $response->setContent($this->view('about', ['title' => 'About']));
}
```
3. A view `content/themes/default/about.blade.php`:
```blade
@extends('layouts.site')
@section('title', $title)
@section('content')
    <div class="container"><h1>{{ $title }}</h1></div>
@endsection
```
Open `/about/`. Link to it anywhere with `{{ route('ABOUT') }}`.
