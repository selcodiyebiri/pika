<?php
use PHPUnit\Framework\TestCase;
use Pika\Pagination;

class PaginationTest extends TestCase
{
    protected function setUp(): void { $_GET = []; }

    public function testOffsetAndClamp(): void
    {
        $_GET['page'] = '3';
        $p = new Pagination(95, 10, baseUrl: '/list');
        $this->assertSame([10, 3, 20], [$p->pages, $p->current, $p->offset]);

        $_GET['page'] = '999';
        $this->assertSame(10, (new Pagination(95, 10))->current);

        $_GET['page'] = 'abc';
        $this->assertSame(1, (new Pagination(95, 10))->current);
    }

    public function testEmptyAndSinglePageRenderNothing(): void
    {
        $this->assertSame('', (new Pagination(0, 10))->render());
        $this->assertSame('', (new Pagination(5, 10))->render());
        $this->assertSame(0, (new Pagination(0, 10))->offset);
    }

    public function testRenderEscapesQueryAndMarksActive(): void
    {
        $_GET = ['q' => '"><script>', 'page' => '2'];
        $html = (new Pagination(50, 10, baseUrl: '/list'))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('page-item active', $html);
    }
}
