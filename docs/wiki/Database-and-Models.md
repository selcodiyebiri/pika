# Database and Models

[Home](Home) | **English** | [Türkçe](Database-and-Models-TR)

PDO with real prepared statements and exceptions. Every value is bound; column and table names are validated.

## `Pika\DB`
```php
DB::get("SELECT * FROM posts WHERE status = ?", [1]);      // array of objects
DB::getRow("SELECT * FROM posts WHERE id = ?", [5]);        // one object or false
DB::getVar("SELECT COUNT(*) FROM posts");                    // one value
DB::exec("UPDATE posts SET status = ? WHERE id = ?", [0, 5]); // affected rows
$id = DB::insert("INSERT INTO posts (title) VALUES (?)", ['Hello']);
DB::transaction(function () { /* several writes, all or nothing */ });
```

## Query builder
```php
$posts = DB::table('posts')->where('status', 1)->orderBy('id', 'desc')->limit(20)->get();
$post  = DB::table('posts')->where('id', '>', 10)->whereNull('deleted_at')->first();
DB::table('posts')->where('id', 5)->update(['title' => 'New']);
DB::table('posts')->where('id', 5)->delete();   // update/delete without a where clause is refused
```
Methods: `select`, `where` (also an array, operators `= != <> < <= > >= LIKE`), `whereIn`, `whereNull`, `whereNotNull`, `orderBy`, `limit`, `offset`, `get`, `first`, `count`, `insert`, `update`, `delete`. `where('col', null)` throws: say `whereNull()` explicitly, so a missing request value can never match rows by accident.

## Models
```php
namespace App\Models;
class Post extends \Pika\Model {
    protected static string $table = 'posts';
    protected static array $fillable = ['title', 'body'];   // mass assignment allow-list
}
Post::find(5);  Post::where('status', 1)->get();  Post::create([...]);  Post::update(5, [...]);  Post::delete(5);
```
Never put password, admin or session columns in `$fillable`.

## Migrations
Plain SQL files in `database/migrations/`:
```bash
php pika make:migration create_posts   # creates 008_create_posts.sql and .down.sql
php pika migrate
php pika migrate:rollback              # undoes the last batch (needs the .down.sql files)
```
Applied migrations are recorded in the `migrations` table; a migration runs once.

## Options
`get_option('key')`, `add_option('key', $value)` and `update_option('key', $value)` store small settings in the `options` table.
