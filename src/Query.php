<?php
namespace Pika;

/**
 * Small fluent query builder. Values are always bound parameters; identifiers and operators are validated.
 *   DB::table('users')->where('status', 1)->where('id', '>', 10)->orderBy('id', 'desc')->limit(20)->get();
 * update() and delete() refuse to run without a where() clause.
 */
class Query
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    private function __construct(private string $table)
    {
        $this->table = self::ident($table);
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name)) {
            throw new \InvalidArgumentException("Invalid identifier: $name");
        }
        return '`' . str_replace('.', '`.`', $name) . '`';
    }

    public function select(string ...$columns): self
    {
        $this->columns = array_map(fn($c) => $c === '*' ? '*' : self::ident($c), $columns);
        return $this;
    }

    /** where('a', 1) | where('a', '>', 1) | where(['a' => 1, 'b' => 2]) */
    public function where(string|array $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) $this->where($col, '=', $val);
            return $this;
        }
        if (func_num_args() === 2) {
            [$operator, $value] = ['=', $operator];
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid operator: $operator");
        }
        if ($value === null) {
            // a missing request value must never quietly become "IS NULL" and match rows: say so with whereNull()
            throw new \InvalidArgumentException("where('$column', null) is ambiguous; use whereNull() or whereNotNull().");
        }
        $this->wheres[] = self::ident($column) . " $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = self::ident($column) . ' IS NULL';
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = self::ident($column) . ' IS NOT NULL';
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = $values === [] ? '1 = 0'
            : self::ident($column) . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException("Invalid direction: $direction");
        }
        $this->orders[] = self::ident($column) . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): self  { $this->limit = max(0, $limit); return $this; }
    public function offset(int $offset): self { $this->offset = max(0, $offset); return $this; }

    private function whereSql(): string
    {
        return $this->wheres ? ' WHERE ' . implode(' AND ', $this->wheres) : '';
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table . $this->whereSql();
        if ($this->orders) $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        if ($this->limit !== null) $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;
        return $sql;
    }

    public function bindings(): array { return $this->bindings; }

    public function get(): array     { return DB::get($this->toSql(), $this->bindings); }
    public function first(): object|false { return DB::getRow($this->limit(1)->toSql(), $this->bindings); }

    public function count(): int
    {
        return (int) DB::getVar('SELECT COUNT(*) FROM ' . $this->table . $this->whereSql(), $this->bindings);
    }

    public function insert(array $data): string
    {
        $cols = array_map([self::class, 'ident'], array_keys($data));
        return DB::insert(
            'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_values($data)
        );
    }

    public function update(array $data): int
    {
        $this->requireWhere('update');
        $set = implode(', ', array_map(fn($c) => self::ident($c) . ' = ?', array_keys($data)));
        return DB::exec('UPDATE ' . $this->table . ' SET ' . $set . $this->whereSql(), [...array_values($data), ...$this->bindings]);
    }

    public function delete(): int
    {
        $this->requireWhere('delete');
        return DB::exec('DELETE FROM ' . $this->table . $this->whereSql(), $this->bindings);
    }

    private function requireWhere(string $what): void
    {
        if (!$this->wheres) {
            throw new \LogicException("Refusing to $what without a where() clause.");
        }
    }
}
