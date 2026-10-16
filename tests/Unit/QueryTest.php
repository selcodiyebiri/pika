<?php
use PHPUnit\Framework\TestCase;
use Pika\Query;

class QueryTest extends TestCase
{
    public function testBuildsParameterisedSelect(): void
    {
        $q = Query::table('users')->select('id', 'username')->where('status', 1)->where('id', '>', 10)
            ->orderBy('id', 'desc')->limit(20)->offset(40);
        $this->assertSame('SELECT `id`, `username` FROM `users` WHERE `status` = ? AND `id` > ? ORDER BY `id` DESC LIMIT 20 OFFSET 40', $q->toSql());
        $this->assertSame([1, 10], $q->bindings());
    }

    public function testArrayWhereNullAndIn(): void
    {
        $q = Query::table('users')->where(['admin' => 1])->whereNull('email')->whereNotNull('phone')->whereIn('id', [1, 2, 3]);
        $this->assertSame('SELECT * FROM `users` WHERE `admin` = ? AND `email` IS NULL AND `phone` IS NOT NULL AND `id` IN (?,?,?)', $q->toSql());
        $this->assertSame([1, 1, 2, 3], $q->bindings());
        $this->assertStringContainsString('1 = 0', Query::table('users')->whereIn('id', [])->toSql());
    }

    public function testNullValueInWhereIsRefusedInsteadOfMatchingNullRows(): void
    {
        foreach ([fn() => Query::table('users')->where('remember_token', null),
                  fn() => Query::table('users')->where('remember_token', '=', null),
                  fn() => Query::table('users')->where('id', '>', null),
                  fn() => Query::table('users')->where(['remember_token' => null])] as $build) {
            try { $build(); $this->fail('expected an exception'); } catch (\InvalidArgumentException $e) { $this->assertStringContainsString('whereNull', $e->getMessage()); }
        }
    }

    public function testValuesNeverEndUpInTheSql(): void
    {
        $q = Query::table('users')->where('username', "x' OR '1'='1");
        $this->assertStringNotContainsString('OR', $q->toSql());
        $this->assertSame(["x' OR '1'='1"], $q->bindings());
    }

    /** @dataProvider badInput */
    public function testRejectsUnsafeIdentifiersOperatorsAndDirections(callable $fn): void
    {
        $this->expectException(InvalidArgumentException::class);
        $fn();
    }

    public static function badInput(): array
    {
        return [
            'table'     => [fn() => Query::table('users; DROP TABLE users')],
            'column'    => [fn() => Query::table('users')->where('id`=1 OR `1', 1)],
            'operator'  => [fn() => Query::table('users')->where('id', '= 1 OR 1=1 --', 1)],
            'direction' => [fn() => Query::table('users')->orderBy('id', 'desc; DROP')],
            'select'    => [fn() => Query::table('users')->select('id, password')],
        ];
    }

    public function testUpdateAndDeleteRefuseToRunWithoutWhere(): void
    {
        foreach ([fn() => Query::table('users')->update(['status' => 0]), fn() => Query::table('users')->delete()] as $fn) {
            try { $fn(); $this->fail('expected LogicException'); } catch (LogicException $e) { $this->assertStringContainsString('where()', $e->getMessage()); }
        }
    }
}
