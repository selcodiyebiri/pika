<?php
namespace Pika;

/**
 * Usage:
 *   $v = Validator::make($_POST, [
 *       'email'    => 'required|email|max:250|unique:users,email',
 *       'password' => 'required|min:8|confirmed',   // needs password_confirmation
 *       'role'     => 'in:admin,user',
 *   ], ['email' => 'E-mail address']);
 *   if ($v->fails()) json_output(['errors' => $v->errors()], 422);
 *
 * Rules: required, min:n, max:n, email, numeric, in:a,b, confirmed, unique:table,column[,ignoreId]
 */
class Validator
{
    private array $errors = [];

    private function __construct(private array $data, private array $rules, private array $names)
    {
        foreach ($rules as $field => $ruleList) {
            $this->validateField($field, is_array($ruleList) ? $ruleList : explode('|', $ruleList));
        }
    }

    public static function make(array $data, array $rules, array $names = []): self
    {
        return new self($data, $rules, $names);
    }

    public function fails(): bool { return $this->errors !== []; }
    public function errors(): array { return $this->errors; }
    public function first(string $field): ?string { return $this->errors[$field][0] ?? null; }

    /** Only the fields that have rules. */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function validateField(string $field, array $ruleList): void
    {
        $value = $this->data[$field] ?? null;
        $label = $this->names[$field] ?? $field;
        $empty = $value === null || $value === '' || $value === [];

        foreach ($ruleList as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required') {
                if ($empty) $this->errors[$field][] = "$label is required.";
                continue;
            }
            if ($empty) continue; // other rules apply only to filled values

            $value = is_scalar($value) ? (string) $value : '';
            $error = match ($name) {
                'min'       => mb_strlen($value) < (int) $arg ? "$label must be at least $arg characters." : null,
                'max'       => mb_strlen($value) > (int) $arg ? "$label may not be longer than $arg characters." : null,
                'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "$label must be a valid e-mail address." : null,
                'numeric'   => !is_numeric($value) ? "$label must be numeric." : null,
                'in'        => !in_array($value, explode(',', (string) $arg), true) ? "$label is invalid." : null,
                'confirmed' => ($this->data[$field . '_confirmation'] ?? null) !== $value ? "$label confirmation does not match." : null,
                'unique'    => $this->isTaken($value, (string) $arg) ? "$label is already taken." : null,
                default     => throw new \InvalidArgumentException("Unknown validation rule: $name"),
            };
            if ($error) $this->errors[$field][] = $error;
        }
    }

    private function isTaken(string $value, string $arg): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $arg), 3, null);
        if (!preg_match('/^\w+$/', (string) $table) || !preg_match('/^\w+$/', (string) $column)) {
            throw new \InvalidArgumentException('Invalid unique rule: ' . $arg);
        }
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
        $bind = [$value];
        if ($ignoreId !== null) {
            $sql .= " AND id <> ?";
            $bind[] = $ignoreId;
        }
        return (int) DB::getVar($sql, $bind) > 0;
    }
}
