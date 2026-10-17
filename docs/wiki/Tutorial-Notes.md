# Tutorial: Build a Notes Feature

[Home](Home) | **English** | [Türkçe](Tutorial-Notes-TR)

This tutorial adds a small "My notes" feature to a fresh Pika project, step by step. You will use almost everything in the framework: a **migration**, a **model**, **routes** (named and grouped), a **controller**, **Blade views** in the site theme and in the admin theme, **validation**, **flash messages**, **pagination**, **login protection**, and a **cron job** that cleans up old notes. Every file below is complete: copy it as it is.

The code on this page is run by the project's test suite on every change, so it works as shown.

## Before you start
* Pika is installed and `php pika doctor` says "No problems found" (see [Getting Started](Getting-Started)).
* The development server runs: `php pika serve`, then open http://127.0.0.1:8000.
* You have a normal account (sign in at `/login`) and an admin (`php pika make:admin`).

Paths are relative to the project folder. Create folders that do not exist yet.

## Step 1: the database table
Create an empty migration, then fill the two files it creates. A migration is a plain SQL file; the `.down.sql` file undoes it.
```bash
php pika make:migration create_notes
```
The number in the file name depends on how many migrations you already have (here `008`). Use the names the command prints.

**File:** `database/migrations/008_create_notes.sql`
```sql
CREATE TABLE notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**File:** `database/migrations/008_create_notes.down.sql`
```sql
DROP TABLE IF EXISTS notes;
```
Run it:
```bash
php pika migrate
```

## Step 2: the model
A model is a thin helper for one table. `$fillable` lists the columns that `create()` may set.

**File:** `app/models/Note.php`
```php
<?php
namespace App\Models;

class Note extends \Pika\Model
{
    protected static string $table = 'notes';
    protected static array $fillable = ['user_id', 'title', 'body', 'created_at'];
}
```

## Step 3: the routes
A route connects a URL to a controller method. Every file in `routes/` is loaded automatically, so this feature gets its own file. A route with a `name` can be linked from anywhere with `route('NAME')`.

**File:** `routes/notes.php`
```php
<?php
// Signed-in users only: the User middleware sends visitors to the login page.
$router->group('notes', function ($router) {
    $router->add('GET|HEAD', '/', 'Notes@index', ['name' => 'NOTES']);                  // /notes/
    $router->add('GET|HEAD|POST', 'new', 'Notes@create', ['name' => 'NOTES_NEW']);       // /notes/new/
    $router->add('POST', 'delete/:any', 'Notes@delete', ['name' => 'NOTES_DELETE']);     // /notes/delete/5/
}, ['before' => User::class]);

// Admin area: the Admin middleware checks the admin session.
$adminPrefix = pika_env('ADMIN_URL', 'admin');
$router->group($adminPrefix, function ($router) {
    $router->add('GET|HEAD', 'notes', 'Admin.Notes@index', ['name' => 'ADMIN_NOTES']);   // /admin/notes/
}, ['before' => Admin::class]);
```
Things to notice: GET routes also list `HEAD`; `:any` is a placeholder whose value is passed to the controller; changing data (`delete`) uses `POST`, never a link.

## Step 4: the controller
A controller method receives the placeholder values first, then the `Request` and `Response`. It returns the response with the rendered view as its content.

**File:** `app/controllers/Notes.php`
```php
<?php
namespace App\Controllers;

use App\Models\Note,
    Pika\Controller,
    Pika\DB,
    Pika\Pagination,
    Pika\Validator,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Notes extends Controller
{
    /** The signed-in user's notes, 5 per page (?page=2). */
    public function index(Request $request, Response $response)
    {
        $userId = currentUser()->id;
        $pages = new Pagination((int) DB::getVar("SELECT COUNT(*) FROM notes WHERE user_id = ?", [$userId]), 5);
        $notes = DB::table('notes')->where('user_id', $userId)->orderBy('id', 'desc')
            ->limit($pages->limit)->offset($pages->offset)->get();

        return $response->setContent($this->view('notes.index', ['title' => 'My notes', 'notes' => $notes, 'pages' => $pages]));
    }

    /** GET shows the form, POST saves it. */
    public function create(Request $request, Response $response)
    {
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $v = Validator::make($_POST, ['title' => 'required|max:150', 'body' => 'max:2000']);
            if (!$v->fails()) {
                Note::create(['user_id' => currentUser()->id, 'created_at' => time()] + $v->validated());
                flash('status', 'Note saved.');
                redirect_to(route('NOTES'));
            }
            $errors = $v->errors();
            $response->setStatusCode(422);
        }

        return $response->setContent($this->view('notes.form', ['title' => 'New note', 'errors' => $errors]));
    }

    /** Only the owner can delete a note: the user id is part of the query. */
    public function delete($id, Request $request, Response $response)
    {
        DB::table('notes')->where('id', (int) $id)->where('user_id', currentUser()->id)->delete();
        flash('status', 'Note deleted.');
        redirect_to(route('NOTES'));
    }
}
```
The CSRF check is automatic: every POST needs the token that `csrf_field()` puts in the forms below.

## Step 5: the views (Blade)
`$this->view('notes.index')` loads `content/themes/default/notes/index.blade.php` (the dot is a folder separator). `@extends('layouts.site')` wraps the page in the site layout; `@section('content')` is the part that fills the layout's `@yield('content')`. `{{ }}` prints a value **escaped**, which protects you from XSS: always use it for text that came from users.

**File:** `content/themes/default/notes/index.blade.php`
```blade
@extends('layouts.site')

@section('title', $title)

