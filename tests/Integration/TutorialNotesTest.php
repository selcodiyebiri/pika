<?php
namespace Tests\Integration;

use Pika\DB;
use Pika\JobRunner;
use Tests\Support\WikiFiles;

/** Builds the feature from docs/wiki/Tutorial-Notes.md exactly as written and uses it through HTTP. */
class TutorialNotesTest extends IntegrationTestCase
{
    private static WikiFiles $files;

    public static function setUpBeforeClass(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) self::markTestSkipped('No MySQL test database available.');

        self::$files = new WikiFiles();
        try {
            foreach (self::$files->install('Tutorial-Notes') as $path => $sql) {
                if (!str_ends_with($path, '.down.sql')) DB::exec($sql);   // the migration, without recording it
            }
            parent::setUpBeforeClass();
        } catch (\Throwable $e) {
            self::cleanUp();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::cleanUp();
    }

    private static function cleanUp(): void
    {
        DB::exec('DROP TABLE IF EXISTS notes');
        self::$files->remove();
        foreach (glob(BASEPATH . '/content/cache/jobs/App_Jobs_Notes_*') ?: [] as $f) @unlink($f);
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::exec('DELETE FROM notes');
    }

    private function signedIn(string $name = 'alice', int $admin = 0): \Tests\Support\HttpClient
    {
        $this->makeUser($name, 'Correct-horse-9', $admin);
        $c = $this->client();
        $this->login($c, $name, 'Correct-horse-9', $admin ? '/admin/login' : '/login');
        return $c;
    }

    private function add(\Tests\Support\HttpClient $c, string $title, string $body = ''): object
    {
        return $c->post('/notes/new/', ['token' => $c->csrf('/notes/new/'), 'title' => $title, 'body' => $body]);
    }

    public function testVisitorsAreSentToTheLoginPage(): void
    {
        $r = $this->client()->get('/notes/');
        $this->assertSame(303, $r->status);
        $this->assertStringContainsString('/login/', $r->location);
    }

    public function testListFormValidationAndSaving(): void
    {
        $c = $this->signedIn();
        $this->assertStringContainsString('No notes yet.', $c->get('/notes/')->body);

        $bad = $this->add($c, '', 'text');
        $this->assertSame(422, $bad->status);
        $this->assertStringContainsString('field-error', $bad->body);
        $this->assertStringContainsString('>text</textarea>', $bad->body, 'the typed text is kept');

        $ok = $this->add($c, 'Buy <milk>', 'two litres');
        $this->assertSame(303, $ok->status);
        $list = $c->get('/notes/');
        $this->assertStringContainsString('Note saved.', $list->body);
        $this->assertStringContainsString('Buy &lt;milk&gt;', $list->body, 'output is escaped');
        $this->assertStringNotContainsString('Buy <milk>', $list->body);
    }

    public function testPaginationAndDeleteOnlyForTheOwner(): void
    {
        $alice = $this->signedIn('alice');
        for ($i = 1; $i <= 7; $i++) $this->add($alice, "Note $i");

        $this->assertStringContainsString('class="pagination"', $alice->get('/notes/')->body);
        $this->assertStringContainsString('Note 2', $alice->get('/notes/?page=2')->body);
        $this->assertStringNotContainsString('Note 7', $alice->get('/notes/?page=2')->body);

        // another user cannot delete alice's note, alice can
        $id = (int) DB::getVar("SELECT id FROM notes ORDER BY id LIMIT 1");
        $mallory = $this->signedIn('mallory');
        $mallory->post("/notes/delete/$id/", ['token' => $mallory->csrf('/notes/new/')]);
        $this->assertSame(7, (int) DB::getVar('SELECT COUNT(*) FROM notes'));

        $alice->post("/notes/delete/$id/", ['token' => $alice->csrf('/notes/new/')]);
        $this->assertSame(6, (int) DB::getVar('SELECT COUNT(*) FROM notes'));
    }

    public function testAdminPage(): void
    {
        $this->add($this->signedIn('alice'), 'Visible to admins');

        $admin = $this->signedIn('boss', 1);
        $page = $admin->get('/admin/notes/');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('Visible to admins', $page->body);
        $this->assertStringContainsString('alice', $page->body);

        $this->assertNotSame(200, $this->client()->get('/admin/notes/')->status, 'visitors are kept out');
    }

    public function testTheCleanupJob(): void
    {
        $id = $this->makeUser('alice');
        DB::insert("INSERT INTO notes (user_id, title, created_at) VALUES (?, 'old', ?), (?, 'fresh', ?)", [$id, time() - 40 * 86400, $id, time()]);

        $this->assertSame('ok', JobRunner::forApp()->run('notes/cleanup')['status']);
        $this->assertSame(['fresh'], array_column(DB::get('SELECT title FROM notes'), 'title'));
    }
}
