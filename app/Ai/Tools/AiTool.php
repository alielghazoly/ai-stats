<?php

namespace App\Ai\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

abstract class AiTool
{
    /** Name the model will call the tool by (snake_case) */
    abstract public function name(): string;

    /** Clear description: the model picks the tool based on it */
    abstract public function description(): string;

    /** JSON Schema for the parameters */
    abstract public function schema(): array;

    /** Runs the tool and returns an array (aggregated numbers only) */
    abstract public function handle(array $input): array;

    final public function definition(): array
    {
        return [
            'name'         => $this->name(),
            'description'  => $this->description(),
            'input_schema' => $this->schema(),
        ];
    }

    /** All tools read from the read-only connection only */
    protected function db(): ConnectionInterface
    {
        return DB::connection(config('ai.db_connection'));
    }

    /**
     * Validates from_date / to_date and returns [start, end] covering whole days
     * in the working timezone (ai.timezone), converted to the database storage timezone (ai.db_timezone),
     * ready to use directly in whereBetween.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function range(array $input): array
    {
        $tz = config('ai.timezone');

        $from = $this->parseDate($input['from_date'] ?? null, 'from_date', $tz);
        $to   = $this->parseDate($input['to_date'] ?? null, 'to_date', $tz);

        if ($to->lt($from)) {
            throw new InvalidArgumentException('to_date must be on or after from_date.');
        }

        if ($from->diffInDays($to) > config('ai.max_range_days')) {
            throw new InvalidArgumentException(
                'Date range too large. Maximum is ' . config('ai.max_range_days') . ' days.'
            );
        }

        $dbTz = config('ai.db_timezone');

        return [
            $from->startOfDay()->setTimezone($dbTz),
            $to->endOfDay()->setTimezone($dbTz),
        ];
    }

    private function parseDate(mixed $value, string $field, string $tz): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("{$field} must be a date in YYYY-MM-DD format.");
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value, $tz);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$field} is not a valid date.");
        }

        return $date;
    }

    /** Ready-made schema for tools that take a time period */
    protected function dateRangeProperties(): array
    {
        return [
            'from_date' => ['type' => 'string', 'description' => 'Start date inclusive, format YYYY-MM-DD'],
            'to_date'   => ['type' => 'string', 'description' => 'End date inclusive, format YYYY-MM-DD'],
        ];
    }
}
