<?php

namespace App\Ai;

use App\Ai\Tools\AiTool;
use App\Ai\Tools\ExportReport;
use App\Ai\Tools\ComplaintsCount;
use App\Ai\Tools\NewUsersCount;
use App\Ai\Tools\NfcTopupsCount;
use App\Ai\Tools\NotificationsCount;
use InvalidArgumentException;
use Throwable;

class ToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools = [];

    /** @param  array<class-string<AiTool>>  $classes */
    public function __construct(array $classes = [
        NotificationsCount::class,
        NfcTopupsCount::class,
        NewUsersCount::class,
        ComplaintsCount::class,
        ExportReport::class,
    ]) {
        foreach ($classes as $class) {
            $tool = app($class);
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function definitions(): array
    {
        return array_values(array_map(fn (AiTool $t) => $t->definition(), $this->tools));
    }

    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Runs a tool by name. Any error is returned to the model as an error result so it can correct itself
     * or explain it to the admin, without crashing the request.
     *
     * @return array{result: array, is_error: bool}
     */
    public function run(string $name, array $input): array
    {
        $tool = $this->tools[$name] ?? null;

        if (! $tool) {
            return ['result' => ['error' => "Unknown tool: {$name}"], 'is_error' => true];
        }

        try {
            return ['result' => $tool->handle($input), 'is_error' => false];
        } catch (InvalidArgumentException $e) {
            // Validation errors: safe to show the message to the model
            return ['result' => ['error' => $e->getMessage()], 'is_error' => true];
        } catch (Throwable $e) {
            report($e);

            // Never return DB details or stack traces to the model
            return ['result' => ['error' => 'The query failed. Please try again later.'], 'is_error' => true];
        }
    }
}
