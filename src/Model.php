<?php
namespace Pika;

/**
 * Thin static helpers over Query for one table. Rows are plain objects.
 * Mass assignment is limited to $fillable (never list password/admin/session columns there).
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];

    public static function query(): Query { return Query::table(static::$table); }

    public static function find(int|string $id): object|false
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function where(string|array $column, mixed $operator = null, mixed $value = null): Query
    {
        return static::query()->where(...func_get_args());
    }

    public static function all(): array { return static::query()->get(); }

    public static function create(array $data): string
    {
        return static::query()->insert(static::filter($data));
    }

    public static function update(int|string $id, array $data): int
    {
        return static::query()->where(static::$primaryKey, $id)->update(static::filter($data));
    }

    public static function delete(int|string $id): int
    {
        return static::query()->where(static::$primaryKey, $id)->delete();
    }

    private static function filter(array $data): array
    {
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}
