<?php

namespace Jhavenz\LaravelBatchUpdate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BatchedQuery
{
    private Model $model;

    private bool $backticksDisabled;

    private array $incrementOperators = [
        '+', '-', '*', '/', '%',
    ];

    public function __construct(Model|string $model)
    {
        if (! is_a($model, Model::class)) {
            throw new \TypeError('Expected class as a string. Got: '.get_debug_type($model));
        }

        $this->model = is_string($model) ? \app($model) : $model;

        $this->backticksDisabled = SqlGrammarUtils::disableBacktick(
            config("database.connections.{$this->model->getConnectionName()}.driver")
        );
    }

    /**
     * Can be used to increment/decrement values too,
     * using the math operators {@see incrementOperators}
     * $sampleValues = [
     *     [
     *        'user_id' => 123456,
     *        'post_id' => 654321,
     *        'approx_read_time' => ['+', 30]
     *     ],
     *     [
     *        'user_id' => '123456',
     *        'post_id' => 654321,
     *        'approx_read_time' => ['*', 2]
     *     ],
     * ];
     *
     * Each row gets updated with it's own value(s)
     */
    public function update(iterable $values, string $index = null, bool $quoted = false): bool|int
    {
        $queryFields = [];
        $keys = [];

        if (blank($values)) {
            return false;
        }

        $index = $index ?: $this->model->getKeyName();

        foreach ($values as $val) {
            $keys[] = $val[$index];

            if ($this->model->usesTimestamps()) {
                $updatedAtColumn = $this->model->getUpdatedAtColumn();

                if (! isset($val[$updatedAtColumn])) {
                    $val[$updatedAtColumn] = Carbon::now()->format($this->model->getDateFormat());
                }
            }

            foreach (array_keys($val) as $field) {
                if ($field !== $index) {
                    if (gettype($val[$field]) == 'array') {
                        // We're Increment or Decrementing
                        $this->guardAgainstInvalidIncrementDecrementOperation($val[$field]);
                        $field1 = $val[$field][0];
                        $field2 = $val[$field][1];

                        $value = <<<VALUE
                        `{$field}`{$field1}{$field2}
                        VALUE;
                    } else {
                        // We're updating
                        $finalField = $quoted
                            ? SqlGrammarUtils::escape($val[$field])
                            : "'".SqlGrammarUtils::escape($val[$field])."'";

                        $value = (is_null($val[$field]) ? 'NULL' : $finalField);
                    }

                    $queryFields[$field][] = $this->buildWhenThenClause($index, $val[$index], $value);
                }
            }
        }

        return $this->model->getConnection()->update(
            $this->buildQueryResult(
                substr($this->buildCaseClause($queryFields), 0, -2),
                $index,
                implode("','", $keys)
            )
        );
    }

    /**
     * @return string
     */
    private function getFullTableName(): string
    {
        return $this->model->getConnection()->getTablePrefix().$this->model->getTable();
    }

    /**
     * @param  string[]  $operators
     * @return void
     */
    private function guardAgainstInvalidIncrementDecrementOperation(array $operators): void
    {
        // If array has two values
        if (! array_key_exists(0, $operators) || ! array_key_exists(1, $operators)) {
            throw new \ArgumentCountError(
                sprintf(
                    'Increment/Decrement array requires 2 values, a math operator [%s] and a number',
                    implode(', ', $this->incrementOperators)
                )
            );
        }

        // Check first value
        if (gettype($operators[0]) != 'string'
            || ! in_array($operators[0], $this->incrementOperators)) {
            throw new \TypeError(
                sprintf(
                    'First value in Increment/Decrement array must be a string and a math operator [%s]',
                    implode(', ', $this->incrementOperators)
                )
            );
        }

        // Check second value
        if (! is_numeric($operators[1])) {
            throw new \TypeError('Second value in Increment/Decrement array needs to be numeric');
        }
    }

    /**
     * @param  array{column_name: string, sql_statements: array}  $queryFields
     * @return string
     */
    private function buildCaseClause(array $queryFields): string
    {
        return collect($queryFields)
            ->reduce(function (string $sql, array $sqlStatements, string $field) {
                $values = implode(PHP_EOL, $sqlStatements);
                $wrappedField = $this->applyWrapping($field);

                $sql .= <<<CASE_CLAUSE
                    {$wrappedField} = (CASE {$values} \n
                    ELSE {$wrappedField}\n END) \n,
                    CASE_CLAUSE;

                return $sql;
            }, '');
    }

    /**
     * @param  string  $field
     * @param  mixed  $value
     * @param  mixed  $then
     * @return string
     */
    private function buildWhenThenClause(string $field, mixed $value, mixed $then): string
    {
        $wrappedField = $this->applyWrapping($field);

        return <<<WHEN_CLAUSE
        WHEN {$wrappedField} = '{$value}' THEN {$then}
        WHEN_CLAUSE;
    }

    /**
     * @param  string  $field
     * @return string
     */
    private function applyWrapping(string $field): string
    {
        if ($this->backticksDisabled) {
            return str($field)->wrap('"')->toString();
        }

        return str($field)->wrap('`')->toString();
    }

    /**
     * @param  string  $case
     * @param  mixed  $index
     * @param  string  $idValues
     * @return string
     */
    private function buildQueryResult(string $case, mixed $index, string $idValues): string
    {
        return <<<UPDATE_TABLE_CLAUSE
        UPDATE "{$this->getFullTableName()}" SET $case WHERE "{$index}" IN('$idValues');
        UPDATE_TABLE_CLAUSE;
    }
}
