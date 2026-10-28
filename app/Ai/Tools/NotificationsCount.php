<?php

namespace App\Ai\Tools;

class NotificationsCount extends AiTool
{
    public function name(): string
    {
        return 'get_notifications_count';
    }

    public function description(): string
    {
        return 'Returns how many notifications were sent in a date range. '
             . 'Optionally filter by channel (database, mail, sms, push).';
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

        $query = $this->db()->table('notifications')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to]);

        return [
            'from'      => $input['from_date'],
            'to'        => $input['to_date'],
            'created'   => (clone $query)->count(),
            'sent'      => (clone $query)->whereNotNull('sent_at')->count(),
            'important' => (clone $query)->where('is_important', true)->count(),
        ];
    }
}