@section('content')
<div class="container">
    <div class="row-between">
        <h1>{{ $title }}</h1>
        <a class="btn btn-primary" href="{{ route('NOTES_NEW') }}">{!! icon('sparkles') !!} New note</a>
    </div>

    @forelse ($notes as $note)
        <div class="card">
            <div class="card-head">
                <h2>{{ $note->title }}</h2>
                <form method="post" action="{{ route('NOTES_DELETE', ['any' => $note->id]) }}">
                    {!! csrf_field() !!}
                    <button class="btn btn-ghost" type="submit">Delete</button>
                </form>
            </div>
            <div class="card-body">
                <p>{{ $note->body }}</p>
                <span class="muted">{{ date('Y-m-d H:i', $note->created_at) }}</span>
            </div>
        </div>
    @empty
        <div class="empty">No notes yet.</div>
    @endforelse

    {!! $pages->render() !!}
</div>
@endsection
```
The form view shows validation errors and keeps what the visitor typed (`old()`):

**File:** `content/themes/default/notes/form.blade.php`
```blade
@extends('layouts.site')

@section('title', $title)

@section('content')
<div class="container">
    <h1>{{ $title }}</h1>

    <form method="post" action="{{ route('NOTES_NEW') }}">
        {!! csrf_field() !!}

        <div class="field">
            <label for="title">Title</label>
            <input class="input" id="title" name="title" value="{{ old('title') }}" required>
            @isset($errors['title'])
                <div class="field-error">{{ $errors['title'][0] }}</div>
            @endisset
        </div>

        <div class="field">
            <label for="body">Note</label>
            <textarea class="input" id="body" name="body" rows="5">{{ old('body') }}</textarea>
            @isset($errors['body'])
                <div class="field-error">{{ $errors['body'][0] }}</div>
            @endisset
        </div>

        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-ghost" href="{{ route('NOTES') }}">Cancel</a>
    </form>
</div>
@endsection
```
The flash message ("Note saved.") is printed by the site layout automatically.

## Step 6: see it work
Open `/notes/`. Signed out, you are sent to the login page. Sign in, click **New note**, send the form empty (you get the error), then fill it in. Add seven notes to see the page links appear. Delete one.

## Step 7: an admin page
The admin area has its own theme (`content/themes/admin`) and its own base controller. The `Admin` middleware already protects the route from Step 3.

**File:** `app/controllers/Admin/Notes.php`
```php
<?php
namespace App\Controllers\Admin;

use Pika\AdminController,
    Pika\DB,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Notes extends AdminController
{
    public function index(Request $request, Response $response)
    {
        $notes = DB::get(
            "SELECT n.title, n.created_at, u.username
             FROM notes n JOIN users u ON u.id = n.user_id ORDER BY n.id DESC LIMIT 50"
        );

        return $response->setContent($this->view('notes', ['title' => 'Notes', 'nav' => 'notes', 'notes' => $notes]));
    }
}
```
**File:** `content/themes/admin/notes.blade.php`
```blade
@extends('layouts.admin')

@section('title', $title . ' · Pika Admin')
@section('heading', $title)

@section('content')
    <section class="card">
        <div class="card-head"><h2>Latest notes</h2></div>
        @if (count($notes))
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Title</th><th>Author</th><th>Created</th></tr></thead>
                    <tbody>
                    @foreach ($notes as $note)
                        <tr>
                            <td><strong>{{ $note->title }}</strong></td>
                            <td>{{ $note->username }}</td>
                            <td class="muted">{{ date('Y-m-d H:i', $note->created_at) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">No notes yet.</div>
        @endif
    </section>
@endsection
```
Open `/admin/notes/` while signed in as admin. To add a menu link, put this line next to the other `nav-link` lines in `content/themes/admin/layouts/admin.blade.php`:
```blade
<a class="nav-link {{ ($nav ?? '') === 'notes' ? 'active' : '' }}" href="{{ route('ADMIN_NOTES') }}">{!! icon('layers') !!} Notes</a>
```

## Step 8: a cron job that deletes old notes
A job is a class that runs on a schedule, without a browser. Create it with the generator:
```bash
php pika make:job notes/cleanup
```
and replace its content:

**File:** `app/jobs/Notes/Cleanup.php`
```php
<?php
namespace App\Jobs\Notes;

use Pika\DB;
use Pika\Job;

class Cleanup extends Job
{
    public function handle(): void
    {
        $deleted = DB::exec("DELETE FROM notes WHERE created_at < ?", [time() - 30 * 86400]);
        log_message('info', "notes cleanup: $deleted old notes deleted");
    }
}
```
Run it by hand to test:
```bash
php pika job:run notes/cleanup
```
You should see `notes/cleanup: ok`. Then schedule it. In cPanel, open **Cron Jobs** and add (every day at 03:30):
```bash
30 3 * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run notes/cleanup
```
Without command-line cron, set `CRON_TOKEN` in `.env` and call it over HTTP instead:
```bash
30 3 * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/notes/cleanup
```
Open `/admin/jobs/` to see when it last ran. More about schedules and troubleshooting: [Cron Jobs](Cron-Jobs).

## What you used
| Piece | Where | Wiki page |
|---|---|---|
| Migration, model | `database/migrations/`, `app/models/` | [Database and Models](Database-and-Models) |
| Routes, middleware | `routes/` | [Routing and Controllers](Routing-and-Controllers) |
| Controller | `app/controllers/` | [Routing and Controllers](Routing-and-Controllers) |
| Views, themes | `content/themes/` | [Views and Themes](Views-and-Themes), [Blade Cookbook](Blade-Cookbook) |
| Validation, pagination | `Pika\Validator`, `Pika\Pagination` | [Helpers and Utilities](Helpers-and-Utilities) |
| Job | `app/jobs/` | [Cron Jobs](Cron-Jobs) |
