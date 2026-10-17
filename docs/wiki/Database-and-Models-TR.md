# Veritabanı ve Modeller

[Ana Sayfa](Home-TR) | [English](Database-and-Models) | **Türkçe**

Gerçek hazırlanmış ifadeler ve istisnalarla PDO. Her değer bağlanır; sütun ve tablo adları doğrulanır.

## `Pika\DB`
```php
DB::get("SELECT * FROM posts WHERE status = ?", [1]);      // nesne dizisi
DB::getRow("SELECT * FROM posts WHERE id = ?", [5]);        // tek nesne ya da false
DB::getVar("SELECT COUNT(*) FROM posts");                    // tek değer
DB::exec("UPDATE posts SET status = ? WHERE id = ?", [0, 5]); // etkilenen satır
$id = DB::insert("INSERT INTO posts (title) VALUES (?)", ['Hello']);
DB::transaction(function () { /* birden çok yazma, ya hepsi ya hiçbiri */ });
```

## Sorgu oluşturucu
```php
$posts = DB::table('posts')->where('status', 1)->orderBy('id', 'desc')->limit(20)->get();
$post  = DB::table('posts')->where('id', '>', 10)->whereNull('deleted_at')->first();
DB::table('posts')->where('id', 5)->update(['title' => 'New']);
DB::table('posts')->where('id', 5)->delete();   // where olmadan update/delete reddedilir
```
Metotlar: `select`, `where` (dizi de olur, işleçler `= != <> < <= > >= LIKE`), `whereIn`, `whereNull`, `whereNotNull`, `orderBy`, `limit`, `offset`, `get`, `first`, `count`, `insert`, `update`, `delete`. `where('col', null)` istisna fırlatır: `whereNull()` deyin, böylece eksik bir istek değeri kazara satırlarla eşleşemez.

## Modeller
```php
namespace App\Models;
class Post extends \Pika\Model {
    protected static string $table = 'posts';
    protected static array $fillable = ['title', 'body'];   // toplu atama izin listesi
}
Post::find(5);  Post::where('status', 1)->get();  Post::create([...]);  Post::update(5, [...]);  Post::delete(5);
```
`$fillable` içine asla parola, admin ya da oturum sütunları koymayın.

## Migration'lar
`database/migrations/` içinde düz SQL dosyaları:
```bash
php pika make:migration create_posts   # 008_create_posts.sql ve .down.sql oluşturur
php pika migrate
php pika migrate:rollback              # son grubu geri alır (.down.sql dosyaları gerekir)
```
Uygulanan migration'lar `migrations` tablosuna yazılır; bir migration bir kez çalışır.

## Seçenekler
`get_option('key')`, `add_option('key', $value)` ve `update_option('key', $value)` küçük ayarları `options` tablosunda saklar.
