<?php

namespace App\Ai\Tools;

class NewUsersCount extends AiTool
{
    public function name(): string
    {
        return 'get_new_users_count';
    }

    public function description(): string
    {
        return 'Returns how many new users registered in a date range.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties(),
            'required'   => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);

        return [
            'from'  => $input['from_date'],
            'to'    => $input['to_date'],
            'count' => $this->db()->table('users')
                ->whereBetween('created_at', [$from, $to])
                ->count(),
        ];
    }
}
