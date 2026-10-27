<?php

namespace App\Ai\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

abstract class AiTool
{
    /** اسم الـ tool اللي النموذج هيناديه (snake_case) */
    abstract public function name(): string;

    /** وصف واضح: النموذج بيختار الـ tool بناءً عليه */
    abstract public function description(): string;

    /** JSON Schema للـ parameters */
    abstract public function schema(): array;

    /** ينفذ الـ tool ويرجّع array (أرقام مجمّعة بس) */
    abstract public function handle(array $input): array;

    final public function definition(): array
    {
        return [
            'name'         => $this->name(),
            'description'  => $this->description(),
            'input_schema' => $this->schema(),
        ];
    }

    /** كل الـ tools بتقرأ من الـ read-only connection فقط */
    protected function db(): ConnectionInterface
    {
        return DB::connection(config('ai.db_connection'));
    }

    /**
     * يتحقق من from_date / to_date ويرجّع [start, end] بحدود اليوم الكاملة
     * بتوقيت الشغل (ai.timezone) ومحوّلة لتوقيت تخزين الداتابيز (ai.db_timezone)،
     * جاهزة للاستخدام مباشرة في whereBetween.
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

    /** Schema جاهز للـ tools اللي بتاخد فترة زمنية */
    protected function dateRangeProperties(): array
    {
        return [
            'from_date' => ['type' => 'string', 'description' => 'Start date inclusive, format YYYY-MM-DD'],
            'to_date'   => ['type' => 'string', 'description' => 'End date inclusive, format YYYY-MM-DD'],
        ];
    }
}
