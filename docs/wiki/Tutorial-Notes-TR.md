# Öğretici: Not Defteri Özelliği Yapmak

[Ana Sayfa](Home-TR) | [English](Tutorial-Notes) | **Türkçe**

Bu öğretici, yeni kurulmuş bir Pika projesine adım adım küçük bir "Notlarım" özelliği ekler. Framework'ün neredeyse tamamını kullanacaksınız: bir **migration**, bir **model**, **rotalar** (isimli ve gruplu), bir **controller**, site temasında ve admin temasında **Blade görünümleri**, **doğrulama**, **flash mesajları**, **sayfalama**, **giriş koruması** ve eski notları temizleyen bir **cron işi**. Aşağıdaki her dosya eksiksizdir: olduğu gibi kopyalayın.

Bu sayfadaki kod, projenin test takımı tarafından her değişiklikte çalıştırılır; yani gösterildiği gibi çalışır.

## Başlamadan önce
* Pika kurulu ve `php pika doctor` "No problems found" diyor (bkz. [Başlangıç](Getting-Started-TR)).
* Geliştirme sunucusu çalışıyor: `php pika serve`, sonra http://127.0.0.1:8000 adresini açın.
* Normal bir hesabınız (`/login` adresinden giriş) ve bir yöneticiniz (`php pika make:admin`) var.

Yollar proje klasörüne görelidir. Henüz olmayan klasörleri oluşturun.

## Adım 1: veritabanı tablosu
Boş bir migration oluşturun, sonra yarattığı iki dosyayı doldurun. Migration düz bir SQL dosyasıdır; `.down.sql` dosyası onu geri alır.
```bash
php pika make:migration create_notes
```
Dosya adındaki numara, kaç migration'ınız olduğuna bağlıdır (burada `008`). Komutun yazdığı adları kullanın.

**Dosya:** `database/migrations/008_create_notes.sql`
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
**Dosya:** `database/migrations/008_create_notes.down.sql`
```sql
DROP TABLE IF EXISTS notes;
```
Çalıştırın:
```bash
php pika migrate
```

## Adım 2: model
Model, tek bir tablo için ince bir yardımcıdır. `$fillable`, `create()` metodunun doldurabileceği sütunları listeler.

**Dosya:** `app/models/Note.php`
```php
<?php
namespace App\Models;

class Note extends \Pika\Model
{
    protected static string $table = 'notes';
    protected static array $fillable = ['user_id', 'title', 'body', 'created_at'];
}
```

## Adım 3: rotalar
Rota, bir URL'yi bir controller metoduna bağlar. `routes/` içindeki her dosya otomatik yüklenir; bu yüzden bu özellik kendi dosyasını alır. `name` verilen bir rotaya her yerden `route('AD')` ile bağlantı verilebilir.

**Dosya:** `routes/notes.php`
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
Dikkat edin: GET rotaları `HEAD`'i de listeler; `:any` değeri controller'a geçen bir yer tutucudur; veri değiştiren işlem (`delete`) `POST` kullanır, asla bağlantı kullanmaz.

## Adım 4: controller
Bir controller metodu önce yer tutucu değerlerini, sonra `Request` ve `Response` nesnelerini alır. Yanıtı, içeriği oluşturulmuş görünüm olacak şekilde döndürür.

**Dosya:** `app/controllers/Notes.php`
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
CSRF kontrolü otomatiktir: her POST, aşağıdaki formlarda `csrf_field()` ile eklenen jetonu ister.

## Adım 5: görünümler (Blade)
`$this->view('notes.index')`, `content/themes/default/notes/index.blade.php` dosyasını yükler (nokta klasör ayırıcıdır). `@extends('layouts.site')` sayfayı site şablonuna sarar; `@section('content')`, şablondaki `@yield('content')` boşluğunu doldurur. `{{ }}` bir değeri **kaçırarak** basar ve sizi XSS'ten korur: kullanıcıdan gelen metin için her zaman bunu kullanın.

**Dosya:** `content/themes/default/notes/index.blade.php`
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
Form görünümü doğrulama hatalarını gösterir ve ziyaretçinin yazdığını korur (`old()`):

**Dosya:** `content/themes/default/notes/form.blade.php`
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
Flash mesajını ("Note saved.") site şablonu kendiliğinden basar.

## Adım 6: çalışırken görün
`/notes/` adresini açın. Çıkış yapmışsanız giriş sayfasına yönlendirilirsiniz. Giriş yapın, **New note** düğmesine basın, formu boş gönderin (hatayı görürsünüz), sonra doldurun. Sayfa bağlantılarının çıktığını görmek için yedi not ekleyin. Birini silin.

## Adım 7: bir yönetim sayfası
Yönetim alanının kendi teması (`content/themes/admin`) ve kendi temel controller'ı vardır. `Admin` middleware'i, Adım 3'teki rotayı zaten korur.

**Dosya:** `app/controllers/Admin/Notes.php`
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
**Dosya:** `content/themes/admin/notes.blade.php`
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
Yönetici olarak giriş yapmışken `/admin/notes/` adresini açın. Menüye bir bağlantı eklemek için bu satırı, `content/themes/admin/layouts/admin.blade.php` içindeki diğer `nav-link` satırlarının yanına koyun:
```blade
<a class="nav-link {{ ($nav ?? '') === 'notes' ? 'active' : '' }}" href="{{ route('ADMIN_NOTES') }}">{!! icon('layers') !!} Notes</a>
```

## Adım 8: eski notları silen bir cron işi
İş, bir tarayıcı olmadan zamanlanmış olarak çalışan bir sınıftır. Oluşturucu ile yaratın:
```bash
php pika make:job notes/cleanup
```
ve içeriğini değiştirin:

**Dosya:** `app/jobs/Notes/Cleanup.php`
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
Denemek için elle çalıştırın:
```bash
php pika job:run notes/cleanup
```
`notes/cleanup: ok` görmelisiniz. Sonra zamanlayın. cPanel'de **Cron Jobs** bölümünü açın ve ekleyin (her gün 03:30):
```bash
30 3 * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run notes/cleanup
```
Komut satırı cron'u yoksa `.env` içinde `CRON_TOKEN` ayarlayın ve bunun yerine HTTP ile çağırın:
```bash
30 3 * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/notes/cleanup
```
Son çalışma zamanını görmek için `/admin/jobs/` adresini açın. Zamanlamalar ve sorun giderme hakkında daha fazlası: [Cron İşleri](Cron-Jobs-TR).

## Kullandıklarınız
| Parça | Nerede | Wiki sayfası |
|---|---|---|
| Migration, model | `database/migrations/`, `app/models/` | [Veritabanı ve Modeller](Database-and-Models-TR) |
| Rotalar, middleware | `routes/` | [Rotalar ve Controller'lar](Routing-and-Controllers-TR) |
| Controller | `app/controllers/` | [Rotalar ve Controller'lar](Routing-and-Controllers-TR) |
| Görünümler, temalar | `content/themes/` | [Görünümler ve Temalar](Views-and-Themes-TR), [Blade Yemek Kitabı](Blade-Cookbook-TR) |
| Doğrulama, sayfalama | `Pika\Validator`, `Pika\Pagination` | [Yardımcılar](Helpers-and-Utilities-TR) |
| İş | `app/jobs/` | [Cron İşleri](Cron-Jobs-TR) |
