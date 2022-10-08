<?php

namespace Jhavenz\LaravelBatchUpdate;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

class BatchedUpdate
{
    private Model $model;

    private array $modelIds;

    private string $compiledQuery;

    private bool $backticksDisabled;

    private array $switchCases = [];

    private array $incrementOperators = [
        '+', '-', '*', '/', '%',
    ];

    public function __construct(Model|string $model)
    {
        Assert::isAOf($model, Model::class);

        $this->model = is_string($model) ? \app($model) : $model;

        $this->backticksDisabled = SqlGrammarUtils::disableBacktick(
            config("database.connections.{$this->model->getConnectionName()}.driver")
        );
    }

    public static function createFromModel(Model|EloquentCollection|string $model): static
    {
        if ($model instanceof EloquentCollection) {
            $models = $model->keyBy(static fn ($model) => match (true) {
                is_object($model) => $model::class,
                is_string($model) && is_a($model, Model::class, true) => $model,
                default => throw new InvalidArgumentException(
                    'Eloquent collection can only contain class strings or Model instances'
                ),
            });

            if ($models->keys()->count() > 1) {
                throw new InvalidArgumentException('Eloquent collection can only contain one type of model');
            }

            $model = $models->first();
        }

        return new static($model);
    }

    protected function compileUpdateQuery(
        iterable $values,
        string $primaryKeyColumn = null,
        bool $raw = false,
        bool $failOnNonExistingColumns = false
    ): static {
        Assert::notEmpty(collect($values));

        $table = $this->model->getTable();
        $primaryKeyColumn = $primaryKeyColumn ?: $this->model->getKeyName();
        $existingColumns = $this->model->getConnection()->getSchemaBuilder()->getColumnListing($table);

        foreach ($values as $modelAttributes) {
            if ($modelAttributes instanceof Model) {
                $modelAttributes = $modelAttributes->toArray();
            }

            $this->modelIds[] = $modelAttributes[$primaryKeyColumn];

            if ($this->model->usesTimestamps()) {
                $updatedAtColumn = $this->model->getUpdatedAtColumn();

                if (! isset($modelAttributes[$updatedAtColumn])) {
                    $modelAttributes[$updatedAtColumn] = Carbon::now()->format($this->model->getDateFormat());
                }
            }

            foreach (array_keys($modelAttributes) as $databaseColumn) {
                if ($failOnNonExistingColumns && ! in_array($databaseColumn, $existingColumns)) {
                    throw new InvalidArgumentException(
                        sprintf("There is no column with name [%s] on table [%s].", $databaseColumn, $table),
                        30 //=> doctrine error code for a missing db column
                    );
                }

                if ($databaseColumn !== $primaryKeyColumn) {
                    if (gettype($modelAttributes[$databaseColumn]) == 'array') {
                        // We're Increment or Decrementing
                        $this->guardAgainstInvalidIncrementDecrementOperation($modelAttributes[$databaseColumn]);
                        $field1 = $modelAttributes[$databaseColumn][0];
                        $field2 = $modelAttributes[$databaseColumn][1];

                        $value = <<<VALUE
                        `{$databaseColumn}`{$field1}{$field2}
                        VALUE;
                    } else {
                        // We're updating
                        $finalField = $raw
                            ? SqlGrammarUtils::escape($modelAttributes[$databaseColumn])
                            : "'".SqlGrammarUtils::escape($modelAttributes[$databaseColumn])."'";

                        $value = (is_null($modelAttributes[$databaseColumn]) ? 'NULL' : $finalField);
                    }

                    $this->switchCases[$databaseColumn][] = $this->buildWhenThenClause($primaryKeyColumn, $modelAttributes[$primaryKeyColumn], $value);
                }
            }
        }

        $this->compiledQuery = $this->buildQueryResult(
            substr($this->buildCaseStatement(), 0, -2),
            $primaryKeyColumn,
            implode("','", $this->modelIds)
        );

        return $this;
    }

    /**
     * Can be used to increment/decrement values too,
     * using the math operators {@see incrementOperators}
     *
     * Note: <adCount> and <adRevenue> are made up attributes
     *
     * $values = [
     *     [
     *        'eventid' => 123456,
     *        'sliverid' => 654321,
     *        'approxoutminutes' => ['+', 30]
     *     ],
     *     // or
     *     [
     *        'eventid' => '123456',
     *        'sliverid' => 654321,
     *        'approxoutminutes' => ['*', 2]
     *     ],
     * ];
     *
     * @param  iterable  $values
     * @param  string|null  $index
     * @param  bool  $raw
     * @return bool|int
     */
    public function update(iterable $values, string $index = null, bool $raw = false): bool|int
    {
        if (blank($values)) {
            return false;
        }

        return $this->model->getConnection()->update(
            $this->compileUpdateQuery(...func_get_args())->getCompiledQuery()
        );
    }

    /**
     * @return string
     */
    public function getCompiledQuery(): string
    {
        return $this->compiledQuery;
    }

    /**
     * @return string
     */
    private function getQualifiedTableName(): string
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
     * @return string
     */
    private function buildCaseStatement(): string
    {
        return collect($this->switchCases)->reduce(function (string $sql, array $sqlStatements, string $field) {
            $values = implode(PHP_EOL, $sqlStatements);
            $wrappedField = $this->applyWrapping($field);

            $sql .= <<<CASE_CLAUSE
            {$wrappedField} = (CASE
            {$values}
            ELSE {$wrappedField} END) \n,
            CASE_CLAUSE;

            return $sql;
        }, '');
    }

    /**
     * @param  string  $field
     * @param  mixed  $value
     * @param  mixed  $values
     * @return string
     */
    private function buildWhenThenClause(string $field, mixed $value, mixed $values): string
    {
        $wrappedField = $this->applyWrapping($field);

        return <<<WHEN_CLAUSE
        WHEN {$wrappedField} = '{$value}' THEN {$values}
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
        UPDATE "{$this->getQualifiedTableName()}" SET $case WHERE "{$index}" IN('$idValues');
        UPDATE_TABLE_CLAUSE;
    }
}
