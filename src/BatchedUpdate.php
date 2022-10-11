<?php

namespace Jhavenz\LaravelBatchUpdate;

use ArgumentCountError;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use TypeError;
use Webmozart\Assert\Assert;

/**
 * @template T of \Illuminate\Support\Collection<\Jhavenz\LaravelBatchUpdate\BatchedUpdate>
 */
class BatchedUpdate
{
    private static array $dbColumnCache;

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

        self::$dbColumnCache[$this->model->getTable()] ??= $this
            ->model
            ->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->model->getTable());

        $this->backticksDisabled = SqlGrammarUtils::disableBacktick(
            config("database.connections.{$this->model->getConnectionName()}.driver")
        );
    }

    /** @return T */
    public static function createFromCollection(iterable $models): Collection
    {
        return collect($models)
            ->whereInstanceOf(Model::class)
            ->whenEmpty(fn () => throw new InvalidArgumentException("No Eloquent models were provided"))
            ->keyBy(fn (Model $model) => $model::class)
            ->keys()
            ->unique()
            ->map(fn (Model $model) => static::createFromModel($model));
    }

    public static function createFromModel(Model|string $model): static
    {
        return new static($model);
    }

    public function compileUpdateQuery(
        iterable $values,
        string $primaryKeyColumn = null,
        bool $raw = false,
        bool $failOnNonExistingColumns = false
    ): static {
        Assert::notEmpty(collect($values));

        $table = $this->model->getTable();
        $primaryKeyColumn = $primaryKeyColumn ?: $this->model->getKeyName();

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
                if ($failOnNonExistingColumns && ! in_array($databaseColumn, self::$dbColumnCache[$this->model->getTable()])) {
                    throw new InvalidArgumentException(
                        sprintf("There is no column with name [%s] on table [%s].", $databaseColumn, $table),
                        30 //=> doctrine error code for a missing db column
                    );
                }

                if ($databaseColumn !== $primaryKeyColumn) {
                    $columnValue = $modelAttributes[$databaseColumn];

                    if (null !== $columnValue &&
                        false === is_scalar($columnValue) &&
                        $this->isCastable($databaseColumn)) {
                        throw new InvalidArgumentException(sprintf(
                            "Castable attributes must be provided as scalar values. [%s] for [%s] is invalid",
                            get_debug_type($columnValue),
                            $databaseColumn
                        ));
                    }

                    if ($this->isIncrementalOperation($columnValue)) {
                        // We're Increment or Decrementing
                        $this->switchCases[$databaseColumn][] = $this->buildWhenThenClause(
                            $primaryKeyColumn,
                            $modelAttributes[$primaryKeyColumn],
                            "`{$databaseColumn}`{$columnValue[0]}{$columnValue[1]}"
                        );

                        continue;
                    }

                    // We're updating
                    $finalField = $raw
                        ? SqlGrammarUtils::escape($columnValue)
                        : "'".SqlGrammarUtils::escape($columnValue)."'";

                    $value = (is_null($columnValue) ? 'NULL' : $finalField);

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

    public function getCompiledQuery(): string
    {
        return $this->compiledQuery;
    }

    private function getQualifiedTableName(): string
    {
        return $this->model->getConnection()->getTablePrefix().$this->model->getTable();
    }

    private function guardAgainstInvalidIncrementDecrementOperation(array $operators): void
    {
        // If array has two values
        if (! array_key_exists(0, $operators) || ! array_key_exists(1, $operators)) {
            throw new ArgumentCountError(
                sprintf(
                    'Increment/Decrement array requires 2 values, a math operator [%s] and a number',
                    implode(', ', $this->incrementOperators)
                )
            );
        }

        // Check first value
        if (gettype($operators[0]) != 'string'
            || ! in_array($operators[0], $this->incrementOperators)) {
            throw new TypeError(
                sprintf(
                    'First value in Increment/Decrement array must be a string and a math operator [%s]',
                    implode(', ', $this->incrementOperators)
                )
            );
        }

        // Check second value
        if (! is_numeric($operators[1])) {
            throw new TypeError('Second value in Increment/Decrement array needs to be numeric');
        }
    }

    private function buildCaseStatement(): string
    {
        return collect($this->switchCases)->reduce(function (string $sql, array $sqlStatements, string $field) {
            $values = implode(PHP_EOL, $sqlStatements);
            $wrappedField = $this->applyFieldWrapping($field);

            $sql .= <<<CASE_CLAUSE
            {$wrappedField} = (CASE
            {$values}
            ELSE {$wrappedField} END)\n,
            CASE_CLAUSE;

            return $sql;
        }, '');
    }

    private function buildWhenThenClause(string $field, mixed $value, mixed $values): string
    {
        $wrappedField = $this->applyFieldWrapping($field);

        return <<<WHEN_CLAUSE
        WHEN {$wrappedField} = '{$value}' THEN {$values}
        WHEN_CLAUSE;
    }

    private function applyFieldWrapping(string $field): string
    {
        return match (true) {
            $this->model->getConnection() instanceof SqlServerConnection => str($field)->wrap('[', ']')->toString(),
            $this->backticksDisabled => str($field)->wrap('"')->toString(),
            default => str($field)->wrap('`')->toString()
        };
    }

    private function buildQueryResult(string $case, mixed $index, string $idValues): string
    {
        return <<<UPDATE_TABLE_CLAUSE
        UPDATE "{$this->getQualifiedTableName()}" SET $case WHERE "{$index}" IN('$idValues');
        UPDATE_TABLE_CLAUSE;
    }

    private function isCastable(int|string $column): bool
    {
        try {
            return $this->model->hasGetMutator($column)
                || $this->model->hasAttributeGetMutator($column)
                || $this->model->isClassCastable($column)
                || $this->model->hasCast($column, [
                    'array',
                    'collection',
                    'encrypted:array',
                    'encrypted:collection',
                    'encrypted:json',
                    'encrypted:object',
                    'json',
                    'object',
                ]);
        } catch (InvalidCastException) {
            return false;
        }
    }

    private function isIncrementalOperation(mixed $columnValue): bool
    {
        if (! (is_array($columnValue) && count($columnValue) === 2)) {
            return false;
        }

        [$operator, $value] = $columnValue;

        return in_array($operator, $this->incrementOperators)
            && is_numeric($value);
    }
}
